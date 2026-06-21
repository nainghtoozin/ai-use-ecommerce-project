<?php

namespace Database\Seeders;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Seeder;

class RoleAndPermissionSeeder extends Seeder
{
    private array $adminPermissions = [
        'dashboard.view',
        'users.view',
        'users.create',
        'users.update',
        'users.delete',
        'users.suspend',
        'users.ban',
        'users.activate',
        'users.assign-roles',
        'users.view-activity',
        'roles.view',
        'roles.create',
        'roles.update',
        'roles.delete',
        'permissions.view',
        'permissions.create',
        'permissions.edit',
        'permissions.delete',
        'products.view',
        'products.create',
        'products.edit',
        'products.delete',
        'categories.view',
        'categories.create',
        'categories.update',
        'categories.delete',
        'orders.view',
        'orders.update-status',
        'orders.cancel-any',
        'payments.view',
        'payments.verify',
        'activity-logs.view',

            // Reports
            'reports.view',

            // Settings
            'settings.view',
            'settings.edit',

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

        $this->command->info('Roles, permissions, and Super Admin user seeded successfully.');
    }
}
