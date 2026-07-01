<?php

namespace App\Services\Payment\Platform;

use App\Contracts\PaymentGatewayInterface;
use App\Data\Currency;
use App\Enums\Payment\TransactionStatus;
use App\Models\Subscription;
use App\Services\Payment\DTOs\PaymentResult;

class SubscriptionPaymentService
{
    public function __construct(
        private readonly GatewayResolver $resolver,
        private readonly PaymentAuditService $audit,
    ) {}

    public function createPayment(
        Subscription $subscription,
        string $gateway,
        float $amount,
        Currency $currency,
        array $metadata = [],
    ): PaymentResult {
        $provider = $this->resolver->resolve($gateway);

        $result = $provider->createPayment([
            'amount' => $amount,
            'currency' => $currency->code(),
            'description' => 'Subscription #' . $subscription->id . ' — ' . ($subscription->plan?->name ?? 'Unknown Plan'),
            'metadata' => array_merge($metadata, [
                'subscription_id' => $subscription->id,
                'tenant_id' => $subscription->tenant_id,
                'billing_interval' => $subscription->billing_interval,
            ]),
        ]);

        if ($result->success) {
            $this->audit->log(
                subscription: $subscription,
                event: 'payment_created',
                gateway: $gateway,
                transactionId: $result->transactionId,
                amount: $amount,
                currency: $currency,
                status: TransactionStatus::WAITING_PAYMENT,
                metadata: $metadata,
            );
        }

        return $result;
    }

    public function verifyPayment(
        Subscription $subscription,
        string $gateway,
        string $transactionId,
    ): PaymentResult {
        $provider = $this->resolver->resolve($gateway);

        $result = $provider->verifyPayment($transactionId);

        if ($result->success) {
            $status = TransactionStatus::tryFrom($result->status ?? '') ?? TransactionStatus::APPROVED;

            $this->audit->log(
                subscription: $subscription,
                event: 'payment_verified',
                gateway: $gateway,
                transactionId: $transactionId,
                amount: null,
                currency: null,
                status: $status,
            );
        }

        return $result;
    }

    public function processSuccessfulPayment(
        Subscription $subscription,
        string $gateway,
        string $transactionId,
        float $amount,
        Currency $currency,
    ): void {
        $subscription->renewFromInterval('Auto-renewed via ' . $gateway . ' (' . $transactionId . ')');

        $this->audit->log(
            subscription: $subscription,
            event: 'payment_completed',
            gateway: $gateway,
            transactionId: $transactionId,
            amount: $amount,
            currency: $currency,
            status: TransactionStatus::COMPLETED,
        );
    }

    public function cancelPayment(
        Subscription $subscription,
        string $gateway,
        string $transactionId,
    ): PaymentResult {
        $provider = $this->resolver->resolve($gateway);

        $result = $provider->cancelPayment($transactionId);

        if ($result->success) {
            $this->audit->log(
                subscription: $subscription,
                event: 'payment_cancelled',
                gateway: $gateway,
                transactionId: $transactionId,
                amount: null,
                currency: null,
                status: TransactionStatus::CANCELLED,
            );
        }

        return $result;
    }

    public function refundPayment(
        Subscription $subscription,
        string $gateway,
        string $transactionId,
        ?float $amount = null,
        string $reason = '',
    ): PaymentResult {
        $provider = $this->resolver->resolve($gateway);

        $result = $provider->refund($transactionId, $amount, $reason);

        if ($result->success) {
            $status = ($amount !== null && $amount > 0)
                ? TransactionStatus::PARTIALLY_REFUNDED
                : TransactionStatus::REFUNDED;

            $this->audit->log(
                subscription: $subscription,
                event: 'payment_refunded',
                gateway: $gateway,
                transactionId: $transactionId,
                amount: $amount,
                currency: null,
                status: $status,
                metadata: ['reason' => $reason],
            );
        }

        return $result;
    }

    public function handleWebhook(
        string $gateway,
        array $payload,
        ?string $signature = null,
    ): mixed {
        $provider = $this->resolver->resolve($gateway);

        return $provider->handleWebhook($payload, $signature);
    }
}
