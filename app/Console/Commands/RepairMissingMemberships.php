<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use Illuminate\Console\Command;

class RepairMissingMemberships extends Command
{
    protected $signature = 'memberships:repair
        {--dry-run : Report missing memberships without creating them}
        {--tenant-id= : Assign to a specific tenant ID instead of default}';

    protected $description = 'Create missing TenantMemberships for Accounts that have none';

    public function handle(): int
    {
        $this->info('Scanning for Accounts without TenantMemberships...');

        $accountCount = Account::count();
        $this->line("  Total Accounts: {$accountCount}");

        $accounts = Account::doesntHave('memberships')->get();

        if ($accounts->isEmpty()) {
            $this->info('All Accounts have at least one membership. Nothing to repair.');
            return 0;
        }

        $this->warn('  Accounts without any membership: ' . $accounts->count());

        $dryRun = $this->option('dry-run');

        $tenantId = $this->option('tenant-id');
        $tenant = null;

        if ($tenantId) {
            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
                $this->error("Tenant with ID {$tenantId} not found.");
                return 1;
            }
        } else {
            $tenant = Tenant::getDefault();
            if (!$tenant) {
                $this->error('No default tenant found and no --tenant-id provided.');
                return 1;
            }
        }

        $this->line("  Target tenant: {$tenant->name} (ID: {$tenant->id})");

        $customerRole = $this->ensureCustomerRole($tenant);

        $repaired = 0;
        $skipped = 0;

        foreach ($accounts as $account) {
            if ($account->isSuperAdmin()) {
                $this->line("    Skipped superadmin: {$account->email}");
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $this->line("    Would create membership for: {$account->email}");
                $repaired++;
                continue;
            }

            TenantMembership::create([
                'account_id' => $account->id,
                'tenant_id' => $tenant->id,
                'role_id' => $customerRole->id,
                'is_owner' => false,
                'status' => 'active',
                'joined_at' => now(),
            ]);

            $this->line("    Created membership for: {$account->email}");
            $repaired++;
        }

        if ($dryRun) {
            $this->info("Dry run: {$repaired} memberships would be created, {$skipped} skipped (superadmin).");
        } else {
            $this->info("Repaired: {$repaired} memberships created, {$skipped} skipped (superadmin).");
        }

        return 0;
    }

    protected function ensureCustomerRole(Tenant $tenant): Role
    {
        return Role::firstOrCreate([
            'name' => 'customer',
            'guard_name' => 'web',
            'tenant_id' => $tenant->id,
        ]);
    }
}
