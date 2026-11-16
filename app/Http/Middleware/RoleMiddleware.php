<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, $role)
    {
        $user = $request->user();

        if (!$user) {
            abort(403, 'Unauthorized');
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
