<?php

namespace App\Http\Middleware;

use App\Models\Account;
use App\Models\TenantMembership;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;

class TenantIsValid
{
    public function handle(Request $request, Closure $next)
    {
        $authenticatable = $request->user();

        if (! $authenticatable) {
            return $next($request);
        }

        if ($authenticatable->isSuperAdmin()) {
            return $next($request);
        }

        if ($authenticatable instanceof Account) {
            $currentTenant = \App\Models\Tenant::getCurrent();
            if (!$currentTenant) {
                auth()->guard('accounts')->logout();
                $storeSlug = $request->route('store_slug');
                $redirectRoute = $storeSlug
                    ? 'storefront.login'
                    : 'login';
                return redirect()->route($redirectRoute, $storeSlug ? ['store_slug' => $storeSlug] : [])->withErrors([
                    'email' => 'Your account is not associated with any store.',
                ]);
            }

            $membership = TenantMembership::where('account_id', $authenticatable->id)
                ->where('tenant_id', $currentTenant->id)
                ->first();

            if (!$membership) {
                auth()->guard('accounts')->logout();
                $storeSlug = $request->route('store_slug');
                $redirectRoute = $storeSlug
                    ? 'storefront.login'
                    : 'login';
                return redirect()->route($redirectRoute, $storeSlug ? ['store_slug' => $storeSlug] : [])->withErrors([
                    'email' => 'Your account is not associated with any store.',
                ]);
            }

            return $next($request);
        }

        if ($authenticatable instanceof User) {
            if (empty($authenticatable->tenant_id)) {
                auth()->logout();
                $storeSlug = $request->route('store_slug');
                $redirectRoute = $storeSlug
                    ? 'storefront.login'
                    : 'login';
                return redirect()->route($redirectRoute, $storeSlug ? ['store_slug' => $storeSlug] : [])->withErrors([
                    'email' => 'Your account is not associated with any store.',
                ]);
            }

            $tenant = $authenticatable->tenant;

            if (! $tenant) {
                auth()->logout();
                abort(403, 'Your store account is no longer available.');
            }
        }

        return $next($request);
    }
}
