<?php

namespace App\Services\Payment\Providers;

use App\Contracts\PaymentProvider;
use App\Services\Payment\DTOs\ChargeRequest;
use App\Services\Payment\DTOs\PaymentResult;
use App\Services\Payment\DTOs\RefundRequest;
use App\Services\Payment\DTOs\WebhookResult;

class ManualTransferProvider implements PaymentProvider
{
    public function getName(): string
    {
        return 'manual_transfer';
    }

    public function getDisplayName(): string
    {
        return 'Manual Bank Transfer';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function supportedCurrencies(): array
    {
        return config('payments.gateways.manual_transfer.supported_currencies', ['MMK', 'USD', 'THB']);
    }

    public function charge(ChargeRequest $request): PaymentResult
    {
        return new PaymentResult(
            success: true,
            status: 'pending',
            transactionId: 'manual_' . uniqid(),
            rawResponse: ['note' => 'Manual transfer requires offline verification.'],
        );
    }

    public function refund(RefundRequest $request): PaymentResult
    {
        return new PaymentResult(
            success: true,
            transactionId: $request->transactionId,
            status: 'refunded',
            rawResponse: ['note' => 'Manual refund processed offline.'],
        );
    }

    public function handleWebhook(array $payload, ?string $signature = null): WebhookResult
    {
        return new WebhookResult(
            handled: false,
            errorMessage: 'Manual transfer does not support webhooks.',
        );
    }

    public function validateConfig(): array
    {
        return [];
    }
}
