<?php

namespace App\Services\Payment\Platform;

use App\Enums\Payment\GatewayType;
use App\Enums\Payment\TransactionStatus;
use App\Models\PaymentIntent;
use InvalidArgumentException;

class PaymentIntentValidator
{
    public function validateTransition(PaymentIntent $intent, TransactionStatus $target): void
    {
        if (!$intent->canTransitionTo($target)) {
            throw new InvalidArgumentException(sprintf(
                'Cannot transition PaymentIntent #%d from %s to %s.',
                $intent->id,
                $intent->status,
                $target->value,
            ));
        }
    }

    public function validateNotExpired(PaymentIntent $intent): void
    {
        if ($intent->hasExpired()) {
            throw new InvalidArgumentException(sprintf(
                'PaymentIntent #%d has expired at %s.',
                $intent->id,
                $intent->expires_at?->toDateTimeString() ?? 'unknown',
            ));
        }
    }

    public function validateNotTerminal(PaymentIntent $intent): void
    {
        if ($intent->isTerminal()) {
            throw new InvalidArgumentException(sprintf(
                'PaymentIntent #%d is already in terminal state %s.',
                $intent->id,
                $intent->status,
            ));
        }
    }

    public function validateGateway(string $gateway): void
    {
        if (!GatewayType::tryFrom($gateway)) {
            throw new InvalidArgumentException("Unsupported gateway: {$gateway}");
        }
    }

    public function validateAmount(float $amount): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Amount must be greater than zero.');
        }
    }

    public function validateBillingCycle(string $cycle): void
    {
        if (!in_array($cycle, ['monthly', 'yearly'], true)) {
            throw new InvalidArgumentException("Unsupported billing cycle: {$cycle}");
        }
    }
}
