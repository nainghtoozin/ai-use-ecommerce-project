<?php

namespace App\Events\Subscriptions;

use App\Models\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionActivated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Subscription $subscription,
        public ?string $gateway = null,
        public ?string $transactionId = null,
    ) {}
}
