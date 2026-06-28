<?php

namespace App\Services\Payment\DTOs;

class ChargeRequest
{
    public function __construct(
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $description,
        public readonly array $metadata = [],
        public readonly ?string $returnUrl = null,
        public readonly ?string $cancelUrl = null,
        public readonly ?string $customerEmail = null,
        public readonly ?string $customerName = null,
    ) {}
}
