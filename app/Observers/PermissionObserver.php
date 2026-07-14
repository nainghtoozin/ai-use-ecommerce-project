<?php

namespace App\Observers;

use App\Services\PermissionCacheService;
use Spatie\Permission\Models\Permission;

class PermissionObserver
{
    public function created(Permission $permission): void
    {
        PermissionCacheService::clearAll();
    }

    public function updated(Permission $permission): void
    {
        PermissionCacheService::clearAll();
    }

    public function deleted(Permission $permission): void
    {
        PermissionCacheService::clearAll();
    }
}
