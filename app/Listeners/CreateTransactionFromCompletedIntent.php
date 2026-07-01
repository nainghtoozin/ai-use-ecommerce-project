<?php

namespace App\Listeners;

use App\Events\Payments\PaymentIntentCompleted;
use App\Services\Payment\Platform\LedgerService;
use App\Services\Payment\Platform\PaymentTimelineService;
use App\Services\Payment\Platform\PaymentTransactionService;

class CreateTransactionFromCompletedIntent
{
    public function __construct(
        private readonly PaymentTransactionService $transactions,
        private readonly LedgerService $ledger,
        private readonly PaymentTimelineService $timeline,
    ) {}

    public function handle(PaymentIntentCompleted $event): void
    {
        $intent = $event->intent;

        if ($this->transactions->findByIntent($intent)) {
            return;
        }

        $transaction = $this->transactions->createFromCompletedIntent($intent);

        $this->ledger->record(
            type: 'payment_completed',
            amount: (float) $intent->amount,
            currency: $intent->currency,
            transaction: $transaction,
            intent: $intent,
            description: "Transaction {$transaction->transaction_number} completed",
        );

        $this->timeline->record(
            intent: $intent,
            type: 'completed',
            description: "Payment completed. Transaction: {$transaction->transaction_number}",
            metadata: ['transaction_number' => $transaction->transaction_number],
        );
    }
}
