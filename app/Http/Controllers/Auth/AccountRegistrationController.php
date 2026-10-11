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
        return Inertia::render('Auth/Register');
    }

    public function store(Request $request): RedirectResponse
    {
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
