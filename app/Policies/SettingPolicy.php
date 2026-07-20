<?php

namespace App\Policies;

use App\Models\Account;
use App\Services\AuthorizationService;

class SettingPolicy
{
    public function view(Account $account): bool
    {
        return AuthorizationService::can('settings.view', $account);
    }

    public function update(Account $account): bool
    {
        return AuthorizationService::can('settings.edit', $account);
    }

    public function updateWebsite(Account $account): bool
    {
        return AuthorizationService::can('settings.website', $account);
    }

    public function updateNotifications(Account $account): bool
    {
        return AuthorizationService::can('settings.notifications', $account);
    }

    public function updateTelegram(Account $account): bool
    {
        return AuthorizationService::can('settings.telegram', $account);
    }
}
