<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Inertia\Inertia;

class NewPasswordController extends Controller
{
    public function create(Request $request): \Inertia\Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'store_slug' => $request->route('store_slug'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $useAccounts = config('identity.use_accounts');
        $broker = $useAccounts ? 'accounts' : 'users';
        $storeSlug = $request->route('store_slug') ?? $request->input('store_slug');

        $redirectTo = route('login');
        if ($storeSlug) {
            $redirectTo = url("/store/{$storeSlug}/login");
        }

        $status = Password::broker($broker)->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($authenticatable) use ($request, &$redirectTo, $storeSlug) {
                $authenticatable->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                // Respect explicit store_slug from the request
                if (!$storeSlug) {
                    if ($authenticatable instanceof User && $authenticatable->tenant) {
                        $redirectTo = url("/store/{$authenticatable->tenant->slug}/login");
                    }

                    if ($authenticatable instanceof Account) {
                        $membership = $authenticatable->memberships()->with('tenant')->first();
                        if ($membership && $membership->tenant) {
                            $redirectTo = url("/store/{$membership->tenant->slug}/login");
                        }
                    }
                }

                event(new PasswordReset($authenticatable));
            }
        );

        return $status == Password::PASSWORD_RESET
                    ? redirect()->to($redirectTo)->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }
}
