<?php

namespace App\Services\Payment\DTOs;

class RefundRequest
{
    public function __construct(
        public readonly string $transactionId,
        public readonly ?float $amount = null,
        public readonly string $reason = '',
    ) {}
}
