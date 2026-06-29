<?php

namespace App\Data;

class TelegramPayload
{
    public function __construct(
        public string $message,
        public string $parseMode = 'HTML',
        public string $notificationType = '',
        public ?string $destination = null,
        public array $context = [],
        public array $futureActions = [],
    ) {}

    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'parse_mode' => $this->parseMode,
            'notification_type' => $this->notificationType,
            'destination' => $this->destination,
            'context' => $this->context,
            'future_actions' => $this->futureActions,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            message: $data['message'] ?? '',
            parseMode: $data['parse_mode'] ?? 'HTML',
            notificationType: $data['notification_type'] ?? '',
            destination: $data['destination'] ?? null,
            context: $data['context'] ?? [],
            futureActions: $data['future_actions'] ?? [],
        );
    }
}
