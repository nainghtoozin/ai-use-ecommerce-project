<?php

namespace App\Services\Recovery;

use RuntimeException;
use Throwable;

final class MerchantRecoveryException extends RuntimeException
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
