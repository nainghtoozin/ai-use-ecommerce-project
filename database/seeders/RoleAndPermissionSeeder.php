<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Role;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Seeder;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->call(PermissionSeeder::class);

        // ─────────────────────────────────────────────────────────────
        // PLATFORM ROLE: superadmin (global — tenant_id = NULL)
        //
        // Per Platform Identity Design Lock:
        //   - superadmin is the ONLY global role
        //   - tenant-scoped roles (admin, staff, customer) are created
        //     by MembershipSeeder per tenant
        //   - SuperAdmin Account has NO tenant_memberships
        // ─────────────────────────────────────────────────────────────

        $superadminRole = Role::firstOrCreate(
            ['name' => 'superadmin', 'guard_name' => 'web'],
            ['tenant_id' => null]
        );

        // Assign all permissions to superadmin
        $superadminRole->syncPermissions(Permission::all());

        // ─────────────────────────────────────────────────────────────
        // SUPERADMIN ACCOUNT (platform-only identity)
        //
        // Per Platform Identity Design Lock:
        //   - SuperAdmin lives ONLY in accounts table
        //   - NO tenant_id
        //   - NO TenantMembership
        //   - NO merchant/customer/staff profile
        // ─────────────────────────────────────────────────────────────

        $superAdmin = Account::updateOrCreate(
            ['email' => 'admin@shop.com'],
            [
                'name' => 'Super Admin',
                'password' => bcrypt('password'),
                'status' => Account::STATUS_ACTIVE,
                'email_verified_at' => now(),
            ]
        );

        if (!$superAdmin->hasRole('superadmin')) {
            $superAdmin->assignRole($superadminRole);
        }

        // ─────────────────────────────────────────────────────────────
        // LEGACY USER RECORD (backward compatibility)
        //
        // Synced via SyncsIdentity trait. Created here to ensure the
        // legacy users table has the SuperAdmin record for backward
        // compatibility with existing model relationships.
        // ─────────────────────────────────────────────────────────────

        $superAdminUser = User::updateOrCreate(
            ['email' => 'admin@shop.com'],
            [
                'name' => 'Super Admin',
                'password' => bcrypt('password'),
                'status' => User::STATUS_ACTIVE,
                'email_verified_at' => now(),
            ]
        );

        if (!$superAdminUser->hasRole('superadmin')) {
            $superAdminUser->assignRole($superadminRole);
        }

        $this->command->info('Platform seeder: SuperAdmin created (accounts + users).');
        $this->command->info('Platform seeder: Global superadmin role created.');
    }
}
