<?php

namespace App\Services\Backup;

use RuntimeException;
use Throwable;

class BackupOperationException extends RuntimeException
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $message,
        public readonly string $reason,
        public readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
