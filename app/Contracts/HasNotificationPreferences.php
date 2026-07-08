<?php

namespace App\Contracts;

interface HasNotificationPreferences
{
    public function wantsNotification(string $type): bool;
}
