<?php

namespace App\Listeners;

use App\Contracts\HasDatabaseTenantId;
use Illuminate\Notifications\Events\NotificationSent;

class StampDatabaseNotificationTenant
{
    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'database') {
            return;
        }

        if (!$event->notification instanceof HasDatabaseTenantId) {
            return;
        }

        $response = $event->response;

        if (is_object($response) && method_exists($response, 'forceFill')) {
            $response->forceFill(['tenant_id' => $event->notification->databaseTenantId()])->save();
        }
    }
}
