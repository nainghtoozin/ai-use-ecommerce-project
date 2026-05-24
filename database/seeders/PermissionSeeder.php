<?php

namespace Database\Seeders;

use Spatie\Permission\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
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
            'permissions.view',

            // Product Management
            'products.view',
            'products.create',
            'products.update',
            'products.delete',

            // Category Management
            'categories.view',
            'categories.create',
            'categories.update',
            'categories.delete',

            // Order Management
            'orders.view',
            'orders.view-own',
            'orders.create',
            'orders.update-status',
            'orders.cancel-own',
            'orders.cancel-any',

            // Payment Management
            'payments.upload-proof',
            'payments.view',
            'payments.verify',

            // Dashboard
            'dashboard.view',

            // Activity Logs
            'activity-logs.view',

            // Maintenance
            'bypass maintenance mode',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $this->command->info('Permissions seeded successfully.');
    }
}
