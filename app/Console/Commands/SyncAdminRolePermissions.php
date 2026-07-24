<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;

class SyncAdminRolePermissions extends Command
{
    protected $signature = 'permissions:sync-admin-roles
                            {--dry-run : Preview changes without applying them}';

    protected $description = 'Sync all tenant admin roles with the global template, adding any missing permissions (e.g. newly registered permissions)';

    public function handle(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $globalAdmin = Role::withoutTenantScope()
            ->where('name', 'admin')
            ->whereNull('tenant_id')
            ->first();

        if (!$globalAdmin) {
            $this->error('Global admin template role not found. Run RoleAndPermissionSeeder first.');
            return;
        }

        $allPermissions = Permission::all()->pluck('name')->toArray();
        $globalPerms = $globalAdmin->permissions->pluck('name')->toArray();
        $missingFromGlobal = array_diff($allPermissions, $globalPerms);

        if (!empty($missingFromGlobal)) {
            if ($this->option('dry-run')) {
                $this->warn('[DRY-RUN] Would add ' . count($missingFromGlobal) . ' permission(s) to global admin template:');
                foreach ($missingFromGlobal as $perm) {
                    $this->line("  + {$perm}");
                }
            } else {
                $globalAdmin->givePermissionTo($missingFromGlobal);
                $this->info('Added ' . count($missingFromGlobal) . ' missing permission(s) to global admin template.');
                foreach ($missingFromGlobal as $perm) {
                    $this->line("  + {$perm}");
                }
            }
        } else {
            $this->info('Global admin template is up to date.');
        }

        $globalPerms = $globalAdmin->permissions->pluck('name')->toArray();
        $tenants = Tenant::all();
        $bar = $this->output->createProgressBar($tenants->count());
        $bar->start();

        $checked = 0;
        $repaired = 0;
        $totalAdded = 0;

        foreach ($tenants as $tenant) {
            $role = Role::withoutTenantScope()
                ->where('name', 'admin')
                ->where('tenant_id', $tenant->id)
                ->first();

            if ($role) {
                $checked++;
                $currentPerms = $role->permissions->pluck('name')->toArray();
                $missing = array_diff($globalPerms, $currentPerms);

                if (!empty($missing)) {
                    $repaired++;
                    $totalAdded += count($missing);

                    if (!$this->option('dry-run')) {
                        $role->givePermissionTo($missing);
                    }
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if ($this->option('dry-run')) {
            $this->info("Dry-run complete. Checked {$checked} tenant admin role(s). "
                . "{$repaired} role(s) would be updated (would add {$totalAdded} permission(s)).");
        } else {
            $this->info("Sync complete. Checked {$checked} tenant admin role(s). "
                . "{$repaired} role(s) updated ({$totalAdded} permission(s) added).");
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
