<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate([
            'name' => 'billing.manage',
            'guard_name' => 'web',
        ]);

        foreach (['superadmin', 'admin'] as $roleName) {
            $template = Role::withoutTenantScope()
                ->where('name', $roleName)
                ->whereNull('tenant_id')
                ->first();

            if ($template && !$template->hasPermissionTo($permission)) {
                $template->givePermissionTo($permission);
            }
        }

        Role::withoutTenantScope()
            ->where('name', 'admin')
            ->whereNotNull('tenant_id')
            ->chunkById(100, function ($roles) use ($permission) {
                foreach ($roles as $role) {
                    if (!$role->hasPermissionTo($permission)) {
                        $role->givePermissionTo($permission);
                    }
                }
            });

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('role_has_permissions')
            ->whereIn('permission_id', Permission::where('name', 'billing.manage')->pluck('id'))
            ->delete();

        Permission::where('name', 'billing.manage')->delete();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
