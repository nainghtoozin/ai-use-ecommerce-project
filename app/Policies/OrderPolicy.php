<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\Order;
use App\Services\AuthorizationService;

class OrderPolicy
{
    public function viewAny(Account $account): bool
    {
        return AuthorizationService::can('orders.view', $account);
    }

    public function view(Account $account, Order $order): bool
    {
        // Admin can view all orders
        if (AuthorizationService::can('orders.view', $account)) {
            return true;
        }

        // Customer can view own orders
        if (AuthorizationService::can('orders.view-own', $account)) {
            return $order->user_id === $account->id && $order->user_type === Account::class;
        }

        return false;
    }

    public function create(Account $account): bool
    {
        return AuthorizationService::can('orders.create', $account);
    }

    public function update(Account $account, Order $order): bool
    {
        return AuthorizationService::can('orders.update-status', $account);
    }

    public function delete(Account $account, Order $order): bool
    {
        return AuthorizationService::can('orders.cancel-any', $account);
    }

    public function restore(Account $account, Order $order): bool
    {
        return AuthorizationService::can('orders.update-status', $account);
    }

    public function forceDelete(Account $account, Order $order): bool
    {
        return AuthorizationService::isSuperAdmin($account);
    }
}
