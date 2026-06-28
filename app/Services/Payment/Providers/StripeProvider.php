<?php

namespace App\Services\Payment\Providers;

use App\Contracts\PaymentProvider;
use App\Services\Payment\DTOs\ChargeRequest;
use App\Services\Payment\DTOs\PaymentResult;
use App\Services\Payment\DTOs\RefundRequest;
use App\Services\Payment\DTOs\WebhookResult;

class StripeProvider implements PaymentProvider
{
    public function getName(): string
    {
        return 'stripe';
    }

    public function getDisplayName(): string
    {
        return 'Stripe';
    }

    public function isAvailable(): bool
    {
        return $this->isConfigured();
    }

    public function isConfigured(): bool
    {
        return config('payments.gateways.stripe.keys.secret') !== '';
    }

    public function supportedCurrencies(): array
    {
        return config('payments.gateways.stripe.supported_currencies', ['USD']);
    }

    public function charge(ChargeRequest $request): PaymentResult
    {
        return new PaymentResult(
            success: false,
            errorMessage: 'Stripe integration not yet implemented.',
        );
    }

    public function refund(RefundRequest $request): PaymentResult
    {
        return new PaymentResult(
            success: false,
            errorMessage: 'Stripe integration not yet implemented.',
        );
    }

    public function handleWebhook(array $payload, ?string $signature = null): WebhookResult
    {
        return new WebhookResult(
            handled: false,
            errorMessage: 'Stripe integration not yet implemented.',
        );
    }

    public function validateConfig(): array
    {
        $errors = [];

        if (config('payments.gateways.stripe.keys.public') === '') {
            $errors[] = 'Stripe public key is not configured.';
        }

        if (config('payments.gateways.stripe.keys.secret') === '') {
            $errors[] = 'Stripe secret key is not configured.';
        }

        return $errors;
    }
}
