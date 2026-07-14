<?php

namespace App\Http\Controllers\Auth;

use App\Auth\LoginRedirectResolver;
use App\Http\Controllers\Controller;
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
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
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

                if ($account->getCurrentMembership() && !$account->isSuperAdmin()) {
                    return back()->withErrors([
                        'email' => 'Please login through your store URL.',
                    ])->onlyInput('email');
                }
            }
        } else {
            $user = User::where('email', $request->email)->first();

            if ($user) {
                if ($user->tenant_id && !$user->isSuperAdmin()) {
                    return back()->withErrors([
                        'email' => 'Please login through your store URL.',
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

                if ($user->tenant && $user->tenant->status === 'suspended' && !$user->isSuperAdmin()) {
                    return back()->withErrors([
                        'email' => 'Your account has been suspended. Please contact support.',
                    ])->onlyInput('email');
                }
            }
        }

        $request->authenticate();

        $guard = $useAccounts ? 'accounts' : 'web';

        $request->session()->regenerate();

        $authenticatable = Auth::guard($guard)->user();

        $request->session()->forget('current_tenant_slug');

        ActivityLogger::log(
            'User logged in',
            'login',
            $authenticatable,
            ['ip' => $request->ip(), 'user_agent' => $request->userAgent()],
            'auth'
        );

        return redirect()->to(app(LoginRedirectResolver::class)->resolveLogin($authenticatable));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $useAccounts = config('identity.use_accounts');
        $guard = $useAccounts ? 'accounts' : 'web';

        $authenticatable = Auth::guard($guard)->user();

        if ($authenticatable) {
            ActivityLogger::log(
                'User logged out',
                'logout',
                $authenticatable,
                ['ip' => $request->ip()],
                'auth'
            );
        }

        $isSuperAdmin = $authenticatable && $authenticatable->isSuperAdmin();

        // ─────────────────────────────────────────────────────────
        // CAPTURE TENANT SLUG BEFORE SESSION INVALIDATION
        //
        // Priority order:
        // 1. Explicit store_slug from request input (form field)
        // 2. Route parameter store_slug (from URL)
        // 3. Current tenant from middleware
        // 4. Session value (before invalidation)
        // ─────────────────────────────────────────────────────────
        $tenant = !$isSuperAdmin && $authenticatable ? Tenant::getCurrent() : null;
        $storeSlug = $request->input('store_slug')
            ?: $request->route('store_slug')
            ?: ($tenant ? $tenant->slug : null)
            ?: $request->session()->get('current_tenant_slug');
        $context = $request->input('context');

        Auth::guard($guard)->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $redirectUrl = app(LoginRedirectResolver::class)->resolveLogout(
            $authenticatable,
            $storeSlug,
            $context
        );

        return redirect()->to($redirectUrl);
    }
}
