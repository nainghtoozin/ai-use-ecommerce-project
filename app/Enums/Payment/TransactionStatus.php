<?php

namespace App\Enums\Payment;

enum TransactionStatus: string
{
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case WAITING_PAYMENT = 'waiting_payment';
    case WAITING_REVIEW = 'waiting_review';
    case APPROVED = 'approved';
    case PAID = 'paid';
    case COMPLETED = 'completed';
    case REJECTED = 'rejected';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';
    case REFUNDED = 'refunded';
    case PARTIALLY_REFUNDED = 'partially_refunded';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PENDING => 'Pending',
            self::WAITING_PAYMENT => 'Waiting Payment',
            self::WAITING_REVIEW => 'Waiting Review',
            self::APPROVED => 'Approved',
            self::PAID => 'Paid',
            self::COMPLETED => 'Completed',
            self::REJECTED => 'Rejected',
            self::FAILED => 'Failed',
            self::CANCELLED => 'Cancelled',
            self::EXPIRED => 'Expired',
            self::REFUNDED => 'Refunded',
            self::PARTIALLY_REFUNDED => 'Partially Refunded',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::COMPLETED,
            self::FAILED,
            self::CANCELLED,
            self::EXPIRED,
            self::REFUNDED,
            self::PARTIALLY_REFUNDED,
        ]);
    }

    public function isSuccess(): bool
    {
        return in_array($this, [self::APPROVED, self::PAID, self::COMPLETED]);
    }

    public function isPending(): bool
    {
        return in_array($this, [self::DRAFT, self::PENDING, self::WAITING_PAYMENT, self::WAITING_REVIEW]);
    }

    public function isRejected(): bool
    {
        return $this === self::REJECTED;
    }

    public function canTransitionTo(self $target): bool
    {
        if ($this === $target) {
            return false;
        }

        if ($this->isTerminal()) {
            return false;
        }

        if ($target->isTerminal() && $target !== self::CANCELLED) {
            return true;
        }

        if ($target === self::CANCELLED) {
            return $this->isPending() || $this === self::REJECTED;
        }

        $flow = [
            self::DRAFT->value => [self::PENDING->value],
            self::PENDING->value => [self::WAITING_PAYMENT->value, self::FAILED->value, self::CANCELLED->value],
            self::WAITING_PAYMENT->value => [self::WAITING_REVIEW->value, self::FAILED->value, self::EXPIRED->value],
            self::WAITING_REVIEW->value => [self::APPROVED->value, self::REJECTED->value, self::FAILED->value, self::EXPIRED->value],
            self::REJECTED->value => [self::WAITING_PAYMENT->value, self::CANCELLED->value],
            self::APPROVED->value => [self::PAID->value, self::FAILED->value],
            self::PAID->value => [self::COMPLETED->value, self::REFUNDED->value, self::PARTIALLY_REFUNDED->value],
        ];

        return in_array($target->value, $flow[$this->value] ?? []);
    }

    public static function initial(): self
    {
        return self::DRAFT;
    }
}
