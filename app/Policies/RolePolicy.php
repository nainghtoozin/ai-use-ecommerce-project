<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\Role;
use App\Services\AuthorizationService;

class RolePolicy
{
    public function viewAny(Account $account): bool
    {
        return AuthorizationService::can('roles.view', $account);
    }

    public function view(Account $account, Role $role): bool
    {
        return AuthorizationService::can('roles.view', $account);
    }

    public function create(Account $account): bool
    {
        return AuthorizationService::can('roles.create', $account);
    }

    public function update(Account $account, Role $role): bool
    {
        // Cannot edit protected system roles
        if (in_array($role->name, ['superadmin', 'admin'])) {
            return false;
        }

        return AuthorizationService::can('roles.update', $account);
    }

    public function delete(Account $account, Role $role): bool
    {
        // Cannot delete protected system roles
        if (in_array($role->name, ['superadmin', 'admin'])) {
            return false;
        }

        return AuthorizationService::can('roles.delete', $account);
    }

    public function restore(Account $account, Role $role): bool
    {
        return AuthorizationService::can('roles.update', $account);
    }

    public function forceDelete(Account $account, Role $role): bool
    {
        return AuthorizationService::isSuperAdmin($account);
    }
}
