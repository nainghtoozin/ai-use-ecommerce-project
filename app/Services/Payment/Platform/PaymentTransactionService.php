<?php

namespace App\Services\Payment\Platform;

use App\Models\PaymentIntent;
use App\Models\PaymentTransaction;
use App\Models\Tenant;
use Illuminate\Support\Collection;

class PaymentTransactionService
{
    public function __construct(
        private readonly ReferenceNumberService $referenceNumbers,
    ) {}

    public function createFromCompletedIntent(PaymentIntent $intent): PaymentTransaction
    {
        return PaymentTransaction::create([
            'payment_intent_id' => $intent->id,
            'transaction_number' => $this->referenceNumbers->generateTransactionRef(),
            'tenant_id' => $intent->tenant_id,
            'plan_id' => $intent->plan_id,
            'subscription_id' => $intent->subscription_id,
            'amount' => $intent->amount,
            'currency' => $intent->currency,
            'gateway' => $intent->gateway,
            'status' => 'completed',
            'metadata' => $intent->metadata ?? [],
        ]);
    }

    public function findByTransactionNumber(string $number): ?PaymentTransaction
    {
        return PaymentTransaction::where('transaction_number', $number)->first();
    }

    public function findByIntent(PaymentIntent $intent): ?PaymentTransaction
    {
        return PaymentTransaction::where('payment_intent_id', $intent->id)->first();
    }

    public function getForTenant(Tenant $tenant): Collection
    {
        return PaymentTransaction::where('tenant_id', $tenant->id)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getForIntent(PaymentIntent $intent): Collection
    {
        return PaymentTransaction::where('payment_intent_id', $intent->id)->get();
    }

    public function search(?string $referenceNumber = null, ?string $gateway = null, ?string $status = null): Collection
    {
        $query = PaymentTransaction::query();

        if ($referenceNumber) {
            $query->where('transaction_number', 'like', "%{$referenceNumber}%");
        }

        if ($gateway) {
            $query->where('gateway', $gateway);
        }

        if ($status) {
            $query->where('status', $status);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }
}
