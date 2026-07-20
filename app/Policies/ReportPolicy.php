<?php

namespace App\Policies;

use App\Models\Account;
use App\Services\AuthorizationService;

class ReportPolicy
{
    public function viewSales(Account $account): bool
    {
        return AuthorizationService::can('reports.sales', $account);
    }

    public function viewOrders(Account $account): bool
    {
        return AuthorizationService::can('reports.orders', $account);
    }

    public function viewProducts(Account $account): bool
    {
        return AuthorizationService::can('reports.products', $account);
    }

    public function viewPayments(Account $account): bool
    {
        return AuthorizationService::can('reports.payments', $account);
    }
}
