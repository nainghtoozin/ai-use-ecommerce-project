<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use App\Models\Role;

class SyncUserRoles extends Command
{
    protected $signature = 'sync:user-roles';
    protected $description = 'Assign customer role to users without any Spatie role';

    public function handle(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $bar = $this->output->createProgressBar(User::count());
        $bar->start();

        $updated = 0;
        User::chunk(100, function ($users) use ($bar, &$updated) {
            foreach ($users as $user) {
                if (!$user->hasAnyRole(Role::all())) {
                    $customerRole = Role::firstOrCreate([
                        'name' => 'customer',
                        'guard_name' => 'web',
                        'tenant_id' => $user->tenant_id,
                    ]);
                    $user->assignRole($customerRole);
                    $updated++;
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Synced {$updated} users.");
    }
}
