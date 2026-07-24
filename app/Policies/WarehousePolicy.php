<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\Warehouse;
use App\Services\AuthorizationService;
use App\Services\FeatureGate;

class WarehousePolicy
{
    public function before(Account $account, string $ability): ?bool
    {
        if (FeatureGate::isDevMode()) {
            return true;
        }

        if (!FeatureGate::enabled('warehouse_management')) {
            return false;
        }

        return null;
    }

    public function viewAny(Account $account): bool
    {
        return AuthorizationService::can('warehouses.view', $account);
    }

    public function view(Account $account, Warehouse $warehouse): bool
    {
        return AuthorizationService::can('warehouses.view', $account);
    }

    public function create(Account $account): bool
    {
        return AuthorizationService::can('warehouses.create', $account);
    }

    public function update(Account $account, Warehouse $warehouse): bool
    {
        return AuthorizationService::can('warehouses.update', $account);
    }

    public function delete(Account $account, Warehouse $warehouse): bool
    {
        return AuthorizationService::can('warehouses.delete', $account);
    }
}
