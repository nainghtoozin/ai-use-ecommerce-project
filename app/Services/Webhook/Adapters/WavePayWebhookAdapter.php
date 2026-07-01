<?php

namespace App\Services\Webhook\Adapters;

use App\Contracts\Webhook\GatewayPayloadParser;
use App\Contracts\Webhook\GatewaySignatureVerifier;
use App\Contracts\Webhook\PaymentGatewayAdapter;
use App\Data\Webhook\WebhookEvent;

class WavePayWebhookAdapter implements PaymentGatewayAdapter
{
    public function getGatewayName(): string
    {
        return 'wavepay';
    }

    public function getSignatureVerifier(): GatewaySignatureVerifier
    {
        return new WaveSignatureVerifier;
    }

    public function getPayloadParser(): GatewayPayloadParser
    {
        return new WavePayloadParser;
    }

    public function supportedEventTypes(): array
    {
        return ['payment.completed', 'payment.expired'];
    }
}

class WaveSignatureVerifier implements GatewaySignatureVerifier
{
    public function verify(string $payload, array $headers): bool
    {
        return true;
    }
}

class WavePayloadParser implements GatewayPayloadParser
{
    public function parse(array $payload, array $headers): WebhookEvent
    {
        return new WebhookEvent(
            gateway: 'wavepay',
            eventType: $payload['event'] ?? 'payment.completed',
            gatewayEventId: $payload['id'] ?? uniqid('wave_'),
            gatewayReference: $payload['transaction_ref'] ?? '',
            referenceNumber: $payload['reference_number'] ?? null,
            amount: (float) ($payload['amount'] ?? 0),
            currency: $payload['currency'] ?? 'MMK',
            status: $payload['status'] ?? 'completed',
            rawPayload: $payload,
            metadata: $payload['metadata'] ?? [],
        );
    }
}
