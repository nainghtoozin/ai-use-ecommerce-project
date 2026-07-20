<?php

namespace App\Listeners;

use App\Events\Payments\PaymentIntentCompleted;
use App\Models\Invoice;
use App\Services\InvoiceService;

class GenerateInvoiceFromCompletedIntent
{
    public function __construct(
        private readonly InvoiceService $invoiceService
    ) {}

    public function handle(PaymentIntentCompleted $event): void
    {
        $intent = $event->intent;

        $exists = Invoice::where('payment_intent_id', $intent->id)->exists();

        if ($exists) {
            return;
        }

        $this->invoiceService->generateFromPaymentIntent($intent);
    }
}
