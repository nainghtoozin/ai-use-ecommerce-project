<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Account;
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

            if ($account && !$account->isActive()) {
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
        } else {
            $user = User::where('email', $request->email)->first();

            // Root /login is for SuperAdmin only.
            // Tenant users (customers and admins) must login through their store URL.
            if ($user && $user->tenant_id && !$user->isSuperAdmin()) {
                return back()->withErrors([
                    'email' => 'Please login through your store URL.',
                ])->onlyInput('email');
            }

            if ($user && !$user->isActive()) {
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

            if ($user && $user->tenant && $user->tenant->status === 'suspended' && !$user->isSuperAdmin()) {
                return back()->withErrors([
                    'email' => 'Your account has been suspended. Please contact support.',
                ])->onlyInput('email');
            }
        }

        $request->authenticate();

        $guard = $useAccounts ? 'accounts' : 'web';

        $request->session()->regenerate();

        $authenticatable = Auth::guard($guard)->user();

        ActivityLogger::log(
            'User logged in',
            'login',
            $authenticatable,
            ['ip' => $request->ip(), 'user_agent' => $request->userAgent()],
            'auth'
        );

        if ($authenticatable->isAdmin()) {
            $tenant = \App\Models\Tenant::getCurrent();
            if ($tenant) {
                return redirect()->route('storefront.admin.dashboard', ['store_slug' => $tenant->slug]);
            }
            return redirect()->route('admin.dashboard');
        }

        return redirect()->route('client.dashboard');
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
        $tenant = $authenticatable ? \App\Models\Tenant::getCurrent() : null;
        $storeSlug = $request->input('store_slug')
            ?: ($tenant ? $tenant->slug : null)
            ?: $request->session()->get('current_tenant_slug');
        $context = $request->input('context');

        // Determine context from POST data or user role
        if (!$context) {
            if ($isSuperAdmin) {
                $context = 'superadmin';
            } elseif ($storeSlug && $authenticatable && $authenticatable->isAdmin()) {
                $context = 'admin';
            } elseif ($storeSlug) {
                $context = 'storefront';
            }
        }

        Auth::guard($guard)->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return match ($context) {
            'superadmin' => redirect()->route('superadmin.login'),
            'admin' => $storeSlug
                ? redirect()->route('storefront.admin.login', ['store_slug' => $storeSlug])
                : redirect()->route('admin.login'),
            'storefront' => $storeSlug
                ? redirect()->route('storefront.index', ['store_slug' => $storeSlug])
                : redirect('/'),
            default => $this->fallbackLogoutRedirect($isSuperAdmin, $storeSlug),
        };
    }

    private function fallbackLogoutRedirect(bool $isSuperAdmin, ?string $storeSlug): RedirectResponse
    {
        if ($isSuperAdmin) {
            return redirect()->route('superadmin.login');
        }
        if ($storeSlug) {
            return redirect()->route('storefront.index', ['store_slug' => $storeSlug]);
        }
        return redirect('/');
    }
}
