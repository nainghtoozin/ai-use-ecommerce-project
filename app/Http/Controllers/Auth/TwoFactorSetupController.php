<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TwoFactorSetupController extends Controller
{
    public function __construct(private TwoFactorService $twoFactor) {}

    public function show(Request $request)
    {
        $useAccounts = config('identity.use_accounts');
        $guard = $useAccounts ? 'accounts' : 'web';
        $user = Auth::guard($guard)->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $secret = $request->session()->get('2fa_setup_secret');
        if (!$secret) {
            $secret = $this->twoFactor->generateSecret();
            $request->session()->put('2fa_setup_secret', $secret);
        }

        $qrUri = $this->twoFactor->getOtpAuthUri($secret, $user->email);

        return \Inertia\Inertia::render('Auth/TwoFactorSetup', [
            'secret' => $secret,
            'qrUri' => $qrUri,
            'recoveryCodes' => null,
        ]);
    }

    public function enable(Request $request)
    {
        $request->validate(['code' => ['required', 'string']]);

        $useAccounts = config('identity.use_accounts');
        $guard = $useAccounts ? 'accounts' : 'web';
        $user = Auth::guard($guard)->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $secret = $request->session()->get('2fa_setup_secret');
        if (!$secret) {
            return back()->withErrors(['code' => 'Setup session expired. Please try again.']);
        }

        if (!$this->twoFactor->verify($secret, $request->input('code'))) {
            return back()->withErrors(['code' => 'Invalid authentication code.']);
        }

        $recoveryCodes = $this->twoFactor->generateRecoveryCodes();
        $hashed = array_map(fn ($c) => $this->twoFactor->hashRecoveryCode($c), $recoveryCodes);

        $user->setTwoFactorSecret($secret);
        $user->setRecoveryCodes($hashed);
        $user->two_factor_confirmed_at = now();
        $user->two_factor_required = true;
        $user->save();

        $request->session()->forget('2fa_setup_secret');
        $request->session()->put('2fa_verified', true);
        $request->session()->put('2fa_enrollment_pending', false);

        return \Inertia\Inertia::render('Auth/TwoFactorSetup', [
            'secret' => null,
            'qrUri' => null,
            'recoveryCodes' => $recoveryCodes,
        ]);
    }

    public function disable(Request $request)
    {
        $request->validate([
            'password' => ['nullable', 'string'],
            'code' => ['nullable', 'string'],
        ]);

        $useAccounts = config('identity.use_accounts');
        $guard = $useAccounts ? 'accounts' : 'web';
        $user = Auth::guard($guard)->user();
        if (!$user) {
            return redirect()->route('login');
        }

        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return back()->withErrors(['code' => 'SuperAdmin mandatory 2FA cannot be disabled.']);
        }

        // Password re-authentication where a password exists
        if (!empty($user->password)) {
            if (!$request->filled('password') || !\Illuminate\Support\Facades\Hash::check($request->input('password'), $user->password)) {
                return back()->withErrors(['password' => 'Invalid password.']);
            }
        }

        // Require current TOTP or recovery code
        $code = $request->input('code');
        $ok = false;
        if ($code) {
            $secret = $user->getTwoFactorSecretDecrypted();
            if ($secret && $this->twoFactor->verify($secret, $code)) {
                $ok = true;
            } elseif ($user->consumeRecoveryCode($code)) {
                $ok = true;
            }
        }

        if (!$ok) {
            return back()->withErrors(['code' => 'Invalid authentication code.']);
        }

        $user->setTwoFactorSecret(null);
        $user->setRecoveryCodes([]);
        $user->two_factor_confirmed_at = null;
        $user->two_factor_last_used_step = null;
        // keep required flag? If mandatory, re-enable required? For disable by user, set required false? Policy says superadmin required. Keep required for superadmin.
        if (!$user->isSuperAdmin()) {
            $user->two_factor_required = false;
        }
        $user->save();

        $request->session()->put('2fa_verified', true);

        return redirect()->route('security.settings')->with('status', 'Two-factor authentication disabled.');
    }

    public function regenerateRecoveryCodes(Request $request)
    {
        $request->validate(['code' => ['required', 'string']]);

        $useAccounts = config('identity.use_accounts');
        $guard = $useAccounts ? 'accounts' : 'web';
        $user = Auth::guard($guard)->user();
        if (!$user || !$user->hasTwoFactorEnabled()) {
            return redirect()->route('login');
        }

        if (!empty($user->password)) {
            if (!$request->filled('password') || !\Illuminate\Support\Facades\Hash::check($request->input('password'), $user->password)) {
                return back()->withErrors(['password' => 'Invalid password.']);
            }
        }

        $secret = $user->getTwoFactorSecretDecrypted();
        if (!$secret || !$this->twoFactor->verify($secret, $request->input('code'))) {
            return back()->withErrors(['code' => 'Invalid authentication code.']);
        }

        $recoveryCodes = $this->twoFactor->generateRecoveryCodes();
        $hashed = array_map(fn ($c) => $this->twoFactor->hashRecoveryCode($c), $recoveryCodes);
        $user->setRecoveryCodes($hashed);
        $user->save();

        return \Inertia\Inertia::render('Auth/TwoFactorSetup', [
            'secret' => null,
            'qrUri' => null,
            'recoveryCodes' => $recoveryCodes,
            'regenerated' => true,
        ]);
    }
}
