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
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $existing = Account::where('email', $request->email)->first();

        if ($existing) {
            if (!Hash::check($request->password, $existing->password)) {
                return redirect()->route('login')
                    ->with('status', 'This email is already registered. Please sign in with your password.');
            }

            Auth::guard('accounts')->login($existing);

            if (!$existing->hasVerifiedEmail()) {
                return redirect()->route('verification.notice')
                    ->with('status', 'This email is already registered. Please verify your email to continue.');
            }

            return redirect()->route('login')
                ->with('status', 'This email is already registered. Please sign in.');
        }

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

        return redirect()->route('verification.notice');
    }

    protected function storeUser(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
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

        return redirect()->route('verification.notice');
    }
}
