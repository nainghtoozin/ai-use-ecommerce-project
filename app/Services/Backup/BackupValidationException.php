<?php

namespace App\Services\Backup;

use RuntimeException;

class BackupValidationException extends RuntimeException
{
    public function __construct(string $message, public readonly string $reason)
    {
        parent::__construct($message);
    }
}
