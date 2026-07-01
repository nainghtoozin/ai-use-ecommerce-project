<?php

namespace App\Events\Payments;

use App\Models\PaymentIntent;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentIntentCancelled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public PaymentIntent $intent,
    ) {}
}
