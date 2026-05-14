<?php

namespace Database\Seeders;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Seeder;

class RoleAndPermissionSeeder extends Seeder
{
    private array $superadminPermissions = [
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
    ];

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
        'permissions.view',
        'products.view',
        'products.create',
        'products.update',
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
        $superadminRole->syncPermissions($this->superadminPermissions);

        // Assign admin permissions
        $adminRole->syncPermissions($this->adminPermissions);

        // Assign customer permissions
        $customerRole->syncPermissions($this->customerPermissions);

        // Assign superadmin role to admin@shop.com if the user exists
        $adminUser = \App\Models\User::where('email', 'admin@shop.com')->first();
        if ($adminUser) {
            $adminUser->syncRoles(['superadmin']);
            if ($adminUser->role !== 'superadmin') {
                $adminUser->update(['role' => 'superadmin']);
            }
            $this->command->info('Superadmin role assigned to admin@shop.com.');
        }

        // Sync existing admins to admin role
        $adminCount = \App\Models\User::where('email', '!=', 'admin@shop.com')
            ->where(function ($q) {
                $q->where('role', 'admin')
                  ->orWhereHas('roles', fn($r) => $r->where('name', 'admin'));
            })
            ->count();
        if ($adminCount > 0) {
            \App\Models\User::where('email', '!=', 'admin@shop.com')
                ->where(function ($q) {
                    $q->where('role', 'admin')
                      ->orWhereHas('roles', fn($r) => $r->where('name', 'admin'));
                })
                ->chunk(100, function ($users) {
                    foreach ($users as $user) {
                        $user->syncRoles(['admin']);
                        if ($user->role !== 'admin') {
                            $user->update(['role' => 'admin']);
                        }
                    }
                });
            $this->command->info("Synced {$adminCount} existing admins to admin role.");
        }

        // Sync remaining users (non-admin, non-superadmin) to customer role
        $customerCount = \App\Models\User::where('email', '!=', 'admin@shop.com')
            ->whereDoesntHave('roles')
            ->orWhere(function ($q) {
                $q->where('email', '!=', 'admin@shop.com')
                  ->whereHas('roles', fn($r) => $r->where('name', 'customer'));
            })
            ->count();

        \App\Models\User::where('email', '!=', 'admin@shop.com')
            ->whereDoesntHave('roles', fn($q) => $q->whereIn('name', ['superadmin', 'admin']))
            ->orWhere(function ($q) {
                $q->where('email', '!=', 'admin@shop.com')
                  ->where('role', 'customer');
            })
            ->chunk(100, function ($users) {
                foreach ($users as $user) {
                    if (!$user->hasRole('superadmin') && !$user->hasRole('admin')) {
                        $user->syncRoles(['customer']);
                        if ($user->role !== 'customer') {
                            $user->update(['role' => 'customer']);
                        }
                    }
                }
            });

        $this->command->info('Roles and permissions seeded successfully.');
    }
}
