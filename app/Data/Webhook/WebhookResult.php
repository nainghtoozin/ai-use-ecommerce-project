<?php

namespace App\Data\Webhook;

use App\Models\PaymentIntent;
use App\Models\PaymentTransaction;

class WebhookResult
{
    public const STATUS_RECEIVED = 'received';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_DUPLICATE = 'duplicate';
    public const STATUS_FAILED = 'failed';
    public const STATUS_UNHANDLED = 'unhandled';

    public function __construct(
        public readonly string $status,
        public readonly string $message = '',
        public readonly ?PaymentIntent $intent = null,
        public readonly ?PaymentTransaction $transaction = null,
    ) {}

    public static function processed(string $message = '', ?PaymentIntent $intent = null, ?PaymentTransaction $transaction = null): self
    {
        return new self(self::STATUS_PROCESSED, $message, $intent, $transaction);
    }

    public static function duplicate(string $message = 'Duplicate webhook notification'): self
    {
        return new self(self::STATUS_DUPLICATE, $message);
    }

    public static function failed(string $message): self
    {
        return new self(self::STATUS_FAILED, $message);
    }

    public static function unhandled(string $message): self
    {
        return new self(self::STATUS_UNHANDLED, $message);
    }

    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_PROCESSED;
    }

    public function httpStatus(): int
    {
        return match ($this->status) {
            self::STATUS_PROCESSED => 200,
            self::STATUS_DUPLICATE => 200,
            self::STATUS_UNHANDLED => 202,
            self::STATUS_FAILED => 400,
            default => 500,
        };
    }
}
