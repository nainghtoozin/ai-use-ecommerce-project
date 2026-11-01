<?php

namespace App\Services\Export;

use RuntimeException;

final class MerchantBackupVerificationException extends RuntimeException
{
    public function __construct(
        private readonly string $reason,
        string $message
    ) {
        parent::__construct($message);
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
