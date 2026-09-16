<?php

namespace App\Services\Payment\Platform;

use App\Models\PaymentIntent;
use Illuminate\Support\Facades\DB;
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
        return DB::transaction(function () use ($intent, $action, $callback, $onDuplicate) {
            $locked = PaymentIntent::query()->whereKey($intent->id)->lockForUpdate()->firstOrFail();

            $metadata = $locked->metadata ?? [];

            if ($this->idempotency->hasActionExecuted($metadata, $action)) {
                if ($onDuplicate !== null) {
                    return $onDuplicate($locked, $action);
                }

                throw new InvalidArgumentException(sprintf(
                    'Action "%s" has already been executed for PaymentIntent #%d (%s).',
                    $action,
                    $locked->id,
                    $locked->reference_number ?? $locked->idempotency_key,
                ));
            }

            if (empty($locked->idempotency_key)) {
                throw new InvalidArgumentException(sprintf(
                    'PaymentIntent #%d has no idempotency key.',
                    $locked->id,
                ));
            }

            $result = $callback($locked);

            $locked->refresh();

            $metadata = $this->idempotency->markActionExecuted(
                $locked->metadata ?? [],
                $action,
                is_string($result) ? $result : null,
            );

            $locked->update(['metadata' => $metadata]);

            return $result;
        });
    }

    public function hasActionBeenExecuted(PaymentIntent $intent, string $action): bool
    {
        return $this->idempotency->hasActionExecuted(
            $intent->metadata ?? [],
            $action,
        );
    }
}
