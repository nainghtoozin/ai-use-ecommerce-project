<?php

namespace App\Listeners;

use App\Events\Payments\PaymentIntentCompleted;
use App\Services\BillingEmailService;
use App\Services\ReceiptService;

class CreateReceiptFromCompletedIntent
{
    public function __construct(
        private readonly ReceiptService $receipts,
        private readonly BillingEmailService $billingEmails,
    ) {}

    public function handle(PaymentIntentCompleted $event): void
    {
        $receipt = $this->receipts->createFromCompletedIntent($event->intent);

        $this->billingEmails->sendApprovedEmail($event->intent);
        $this->billingEmails->sendReceiptEmail($event->intent, $receipt);
    }
}
