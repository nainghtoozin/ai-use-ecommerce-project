<?php

namespace App\Services\Payment\Platform;

use App\Models\LedgerEntry;
use App\Models\PaymentIntent;
use App\Models\PaymentTransaction;
use Illuminate\Support\Collection;

class LedgerService
{
    public function record(
        string $type,
        float $amount,
        string $currency,
        ?PaymentTransaction $transaction = null,
        ?PaymentIntent $intent = null,
        ?string $description = null,
        array $metadata = [],
    ): LedgerEntry {
        return LedgerEntry::create([
            'transaction_id' => $transaction?->id,
            'payment_intent_id' => $intent?->id,
            'type' => $type,
            'amount' => $amount,
            'currency' => $currency,
            'description' => $description,
            'metadata' => $metadata,
            'recorded_at' => now(),
        ]);
    }

    public function getForTransaction(PaymentTransaction $transaction): Collection
    {
        return LedgerEntry::where('transaction_id', $transaction->id)
            ->orderBy('recorded_at', 'asc')
            ->get();
    }

    public function getForIntent(PaymentIntent $intent): Collection
    {
        return LedgerEntry::where('payment_intent_id', $intent->id)
            ->orderBy('recorded_at', 'asc')
            ->get();
    }

    public function getByType(string $type): Collection
    {
        return LedgerEntry::where('type', $type)
            ->orderBy('recorded_at', 'desc')
            ->get();
    }
}
