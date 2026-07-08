<?php

namespace App\Http\Middleware;

use App\Models\Account;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $authenticatable = $request->user();

        if (!$authenticatable) {
            return $next($request);
        }

        if ($authenticatable->isSuperAdmin()) {
            return $next($request);
        }

        $currentTenant = Tenant::getCurrent();

        if (!$currentTenant) {
            return $next($request);
        }

        if ($authenticatable instanceof Account) {
            $membership = TenantMembership::where('account_id', $authenticatable->id)
                ->where('tenant_id', $currentTenant->id)
                ->first();

            if (!$membership) {
                auth()->guard('accounts')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                $isAdminRoute = $request->route() && Str::contains($request->route()->getName(), 'storefront.admin.');
                $redirectRoute = $isAdminRoute ? 'storefront.admin.login' : 'storefront.login';

                return redirect()->route($redirectRoute, ['store_slug' => $currentTenant->slug])
                    ->with('error', 'Your account is not associated with this store.');
            }

            return $next($request);
        }

        if ($authenticatable instanceof User) {
            if ((int) $authenticatable->tenant_id !== (int) $currentTenant->id) {
                auth()->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                $isAdminRoute = $request->route() && Str::contains($request->route()->getName(), 'storefront.admin.');
                $redirectRoute = $isAdminRoute ? 'storefront.admin.login' : 'storefront.login';

                return redirect()->route($redirectRoute, ['store_slug' => $currentTenant->slug])
                    ->with('error', 'Your account is not associated with this store.');
            }
        }

        return $next($request);
    }
}
