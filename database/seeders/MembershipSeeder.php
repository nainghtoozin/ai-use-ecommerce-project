<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MembershipSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->command->warn('No tenants found. Skipping membership seeding.');
            return;
        }

        foreach ($tenants as $tenant) {
            $this->ensureTenantRoles($tenant);
            $this->ensureOwnerMembership($tenant);
        }

        $this->ensureCustomerMemberships();
        $this->repairDuplicateOwners();
        $this->repairMissingOwners();
    }

    // ─────────────────────────────────────────────────────────────
    // TENANT-SCOPED ROLES
    //
    // Per Platform Identity Design Lock:
    //   - superadmin is global (created by RoleAndPermissionSeeder)
    //   - admin, staff, customer are tenant-scoped (created here)
    // ─────────────────────────────────────────────────────────────

    protected function ensureTenantRoles(Tenant $tenant): void
    {
        foreach (['admin', 'staff', 'customer'] as $roleName) {
            $role = Role::withoutTenantScope()->firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web', 'tenant_id' => $tenant->id]
            );

            // Sync permissions from global template role
            $globalRole = Role::withoutTenantScope()
                ->where('name', $roleName)
                ->whereNull('tenant_id')
                ->first();

            if ($globalRole && $role->permissions->count() === 0) {
                $role->syncPermissions($globalRole->permissions);
            }
        }

        $this->command?->info("  Tenant roles ensured for '{$tenant->name}'.");
    }

    // ─────────────────────────────────────────────────────────────
    // OWNER MEMBERSHIP
    //
    // Per Platform Identity Design Lock:
    //   - Every tenant MUST have exactly one owner
    //   - Owner Account has NO direct role assignment (is_owner=true)
    //   - Owner implicitly has all permissions
    //   - Owner is NOT a SuperAdmin
    // ─────────────────────────────────────────────────────────────

    protected function ensureOwnerMembership(Tenant $tenant): void
    {
        $existingOwner = TenantMembership::where('tenant_id', $tenant->id)
            ->where('is_owner', true)
            ->first();

        if ($existingOwner) {
            $this->command?->info("  Owner already exists for '{$tenant->name}'.");
            return;
        }

        $ownerEmail = match ($tenant->slug) {
            'default' => 'owner@defaultstore.com',
            'khine' => 'owner@khine.com',
            'gadget' => 'owner@gadget.com',
            default => "owner@{$tenant->slug}.com",
        };

        $owner = Account::updateOrCreate(
            ['email' => $ownerEmail],
            [
                'name' => $tenant->name . ' Owner',
                'password' => Hash::make('password'),
                'status' => Account::STATUS_ACTIVE,
                'email_verified_at' => now(),
            ]
        );

        $adminRole = Role::where('name', 'admin')
            ->where('tenant_id', $tenant->id)
            ->first();

        if (!$adminRole) {
            $this->command->error("  Admin role not found for tenant '{$tenant->name}'. Skipping owner.");
            return;
        }

        TenantMembership::create(
            [
                'account_id' => $owner->id,
                'tenant_id' => $tenant->id,
                'role_id' => $adminRole->id,
                'is_owner' => true,
                'status' => 'active',
                'joined_at' => now(),
            ]
        );

        $this->command?->info("  Owner membership created for '{$tenant->name}' ({$ownerEmail}).");
    }

    // ─────────────────────────────────────────────────────────────
    // CUSTOMER MEMBERSHIPS (Default Store demo data)
    //
    // Creates demo customer accounts + memberships for Default Store.
    // These are DEMO DATA — not production records.
    // ─────────────────────────────────────────────────────────────

    protected function ensureCustomerMemberships(): void
    {
        $defaultTenant = Tenant::where('slug', 'default')->first();

        if (!$defaultTenant) {
            $this->command?->info('  No Default Store found. Skipping customer memberships.');
            return;
        }

        $customerRole = Role::where('name', 'customer')
            ->where('tenant_id', $defaultTenant->id)
            ->first();

        if (!$customerRole) {
            $this->command->error('  Customer role not found for Default Store. Skipping.');
            return;
        }

        $customers = [
            ['name' => 'John Doe', 'email' => 'john@example.com'],
            ['name' => 'Jane Smith', 'email' => 'jane@example.com'],
            ['name' => 'Mike Johnson', 'email' => 'mike@example.com'],
            ['name' => 'Sarah Williams', 'email' => 'sarah@example.com'],
            ['name' => 'David Brown', 'email' => 'david@example.com'],
            ['name' => 'Emily Davis', 'email' => 'emily@example.com'],
            ['name' => 'Chris Wilson', 'email' => 'chris@example.com'],
            ['name' => 'Lisa Taylor', 'email' => 'lisa@example.com'],
            ['name' => 'Tom Anderson', 'email' => 'tom@example.com'],
            ['name' => 'Anna White', 'email' => 'anna@example.com'],
        ];

        foreach ($customers as $data) {
            $account = Account::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make('password'),
                    'status' => Account::STATUS_ACTIVE,
                    'email_verified_at' => now(),
                ]
            );

            $membership = TenantMembership::firstOrCreate(
                [
                    'account_id' => $account->id,
                    'tenant_id' => $defaultTenant->id,
                ],
                [
                    'role_id' => $customerRole->id,
                    'is_owner' => false,
                    'status' => 'active',
                    'invited_at' => now(),
                    'joined_at' => now(),
                ]
            );

            if ($membership->wasRecentlyCreated) {
                $this->command?->info("  Customer membership created for '{$data['email']}'.");
            }
        }
    }

    // ─────────────────────────────────────────────────────────────
    // REPAIR: Duplicate Owners
    //
    // Ensures each tenant has at most one owner.
    // If duplicates exist, keeps the first and clears others.
    // ─────────────────────────────────────────────────────────────

    protected function repairDuplicateOwners(): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $owners = TenantMembership::where('tenant_id', $tenant->id)
                ->where('is_owner', true)
                ->orderBy('id')
                ->get();

            if ($owners->count() <= 1) {
                continue;
            }

            $this->command->warn("  Repairing duplicate owners for '{$tenant->name}'.");

            // Keep first owner, clear others
            $keepOwner = $owners->first();
            $owners->slice(1)->each(function ($membership) {
                $membership->update(['is_owner' => false]);
            });

            $cleared = $owners->count() - 1;
            $this->command->info("    Kept owner #{$keepOwner->account_id}, cleared {$cleared} duplicate(s).");
        }
    }

    // ─────────────────────────────────────────────────────────────
    // REPAIR: Missing Owners
    //
    // Ensures every tenant has an owner membership.
    // If missing, creates one from the tenant's email field.
    // ─────────────────────────────────────────────────────────────

    protected function repairMissingOwners(): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $hasOwner = TenantMembership::where('tenant_id', $tenant->id)
                ->where('is_owner', true)
                ->exists();

            if ($hasOwner) {
                continue;
            }

            $this->command->warn("  Repairing missing owner for '{$tenant->name}'.");

            // Use tenant email as owner email
            $ownerEmail = $tenant->email ?? "owner@{$tenant->slug}.com";

            $owner = Account::updateOrCreate(
                ['email' => $ownerEmail],
                [
                    'name' => $tenant->name . ' Owner',
                    'password' => Hash::make('password'),
                    'status' => Account::STATUS_ACTIVE,
                    'email_verified_at' => now(),
                ]
            );

            $adminRole = Role::where('name', 'admin')
                ->where('tenant_id', $tenant->id)
                ->first();

            if (!$adminRole) {
                $this->command->error("    Cannot repair: admin role missing for '{$tenant->name}'.");
                continue;
            }

            TenantMembership::create(
                [
                    'account_id' => $owner->id,
                    'tenant_id' => $tenant->id,
                    'role_id' => $adminRole->id,
                    'is_owner' => true,
                    'status' => 'active',
                    'joined_at' => now(),
                ]
            );

            $this->command->info("    Owner repaired for '{$tenant->name}' ({$ownerEmail}).");
        }
    }
}
