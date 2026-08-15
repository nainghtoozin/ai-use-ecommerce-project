<?php

namespace App\Http\Middleware;

use App\Models\WebsiteInfo;
use App\Models\Account;
use App\Models\TenantMembership;
use App\Models\User;
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
}
