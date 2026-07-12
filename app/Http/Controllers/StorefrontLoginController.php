<?php

namespace App\Http\Controllers;

use App\Auth\LoginRedirectResolver;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Account;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class StorefrontLoginController extends Controller
{
    public function create(Request $request): \Inertia\Response|\Illuminate\Http\RedirectResponse
    {
        $tenant = Tenant::getCurrent();
        if (!$tenant) {
            return redirect()->route('login');
        }

        return Inertia::render('Storefront/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'store_url' => $tenant->store_url,
            ],
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $tenant = Tenant::getCurrent();
        if (!$tenant) {
            return redirect()->route('login');
        }

        $useAccounts = config('identity.use_accounts');

        if ($useAccounts) {
            $account = Account::where('email', $request->email)->first();

            if ($account) {
                if ($account->isSuperAdmin()) {
                    return back()->withErrors([
                        'email' => 'Please use the platform login page for super admin access.',
                    ])->onlyInput('email');
                }

                if (!$account->isActive()) {
                    if ($account->isSuspended()) {
                        return back()->withErrors([
                            'email' => 'Your account has been suspended. Please contact support.',
                        ])->onlyInput('email');
                    }
                    if ($account->isBanned()) {
                        return back()->withErrors([
                            'email' => 'Your account has been banned. Please contact support.',
                        ])->onlyInput('email');
                    }
                    return back()->withErrors([
                        'email' => 'Your account is inactive.',
                    ])->onlyInput('email');
                }

            }
        } else {
            $user = User::where('email', $request->email)->first();

            if ($user) {
                if ($user->isSuperAdmin()) {
                    return back()->withErrors([
                        'email' => 'Please use the platform login page for super admin access.',
                    ])->onlyInput('email');
                }

                if (!$user->isActive()) {
                    if ($user->isSuspended()) {
                        return back()->withErrors([
                            'email' => 'Your account has been suspended. Please contact support.',
                        ])->onlyInput('email');
                    }
                    if ($user->isBanned()) {
                        return back()->withErrors([
                            'email' => 'Your account has been banned. Please contact support.',
                        ])->onlyInput('email');
                    }
                    return back()->withErrors([
                        'email' => 'Your account is inactive.',
                    ])->onlyInput('email');
                }

                if ($user->tenant && $user->tenant->status === 'pending' && $user->isAdmin() && !$user->hasVerifiedEmail()) {
                    return back()->withErrors([
                        'email' => 'Please verify your email first.',
                    ])->onlyInput('email');
                }

                if ($user->tenant && $user->tenant->status === 'suspended') {
                    return back()->withErrors([
                        'email' => 'Your account has been suspended. Please contact support.',
                    ])->onlyInput('email');
                }

                if ($user->tenant_id !== null && $user->tenant_id !== $tenant->id) {
                    return back()->withErrors([
                        'email' => 'These credentials do not match our records.',
                    ])->onlyInput('email');
                }
            }
        }

        $request->authenticate();

        $guard = $useAccounts ? 'accounts' : 'web';

        if (!$useAccounts) {
            $user = Auth::guard($guard)->user();
            if ($user->tenant_id === null) {
                $user->update(['tenant_id' => $tenant->id]);
            }
        }

        $request->session()->regenerate();

        $authenticatable = Auth::guard($guard)->user();

        ActivityLogger::log(
            'User logged in',
            'login',
            $authenticatable,
            ['ip' => $request->ip(), 'user_agent' => $request->userAgent()],
            'auth'
        );

        return app(LoginRedirectResolver::class)->intended($authenticatable, $tenant);
    }
}
