<?php

namespace App\Listeners;

use App\Events\Payments\PaymentIntentCompleted;
use App\Services\ReceiptService;

class CreateReceiptFromCompletedIntent
{
    public function __construct(private readonly ReceiptService $receipts) {}

    public function handle(PaymentIntentCompleted $event): void
    {
        $this->receipts->createFromCompletedIntent($event->intent);
    }
}
