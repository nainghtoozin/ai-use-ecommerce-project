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
        return [
            // Dashboard
            'dashboard.view',

            // User Management
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'users.suspend',
            'users.ban',
            'users.activate',
            'users.assign-roles',
            'users.view-activity',

            // Role Management
            'roles.view',
            'roles.create',
            'roles.update',
            'roles.delete',

            // Permission Management
            'permissions.view',
            'permissions.create',
            'permissions.edit',
            'permissions.delete',

            // Product Management
            'products.view',
            'products.create',
            'products.edit',
            'products.delete',

            // Category Management
            'categories.view',
            'categories.create',
            'categories.update',
            'categories.delete',

            // Order Management
            'orders.view',
            'orders.update-status',
            'orders.cancel-any',
            'orders.override-status',
            'orders.override-payment',

            // Payment / Billing
            'payments.view',
            'payments.create',
            'payments.update',
            'payments.delete',
            'payments.verify',
            'billing.view',
            'billing.renew',

            // Dashboard
            'activity-logs.view',

            // Reports
            'reports.view',
            'reports.sales',
            'reports.orders',
            'reports.products',
            'reports.payments',

            // Settings
            'settings.view',
            'settings.edit',
            'settings.website',
            'settings.telegram',
            'settings.notifications',

            // Unit Management
            'units.view',
            'units.create',
            'units.update',
            'units.delete',

            // Brand Management
            'brands.view',
            'brands.create',
            'brands.update',
            'brands.delete',

            // Coupon Management
            'coupons.view',
            'coupons.create',
            'coupons.update',
            'coupons.delete',

            // Promotion Management
            'promotions.view',
            'promotions.create',
            'promotions.update',
            'promotions.delete',

            // City Management
            'cities.view',
            'cities.create',
            'cities.update',
            'cities.delete',

            // Township Management
            'townships.view',
            'townships.create',
            'townships.update',
            'townships.delete',

            // Maintenance
            'bypass maintenance mode',
        ];
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
