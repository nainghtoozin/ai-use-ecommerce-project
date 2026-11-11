<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Role;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

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
        //
        // Credentials are NEVER hard-coded. They come from environment
        // variables (SUPERADMIN_NAME / SUPERADMIN_EMAIL / SUPERADMIN_PASSWORD)
        // or, when running interactively, from secure prompts.
        // If none are provided, SuperAdmin creation is skipped and the
        // developer is told how to create one (php artisan superadmin:create).
        // ─────────────────────────────────────────────────────────────

        $name = env('SUPERADMIN_NAME');
        $email = env('SUPERADMIN_EMAIL');
        $password = env('SUPERADMIN_PASSWORD');

        $interactive = $this->command
            && method_exists($this->command, 'input')
            && $this->command->input
            && $this->command->input->isInteractive();

        if ((!$email || !$password) && $interactive) {
            $name = $name ?: $this->command->ask('SuperAdmin name', 'Super Admin');
            $email = $this->command->ask('SuperAdmin email');
            $password = $this->command->secret('SuperAdmin password');
            $confirm = $this->command->secret('Confirm password');
            if ($password !== $confirm) {
                $this->command->error('Passwords do not match. SuperAdmin not created.');
                $email = null;
                $password = null;
            }
        }

        if ($email && $password) {
            $name = $name ?: 'Super Admin';

            $superAdmin = Account::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => bcrypt($password),
                    'status' => Account::STATUS_ACTIVE,
                    'email_verified_at' => now(),
                ]
            );

            if ($superAdmin->wasRecentlyCreated) {
                // password already set above
            } else {
                $superAdmin->name = $name;
                $superAdmin->status = Account::STATUS_ACTIVE;
                if (!$superAdmin->email_verified_at) {
                    $superAdmin->email_verified_at = now();
                }
                $superAdmin->save();
            }

            if (!$superAdmin->hasRole('superadmin')) {
                $superAdmin->assignRole($superadminRole);
            }

            // LEGACY USER RECORD (backward compatibility)
            $superAdminUser = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => bcrypt($password),
                    'status' => User::STATUS_ACTIVE,
                    'email_verified_at' => now(),
                ]
            );

            if (!$superAdminUser->wasRecentlyCreated) {
                $superAdminUser->name = $name;
                $superAdminUser->status = User::STATUS_ACTIVE;
                if (!$superAdminUser->email_verified_at) {
                    $superAdminUser->email_verified_at = now();
                }
                $superAdminUser->save();
            }

            if (!$superAdminUser->hasRole('superadmin')) {
                $superAdminUser->assignRole($superadminRole);
            }

            if ($this->command) {
                $this->command->info("Platform seeder: SuperAdmin created ({$email}).");
            }
        } else {
            if ($this->command) {
                $this->command->warn('Platform seeder: SuperAdmin not created (no credentials provided).');
                $this->command->warn('Set SUPERADMIN_NAME/SUPERADMIN_EMAIL/SUPERADMIN_PASSWORD or run: php artisan superadmin:create');
            }
        }

        if ($this->command) {
            $this->command->info('Platform seeder: Global superadmin role created.');
            $this->command->info('Platform seeder: Global template roles (admin, customer) created.');
        }
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
            'store-categories.view',
            'store-categories.create',
            'store-categories.update',
            'store-categories.delete',

            // Customer-only — not assigned to admin/staff roles
            'orders.view-own',
            'orders.create',
            'orders.cancel-own',
            'payments.upload-proof',

            // Owner-only — granted directly to the store owner by
            // TenantBootstrapService. Staff never receive recovery rights.
            'backup.recover',
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
