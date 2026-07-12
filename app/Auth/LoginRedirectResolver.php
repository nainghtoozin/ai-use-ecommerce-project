<?php

namespace App\Auth;

use App\Models\Account;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class LoginRedirectResolver
{
    /**
     * Resolve the post-login redirect URL for the given authenticatable.
     */
    public function resolveLogin(User|Account $authenticatable, ?Tenant $tenant = null): string
    {
        if ($authenticatable->isSuperAdmin()) {
            return route('superadmin.dashboard');
        }

        $resolvedTenant = $tenant ?: $this->resolveTenant($authenticatable);

        if ($resolvedTenant) {
            $storeSlug = $resolvedTenant->slug;

            if ($authenticatable->isAdmin()) {
                return route('storefront.admin.dashboard', ['store_slug' => $storeSlug]);
            }

            return route('storefront.index', ['store_slug' => $storeSlug]);
        }

        if ($authenticatable->isAdmin()) {
            return route('admin.dashboard');
        }

        return route('client.dashboard');
    }

    /**
     * Redirect to intended URL based on role + tenant context.
     */
    public function intended(User|Account $authenticatable, ?Tenant $tenant = null): RedirectResponse
    {
        return redirect()->intended($this->resolveLogin($authenticatable, $tenant));
    }

    /**
     * Resolve the post-logout redirect URL.
     */
    public function resolveLogout(
        User|Account|null $authenticatable,
        ?string $storeSlug = null,
        ?string $context = null,
    ): string {
        $resolvedContext = $context ?: $this->inferLogoutContext($authenticatable, $storeSlug);

        return match ($resolvedContext) {
            'superadmin' => route('superadmin.login'),
            'admin' => $storeSlug
                ? route('storefront.admin.login', ['store_slug' => $storeSlug])
                : route('admin.login'),
            'storefront' => $storeSlug
                ? route('storefront.index', ['store_slug' => $storeSlug])
                : '/',
            default => $this->fallbackLogoutRedirect($authenticatable, $storeSlug),
        };
    }

    /**
     * Resolve redirect URL after registration.
     */
    public function resolveAfterRegistration(User|Account $authenticatable, Tenant $tenant): string
    {
        if ($authenticatable->isAdmin()) {
            return route('storefront.admin.dashboard', ['store_slug' => $tenant->slug]);
        }

        return route('storefront.index', ['store_slug' => $tenant->slug]);
    }

    /**
     * Resolve redirect URL after email verification.
     */
    public function resolveAfterEmailVerification(User|Account $authenticatable): string
    {
        $tenant = $this->resolveTenant($authenticatable);
        if ($tenant) {
            return route('storefront.onboarding.complete', ['store_slug' => $tenant->slug]);
        }

        return route('login');
    }

    /**
     * Resolve redirect URL after password reset.
     */
    public function resolveAfterPasswordReset(User|Account $authenticatable): string
    {
        $tenant = $this->resolveTenant($authenticatable);
        if ($tenant) {
            return url("/store/{$tenant->slug}/login");
        }

        return route('login');
    }

    /**
     * Resolve redirect URL after impersonation start.
     */
    public function resolveAfterImpersonation(User|Account $impersonatedUser): string
    {
        $tenant = $this->resolveTenant($impersonatedUser);
        if ($tenant) {
            return route('storefront.admin.dashboard', ['store_slug' => $tenant->slug]);
        }

        return route('admin.dashboard');
    }

    /**
     * Resolve redirect URL after returning from impersonation.
     */
    public function resolveAfterImpersonationLeave(): string
    {
        return route('superadmin.dashboard');
    }

    private function resolveTenant(User|Account $authenticatable): ?Tenant
    {
        if ($authenticatable instanceof User && $authenticatable->tenant) {
            return $authenticatable->tenant;
        }

        if ($authenticatable instanceof Account) {
            $current = Tenant::getCurrent();
            if ($current) {
                return $current;
            }

            $membership = $authenticatable->getCurrentMembership();
            if ($membership) {
                $tenant = $membership->relationLoaded('tenant') ? $membership->tenant : $membership->tenant()->first();
                if ($tenant) return $tenant;
            }

            $membership = $authenticatable->memberships()->with('tenant')->first();
            if ($membership && $membership->tenant) {
                return $membership->tenant;
            }
        }

        return null;
    }

    private function inferLogoutContext(User|Account|null $authenticatable, ?string $storeSlug): ?string
    {
        if (!$authenticatable) {
            return null;
        }

        $fresh = $authenticatable instanceof User
            ? User::find($authenticatable->id)
            : ($authenticatable instanceof Account ? Account::find($authenticatable->id) : null);

        if (!$fresh) {
            return null;
        }

        if ($fresh->isSuperAdmin()) {
            return 'superadmin';
        }

        if ($storeSlug && $fresh->isAdmin()) {
            return 'admin';
        }

        if ($storeSlug) {
            return 'storefront';
        }

        return null;
    }

    private function fallbackLogoutRedirect(User|Account|null $authenticatable, ?string $storeSlug): string
    {
        if ($authenticatable && $authenticatable->isSuperAdmin()) {
            return route('superadmin.login');
        }
        if ($storeSlug) {
            return route('storefront.index', ['store_slug' => $storeSlug]);
        }
        return '/';
    }
}
