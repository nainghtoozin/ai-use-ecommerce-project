<?php

namespace App\Http\Middleware;

use App\Models\Account;
use App\Models\TenantMembership;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckUserStatus
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $authenticatable = Auth::user();
            $useAccounts = config('identity.use_accounts');
            $guard = $useAccounts ? 'accounts' : 'web';

            // ─────────────────────────────────────────────────────────
            // PLATFORM IDENTITY: SuperAdmin status check
            //
            // Per Platform Identity Design Lock:
            //   - SuperAdmin is platform-only
            //   - Redirect to /superadmin/login on suspension/ban
            //   - Never redirect to tenant login page
            // ─────────────────────────────────────────────────────────
            if ($authenticatable->isSuperAdmin()) {
                if ($authenticatable->isSuspended() || $authenticatable->isBanned()) {
                    Auth::guard($guard)->logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    $message = $authenticatable->isSuspended()
                        ? 'Your account has been suspended. Please contact support.'
                        : 'Your account has been banned. Please contact support.';

                    return redirect()->route('superadmin.login')
                        ->with('error', $message);
                }

                return $next($request);
            }

            if ($authenticatable->isSuspended()) {
                $storeSlug = $request->route('store_slug');
                Auth::guard($guard)->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                if ($storeSlug) {
                    return redirect()->route('storefront.login', ['store_slug' => $storeSlug])
                        ->with('error', 'Your account has been suspended. Please contact support.');
                }

                return redirect()->route('login')
                    ->with('error', 'Your account has been suspended. Please contact support.');
            }

            if ($authenticatable->isBanned()) {
                $storeSlug = $request->route('store_slug');
                Auth::guard($guard)->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                if ($storeSlug) {
                    return redirect()->route('storefront.login', ['store_slug' => $storeSlug])
                        ->with('error', 'Your account has been banned. Please contact support.');
                }

                return redirect()->route('login')
                    ->with('error', 'Your account has been banned. Please contact support.');
            }

            // Check tenant suspension for User
            if ($authenticatable instanceof User) {
                if ($authenticatable->tenant && $authenticatable->tenant->status === 'suspended' && !$authenticatable->isSuperAdmin()) {
                    // Prevent redirect loop: allow suspended/expired pages to render
                    $route = $request->route();
                    if ($route && in_array($route->getName(), [
                        'admin.suspended', 'storefront.admin.suspended',
                        'admin.expired', 'storefront.admin.expired',
                    ])) {
                        return $next($request);
                    }

                    if ($authenticatable->hasRole('admin')) {
                        $storeSlug = $request->route('store_slug');
                        if ($storeSlug) {
                            return redirect()->route('storefront.admin.suspended', ['store_slug' => $storeSlug]);
                        }
                        return redirect()->route('admin.suspended');
                    }

                    Auth::guard('web')->logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    $storeSlug = $request->route('store_slug');
                    if ($storeSlug) {
                        return redirect()->route('storefront.login', ['store_slug' => $storeSlug])
                            ->with('error', 'This store has been suspended. Please contact support.');
                    }

                    return redirect()->route('login')
                        ->with('error', 'This store has been suspended. Please contact support.');
                }
            }

            // Check tenant suspension for Account via membership
            if ($authenticatable instanceof Account && !$authenticatable->isSuperAdmin()) {
                $storeSlug = $request->route('store_slug');
                $currentTenant = $storeSlug
                    ? \App\Models\Tenant::where('slug', $storeSlug)->first()
                    : \App\Models\Tenant::getCurrent();
                if ($currentTenant && $currentTenant->status === 'suspended') {
                    $route = $request->route();
                    if ($route && in_array($route->getName(), [
                        'admin.suspended', 'storefront.admin.suspended',
                        'admin.expired', 'storefront.admin.expired',
                    ])) {
                        return $next($request);
                    }

                    if ($authenticatable->hasRole('admin')) {
                        $storeSlug = $request->route('store_slug');
                        if ($storeSlug) {
                            return redirect()->route('storefront.admin.suspended', ['store_slug' => $storeSlug]);
                        }
                        return redirect()->route('admin.suspended');
                    }

                    Auth::guard('accounts')->logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    $storeSlug = $request->route('store_slug');
                    if ($storeSlug) {
                        return redirect()->route('storefront.login', ['store_slug' => $storeSlug])
                            ->with('error', 'This store has been suspended. Please contact support.');
                    }

                    return redirect()->route('login')
                        ->with('error', 'This store has been suspended. Please contact support.');
                }
            }
        }

        return $next($request);
    }
}
