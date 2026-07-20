<?php

namespace App\Auth;

use App\Models\Account;
use App\Models\Tenant;
use App\Models\User;

class IdentityProjection
{
    public function forAuthenticatable(User|Account|null $user): ?array
    {
        if (!$user) {
            return null;
        }

        $isSuperAdmin = $user->isSuperAdmin();

        $useAccounts = config('identity.use_accounts');
        $tenant = null;
        $membership = null;

        if (!$isSuperAdmin) {
            $tenant = Tenant::getCurrent();
            $membership = $useAccounts && $user instanceof Account ? $user->getCurrentMembership() : null;
        }

        $displayName = $user->getDisplayName();
        $roleLabel = $user->getRoleLabel();
        $roleNames = $user->getRoleNames()->toArray() ?: [null];
        $nameParts = explode(' ', $displayName, 2);

        $permissions = $user->getAllPermissions()->pluck('name')->toArray();

        $isOwner = !$isSuperAdmin && $useAccounts && $user instanceof Account
            ? $user->isOwner($tenant?->id)
            : ($user instanceof User && method_exists($user, 'isOwner') ? $user->isOwner() : false);

        $joinedAt = !$isSuperAdmin && $useAccounts && $user instanceof Account && $membership
            ? ($membership->joined_at ? $membership->joined_at->toDateString() : null)
            : $user->created_at?->toDateString();

        $tenantName = $tenant?->name;
        $tenantSlug = $tenant?->slug;

        $memberships = [];
        if ($useAccounts && $user instanceof Account) {
            $memberships = $user->memberships()
                ->with('tenant', 'role')
                ->where('status', 'active')
                ->get()
                ->map(fn($m) => [
                    'tenant_id' => $m->tenant_id,
                    'tenant_name' => $m->tenant->name ?? 'Unknown',
                    'tenant_slug' => $m->tenant->slug ?? '',
                    'tenant_logo' => $m->tenant->logo_url ?? null,
                    'is_owner' => $m->is_owner,
                    'is_default' => $m->is_default,
                    'role_name' => $m->role?->name,
                    'is_current' => $m->tenant_id === $tenant?->id,
                ])
                ->toArray();
        }

        return [
            'id' => $user->id,
            'display_name' => $displayName,
            'name' => $displayName,
            'first_name' => $nameParts[0] ?? '',
            'last_name' => $nameParts[1] ?? '',
            'email' => $user->email,
            'avatar' => $user->profile_image,
            'profile_image' => $user->profile_image,
            'profile_image_url' => $user->profile_image_url,
            'role' => $roleNames[0],
            'role_name' => $roleNames[0],
            'role_label' => $roleLabel,
            'roles' => $roleNames,
            'status' => $user->status,
            'membership_status' => $membership?->status ?? $user->status,
            'is_owner' => $isOwner,
            'is_admin' => $user->isAdmin(),
            'is_superadmin' => $isSuperAdmin,
            'tenant_id' => $user instanceof User ? $user->tenant_id : $tenant?->id,
            'tenant_name' => $tenantName,
            'tenant_slug' => $tenantSlug,
            'permissions' => $permissions,
            'email_verified_at' => $user->email_verified_at,
            'joined_at' => $joinedAt,
            'created_at' => $user->created_at?->toDateString(),
            'memberships' => $memberships,
        ];
    }
}
