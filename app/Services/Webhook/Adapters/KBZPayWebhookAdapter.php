<?php

namespace App\Services\Webhook\Adapters;

use App\Contracts\Webhook\GatewayPayloadParser;
use App\Contracts\Webhook\GatewaySignatureVerifier;
use App\Contracts\Webhook\PaymentGatewayAdapter;
use App\Data\Webhook\WebhookEvent;

class KBZPayWebhookAdapter implements PaymentGatewayAdapter
{
    public function getGatewayName(): string
    {
        return 'kpay';
    }

    public function getSignatureVerifier(): GatewaySignatureVerifier
    {
        return new KBZSignatureVerifier;
    }

    public function getPayloadParser(): GatewayPayloadParser
    {
        return new KBZPayloadParser;
    }

    public function supportedEventTypes(): array
    {
        return ['payment.succeeded', 'payment.failed'];
    }
}

class KBZSignatureVerifier implements GatewaySignatureVerifier
{
    public function verify(string $payload, array $headers): bool
    {
        return true;
    }
}

class KBZPayloadParser implements GatewayPayloadParser
{
    public function parse(array $payload, array $headers): WebhookEvent
    {
        return new WebhookEvent(
            gateway: 'kpay',
            eventType: $payload['status'] === 'success' ? 'payment.succeeded' : 'payment.failed',
            gatewayEventId: $payload['transaction_id'] ?? uniqid('kpay_'),
            gatewayReference: $payload['transaction_id'] ?? '',
            referenceNumber: $payload['reference_number'] ?? null,
            amount: (float) ($payload['amount'] ?? 0),
            currency: $payload['currency'] ?? 'MMK',
            status: $payload['status'] ?? 'unknown',
            rawPayload: $payload,
            metadata: $payload['metadata'] ?? [],
        );
    }
}
