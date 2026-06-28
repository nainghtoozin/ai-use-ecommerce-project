<?php

namespace App\Services\Payment\DTOs;

class PaymentResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $transactionId = null,
        public readonly ?string $status = null,
        public readonly ?string $redirectUrl = null,
        public readonly ?string $errorMessage = null,
        public readonly array $rawResponse = [],
    ) {}

    public function requiresRedirect(): bool
    {
        return $this->redirectUrl !== null;
    }
}
