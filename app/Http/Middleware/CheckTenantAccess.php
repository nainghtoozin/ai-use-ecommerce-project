<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        $currentTenant = Tenant::getCurrent();

        if (!$currentTenant) {
            return $next($request);
        }

        if ((int) $user->tenant_id !== (int) $currentTenant->id) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $isAdminRoute = $request->route() && Str::contains($request->route()->getName(), 'storefront.admin.');
            $redirectRoute = $isAdminRoute ? 'storefront.admin.login' : 'storefront.login';

            return redirect()->route($redirectRoute, ['store_slug' => $currentTenant->slug])
                ->with('error', 'Your account is not associated with this store.');
        }

        return $next($request);
    }
}
