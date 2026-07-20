<?php

namespace App\Policies;

use App\Models\Account;
use App\Services\AuthorizationService;

class UserPolicy
{
    public function viewAny(Account $account): bool
    {
        return AuthorizationService::can('users.view', $account);
    }

    public function view(Account $account, Account $target): bool
    {
        return AuthorizationService::can('users.view', $account);
    }

    public function create(Account $account): bool
    {
        return AuthorizationService::can('users.create', $account);
    }

    public function update(Account $account, Account $target): bool
    {
        return AuthorizationService::can('users.update', $account);
    }

    public function delete(Account $account, Account $target): bool
    {
        // Cannot delete owner
        if ($target->isOwner()) {
            return false;
        }

        return AuthorizationService::can('users.delete', $account);
    }

    public function restore(Account $account, Account $target): bool
    {
        return AuthorizationService::can('users.update', $account);
    }

    public function forceDelete(Account $account, Account $target): bool
    {
        return AuthorizationService::isSuperAdmin($account);
    }

    public function suspend(Account $account, Account $target): bool
    {
        // Cannot suspend owner
        if ($target->isOwner()) {
            return false;
        }

        return AuthorizationService::can('users.suspend', $account);
    }

    public function ban(Account $account, Account $target): bool
    {
        // Cannot ban owner
        if ($target->isOwner()) {
            return false;
        }

        return AuthorizationService::can('users.ban', $account);
    }
}
