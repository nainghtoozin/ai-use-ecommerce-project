<?php

namespace App\Services\Payment\Platform;

use App\Data\Currency;
use App\Enums\Payment\TransactionStatus;
use App\Models\PaymentIntent;
use App\Models\Plan;
use App\Models\Tenant;

class PaymentIntentFactory
{
    public function __construct(
        private readonly ReferenceNumberService $referenceNumber,
        private readonly IdempotencyService $idempotency,
    ) {}

    public function create(
        Tenant $tenant,
        Plan $plan,
        string $billingCycle,
        float $amount,
        Currency $currency,
        string $gateway,
        ?int $subscriptionId = null,
        array $metadata = [],
        ?int $expiresInMinutes = null,
    ): PaymentIntent {
        $expiresInMinutes ??= config('payments.intent_expiry_minutes', 1440);

        return PaymentIntent::create([
            'reference_number' => $this->referenceNumber->generatePaymentIntentRef(),
            'idempotency_key' => $this->idempotency->generate(),
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'subscription_id' => $subscriptionId,
            'billing_cycle' => $billingCycle,
            'amount' => $amount,
            'currency' => $currency->code(),
            'gateway' => $gateway,
            'status' => TransactionStatus::DRAFT->value,
            'expires_at' => now()->addMinutes($expiresInMinutes),
            'metadata' => $metadata,
        ]);
    }

    public function createForSubscription(
        Tenant $tenant,
        Plan $plan,
        string $billingCycle,
        float $amount,
        Currency $currency,
        string $gateway,
        int $subscriptionId,
        array $metadata = [],
    ): PaymentIntent {
        return $this->create(
            tenant: $tenant,
            plan: $plan,
            billingCycle: $billingCycle,
            amount: $amount,
            currency: $currency,
            gateway: $gateway,
            subscriptionId: $subscriptionId,
            metadata: $metadata,
        );
    }
}
