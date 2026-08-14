<?php

namespace App\Listeners;

use App\Events\Payments\PaymentIntentRejected;
use App\Models\Invoice;

class MarkInvoiceRejected
{
    public function handle(PaymentIntentRejected $event): void
    {
        Invoice::where('payment_intent_id', $event->intent->id)->update([
            'status' => Invoice::STATUS_REJECTED,
            'notes' => $event->intent->metadata['rejection_reason'] ?? null,
        ]);
    }
}
