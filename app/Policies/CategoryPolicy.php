<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\Category;
use App\Services\AuthorizationService;

class CategoryPolicy
{
    public function viewAny(Account $account): bool
    {
        return AuthorizationService::can('categories.view', $account);
    }

    public function view(Account $account, Category $category): bool
    {
        return AuthorizationService::can('categories.view', $account);
    }

    public function create(Account $account): bool
    {
        return AuthorizationService::can('categories.create', $account);
    }

    public function update(Account $account, Category $category): bool
    {
        return AuthorizationService::can('categories.update', $account);
    }

    public function delete(Account $account, Category $category): bool
    {
        return AuthorizationService::can('categories.delete', $account);
    }

    public function restore(Account $account, Category $category): bool
    {
        return AuthorizationService::can('categories.update', $account);
    }

    public function forceDelete(Account $account, Category $category): bool
    {
        return AuthorizationService::isSuperAdmin($account);
    }
}
