<?php

namespace App\Services;

use App\Models\Role;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

class PermissionCacheService
{
    /**
     * Clear all permission caches.
     */
    public static function clearAll(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Clear cache for a specific tenant.
     */
    public static function clearForTenant(int $tenantId): void
    {
        $key = config('permission.cache.key', 'spatie.permission.cache');
        Cache::forget("{$key}.tenant.{$tenantId}");
    }

    /**
     * Clear cache for a specific role.
     */
    public static function clearForRole(Role $role): void
    {
        static::clearAll();

        if ($role->tenant_id) {
            static::clearForTenant($role->tenant_id);
        }
    }

    /**
     * Get cache key for tenant-specific permissions.
     */
    public static function getTenantCacheKey(int $tenantId): string
    {
        return config('permission.cache.key', 'spatie.permission.cache') . ".tenant.{$tenantId}";
    }
}
