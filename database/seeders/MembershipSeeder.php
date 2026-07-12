<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MembershipSeeder extends Seeder
{
    public function run(): void
    {
        $useAccounts = config('identity.use_accounts');

        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $this->ensureTenantRoles($tenant);

            if ($useAccounts) {
                $this->ensureOwnerMembership($tenant);
            }
        }

        if ($useAccounts) {
            $this->ensureCustomerMemberships();
        }
    }

    protected function ensureTenantRoles(Tenant $tenant): void
    {
        foreach (['admin', 'customer', 'staff'] as $roleName) {
            Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
                'tenant_id' => $tenant->id,
            ]);
        }
    }

    protected function ensureOwnerMembership(Tenant $tenant): void
    {
        $existingOwner = TenantMembership::where('tenant_id', $tenant->id)
            ->where('is_owner', true)
            ->exists();

        if ($existingOwner) {
            return;
        }

        $ownerEmail = match ($tenant->slug) {
            'default' => 'owner@defaultstore.com',
            'khine' => 'owner@khine.com',
            'gadget' => 'owner@gadget.com',
            default => "owner@{$tenant->slug}.com",
        };

        $owner = Account::firstOrCreate(
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
            return;
        }

        TenantMembership::firstOrCreate(
            [
                'account_id' => $owner->id,
                'tenant_id' => $tenant->id,
            ],
            [
                'role_id' => $adminRole->id,
                'is_owner' => true,
                'status' => 'active',
                'joined_at' => now(),
            ]
        );

        $this->command?->info("  Owner membership ensured for tenant '{$tenant->name}'.");
    }

    protected function ensureCustomerMemberships(): void
    {
        $defaultTenant = Tenant::where('slug', 'default')->first();

        if (!$defaultTenant) {
            return;
        }

        $customerRole = Role::where('name', 'customer')
            ->where('tenant_id', $defaultTenant->id)
            ->first();

        if (!$customerRole) {
            return;
        }

        $customerEmails = [
            'john@example.com',
            'jane@example.com',
            'mike@example.com',
            'sarah@example.com',
            'david@example.com',
            'emily@example.com',
            'chris@example.com',
            'lisa@example.com',
            'tom@example.com',
            'anna@example.com',
        ];

        foreach ($customerEmails as $email) {
            $account = Account::where('email', $email)->first();

            if (!$account) {
                continue;
            }

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
                $this->command?->info("  Customer membership created for '{$email}'.");
            }
        }
    }
}
