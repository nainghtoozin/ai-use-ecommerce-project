<?php

namespace App\Contracts;

use App\Services\Payment\DTOs\PaymentResult;
use App\Services\Payment\DTOs\WebhookResult;

interface PaymentGatewayInterface
{
    public function getName(): string;

    public function getDisplayName(): string;

    public function isAvailable(): bool;

    public function isConfigured(): bool;

    public function supportedCurrencies(): array;

    public function createPayment(array $params): PaymentResult;

    public function verifyPayment(string $transactionId): PaymentResult;

    public function cancelPayment(string $transactionId): PaymentResult;

    public function refund(string $transactionId, ?float $amount = null, string $reason = ''): PaymentResult;

    public function handleWebhook(array $payload, ?string $signature = null): WebhookResult;

    public function validateConfig(): array;
}
