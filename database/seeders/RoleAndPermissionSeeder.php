<?php

namespace Database\Seeders;

use App\Models\Account;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Seeder;

class RoleAndPermissionSeeder extends Seeder
{
    private array $adminPermissions = [
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

    private array $customerPermissions = [
        'orders.view-own',
        'orders.create',
        'orders.cancel-own',
        'payments.upload-proof',
    ];

    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->call(PermissionSeeder::class);

        // Create roles
        $superadminRole = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $customerRole = Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

        // Assign all permissions to superadmin
        $superadminRole->syncPermissions(Permission::all());

        // Assign admin permissions
        $adminRole->syncPermissions($this->adminPermissions);

        // Assign customer permissions
        $customerRole->syncPermissions($this->customerPermissions);

        // Create or update Super Admin user
        $superAdmin = \App\Models\User::updateOrCreate(
            ['email' => 'admin@shop.com'],
            [
                'name' => 'Super Admin',
                'password' => bcrypt('password'),
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        $superAdmin->assignRole('superadmin');

        // When Account mode is active, create a matching Account record so the
        // Super Admin can log in via the 'accounts' guard. Without this, the
        // seeder-created credentials exist only in the users table and the
        // accounts guard ("These credentials do not match our records.").
        if (config('identity.use_accounts')) {
            $superAdminAccount = Account::updateOrCreate(
                ['email' => 'admin@shop.com'],
                [
                    'password' => bcrypt('password'),
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]
            );
            $superAdminAccount->assignRole('superadmin');
        }

        $this->command->info('Roles, permissions, and Super Admin user seeded successfully.');
    }
}
