<?php

namespace App\Console\Commands;

use App\Models\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class SyncAfterSalesPermissions extends Command
{
    protected $signature = 'after-sales:sync-permissions';

    protected $description = 'Backfill after-sales permissions to existing tenant roles';

    private const ADMIN_PERMISSIONS = [
        'cancellations.view',
        'cancellations.manage',
        'cancellations.approve',
        'cancellations.reject',
        'cancellations.settings',
        'returns.view',
        'returns.approve',
        'returns.reject',
        'returns.receive',
        'returns.inspect',
        'returns.settings',
        'refunds.view',
        'refunds.manage',
        'refunds.process',
    ];

    private const CUSTOMER_PERMISSIONS = [
        'returns.view-own',
        'returns.create',
        'orders.cancel-own',
    ];

    public function handle(): int
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (array_merge(self::ADMIN_PERMISSIONS, self::CUSTOMER_PERMISSIONS) as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $adminPermissions = Permission::whereIn('name', self::ADMIN_PERMISSIONS)->get();
        $customerPermissions = Permission::whereIn('name', self::CUSTOMER_PERMISSIONS)->get();

        $roles = Role::withoutTenantScope()->whereNotNull('tenant_id')->get();
        $updated = 0;

        DB::transaction(function () use ($roles, $adminPermissions, $customerPermissions, &$updated) {
            foreach ($roles as $role) {
                $target = match ($role->name) {
                    'admin' => $adminPermissions,
                    'customer' => $customerPermissions,
                    default => null,
                };

                if (!$target) {
                    continue;
                }

                $missing = $target->reject(fn ($permission) => $role->permissions->contains('id', $permission->id));

                if ($missing->isNotEmpty()) {
                    $role->givePermissionTo($missing->all());
                    $updated++;
                }
            }
        });

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->info("After-sales permissions synced. {$updated} tenant role(s) updated.");

        return self::SUCCESS;
    }
}
