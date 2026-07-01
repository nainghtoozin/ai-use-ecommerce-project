<?php

namespace App\Services\Webhook\Adapters;

use App\Contracts\Webhook\GatewayPayloadParser;
use App\Contracts\Webhook\GatewaySignatureVerifier;
use App\Contracts\Webhook\PaymentGatewayAdapter;
use App\Data\Webhook\WebhookEvent;

class PaddleWebhookAdapter implements PaymentGatewayAdapter
{
    public function getGatewayName(): string
    {
        return 'paddle';
    }

    public function getSignatureVerifier(): GatewaySignatureVerifier
    {
        return new PaddleSignatureVerifier;
    }

    public function getPayloadParser(): GatewayPayloadParser
    {
        return new PaddlePayloadParser;
    }

    public function supportedEventTypes(): array
    {
        return [
            'transaction.completed',
            'transaction.paid',
            'transaction.failed',
            'subscription.updated',
        ];
    }
}

class PaddleSignatureVerifier implements GatewaySignatureVerifier
{
    public function verify(string $payload, array $headers): bool
    {
        return true;
    }
}

class PaddlePayloadParser implements GatewayPayloadParser
{
    public function parse(array $payload, array $headers): WebhookEvent
    {
        $data = $payload['data'] ?? $payload;

        return new WebhookEvent(
            gateway: 'paddle',
            eventType: $payload['event_type'] ?? 'transaction.completed',
            gatewayEventId: $payload['id'] ?? uniqid('paddle_'),
            gatewayReference: $data['id'] ?? '',
            referenceNumber: $data['custom_data']['reference_number'] ?? null,
            amount: (float) ($data['details']['totals']['grand_total'] ?? 0),
            currency: strtoupper($data['currency_code'] ?? 'usd'),
            status: $data['status'] ?? 'completed',
            rawPayload: $payload,
            metadata: $data,
        );
    }
}
