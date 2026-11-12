<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Create a password for an account that does not have one.
     */
    public function create(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (!$user || (method_exists($user, 'hasConfiguredPassword') ? $user->hasConfiguredPassword() : !empty($user->password))) {
            return back()->withErrors(['password' => 'A password is already set.']);
        }

        $validated = $request->validate([
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);

        $user->password = Hash::make($validated['password']);
        $user->password_set_at = now();
        $user->save();

        ActivityLogger::log(
            'Password created',
            'password_created',
            $user,
            ['ip' => $request->ip()],
            'security'
        );

        $user = $request->user();
        if ($user instanceof \App\Models\Account && !$user->memberships()->exists()) {
            return redirect()->route('onboarding.store')->with('status', 'Password created successfully. You can now sign in using Google or your email and password.');
        }
        return redirect()->route('security.settings')->with('status', 'Password created successfully. You can now sign in using Google or your email and password.');
    }

    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::min(12)->mixedCase()->numbers()->symbols(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
            'password_set_at' => now(),
        ]);

        ActivityLogger::log(
            'Password changed',
            'password_changed',
            $request->user(),
            ['ip' => $request->ip()],
            'security'
        );

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('success', 'Password changed. Please log in with your new password.');
    }
}
