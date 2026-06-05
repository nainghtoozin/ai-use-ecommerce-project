<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;

class CustomerOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isCustomer();
    }

    public function view(User $user, Order $order): bool
    {
        $tenant = Tenant::getCurrent();

        if ($order->user_id !== $user->id) {
            return false;
        }

        if ($tenant && $order->tenant_id !== $tenant->id) {
            return false;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->isCustomer();
    }

    public function cancel(User $user, Order $order): bool
    {
        return $this->view($user, $order) && $order->canCancel();
    }

    public function uploadPayment(User $user, Order $order): bool
    {
        return $this->view($user, $order) && $order->payment_status === Order::PAYMENT_STATUS_PENDING;
    }

    public function confirmPayment(User $user, Order $order): bool
    {
        return $this->view($user, $order) && $order->payment_status === Order::PAYMENT_STATUS_PENDING;
    }
}
