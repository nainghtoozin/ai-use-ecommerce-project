<?php

namespace App\Http\Middleware;

use App\Models\Account;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;

class CheckStoreLocked
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        $tenant = $user instanceof Account ? Tenant::getCurrent() : $user->tenant;

        if (!$tenant || !$tenant->isLocked()) {
            return $next($request);
        }

        if ($request->isMethod('GET') || $request->isMethod('HEAD') || $request->isMethod('OPTIONS')) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Your subscription has expired. Please renew to make changes.',
            ], 403);
        }

        return redirect()->back()->with(
            'error',
            'Your subscription has expired. Please renew to restore access to all features.'
        );
    }
}
