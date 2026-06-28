<?php

namespace App\Services\Payment\DTOs;

class WebhookResult
{
    public function __construct(
        public readonly bool $handled,
        public readonly ?string $eventType = null,
        public readonly ?string $transactionId = null,
        public readonly ?string $status = null,
        public readonly array $metadata = [],
        public readonly ?string $errorMessage = null,
    ) {}
}
