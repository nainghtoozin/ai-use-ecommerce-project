<?php

namespace App\Http\Middleware;

use App\Models\Account;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\WebsiteInfo;
use App\Services\StoreResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Storefront
{
    public function __construct(private readonly StoreResolver $storeResolver) {}

    public function handle(Request $request, Closure $next)
    {
        $storeSlug = $request->route('store_slug');

        if (!$storeSlug) {
            abort(404);
        }

        $tenant = $this->storeResolver->resolve($storeSlug);

        if (!$tenant) {
            abort(404);
        }

        if (!$this->isManagementRequest($request) && !$tenant->canServePublicStorefront()) {
            return $this->denyStorefront($request, $tenant);
        }

        $authenticatable = Auth::guard('accounts')->check()
            ? Auth::guard('accounts')->user()
            : (Auth::guard('web')->check() ? Auth::guard('web')->user() : null);

        if ($authenticatable && !$authenticatable->isSuperAdmin()) {
            $hasAccess = $authenticatable instanceof Account
                ? TenantMembership::where('account_id', $authenticatable->id)
                    ->where('tenant_id', $tenant->id)
                    ->where('status', 'active')
                    ->exists()
                : $authenticatable instanceof User
                    && (int) $authenticatable->tenant_id === (int) $tenant->id;

            if (!$hasAccess) {
                abort(403, 'Your account does not have access to this store.');
            }
        }

        app()->instance('current.tenant', $tenant);
        $request->merge(['tenant' => $tenant]);
        $request->session()->put('current_tenant_slug', $tenant->slug);

        $settings = WebsiteInfo::first();
        \Inertia\Inertia::share('website_info', $settings ? $settings->toArray() : []);

        return $next($request);
    }

    /**
     * Store admin, storefront preview and recovery-result screens must stay
     * reachable while a store is inactive so the owner can finish activation.
     */
    private function isManagementRequest(Request $request): bool
    {
        $name = $request->route()?->getName();

        if (is_string($name)) {
            if (str_starts_with($name, 'storefront.admin.')
                || str_starts_with($name, 'storefront.customer.')
                || $name === 'storefront.preview'
                || $name === 'storefront.login'
                || $name === 'storefront.admin.login'
                || str_starts_with($name, 'storefront.password.')
                || $name === 'storefront.register'
                || str_starts_with($name, 'storefront.onboarding.')
                || in_array($name, [
                    'storefront.faq', 'storefront.about', 'storefront.contact',
                    'storefront.support', 'storefront.privacy', 'storefront.terms',
                    'storefront.shipping', 'storefront.returns', 'storefront.refunds',
                ], true)) {
                return true;
            }
        }

        $path = $request->path();
        if (preg_match('#(^|/)login$#', $path)
            || preg_match('#(^|/)admin/login$#', $path)
            || preg_match('#(^|/)forgot-password$#', $path)
            || preg_match('#(^|/)register$#', $path)
            || preg_match('#/customer(/|$)#', $path)
            || preg_match('#/(faq|about|contact|support|privacy-policy|terms-and-conditions|shipping-policy|return-policy|refund-policy)$#', $path)) {
            return true;
        }

        return false;
    }

    private function denyStorefront(Request $request, Tenant $tenant)
    {
        $readOnly = $request->isMethod('GET') || $request->isMethod('HEAD');

        if ($readOnly && ($tenant->isLocked() || in_array($tenant->status, ['suspended', 'expired', 'locked'], true))) {
            return \Inertia\Inertia::render('Storefront/Locked', [
                'tenant' => [
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'logo' => $tenant->logo,
                ],
            ]);
        }

        abort(404);
    }
}
