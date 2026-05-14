<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class NotificationPreferenceService
{
    public function userWantsNotification(User $user, string $type): bool
    {
        return $user->wantsNotification($type);
    }

    public function filterUsersByPreference(Collection $users, string $type): Collection
    {
        return $users->filter(fn (User $user) => $user->wantsNotification($type));
    }

    public function getEnabledTypes(User $user): array
    {
        $prefs = $user->notification_preferences;
        return array_keys(array_filter($prefs));
    }
}
