<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\Product;
use App\Services\AuthorizationService;

class ProductPolicy
{
    public function viewAny(Account $account): bool
    {
        return AuthorizationService::can('products.view', $account);
    }

    public function view(Account $account, Product $product): bool
    {
        return AuthorizationService::can('products.view', $account);
    }

    public function create(Account $account): bool
    {
        return AuthorizationService::can('products.create', $account);
    }

    public function update(Account $account, Product $product): bool
    {
        return AuthorizationService::can('products.edit', $account);
    }

    public function delete(Account $account, Product $product): bool
    {
        return AuthorizationService::can('products.delete', $account);
    }

    public function restore(Account $account, Product $product): bool
    {
        return AuthorizationService::can('products.edit', $account);
    }

    public function forceDelete(Account $account, Product $product): bool
    {
        return AuthorizationService::isSuperAdmin($account);
    }
}
