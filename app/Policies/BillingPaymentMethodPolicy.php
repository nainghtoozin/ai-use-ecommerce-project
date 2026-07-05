<?php

namespace App\Policies;

use App\Models\User;

class BillingPaymentMethodPolicy
{
    public function view(User $user): bool
    {
        return $user->can('billing-payment-method.view');
    }

    public function create(User $user): bool
    {
        return $user->can('billing-payment-method.create');
    }

    public function update(User $user): bool
    {
        return $user->can('billing-payment-method.update');
    }

    public function delete(User $user): bool
    {
        return $user->can('billing-payment-method.delete');
    }

    public function toggle(User $user): bool
    {
        return $user->can('billing-payment-method.update');
    }

    public function reorder(User $user): bool
    {
        return $user->can('billing-payment-method.update');
    }

    public function restore(User $user): bool
    {
        return $user->can('billing-payment-method.update');
    }
}
