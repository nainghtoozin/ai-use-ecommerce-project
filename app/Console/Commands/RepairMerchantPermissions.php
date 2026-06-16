<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;

class RepairMerchantPermissions extends Command
{
    protected $signature = 'merchants:repair-permissions
                            {--dry-run : Check for missing permissions without making changes}';

    protected $description = 'Add missing template permissions to existing merchant admin roles and owner users';

    public function handle(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $globalAdmin = Role::whereNull('tenant_id')
            ->where('name', 'admin')
            ->first();

        if (!$globalAdmin) {
            $this->error('Global admin role not found. Run RoleAndPermissionSeeder first.');
            return;
        }

        $globalPerms = $globalAdmin->permissions->pluck('name')->toArray();
        $allPerms = Permission::all()->pluck('name')->toArray();
        $tenants = Tenant::all();

        $checkedRoles = 0;
        $fixedRoles = 0;
        $totalAddedToRoles = 0;
        $checkedOwners = 0;
        $fixedOwners = 0;

        $bar = $this->output->createProgressBar($tenants->count());
        $bar->start();

        foreach ($tenants as $tenant) {
            // Repair admin role
            $role = Role::where('tenant_id', $tenant->id)
                ->where('name', 'admin')
                ->first();

            if ($role) {
                $checkedRoles++;
                $currentPerms = $role->permissions->pluck('name')->toArray();
                $missing = array_diff($globalPerms, $currentPerms);

                if (!empty($missing)) {
                    $fixedRoles++;
                    $count = count($missing);
                    $totalAddedToRoles += $count;

                    if ($this->option('dry-run')) {
                        $this->newLine();
                        $this->warn("  [DRY-RUN] Tenant #{$tenant->id} \"{$tenant->name}\": would add {$count} permission(s) to admin role");
                        foreach ($missing as $perm) {
                            $this->line("    + {$perm}");
                        }
                    } else {
                        $role->givePermissionTo($missing);
                        $this->newLine();
                        $this->info("  Tenant #{$tenant->id} \"{$tenant->name}\": added {$count} permission(s) to admin role");
                        foreach ($missing as $perm) {
                            $this->line("    + {$perm}");
                        }
                    }
                }
            }

            // Repair owner users: give all permissions
            $owners = User::where('tenant_id', $tenant->id)
                ->where('is_owner', true)
                ->get();

            foreach ($owners as $owner) {
                $checkedOwners++;
                $ownerPerms = $owner->getAllPermissions()->pluck('name')->toArray();
                $missingOwnerPerms = array_diff($allPerms, $ownerPerms);

                if (!empty($missingOwnerPerms)) {
                    $fixedOwners++;

                    if ($this->option('dry-run')) {
                        $this->newLine();
                        $this->warn("  [DRY-RUN] Tenant #{$tenant->id} \"{$tenant->name}\": owner \"{$owner->email}\" is missing {$count} permission(s)");
                    } else {
                        $owner->givePermissionTo($missingOwnerPerms);
                    }
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if ($this->option('dry-run')) {
            $this->info("Dry-run complete. Checked {$checkedRoles} admin role(s) and {$checkedOwners} owner(s). "
                . "{$fixedRoles} role(s) and {$fixedOwners} owner(s) would be repaired.");
        } else {
            $this->info("Repair complete. Checked {$checkedRoles} admin role(s) and {$checkedOwners} owner(s). "
                . "{$fixedRoles} role(s) repaired ({$totalAddedToRoles} permissions added), "
                . "{$fixedOwners} owner(s) synced.");
        }
    }
}
