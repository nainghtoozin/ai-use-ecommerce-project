<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class TenantIsValid
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        if (empty($user->tenant_id)) {
            auth()->logout();
            return redirect()->route('login')->withErrors([
                'email' => 'Your account is not associated with any store.',
            ]);
        }

        $tenant = $user->tenant;

        if (! $tenant) {
            auth()->logout();
            abort(403, 'Your store account is no longer available.');
        }

        return $next($request);
    }
}
