<?php

namespace App\Services\Payment\Platform\Gateways;

use App\Contracts\PaymentGatewayInterface;
use App\Services\Payment\DTOs\PaymentResult;
use App\Services\Payment\DTOs\WebhookResult;

class ManualPaymentGateway implements PaymentGatewayInterface
{
    public function getName(): string
    {
        return 'manual';
    }

    public function getDisplayName(): string
    {
        return 'Manual Transfer';
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

    public function createPayment(array $params): PaymentResult
    {
        return new PaymentResult(
            success: true,
            status: 'waiting_payment',
            transactionId: 'manual_' . uniqid(),
            rawResponse: ['note' => 'Manual payment requires offline verification.'],
        );
    }

    public function verifyPayment(string $transactionId): PaymentResult
    {
        return new PaymentResult(
            success: true,
            transactionId: $transactionId,
            status: 'approved',
        );
    }

    public function cancelPayment(string $transactionId): PaymentResult
    {
        return new PaymentResult(
            success: true,
            transactionId: $transactionId,
            status: 'cancelled',
        );
    }

    public function refund(string $transactionId, ?float $amount = null, string $reason = ''): PaymentResult
    {
        return new PaymentResult(
            success: true,
            transactionId: $transactionId,
            status: 'refunded',
        );
    }

    public function handleWebhook(array $payload, ?string $signature = null): WebhookResult
    {
        return new WebhookResult(
            handled: false,
            errorMessage: 'Manual payment does not support webhooks.',
        );
    }

    public function validateConfig(): array
    {
        return [];
    }
}
