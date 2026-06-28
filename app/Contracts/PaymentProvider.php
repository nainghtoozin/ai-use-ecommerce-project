<?php

namespace App\Contracts;

use App\Services\Payment\DTOs\ChargeRequest;
use App\Services\Payment\DTOs\PaymentResult;
use App\Services\Payment\DTOs\RefundRequest;
use App\Services\Payment\DTOs\WebhookResult;

interface PaymentProvider
{
    public function getName(): string;

    public function getDisplayName(): string;

    public function isAvailable(): bool;

    public function isConfigured(): bool;

    public function supportedCurrencies(): array;

    public function charge(ChargeRequest $request): PaymentResult;

    public function refund(RefundRequest $request): PaymentResult;

    public function handleWebhook(array $payload, ?string $signature = null): WebhookResult;

    public function validateConfig(): array;
}
