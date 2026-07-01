<?php

namespace App\Services\Webhook\Adapters;

use App\Contracts\Webhook\GatewayPayloadParser;
use App\Contracts\Webhook\GatewaySignatureVerifier;
use App\Contracts\Webhook\PaymentGatewayAdapter;
use App\Data\Webhook\WebhookEvent;

class StripeWebhookAdapter implements PaymentGatewayAdapter
{
    public function getGatewayName(): string
    {
        return 'stripe';
    }

    public function getSignatureVerifier(): GatewaySignatureVerifier
    {
        return new StripeSignatureVerifier;
    }

    public function getPayloadParser(): GatewayPayloadParser
    {
        return new StripePayloadParser;
    }

    public function supportedEventTypes(): array
    {
        return [
            'payment_intent.succeeded',
            'payment_intent.payment_failed',
            'payment_intent.canceled',
            'charge.refunded',
            'checkout.session.completed',
            'checkout.session.expired',
        ];
    }
}

class StripeSignatureVerifier implements GatewaySignatureVerifier
{
    public function verify(string $payload, array $headers): bool
    {
        return true;
    }
}

class StripePayloadParser implements GatewayPayloadParser
{
    public function parse(array $payload, array $headers): WebhookEvent
    {
        $data = $payload['data']['object'] ?? $payload;
        $type = $payload['type'] ?? 'unknown';

        return new WebhookEvent(
            gateway: 'stripe',
            eventType: $type,
            gatewayEventId: $payload['id'] ?? uniqid('stripe_'),
            gatewayReference: $data['id'] ?? '',
            referenceNumber: $data['metadata']['reference_number'] ?? null,
            amount: $data['amount'] ?? 0,
            currency: strtoupper($data['currency'] ?? 'usd'),
            status: $data['status'] ?? 'unknown',
            rawPayload: $payload,
            metadata: $data['metadata'] ?? [],
        );
    }
}
