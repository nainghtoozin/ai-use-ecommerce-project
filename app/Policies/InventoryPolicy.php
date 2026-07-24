<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\Inventory;
use App\Services\AuthorizationService;
use App\Services\FeatureGate;

class InventoryPolicy
{
    public function before(Account $account, string $ability): ?bool
    {
        if (FeatureGate::isDevMode()) {
            return true;
        }

        if (!FeatureGate::enabled('inventory_management')) {
            return false;
        }

        return null;
    }

    public function viewAny(Account $account): bool
    {
        return AuthorizationService::can('inventory.view', $account);
    }

    public function view(Account $account, Inventory $inventory): bool
    {
        return AuthorizationService::can('inventory.view', $account);
    }

    public function create(Account $account): bool
    {
        return AuthorizationService::can('inventory.create', $account);
    }

    public function update(Account $account, Inventory $inventory): bool
    {
        return AuthorizationService::can('inventory.update', $account);
    }

    public function delete(Account $account, Inventory $inventory): bool
    {
        return AuthorizationService::can('inventory.delete', $account);
    }
}
