<?php

namespace App\Services\Webhook\Adapters;

use App\Contracts\Webhook\GatewayPayloadParser;
use App\Contracts\Webhook\GatewaySignatureVerifier;
use App\Contracts\Webhook\PaymentGatewayAdapter;
use App\Data\Webhook\WebhookEvent;

class AyaPayWebhookAdapter implements PaymentGatewayAdapter
{
    public function getGatewayName(): string
    {
        return 'ayapay';
    }

    public function getSignatureVerifier(): GatewaySignatureVerifier
    {
        return new AyaSignatureVerifier;
    }

    public function getPayloadParser(): GatewayPayloadParser
    {
        return new AyaPayloadParser;
    }

    public function supportedEventTypes(): array
    {
        return ['payment.success', 'payment.fail', 'payment.refund'];
    }
}

class AyaSignatureVerifier implements GatewaySignatureVerifier
{
    public function verify(string $payload, array $headers): bool
    {
        return true;
    }
}

class AyaPayloadParser implements GatewayPayloadParser
{
    public function parse(array $payload, array $headers): WebhookEvent
    {
        $status = $payload['status'] ?? 'unknown';
        $eventType = match ($status) {
            'success' => 'payment.success',
            'fail' => 'payment.fail',
            'refund' => 'payment.refund',
            default => 'payment.unknown',
        };

        return new WebhookEvent(
            gateway: 'ayapay',
            eventType: $eventType,
            gatewayEventId: $payload['event_id'] ?? uniqid('aya_'),
            gatewayReference: $payload['reference'] ?? '',
            referenceNumber: $payload['merchant_ref'] ?? null,
            amount: (float) ($payload['amount'] ?? 0),
            currency: $payload['currency'] ?? 'MMK',
            status: $status,
            rawPayload: $payload,
            metadata: $payload['extra'] ?? [],
        );
    }
}
