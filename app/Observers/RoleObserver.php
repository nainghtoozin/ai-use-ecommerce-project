<?php

namespace App\Observers;

use App\Models\Role;
use App\Services\PermissionCacheService;

class RoleObserver
{
    public function created(Role $role): void
    {
        PermissionCacheService::clearForRole($role);
    }

    public function updated(Role $role): void
    {
        PermissionCacheService::clearForRole($role);
    }

    public function deleted(Role $role): void
    {
        PermissionCacheService::clearForRole($role);
    }
}
