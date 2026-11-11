<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SecuritySettingsController extends Controller
{
    public function show(Request $request)
    {
        $useAccounts = config('identity.use_accounts');
        $guard = $useAccounts ? 'accounts' : 'web';
        $user = Auth::guard($guard)->user();
        if (!$user) {
            return redirect()->route('login');
        }

        return \Inertia\Inertia::render('Auth/SecuritySettings', [
            'twoFactorEnabled' => $user->hasTwoFactorEnabled(),
            'twoFactorRequired' => $user->requiresTwoFactor(),
            'recoveryCodesRemaining' => $user->remainingRecoveryCodes(),
            'googleLinked' => $user->hasGoogleLinked(),
            'googleEmail' => $user->google_email,
        ]);
    }
}
