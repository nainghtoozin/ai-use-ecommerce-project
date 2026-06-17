<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, $role)
    {
        if (!Auth::check()) {
            abort(403, 'Unauthorized');
        }

        $user = Auth::user();

        // Superadmin bypass — one user to rule them all
        if ($role === 'admin' && $user->hasRole('superadmin')) {
            return $next($request);
        }

        // Exact role name match (e.g. user assigned the "admin" role)
        if ($user->hasRole($role)) {
            return $next($request);
        }

        // For admin routes: allow any user who holds permissions via a custom role.
        // Granular action-level control remains in each controller.
        if ($role === 'admin' && $user->getAllPermissions()->isNotEmpty()) {
            return $next($request);
        }

        abort(403, 'Unauthorized');
    }
}
