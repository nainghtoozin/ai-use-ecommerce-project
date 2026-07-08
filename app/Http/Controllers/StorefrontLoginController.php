<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\Account;
use App\Models\Tenant;
use App\Models\TenantMembership;
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

                // Account must have membership for this tenant
                $membership = TenantMembership::where('account_id', $account->id)
                    ->where('tenant_id', $tenant->id)
                    ->first();

                if (!$membership) {
                    return back()->withErrors([
                        'email' => 'These credentials do not match our records.',
                    ])->onlyInput('email');
                }

                if ($membership->tenant && $membership->tenant->status === 'pending' && !$account->isSuperAdmin() && $account->isAdmin()) {
                    return back()->withErrors([
                        'email' => 'Please verify your email first.',
                    ])->onlyInput('email');
                }

                if ($membership->tenant && $membership->tenant->status === 'suspended' && !$account->isSuperAdmin()) {
                    return back()->withErrors([
                        'email' => 'Your account has been suspended. Please contact support.',
                    ])->onlyInput('email');
                }
            }
        } else {
            $user = User::where('email', $request->email)->first();

            if ($user) {
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

                // Pending — owner has not verified email; block admin login
                if ($user->tenant && $user->tenant->status === 'pending' && !$user->isSuperAdmin() && $user->isAdmin()) {
                    return back()->withErrors([
                        'email' => 'Please verify your email first.',
                    ])->onlyInput('email');
                }

                if ($user->tenant && $user->tenant->status === 'suspended' && !$user->isSuperAdmin()) {
                    return back()->withErrors([
                        'email' => 'Your account has been suspended. Please contact support.',
                    ])->onlyInput('email');
                }

                // Tenant verification: the user must belong to the current store
                if ($user->tenant_id !== null && $user->tenant_id !== $tenant->id) {
                    return back()->withErrors([
                        'email' => 'These credentials do not match our records.',
                    ])->onlyInput('email');
                }

                // Legacy users (null tenant_id): remember email for post-auth assignment
                // Must not update tenant_id before authenticate() — see below
            }
        }

        $request->authenticate();

        if (!$useAccounts) {
            // Auto-assign tenant_id for legacy users registered before tenant_id was stored.
            // Runs after authenticate() to avoid persisting tenant_id on failed login attempts.
            $user = Auth::user();
            if ($user->tenant_id === null) {
                $user->update(['tenant_id' => $tenant->id]);
            }
        }

        $request->session()->regenerate();

        $authenticatable = Auth::user();

        ActivityLogger::log(
            'User logged in',
            'login',
            $authenticatable,
            ['ip' => $request->ip(), 'user_agent' => $request->userAgent()],
            'auth'
        );

        if ($authenticatable->isAdmin()) {
            return redirect()->intended(route('storefront.admin.dashboard', ['store_slug' => $tenant->slug]));
        }

        return redirect()->intended(route('storefront.index', ['store_slug' => $tenant->slug]));
    }
}
