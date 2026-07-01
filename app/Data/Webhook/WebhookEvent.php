<?php

namespace App\Data\Webhook;

class WebhookEvent
{
    public function __construct(
        public readonly string $gateway,
        public readonly string $eventType,
        public readonly string $gatewayEventId,
        public readonly string $gatewayReference,
        public readonly ?string $referenceNumber,
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $status,
        public readonly array $rawPayload = [],
        public readonly array $metadata = [],
    ) {}
}
