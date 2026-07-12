<?php

namespace App\Http\Controllers\Auth;

use App\Auth\LoginRedirectResolver;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ConfirmablePasswordController extends Controller
{
    public function show(): \Inertia\Response
    {
        return Inertia::render('Auth/ConfirmPassword');
    }

    public function store(Request $request): RedirectResponse
    {
        $guard = config('identity.use_accounts') ? 'accounts' : 'web';

        if (! Auth::guard($guard)->validate([
            'email' => $request->user()->email,
            'password' => $request->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        $request->session()->put('auth.password_confirmed_at', time());

        return app(LoginRedirectResolver::class)->intended($request->user());
    }
}
