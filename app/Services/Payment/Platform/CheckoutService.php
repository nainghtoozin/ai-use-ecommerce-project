<?php

namespace App\Services\Payment\Platform;

use App\Data\Currency;
use App\Enums\Payment\TransactionStatus;
use App\Models\PaymentIntent;
use App\Models\Plan;
use App\Models\Tenant;

class CheckoutService
{
    public function __construct(
        private readonly SubscriptionPaymentService $paymentService,
        private readonly PaymentAuditService $audit,
        private readonly PaymentIntentService $intents,
        private readonly PaymentIntentValidator $validator,
    ) {}

    public function initiateCheckout(
        Tenant $tenant,
        Plan $plan,
        string $billingCycle,
        float $amount,
        Currency $currency,
        string $gateway,
        array $metadata = [],
    ): PaymentIntent {
        $this->validator->validateGateway($gateway);
        $this->validator->validateAmount($amount);
        $this->validator->validateBillingCycle($billingCycle);

        $existing = $this->findReusableIntent($tenant, $plan, $billingCycle, $gateway);

        if ($existing) {
            return $existing;
        }

        $intent = $this->intents->create(
            tenant: $tenant,
            plan: $plan,
            billingCycle: $billingCycle,
            amount: $amount,
            currency: $currency,
            gateway: $gateway,
            metadata: $metadata,
        );

        $this->intents->markPending($intent);

        return $this->intents->markWaitingPayment($intent->fresh());
    }

    public function findReusableIntent(
        Tenant $tenant,
        Plan $plan,
        string $billingCycle,
        string $gateway,
    ): ?PaymentIntent {
        return PaymentIntent::forTenant($tenant->id)
            ->where('plan_id', $plan->id)
            ->where('billing_cycle', $billingCycle)
            ->where('gateway', $gateway)
            ->whereIn('status', [
                TransactionStatus::WAITING_PAYMENT->value,
                TransactionStatus::WAITING_REVIEW->value,
            ])
            ->orderBy('created_at', 'desc')
            ->first();
    }

    public function getCheckoutStatus(TransactionStatus $status): string
    {
        return match (true) {
            $status->isSuccess() => 'completed',
            $status->isPending() => 'in_progress',
            $status->isTerminal() => 'failed',
            default => 'unknown',
        };
    }
}
