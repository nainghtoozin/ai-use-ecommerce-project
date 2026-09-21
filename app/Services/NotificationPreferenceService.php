<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\User as Authenticatable;

class NotificationPreferenceService
{
    public function userWantsNotification(User|Account|Authenticatable $user, string $type): bool
    {
        return $user->wantsNotification($type);
    }

    public function tenantAllows(?int $tenantId, string ...$keys): bool
    {
        if (Setting::get('notifications_enabled', 'true', $tenantId) !== 'true') {
            return false;
        }

        foreach ($keys as $key) {
            if (Setting::get($key, 'true', $tenantId) !== 'true') {
                return false;
            }
        }

        return true;
    }

    public function filterUsersByPreference(Collection $users, string $type): Collection
    {
        return $users->filter(fn (User|Account $user) => $user->wantsNotification($type));
    }

    public function getEnabledTypes(User|Account|Authenticatable $user): array
    {
        $prefs = $user->notification_preferences;
        return array_keys(array_filter($prefs));
    }
}
