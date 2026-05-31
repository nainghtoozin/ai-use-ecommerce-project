<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Console\Command;

class RepairMerchantPermissions extends Command
{
    protected $signature = 'merchants:repair-permissions
                            {--dry-run : Check for missing permissions without making changes}';

    protected $description = 'Add missing template permissions to existing merchant admin roles';

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
        $tenants = Tenant::all();

        $checked = 0;
        $fixed = 0;
        $totalAdded = 0;

        $bar = $this->output->createProgressBar($tenants->count());
        $bar->start();

        foreach ($tenants as $tenant) {
            $role = Role::where('tenant_id', $tenant->id)
                ->where('name', 'admin')
                ->first();

            if (!$role) {
                $bar->advance();
                continue;
            }

            $checked++;
            $currentPerms = $role->permissions->pluck('name')->toArray();
            $missing = array_diff($globalPerms, $currentPerms);

            if (!empty($missing)) {
                $fixed++;
                $count = count($missing);
                $totalAdded += $count;

                if ($this->option('dry-run')) {
                    $this->newLine();
                    $this->warn("  [DRY-RUN] Tenant #{$tenant->id} \"{$tenant->name}\": would add {$count} permission(s)");
                    foreach ($missing as $perm) {
                        $this->line("    + {$perm}");
                    }
                } else {
                    $role->givePermissionTo($missing);
                    $this->newLine();
                    $this->info("  Tenant #{$tenant->id} \"{$tenant->name}\": added {$count} permission(s)");
                    foreach ($missing as $perm) {
                        $this->line("    + {$perm}");
                    }
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if ($this->option('dry-run')) {
            $this->info("Dry-run complete. Checked {$checked} admin role(s). {$fixed} would be repaired ({$totalAdded} permissions).");
        } else {
            $this->info("Repair complete. Checked {$checked} admin role(s). {$fixed} repaired ({$totalAdded} permissions added).");
        }
    }
}
