<?php

namespace App\Events\Webhooks;

use App\Data\Webhook\WebhookEvent;
use App\Models\PaymentIntent;
use Illuminate\Foundation\Events\Dispatchable;

class PaymentFailed
{
    use Dispatchable;

    public function __construct(
        public readonly PaymentIntent $intent,
        public readonly WebhookEvent $event,
    ) {}
}
