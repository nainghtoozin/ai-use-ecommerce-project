<?php

namespace App\Services\Payment\Platform;

use App\Data\Currency;
use App\Enums\Payment\TransactionStatus;
use App\Events\Payments\PaymentIntentCancelled;
use App\Events\Payments\PaymentIntentCompleted;
use App\Events\Payments\PaymentIntentCreated;
use App\Events\Payments\PaymentIntentExpired;
use App\Models\PaymentIntent;
use App\Models\Plan;
use App\Models\Tenant;

class PaymentIntentService
{
    public function __construct(
        private readonly PaymentIntentFactory $factory,
        private readonly PaymentIntentValidator $validator,
        private readonly PaymentAuditService $audit,
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
    ): PaymentIntent {
        $this->validator->validateGateway($gateway);
        $this->validator->validateAmount($amount);
        $this->validator->validateBillingCycle($billingCycle);

        $intent = $this->factory->create(
            tenant: $tenant,
            plan: $plan,
            billingCycle: $billingCycle,
            amount: $amount,
            currency: $currency,
            gateway: $gateway,
            subscriptionId: $subscriptionId,
            metadata: $metadata,
        );

        PaymentIntentCreated::dispatch($intent);

        return $intent;
    }

    public function markPending(PaymentIntent $intent): PaymentIntent
    {
        $this->validator->validateNotExpired($intent);
        $this->validator->validateTransition($intent, TransactionStatus::PENDING);

        $intent->update(['status' => TransactionStatus::PENDING->value]);

        return $intent->fresh();
    }

    public function markWaitingPayment(PaymentIntent $intent): PaymentIntent
    {
        $this->validator->validateNotExpired($intent);
        $this->validator->validateTransition($intent, TransactionStatus::WAITING_PAYMENT);

        $intent->update(['status' => TransactionStatus::WAITING_PAYMENT->value]);

        return $intent->fresh();
    }

    public function markWaitingReview(PaymentIntent $intent): PaymentIntent
    {
        $this->validator->validateNotExpired($intent);
        $this->validator->validateTransition($intent, TransactionStatus::WAITING_REVIEW);

        $intent->update(['status' => TransactionStatus::WAITING_REVIEW->value]);

        return $intent->fresh();
    }

    public function approve(PaymentIntent $intent): PaymentIntent
    {
        $this->validator->validateNotExpired($intent);
        $this->validator->validateTransition($intent, TransactionStatus::APPROVED);

        $intent->update(['status' => TransactionStatus::APPROVED->value]);

        return $intent->fresh();
    }

    public function complete(PaymentIntent $intent): PaymentIntent
    {
        $this->validator->validateNotTerminal($intent);
        $this->validator->validateTransition($intent, TransactionStatus::COMPLETED);

        $intent->markAsCompleted();

        PaymentIntentCompleted::dispatch($intent);

        return $intent->fresh();
    }

    public function cancel(PaymentIntent $intent): PaymentIntent
    {
        $this->validator->validateNotTerminal($intent);

        $intent->markAsCancelled();

        PaymentIntentCancelled::dispatch($intent);

        return $intent->fresh();
    }

    public function markExpired(PaymentIntent $intent): PaymentIntent
    {
        if ($intent->isTerminal()) {
            return $intent;
        }

        $intent->markAsExpired();

        PaymentIntentExpired::dispatch($intent);

        return $intent->fresh();
    }

    public function expireOverdue(): int
    {
        return PaymentIntent::whereIn('status', [
                TransactionStatus::DRAFT->value,
                TransactionStatus::PENDING->value,
                TransactionStatus::WAITING_PAYMENT->value,
                TransactionStatus::WAITING_REVIEW->value,
            ])
            ->where('expires_at', '<', now())
            ->update(['status' => TransactionStatus::EXPIRED->value]);
    }

    public function getIntent(int $id): ?PaymentIntent
    {
        return PaymentIntent::find($id);
    }

    public function getIntentForTenant(Tenant $tenant, int $id): ?PaymentIntent
    {
        return PaymentIntent::forTenant($tenant->id)->find($id);
    }

    public function findByReference(string $reference): ?PaymentIntent
    {
        return PaymentIntent::findByReference($reference);
    }

    public function findByReferenceForTenant(Tenant $tenant, string $reference): ?PaymentIntent
    {
        return PaymentIntent::forTenant($tenant->id)
            ->whereReference($reference)
            ->first();
    }

    public function getPendingIntents(Tenant $tenant): iterable
    {
        return PaymentIntent::forTenant($tenant->id)
            ->whereIn('status', [
                TransactionStatus::DRAFT->value,
                TransactionStatus::PENDING->value,
                TransactionStatus::WAITING_PAYMENT->value,
                TransactionStatus::WAITING_REVIEW->value,
            ])
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
