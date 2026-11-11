<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $useAccounts = config('identity.use_accounts');
        $guard = $useAccounts ? 'accounts' : 'web';
        $user = Auth::guard($guard)->user();

        if (!$user) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();
        $exempt = [
            'login', 'logout', 'register', 'password.request', 'password.email', 'password.reset', 'password.store',
            'verification.notice', 'verification.verify', 'verification.send',
            'two-factor.challenge', 'two-factor.verify', 'two-factor.setup', 'two-factor.enable', 'two-factor.disable',
            'two-factor.recovery-codes.regenerate', 'security.settings',
            'auth.google.redirect', 'auth.google.callback', 'auth.google.link',
        ];
        if ($routeName && in_array($routeName, $exempt, true)) {
            return $next($request);
        }

        // If 2FA enabled and not verified this session
        if (method_exists($user, 'hasTwoFactorEnabled') && $user->hasTwoFactorEnabled()) {
            if ($request->session()->get('2fa_verified') !== true) {
                return redirect()->route('two-factor.challenge');
            }
        }

        // If mandatory but not enabled
        if (method_exists($user, 'requiresTwoFactor') && $user->requiresTwoFactor() && !($user->hasTwoFactorEnabled())) {
            if ($request->session()->get('2fa_enrollment_pending') !== false) {
                return redirect()->route('two-factor.setup');
            }
        }

        return $next($request);
    }
}
