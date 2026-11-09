<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class AccountRegistrationController extends Controller
{
    public function create(): Response
    {
        if (! \App\Models\PlatformSetting::current()->registration_enabled) {
            return Inertia::render('Auth/RegistrationUnavailable', [
                'supportUrl' => $this->supportUrl(),
            ]);
        }

        return Inertia::render('Auth/Register');
    }

    public function unavailable(): Response
    {
        return Inertia::render('Auth/RegistrationUnavailable', [
            'supportUrl' => $this->supportUrl(),
        ]);
    }

    protected function supportUrl(): string
    {
        $settings = \App\Models\PlatformSetting::current();

        if ($settings->support_center_enabled ?? true) {
            return '/support';
        }

        if ($settings->support_page_url) {
            return $settings->support_page_url;
        }

        if ($settings->support_email) {
            return 'mailto:' . $settings->support_email;
        }

        if ($settings->support_phone) {
            return 'tel:' . $settings->support_phone;
        }

        return '/support';
    }

    public function store(Request $request): RedirectResponse
    {
        if (! \App\Models\PlatformSetting::current()->registration_enabled) {
            return redirect()->route('registration.unavailable');
        }

        $useAccounts = config('identity.use_accounts');

        if ($useAccounts) {
            return $this->storeAccount($request);
        }

        return $this->storeUser($request);
    }

    protected function storeAccount(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:accounts,email', 'unique:users,email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [
            'email.unique' => 'This email already has an account. Please sign in to continue.',
        ]);

        $account = Account::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'status' => Account::STATUS_ACTIVE,
            'notification_preferences' => [
                'email' => true,
                'browser' => true,
                'telegram' => false,
                'marketing' => false,
                'order_updates' => true,
                'system_alerts' => true,
            ],
        ]);

        event(new Registered($account));

        Auth::guard('accounts')->login($account);
        $request->session()->regenerate();

        return redirect()->route('verification.notice');
    }

    protected function storeUser(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email', 'unique:accounts,email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [
            'email.unique' => 'This email already has an account. Please sign in to continue.',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'status' => 'active',
            'notification_preferences' => [
                'email' => true,
                'browser' => true,
                'telegram' => false,
                'marketing' => false,
                'order_updates' => true,
                'system_alerts' => true,
            ],
        ]);

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('verification.notice');
    }
}
