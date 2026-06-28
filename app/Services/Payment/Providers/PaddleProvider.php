<?php

namespace App\Services\Payment\Providers;

use App\Contracts\PaymentProvider;
use App\Services\Payment\DTOs\ChargeRequest;
use App\Services\Payment\DTOs\PaymentResult;
use App\Services\Payment\DTOs\RefundRequest;
use App\Services\Payment\DTOs\WebhookResult;

class PaddleProvider implements PaymentProvider
{
    public function getName(): string
    {
        return 'paddle';
    }

    public function getDisplayName(): string
    {
        return 'Paddle';
    }

    public function isAvailable(): bool
    {
        return $this->isConfigured();
    }

    public function isConfigured(): bool
    {
        return config('payments.gateways.paddle.keys.vendor_auth_code') !== '';
    }

    public function supportedCurrencies(): array
    {
        return config('payments.gateways.paddle.supported_currencies', ['USD', 'EUR', 'GBP']);
    }

    public function charge(ChargeRequest $request): PaymentResult
    {
        return new PaymentResult(
            success: false,
            errorMessage: 'Paddle integration not yet implemented.',
        );
    }

    public function refund(RefundRequest $request): PaymentResult
    {
        return new PaymentResult(
            success: false,
            errorMessage: 'Paddle integration not yet implemented.',
        );
    }

    public function handleWebhook(array $payload, ?string $signature = null): WebhookResult
    {
        return new WebhookResult(
            handled: false,
            errorMessage: 'Paddle integration not yet implemented.',
        );
    }

    public function validateConfig(): array
    {
        $errors = [];

        if (config('payments.gateways.paddle.keys.vendor_id') === '') {
            $errors[] = 'Paddle vendor ID is not configured.';
        }

        if (config('payments.gateways.paddle.keys.vendor_auth_code') === '') {
            $errors[] = 'Paddle vendor auth code is not configured.';
        }

        return $errors;
    }
}
