<?php

namespace App\Services\Payment\Providers;

use App\Contracts\PaymentProvider;
use App\Services\Payment\DTOs\ChargeRequest;
use App\Services\Payment\DTOs\PaymentResult;
use App\Services\Payment\DTOs\RefundRequest;
use App\Services\Payment\DTOs\WebhookResult;

class WavePayProvider implements PaymentProvider
{
    public function getName(): string
    {
        return 'wavepay';
    }

    public function getDisplayName(): string
    {
        return 'WavePay';
    }

    public function isAvailable(): bool
    {
        return $this->isConfigured();
    }

    public function isConfigured(): bool
    {
        return config('payments.gateways.wavepay.keys.merchant_code') !== ''
            && config('payments.gateways.wavepay.keys.api_key') !== '';
    }

    public function supportedCurrencies(): array
    {
        return config('payments.gateways.wavepay.supported_currencies', ['MMK']);
    }

    public function charge(ChargeRequest $request): PaymentResult
    {
        return new PaymentResult(
            success: false,
            errorMessage: 'WavePay integration not yet implemented.',
        );
    }

    public function refund(RefundRequest $request): PaymentResult
    {
        return new PaymentResult(
            success: false,
            errorMessage: 'WavePay integration not yet implemented.',
        );
    }

    public function handleWebhook(array $payload, ?string $signature = null): WebhookResult
    {
        return new WebhookResult(
            handled: false,
            errorMessage: 'WavePay integration not yet implemented.',
        );
    }

    public function validateConfig(): array
    {
        $errors = [];

        if (config('payments.gateways.wavepay.keys.merchant_code') === '') {
            $errors[] = 'WavePay merchant code is not configured.';
        }

        if (config('payments.gateways.wavepay.keys.api_key') === '') {
            $errors[] = 'WavePay API key is not configured.';
        }

        return $errors;
    }
}
