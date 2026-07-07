<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add tenant_id to roles table
        Schema::table('roles', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')
                ->constrained()->nullOnDelete();
        });

        // 2. Drop old unique index, create new one with tenant_id
        DB::statement('ALTER TABLE roles DROP INDEX roles_name_guard_name_unique');
        Schema::table('roles', function (Blueprint $table) {
            $table->unique(['tenant_id', 'name', 'guard_name'], 'roles_tenant_id_name_guard_name_unique');
        });

        // 3. Create tenant-specific roles and reassign users
        $this->backfillTenantRoles();
    }

    private function backfillTenantRoles(): void
    {
        $tenantIds = DB::table('tenants')->pluck('id');

        // Get global role IDs and their permissions
        $globalRoles = DB::table('roles')->whereNull('tenant_id')->get()->keyBy('name');

        foreach ($tenantIds as $tenantId) {
            foreach (['admin', 'customer'] as $roleName) {
                $globalRole = $globalRoles->get($roleName);
                if (!$globalRole) {
                    continue;
                }

                // Check if tenant-specific role already exists
                $existing = DB::table('roles')
                    ->where('tenant_id', $tenantId)
                    ->where('name', $roleName)
                    ->where('guard_name', 'web')
                    ->exists();

                if ($existing) {
                    continue;
                }

                // Create tenant-specific role
                $newRoleId = DB::table('roles')->insertGetId([
                    'tenant_id' => $tenantId,
                    'name' => $roleName,
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Copy permissions from global role
                $permissionIds = DB::table('role_has_permissions')
                    ->where('role_id', $globalRole->id)
                    ->pluck('permission_id');

                foreach ($permissionIds as $permId) {
                    DB::table('role_has_permissions')->insert([
                        'permission_id' => $permId,
                        'role_id' => $newRoleId,
                    ]);
                }

                // Reassign users from this tenant from global role to tenant-specific role
                $usersToReassign = DB::table('model_has_roles')
                    ->join('users', 'model_has_roles.model_id', '=', 'users.id')
                    ->where('model_has_roles.role_id', $globalRole->id)
                    ->where('model_has_roles.model_type', 'App\Models\User')
                    ->where('users.tenant_id', $tenantId)
                    ->select('model_has_roles.model_id')
                    ->get();

                foreach ($usersToReassign as $user) {
                    DB::table('model_has_roles')
                        ->where('role_id', $globalRole->id)
                        ->where('model_id', $user->model_id)
                        ->where('model_type', 'App\Models\User')
                        ->delete();

                    DB::table('model_has_roles')->insert([
                        'role_id' => $newRoleId,
                        'model_type' => 'App\Models\User',
                        'model_id' => $user->model_id,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // Remove tenant-specific roles (tenant_id is not null) — keep global roles
        DB::table('roles')->whereNotNull('tenant_id')->delete();

        Schema::table('roles', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropUnique('roles_tenant_id_name_guard_name_unique');
        });

        DB::statement('ALTER TABLE roles ADD UNIQUE INDEX roles_name_guard_name_unique (name, guard_name)');

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('tenant_id');
        });
    }
};
