<?php

namespace App\Services\Payment\Platform;

use App\Models\PaymentIntent;
use InvalidArgumentException;

class PaymentExecutionGuard
{
    public function __construct(
        private readonly IdempotencyService $idempotency,
    ) {}

    public function executeOnce(
        PaymentIntent $intent,
        string $action,
        callable $callback,
        ?callable $onDuplicate = null,
    ): mixed {
        $metadata = $intent->metadata ?? [];

        if ($this->idempotency->hasActionExecuted($metadata, $action)) {
            if ($onDuplicate !== null) {
                return $onDuplicate($intent, $action);
            }

            throw new InvalidArgumentException(sprintf(
                'Action "%s" has already been executed for PaymentIntent #%d (%s).',
                $action,
                $intent->id,
                $intent->reference_number ?? $intent->idempotency_key,
            ));
        }

        if (empty($intent->idempotency_key)) {
            throw new InvalidArgumentException(sprintf(
                'PaymentIntent #%d has no idempotency key.',
                $intent->id,
            ));
        }

        $result = $callback($intent);

        $metadata = $this->idempotency->markActionExecuted(
            $metadata,
            $action,
            is_string($result) ? $result : null,
        );

        $intent->update(['metadata' => $metadata]);

        return $result;
    }

    public function hasActionBeenExecuted(PaymentIntent $intent, string $action): bool
    {
        return $this->idempotency->hasActionExecuted(
            $intent->metadata ?? [],
            $action,
        );
    }
}
