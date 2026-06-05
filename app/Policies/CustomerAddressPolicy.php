<?php

namespace App\Policies;

use App\Models\CustomerAddress;
use App\Models\Tenant;
use App\Models\User;

class CustomerAddressPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isCustomer();
    }

    public function view(User $user, CustomerAddress $address): bool
    {
        $tenant = Tenant::getCurrent();

        if ($address->user_id !== $user->id) {
            return false;
        }

        if ($tenant && $address->tenant_id !== $tenant->id) {
            return false;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->isCustomer();
    }

    public function update(User $user, CustomerAddress $address): bool
    {
        return $this->view($user, $address);
    }

    public function delete(User $user, CustomerAddress $address): bool
    {
        return $this->view($user, $address);
    }
}
