<?php

namespace App\Listeners;

use App\Events\Payments\PaymentIntentCompleted;
use App\Services\SubscriptionLifecycleService;

class ActivateSubscriptionOnPaymentCompleted
{
    public function __construct(
        private readonly SubscriptionLifecycleService $lifecycle,
    ) {}

    public function handle(PaymentIntentCompleted $event): void
    {
        $this->lifecycle->handleCompletedPayment($event->intent);
    }
}
