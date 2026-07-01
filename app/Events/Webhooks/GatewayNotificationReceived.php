<?php

namespace App\Events\Webhooks;

use App\Data\Webhook\WebhookEvent;
use Illuminate\Foundation\Events\Dispatchable;

class GatewayNotificationReceived
{
    use Dispatchable;

    public function __construct(
        public readonly string $gateway,
        public readonly WebhookEvent $event,
    ) {}
}
