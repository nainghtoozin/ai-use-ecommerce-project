<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckUserStatus
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $user = Auth::user();

            if ($user->isSuspended()) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->with('error', 'Your account has been suspended. Please contact support.');
            }

            if ($user->isBanned()) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->with('error', 'Your account has been banned. Please contact support.');
            }

            if ($user->tenant && $user->tenant->status === 'suspended' && !$user->isSuperAdmin()) {
                // Prevent redirect loop: allow suspended/expired pages to render
                $route = $request->route();
                if ($route && in_array($route->getName(), [
                    'admin.suspended', 'storefront.admin.suspended',
                    'admin.expired', 'storefront.admin.expired',
                ])) {
                    return $next($request);
                }

                if ($user->hasRole('admin')) {
                    $storeSlug = $request->route('store_slug');
                    if ($storeSlug) {
                        return redirect()->route('storefront.admin.suspended', ['store_slug' => $storeSlug]);
                    }
                    return redirect()->route('admin.suspended');
                }

                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->with('error', 'This store has been suspended. Please contact support.');
            }
        }

        return $next($request);
    }
}
