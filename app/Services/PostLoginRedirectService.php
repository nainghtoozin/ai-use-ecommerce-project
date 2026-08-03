<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Tenant;
use App\Models\User;

class PostLoginRedirectService
{
    public function resolveDestination(User|Account $authenticatable): string
    {
        if ($authenticatable->isSuperAdmin()) {
            return route('superadmin.dashboard');
        }

        if ($authenticatable instanceof Account) {
            return $this->resolveAccountDestination($authenticatable);
        }

        return $this->resolveUserDestination($authenticatable);
    }

    public function getAccountState(User|Account $authenticatable): array
    {
        if ($authenticatable instanceof Account) {
            return $this->analyzeAccount($authenticatable);
        }

        return $this->analyzeUser($authenticatable);
    }

    protected function resolveAccountDestination(Account $account): string
    {
        if (!$account->hasVerifiedEmail()) {
            return route('verification.notice');
        }

        $ownerMembership = $account->memberships()
            ->where('is_owner', true)
            ->with('tenant')
            ->first();

        if ($ownerMembership && $ownerMembership->tenant) {
            return route('storefront.admin.dashboard', [
                'store_slug' => $ownerMembership->tenant->slug,
            ]);
        }

        $anyMembership = $account->memberships()->with('tenant')->first();

        if ($anyMembership && $anyMembership->tenant) {
            return route('storefront.admin.dashboard', [
                'store_slug' => $anyMembership->tenant->slug,
            ]);
        }

        return route('onboarding.store');
    }

    protected function resolveUserDestination(User $user): string
    {
        if ($user->tenant) {
            if ($user->isOwner()) {
                return route('storefront.admin.dashboard', [
                    'store_slug' => $user->tenant->slug,
                ]);
            }

            if ($user->isAdmin()) {
                return route('storefront.admin.dashboard', [
                    'store_slug' => $user->tenant->slug,
                ]);
            }

            return route('storefront.index', [
                'store_slug' => $user->tenant->slug,
            ]);
        }

        if (!$user->hasVerifiedEmail()) {
            return route('verification.notice');
        }

        return route('onboarding.store');
    }

    protected function analyzeAccount(Account $account): array
    {
        if ($account->isSuperAdmin()) {
            return [
                'type' => 'superadmin',
                'has_store' => false,
                'is_owner' => false,
                'is_staff' => false,
                'is_verified' => true,
                'needs_onboarding' => false,
            ];
        }

        $ownerMembership = $account->memberships()
            ->where('is_owner', true)
            ->with('tenant')
            ->first();

        if ($ownerMembership && $ownerMembership->tenant) {
            return [
                'type' => 'owner',
                'has_store' => true,
                'is_owner' => true,
                'is_staff' => false,
                'is_verified' => $account->hasVerifiedEmail(),
                'needs_onboarding' => false,
                'tenant' => $ownerMembership->tenant,
            ];
        }

        $anyMembership = $account->memberships()->with('tenant')->first();

        if ($anyMembership && $anyMembership->tenant) {
            return [
                'type' => 'staff',
                'has_store' => true,
                'is_owner' => false,
                'is_staff' => true,
                'is_verified' => $account->hasVerifiedEmail(),
                'needs_onboarding' => false,
                'tenant' => $anyMembership->tenant,
            ];
        }

        return [
            'type' => 'new',
            'has_store' => false,
            'is_owner' => false,
            'is_staff' => false,
            'is_verified' => $account->hasVerifiedEmail(),
            'needs_onboarding' => $account->hasVerifiedEmail(),
        ];
    }

    protected function analyzeUser(User $user): array
    {
        if ($user->isSuperAdmin()) {
            return [
                'type' => 'superadmin',
                'has_store' => false,
                'is_owner' => false,
                'is_staff' => false,
                'is_verified' => true,
                'needs_onboarding' => false,
            ];
        }

        if ($user->tenant) {
            return [
                'type' => $user->isOwner() ? 'owner' : 'staff',
                'has_store' => true,
                'is_owner' => $user->isOwner(),
                'is_staff' => !$user->isOwner(),
                'is_verified' => $user->hasVerifiedEmail(),
                'needs_onboarding' => false,
                'tenant' => $user->tenant,
            ];
        }

        return [
            'type' => 'new',
            'has_store' => false,
            'is_owner' => false,
            'is_staff' => false,
            'is_verified' => $user->hasVerifiedEmail(),
            'needs_onboarding' => $user->hasVerifiedEmail(),
        ];
    }
}
