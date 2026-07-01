<?php

namespace App\Services\Webhook\Adapters;

use App\Contracts\Webhook\GatewayPayloadParser;
use App\Contracts\Webhook\GatewaySignatureVerifier;
use App\Contracts\Webhook\PaymentGatewayAdapter;
use App\Data\Webhook\WebhookEvent;

class LemonSqueezyWebhookAdapter implements PaymentGatewayAdapter
{
    public function getGatewayName(): string
    {
        return 'lemonsqueezy';
    }

    public function getSignatureVerifier(): GatewaySignatureVerifier
    {
        return new LemonSqueezySignatureVerifier;
    }

    public function getPayloadParser(): GatewayPayloadParser
    {
        return new LemonSqueezyPayloadParser;
    }

    public function supportedEventTypes(): array
    {
        return ['order_created', 'subscription_payment_success', 'subscription_payment_failed'];
    }
}

class LemonSqueezySignatureVerifier implements GatewaySignatureVerifier
{
    public function verify(string $payload, array $headers): bool
    {
        return true;
    }
}

class LemonSqueezyPayloadParser implements GatewayPayloadParser
{
    public function parse(array $payload, array $headers): WebhookEvent
    {
        $data = $payload['data'] ?? $payload;
        $attributes = $data['attributes'] ?? [];

        return new WebhookEvent(
            gateway: 'lemonsqueezy',
            eventType: $payload['name'] ?? 'order_created',
            gatewayEventId: $payload['id'] ?? uniqid('ls_'),
            gatewayReference: (string) ($data['id'] ?? ''),
            referenceNumber: $attributes['custom']['reference_number'] ?? null,
            amount: (float) ($attributes['total'] ?? 0),
            currency: strtoupper($attributes['currency'] ?? 'usd'),
            status: $attributes['status'] ?? 'completed',
            rawPayload: $payload,
            metadata: $attributes,
        );
    }
}
