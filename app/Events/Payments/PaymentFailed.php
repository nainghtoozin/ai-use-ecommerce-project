<?php

namespace App\Events\Payments;

use App\Models\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Subscription $subscription,
        public string $gateway,
        public ?string $transactionId,
        public ?float $amount,
        public ?string $currency,
        public string $reason,
        public array $metadata = [],
    ) {}
}
