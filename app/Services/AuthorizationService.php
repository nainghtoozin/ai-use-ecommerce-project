<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Tenant;
use App\Models\TenantMembership;
use Illuminate\Contracts\Auth\Authenticatable;

class AuthorizationService
{
    /**
     * Check if the authenticated user has a specific permission.
     * Resolves through TenantMembership for Account users.
     */
    public static function can(string $permission, ?Authenticatable $user = null): bool
    {
        $user = $user ?? auth()->user();

        if (!$user) {
            return false;
        }

        // SuperAdmin bypasses all permission checks
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return true;
        }

        // Owner bypasses all permission checks within their tenant
        if (static::isOwner($user)) {
            return true;
        }

        return $user->can($permission);
    }

    /**
     * Check if the authenticated user lacks a specific permission.
     */
    public static function cannot(string $permission, ?Authenticatable $user = null): bool
    {
        return !static::can($permission, $user);
    }

    /**
     * Check if the authenticated user has a specific role.
     */
    public static function hasRole(string $role, ?Authenticatable $user = null): bool
    {
        $user = $user ?? auth()->user();

        if (!$user) {
            return false;
        }

        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return true;
        }

        return $user->hasRole($role);
    }

    /**
     * Check if the authenticated user has a specific permission (alias for can).
     */
    public static function hasPermission(string $permission, ?Authenticatable $user = null): bool
    {
        return static::can($permission, $user);
    }

    /**
     * Check if the user is the owner of the current tenant.
     */
    public static function isOwner(?Authenticatable $user = null): bool
    {
        $user = $user ?? auth()->user();

        if (!$user) {
            return false;
        }

        if ($user instanceof Account) {
            return $user->isOwner();
        }

        return false;
    }

    /**
     * Check if the user is a SuperAdmin.
     */
    public static function isSuperAdmin(?Authenticatable $user = null): bool
    {
        $user = $user ?? auth()->user();

        if (!$user) {
            return false;
        }

        return method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();
    }

    /**
     * Check if the user is an admin (owner or admin role).
     */
    public static function isAdmin(?Authenticatable $user = null): bool
    {
        $user = $user ?? auth()->user();

        if (!$user) {
            return false;
        }

        if (static::isSuperAdmin($user)) {
            return true;
        }

        if (static::isOwner($user)) {
            return true;
        }

        return $user->hasRole('admin');
    }

    /**
     * Get the current tenant membership for the user.
     */
    public static function getMembership(?Authenticatable $user = null): ?TenantMembership
    {
        $user = $user ?? auth()->user();

        if (!$user || !($user instanceof Account)) {
            return null;
        }

        return $user->getCurrentMembership();
    }

    /**
     * Check if the user is a member of the current tenant.
     */
    public static function isTenantMember(?Authenticatable $user = null): bool
    {
        $user = $user ?? auth()->user();

        if (!$user) {
            return false;
        }

        if (static::isSuperAdmin($user)) {
            return true;
        }

        if ($user instanceof Account) {
            return $user->getCurrentMembership() !== null;
        }

        // Legacy User model - check tenant_id
        if (method_exists($user, 'getAttribute') && $user->getAttribute('tenant_id')) {
            $currentTenant = Tenant::getCurrent();
            return $currentTenant && $user->getAttribute('tenant_id') == $currentTenant->id;
        }

        return false;
    }

    /**
     * Abort with 403 if user lacks permission.
     */
    public static function authorize(string $permission, ?Authenticatable $user = null): void
    {
        if (!static::can($permission, $user)) {
            abort(403, 'Unauthorized');
        }
    }

    /**
     * Abort with 403 if user lacks role.
     */
    public static function authorizeRole(string $role, ?Authenticatable $user = null): void
    {
        if (!static::hasRole($role, $user)) {
            abort(403, 'Unauthorized');
        }
    }

    /**
     * Abort with 403 if user is not owner.
     */
    public static function authorizeOwner(?Authenticatable $user = null): void
    {
        if (!static::isOwner($user) && !static::isSuperAdmin($user)) {
            abort(403, 'Unauthorized');
        }
    }
}
