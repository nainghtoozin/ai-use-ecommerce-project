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
        // GLOBAL TEMPLATE ROLES (tenant_id = NULL)
        //
        // These serve as permission templates for tenant-scoped roles.
        // When TenantBootstrapService creates tenant roles, it copies
        // permissions from these global templates.
        // ─────────────────────────────────────────────────────────────

        $this->createGlobalTemplateRole('admin', $this->adminPermissions());
        $this->createGlobalTemplateRole('customer', $this->customerPermissions());

        // ─────────────────────────────────────────────────────────────
        // SUPERADMIN ACCOUNT (platform-only identity)
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
        $this->command->info('Platform seeder: Global template roles (admin, customer) created.');
    }

    protected function createGlobalTemplateRole(string $name, array $permissions): Role
    {
        $role = Role::firstOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
            ['tenant_id' => null]
        );

        $permissionModels = Permission::whereIn('name', $permissions)->get();
        $role->syncPermissions($permissionModels);

        return $role;
    }

    protected function adminPermissions(): array
    {
        return Permission::whereNotIn('name', [
            // SuperAdmin-only — platform-level settings, not tenant-facing
            'platform.settings.view',
            'platform.settings.update',
            'billing-payment-method.view',
            'billing-payment-method.create',
            'billing-payment-method.update',
            'billing-payment-method.delete',

            // Customer-only — not assigned to admin/staff roles
            'orders.view-own',
            'orders.create',
            'orders.cancel-own',
            'payments.upload-proof',
        ])->pluck('name')->toArray();
    }

    protected function customerPermissions(): array
    {
        return [
            'orders.view-own',
            'orders.create',
            'orders.cancel-own',
            'payments.upload-proof',
        ];
    }
}
