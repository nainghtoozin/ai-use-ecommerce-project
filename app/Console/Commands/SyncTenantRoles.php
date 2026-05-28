<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;

class SyncTenantRoles extends Command
{
    protected $signature = 'tenants:sync-roles {--migrate-assignments : Migrate existing role assignments from global to tenant-specific roles}';

    protected $description = 'Create tenant-specific default roles for all tenants';

    public function handle(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $tenants = Tenant::all();
        $bar = $this->output->createProgressBar($tenants->count());
        $bar->start();

        $created = 0;
        $migrated = 0;

        foreach ($tenants as $tenant) {
            foreach (['admin', 'customer'] as $roleName) {
                $role = Role::firstOrCreate([
                    'name' => $roleName,
                    'guard_name' => 'web',
                    'tenant_id' => $tenant->id,
                ]);

                if ($role->wasRecentlyCreated) {
                    $created++;
                    $globalRole = Role::where('name', $roleName)
                        ->whereNull('tenant_id')
                        ->first();
                    if ($globalRole) {
                        $role->syncPermissions($globalRole->permissions);
                    }
                }
            }

            if ($this->option('migrate-assignments')) {
                $globalAdminRole = Role::where('name', 'admin')->whereNull('tenant_id')->first();
                $globalCustomerRole = Role::where('name', 'customer')->whereNull('tenant_id')->first();

                $tenantAdminRole = Role::where('name', 'admin')->where('tenant_id', $tenant->id)->first();
                $tenantCustomerRole = Role::where('name', 'customer')->where('tenant_id', $tenant->id)->first();

                if ($globalAdminRole && $tenantAdminRole) {
                    $usersOnGlobal = User::where('tenant_id', $tenant->id)
                        ->role($globalAdminRole)
                        ->get();
                    foreach ($usersOnGlobal as $user) {
                        $user->removeRole($globalAdminRole);
                        $user->assignRole($tenantAdminRole);
                        $migrated++;
                    }
                }

                if ($globalCustomerRole && $tenantCustomerRole) {
                    $usersOnGlobal = User::where('tenant_id', $tenant->id)
                        ->role($globalCustomerRole)
                        ->get();
                    foreach ($usersOnGlobal as $user) {
                        $user->removeRole($globalCustomerRole);
                        $user->assignRole($tenantCustomerRole);
                        $migrated++;
                    }
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Created {$created} tenant-specific role(s).");
        if ($this->option('migrate-assignments')) {
            $this->info("Migrated {$migrated} user role assignment(s).");
        }
    }
}
