<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class TwoFactorChallengeController extends Controller
{
    public function __construct(private TwoFactorService $twoFactor) {}

    public function show(Request $request)
    {
        $useAccounts = config('identity.use_accounts');
        $guard = $useAccounts ? 'accounts' : 'web';

        if (!Auth::guard($guard)->check()) {
            // allow pending? For enabled 2FA we kept user logged in, so check.
        }

        return \Inertia\Inertia::render('Auth/TwoFactorChallenge', [
            'status' => session('status'),
        ]);
    }

    public function verify(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $useAccounts = config('identity.use_accounts');
        $guard = $useAccounts ? 'accounts' : 'web';
        $user = Auth::guard($guard)->user();

        if (!$user) {
            return redirect()->route('login');
        }

        $key = '2fa-verify:' . $user->id . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return back()->withErrors(['code' => "Too many attempts. Try again in {$seconds} seconds."]);
        }

        $code = trim($request->input('code'));

        if ($user->hasTwoFactorEnabled()) {
            $secret = $user->getTwoFactorSecretDecrypted();
            if ($secret) {
                $step = $this->twoFactor->verifyAndGetStep($secret, $code);
                if ($step !== null) {
                    // Replay prevention: step must be newer than last used
                    if ($user->two_factor_last_used_step !== null && $step <= $user->two_factor_last_used_step) {
                        RateLimiter::hit($key);
                        return back()->withErrors(['code' => 'This code has already been used.']);
                    }
                    $user->two_factor_last_used_step = $step;
                    $user->save();
                    RateLimiter::clear($key);
                    $request->session()->put('2fa_verified', true);
                    return redirect()->intended($this->redirectAfter($user));
                }
            }

            // try recovery code
            if ($user->consumeRecoveryCode($code)) {
                RateLimiter::clear($key);
                $request->session()->put('2fa_verified', true);
                return redirect()->intended($this->redirectAfter($user));
            }
        }

        RateLimiter::hit($key);
        return back()->withErrors(['code' => 'Invalid authentication code.']);
    }

    protected function redirectAfter($user): string
    {
        $resolver = app(\App\Auth\LoginRedirectResolver::class);
        $tenant = $resolver->resolveTenant($user);
        return $resolver->resolveLogin($user, $tenant);
    }
}
