<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use DB;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

class TenantDeletionService
{
    public function delete(Tenant $tenant, bool $dryRun = false): array
    {
        $tenantId = $tenant->id;
        $summary = [];

        $this->collectUserIds($tenantId);
        $this->collectRoleIds($tenantId);

        $this->guardLastSuperadmin($tenantId);

        DB::beginTransaction();
        try {
            $this->deleteModelHasRoles($summary, $dryRun);
            $this->deleteModelHasPermissions($summary, $dryRun);
            $this->deleteRoleHasPermissions($summary, $dryRun);

            $this->deletePivotTables($tenantId, $summary, $dryRun);
            $this->deleteChildTables($tenantId, $summary, $dryRun);
            $this->deleteStandaloneTables($tenantId, $summary, $dryRun);

            $this->deleteTownships($tenantId, $summary, $dryRun);
            $this->deleteCities($tenantId, $summary, $dryRun);

            $this->deleteParentTables($tenantId, $summary, $dryRun);

            $this->deleteUsers($tenantId, $summary, $dryRun);
            $this->deleteRoles($tenantId, $summary, $dryRun);
            $this->deleteSubscriptions($tenantId, $summary, $dryRun);

            if (!$dryRun) {
                app(PermissionRegistrar::class)->forgetCachedPermissions();
                $tenant->delete();
                Tenant::clearDefaultCache();
            }

            $summary['tenant'] = 1;

            if ($dryRun) {
                DB::rollBack();
                return $summary;
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $summary;
    }

    private array $userIds = [];
    private array $roleIds = [];
    private int $ownerCount = 0;

    private function collectUserIds(int $tenantId): void
    {
        $this->userIds = DB::table('users')
            ->where('tenant_id', $tenantId)
            ->pluck('id')
            ->toArray();
    }

    private function collectRoleIds(int $tenantId): void
    {
        $this->roleIds = DB::table('roles')
            ->where('tenant_id', $tenantId)
            ->pluck('id')
            ->toArray();
    }

    private function guardLastSuperadmin(int $tenantId): void
    {
        $superadminRoleId = DB::table('roles')
            ->where('name', 'superadmin')
            ->whereNull('tenant_id')
            ->value('id');

        if (!$superadminRoleId) {
            return;
        }

        $superadminCount = DB::table('model_has_roles')
            ->where('role_id', $superadminRoleId)
            ->where('model_type', (new User)->getMorphClass())
            ->whereIn('model_id', $this->userIds)
            ->count();

        if ($superadminCount > 0) {
            $totalSuperadmins = DB::table('model_has_roles')
                ->where('role_id', $superadminRoleId)
                ->where('model_type', (new User)->getMorphClass())
                ->count();

            if ($totalSuperadmins - $superadminCount <= 0) {
                throw new RuntimeException(
                    'Cannot delete tenant: it contains the last remaining superadmin account(s).'
                );
            }
        }
    }

    private function countOwners(int $tenantId): void
    {
        $this->ownerCount = DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where('is_owner', true)
            ->count();
    }

    private function deleteModelHasRoles(array &$summary, bool $dryRun): void
    {
        $q = DB::table('model_has_roles')->where(function ($q) {
            $q->whereIn('role_id', $this->roleIds);
            if (!empty($this->userIds)) {
                $q->orWhere(function ($q) {
                    $q->where('model_type', 'App\Models\User')
                      ->whereIn('model_id', $this->userIds);
                });
            }
        });
        $this->doDelete('model_has_roles', $q, $summary, $dryRun);
    }

    private function deleteModelHasPermissions(array &$summary, bool $dryRun): void
    {
        if (empty($this->userIds)) {
            return;
        }
        $q = DB::table('model_has_permissions')
            ->where('model_type', 'App\Models\User')
            ->whereIn('model_id', $this->userIds);
        $this->doDelete('model_has_permissions', $q, $summary, $dryRun);
    }

    private function deleteRoleHasPermissions(array &$summary, bool $dryRun): void
    {
        if (empty($this->roleIds)) {
            return;
        }
        $q = DB::table('role_has_permissions')->whereIn('role_id', $this->roleIds);
        $this->doDelete('role_has_permissions', $q, $summary, $dryRun);
    }

    private function deletePivotTables(int $tenantId, array &$summary, bool $dryRun): void
    {
        foreach (['coupon_category', 'coupon_product', 'order_coupon',
                  'promotion_banners', 'promotion_category',
                  'promotion_product', 'promotion_usages'] as $table) {
            $this->deleteByTenantId($table, $tenantId, $summary, $dryRun);
        }
    }

    private function deleteChildTables(int $tenantId, array &$summary, bool $dryRun): void
    {
        foreach (['order_items', 'product_combos', 'product_variants',
                  'wishlists', 'messages'] as $table) {
            $this->deleteByTenantId($table, $tenantId, $summary, $dryRun);
        }
    }

    private function deleteStandaloneTables(int $tenantId, array &$summary, bool $dryRun): void
    {
        foreach (['activity_logs', 'notifications', 'payment_methods',
                  'settings', 'telegram_integrations', 'website_infos'] as $table) {
            $this->deleteByTenantId($table, $tenantId, $summary, $dryRun);
        }
    }

    private function deleteTownships(int $tenantId, array &$summary, bool $dryRun): void
    {
        $this->deleteByTenantId('townships', $tenantId, $summary, $dryRun);
    }

    private function deleteCities(int $tenantId, array &$summary, bool $dryRun): void
    {
        $this->deleteByTenantId('cities', $tenantId, $summary, $dryRun);
    }

    private function deleteParentTables(int $tenantId, array &$summary, bool $dryRun): void
    {
        foreach (['coupons', 'promotions', 'orders',
                  'products', 'categories'] as $table) {
            $this->deleteByTenantId($table, $tenantId, $summary, $dryRun);
        }
    }

    private function deleteUsers(int $tenantId, array &$summary, bool $dryRun): void
    {
        if (empty($this->userIds)) {
            return;
        }

        $this->countOwners($tenantId);
        $ownerLabel = $this->ownerCount > 0 ? " (incl. {$this->ownerCount} owner)" : '';

        $q = DB::table('users')->where('tenant_id', $tenantId);
        $this->doDelete('users' . $ownerLabel, $q, $summary, $dryRun);
    }

    private function deleteRoles(int $tenantId, array &$summary, bool $dryRun): void
    {
        if (empty($this->roleIds)) {
            return;
        }
        $q = DB::table('roles')->where('tenant_id', $tenantId);
        $this->doDelete('roles', $q, $summary, $dryRun);
    }

    private function deleteSubscriptions(int $tenantId, array &$summary, bool $dryRun): void
    {
        $this->deleteByTenantId('subscriptions', $tenantId, $summary, $dryRun);
    }

    private function deleteByTenantId(string $table, int $tenantId, array &$summary, bool $dryRun): void
    {
        $this->doDelete($table, DB::table($table)->where('tenant_id', $tenantId), $summary, $dryRun);
    }

    private function doDelete(string $label, $query, array &$summary, bool $dryRun): void
    {
        $count = $dryRun ? $query->count() : $query->delete();
        if ($count > 0) {
            $summary[$label] = $count;
        }
    }
}
