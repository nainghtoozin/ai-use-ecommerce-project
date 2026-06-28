<?php

namespace App\Services\Payment\Providers;

use App\Contracts\PaymentProvider;
use App\Services\Payment\DTOs\ChargeRequest;
use App\Services\Payment\DTOs\PaymentResult;
use App\Services\Payment\DTOs\RefundRequest;
use App\Services\Payment\DTOs\WebhookResult;

class PayPalProvider implements PaymentProvider
{
    public function getName(): string
    {
        return 'paypal';
    }

    public function getDisplayName(): string
    {
        return 'PayPal';
    }

    public function isAvailable(): bool
    {
        return $this->isConfigured();
    }

    public function isConfigured(): bool
    {
        return config('payments.gateways.paypal.keys.client_id') !== ''
            && config('payments.gateways.paypal.keys.secret') !== '';
    }

    public function supportedCurrencies(): array
    {
        return config('payments.gateways.paypal.supported_currencies', ['USD']);
    }

    public function charge(ChargeRequest $request): PaymentResult
    {
        return new PaymentResult(
            success: false,
            errorMessage: 'PayPal integration not yet implemented.',
        );
    }

    public function refund(RefundRequest $request): PaymentResult
    {
        return new PaymentResult(
            success: false,
            errorMessage: 'PayPal integration not yet implemented.',
        );
    }

    public function handleWebhook(array $payload, ?string $signature = null): WebhookResult
    {
        return new WebhookResult(
            handled: false,
            errorMessage: 'PayPal integration not yet implemented.',
        );
    }

    public function validateConfig(): array
    {
        $errors = [];

        if (config('payments.gateways.paypal.keys.client_id') === '') {
            $errors[] = 'PayPal client ID is not configured.';
        }

        if (config('payments.gateways.paypal.keys.secret') === '') {
            $errors[] = 'PayPal secret is not configured.';
        }

        return $errors;
    }
}
