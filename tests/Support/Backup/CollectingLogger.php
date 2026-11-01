<?php

namespace Tests\Support\Backup;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Stringable;

class CollectingLogger implements LoggerInterface
{
    use LoggerTrait;

    /** @var array<int, array{level: string, message: string, context: array<string, mixed>}> */
    public array $records = [];

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lastContext(): ?array
    {
        $record = end($this->records);

        return $record === false ? null : $record['context'];
    }

    public function lastMessage(): ?string
    {
        $record = end($this->records);

        return $record === false ? null : $record['message'];
    }
}
