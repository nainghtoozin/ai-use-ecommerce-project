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
                if ($user->hasRole('admin')) {
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
