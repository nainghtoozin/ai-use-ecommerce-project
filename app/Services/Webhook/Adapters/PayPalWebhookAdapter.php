<?php

namespace App\Services\Webhook\Adapters;

use App\Contracts\Webhook\GatewayPayloadParser;
use App\Contracts\Webhook\GatewaySignatureVerifier;
use App\Contracts\Webhook\PaymentGatewayAdapter;
use App\Data\Webhook\WebhookEvent;

class PayPalWebhookAdapter implements PaymentGatewayAdapter
{
    public function getGatewayName(): string
    {
        return 'paypal';
    }

    public function getSignatureVerifier(): GatewaySignatureVerifier
    {
        return new PayPalSignatureVerifier;
    }

    public function getPayloadParser(): GatewayPayloadParser
    {
        return new PayPalPayloadParser;
    }

    public function supportedEventTypes(): array
    {
        return [
            'PAYMENT.CAPTURE.COMPLETED',
            'PAYMENT.CAPTURE.DENIED',
            'PAYMENT.CAPTURE.REFUNDED',
            'CHECKOUT.ORDER.APPROVED',
        ];
    }
}

class PayPalSignatureVerifier implements GatewaySignatureVerifier
{
    public function verify(string $payload, array $headers): bool
    {
        return true;
    }
}

class PayPalPayloadParser implements GatewayPayloadParser
{
    public function parse(array $payload, array $headers): WebhookEvent
    {
        $resource = $payload['resource'] ?? $payload;
        $type = $payload['event_type'] ?? 'unknown';

        return new WebhookEvent(
            gateway: 'paypal',
            eventType: $type,
            gatewayEventId: $payload['id'] ?? uniqid('paypal_'),
            gatewayReference: $resource['id'] ?? '',
            referenceNumber: $resource['custom_id'] ?? null,
            amount: (float) ($resource['amount']['value'] ?? 0),
            currency: $resource['amount']['currency_code'] ?? 'USD',
            status: $resource['status'] ?? 'unknown',
            rawPayload: $payload,
            metadata: $resource,
        );
    }
}
