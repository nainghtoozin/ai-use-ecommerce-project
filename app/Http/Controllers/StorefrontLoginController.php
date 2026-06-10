<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
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

            // Auto-assign tenant_id for legacy users registered before tenant_id was stored
            if ($user->tenant_id === null) {
                $user->update(['tenant_id' => $tenant->id]);
            }
        }

        $request->authenticate();

        $request->session()->regenerate();

        $user = Auth::user();

        ActivityLogger::log(
            'User logged in',
            'login',
            $user,
            ['ip' => $request->ip(), 'user_agent' => $request->userAgent()],
            'auth'
        );

        if ($user->isAdmin()) {
            return redirect()->intended(route('storefront.admin.dashboard', ['store_slug' => $tenant->slug]));
        }

        return redirect()->intended(route('storefront.index', ['store_slug' => $tenant->slug]));
    }
}
