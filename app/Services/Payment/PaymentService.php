<?php

namespace App\Services\Payment;

use App\Services\Payment\DTOs\ChargeRequest;
use App\Services\Payment\DTOs\PaymentResult;
use App\Services\Payment\DTOs\RefundRequest;
use App\Services\Payment\DTOs\WebhookResult;

class PaymentService
{
    public function __construct(
        private PaymentGatewayResolver $resolver,
    ) {}

    public function charge(
        string $gateway,
        float $amount,
        string $currency,
        string $description,
        array $metadata = [],
        ?string $returnUrl = null,
        ?string $cancelUrl = null,
    ): PaymentResult {
        $provider = $this->resolver->resolve($gateway);

        $request = new ChargeRequest(
            amount: $amount,
            currency: $currency,
            description: $description,
            metadata: $metadata,
            returnUrl: $returnUrl,
            cancelUrl: $cancelUrl,
        );

        return $provider->charge($request);
    }

    public function refund(
        string $gateway,
        string $transactionId,
        ?float $amount = null,
        string $reason = '',
    ): PaymentResult {
        $provider = $this->resolver->resolve($gateway);

        $request = new RefundRequest(
            transactionId: $transactionId,
            amount: $amount,
            reason: $reason,
        );

        return $provider->refund($request);
    }

    public function handleWebhook(
        string $gateway,
        array $payload,
        ?string $signature = null,
    ): WebhookResult {
        $provider = $this->resolver->resolve($gateway);

        return $provider->handleWebhook($payload, $signature);
    }

    public function getAvailableGateways(): array
    {
        return $this->resolver->available();
    }

    public function getConfiguredGateways(): array
    {
        return $this->resolver->configured();
    }

    public function supports(string $gateway): bool
    {
        return $this->resolver->supports($gateway);
    }
}
