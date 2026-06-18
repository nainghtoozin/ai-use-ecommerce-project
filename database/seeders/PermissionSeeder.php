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
            'categories.edit',
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
            'settings.payment-methods',
            'settings.shipping',
            'settings.seo',

            // Super Admin Override
            'orders.override-status',
            'orders.override-payment',

            // Unit Management
            'units.view',
            'units.create',
            'units.edit',
            'units.delete',

            // Brand Management
            'brands.view',
            'brands.create',
            'brands.edit',
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

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $this->command->info('Permissions seeded successfully.');
    }
}
