<?php

namespace App\Providers;

use App\Models\Role;
use App\Observers\RoleObserver;
use App\Observers\PermissionObserver;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Permission;

class PermissionObserverServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Role::observe(RoleObserver::class);
        Permission::observe(PermissionObserver::class);
    }
}
