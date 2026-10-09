<?php

namespace App\Exceptions;

use RuntimeException;

class SubscriptionLimitException extends RuntimeException
{
    public function __construct(
        public readonly string $limitKey,
        public readonly string $label,
        public readonly int $current,
        public readonly int $limit,
        public readonly int $required = 1,
        ?string $message = null,
    ) {
        parent::__construct($message ?? self::buildMessage($label, $current, $limit));
    }

    public static function buildMessage(string $label, int $current, int $limit): string
    {
        return "{$label} limit reached. You have {$current} of {$limit}. Please upgrade your plan to continue.";
    }

    public function toArray(): array
    {
        return [
            'type' => 'subscription_limit',
            'limit_key' => $this->limitKey,
            'label' => $this->label,
            'current' => $this->current,
            'limit' => $this->limit,
            'required' => $this->required,
            'message' => $this->getMessage(),
            'upgrade_url' => '/admin/billing',
        ];
    }
}
