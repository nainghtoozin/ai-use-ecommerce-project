<?php

namespace App\Services;

use RuntimeException;
use Throwable;

final class MerchantStoreResetException extends RuntimeException
{
    public function __construct(
        private readonly string $reason,
        string $message,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function reason(): string
    {
        return $this->reason;
    }
}