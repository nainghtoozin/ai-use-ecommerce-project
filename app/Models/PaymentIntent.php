<?php

namespace App\Models;

use App\Enums\Payment\TransactionStatus;
use App\Models\Traits\TenantAware;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentIntent extends Model
{
    use TenantAware;

    public const TYPE_NEW_SUBSCRIPTION = 'new_subscription';
    public const TYPE_RENEWAL = 'renewal';
    public const TYPE_UPGRADE = 'upgrade';
    public const TYPE_PLAN_CHANGE = 'plan_change';
    public const TYPE_OTHER = 'other';

    public const PAYMENT_TYPE_LABELS = [
        self::TYPE_NEW_SUBSCRIPTION => 'New Subscription',
        self::TYPE_RENEWAL => 'Renewal',
        self::TYPE_UPGRADE => 'Upgrade',
        self::TYPE_PLAN_CHANGE => 'Plan Change',
        self::TYPE_OTHER => 'Other',
    ];

    /**
     * Merchant-declarable payment reasons mapped to the canonical payment type.
     */
    public const PAYMENT_REASONS = [
        'subscription_renewal' => ['label' => 'Subscription renewal payment', 'type' => self::TYPE_RENEWAL],
        'new_subscription' => ['label' => 'New subscription payment', 'type' => self::TYPE_NEW_SUBSCRIPTION],
        'plan_upgrade' => ['label' => 'Plan upgrade payment', 'type' => self::TYPE_UPGRADE],
        'plan_change' => ['label' => 'Plan change payment', 'type' => self::TYPE_PLAN_CHANGE],
        'other' => ['label' => 'Other', 'type' => self::TYPE_OTHER],
    ];

    protected $fillable = [
        'reference_number',
        'idempotency_key',
        'tenant_id',
        'plan_id',
        'subscription_id',
        'billing_cycle',
        'amount',
        'currency',
        'gateway',
        'status',
        'expires_at',
        'metadata',
        'completed_at',
        'cancelled_at',
        'rejected_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expires_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'rejected_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(PaymentEvidence::class);
    }

    public function receipt(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Receipt::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(PaymentReview::class)->latest();
    }

    public function latestReview(): HasMany
    {
        return $this->hasMany(PaymentReview::class)->latest()->limit(1);
    }

    public function transaction(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function timelineEvents(): HasMany
    {
        return $this->hasMany(PaymentTimelineEvent::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PaymentComment::class);
    }

    public function status(): TransactionStatus
    {
        return TransactionStatus::from($this->status);
    }

    public function isPending(): bool
    {
        return $this->status()->isPending();
    }

    public function isTerminal(): bool
    {
        return $this->status()->isTerminal();
    }

    public function isRejected(): bool
    {
        return $this->status === TransactionStatus::REJECTED->value;
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function canTransitionTo(TransactionStatus $target): bool
    {
        return $this->status()->canTransitionTo($target);
    }

    public function hasExpired(): bool
    {
        return $this->status === TransactionStatus::EXPIRED->value
            || ($this->expires_at && $this->expires_at->isPast() && $this->isPending());
    }

    public function markAsPaid(?Carbon $at = null): void
    {
        $this->update([
            'status' => TransactionStatus::PAID->value,
        ]);
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => TransactionStatus::COMPLETED->value,
            'completed_at' => now(),
        ]);
    }

    public function markAsCancelled(?Carbon $at = null): void
    {
        $this->update([
            'status' => TransactionStatus::CANCELLED->value,
            'cancelled_at' => $at ?? now(),
        ]);
    }

    public function markAsRejected(?Carbon $at = null): void
    {
        $this->update([
            'status' => TransactionStatus::REJECTED->value,
            'rejected_at' => $at ?? now(),
        ]);
    }

    public function markAsExpired(): void
    {
        $this->update([
            'status' => TransactionStatus::EXPIRED->value,
        ]);
    }

    public function scopeWhereReference(Builder $query, string $reference): Builder
    {
        return $query->where('reference_number', $reference);
    }

    public function scopeWhereRejected(Builder $query): Builder
    {
        return $query->where('status', TransactionStatus::REJECTED->value);
    }

    public function scopeWherePendingReview(Builder $query): Builder
    {
        return $query->where('status', TransactionStatus::WAITING_REVIEW->value);
    }

    public static function findByReference(string $reference): ?self
    {
        return static::whereReference($reference)->first();
    }

    /* ── Payment purpose classification (structured, never from free text) ── */

    /**
     * Canonical payment type derived from structured intent metadata.
     * Merchant-declared reasons act as a fallback only when no structured
     * source/type is present. Never derives the type from remarks.
     */
    public static function classifyPaymentType(array $metadata): string
    {
        $source = $metadata['source'] ?? null;
        $type = $metadata['type'] ?? null;

        if ($type === 'plan_upgrade' || $source === 'merchant_upgrade') {
            return self::TYPE_UPGRADE;
        }

        if ($source === 'merchant_renewal') {
            return self::TYPE_RENEWAL;
        }

        if (!empty($metadata['trial_conversion']) || $source === 'merchant_trial_conversion') {
            return self::TYPE_NEW_SUBSCRIPTION;
        }

        $reason = $metadata['payment_reason'] ?? null;
        if ($reason && isset(self::PAYMENT_REASONS[$reason])) {
            return self::PAYMENT_REASONS[$reason]['type'];
        }

        // Fall back to a previously stored canonical type (records without source/reason).
        $stored = $metadata['payment_type'] ?? null;
        if ($stored && isset(self::PAYMENT_TYPE_LABELS[$stored])) {
            return $stored;
        }

        return self::TYPE_OTHER;
    }

    public function paymentType(): string
    {
        return self::classifyPaymentType($this->metadata ?? []);
    }

    public function paymentTypeLabel(): string
    {
        return self::PAYMENT_TYPE_LABELS[$this->paymentType()] ?? self::PAYMENT_TYPE_LABELS[self::TYPE_OTHER];
    }

    public function paymentReason(): ?string
    {
        return $this->metadata['payment_reason'] ?? null;
    }

    public function paymentReasonLabel(): ?string
    {
        $reason = $this->paymentReason();

        if ($reason === 'other') {
            $custom = trim((string) ($this->metadata['payment_reason_text'] ?? ''));

            return $custom !== '' ? $custom : (self::PAYMENT_REASONS['other']['label'] ?? 'Other');
        }

        return $reason && isset(self::PAYMENT_REASONS[$reason])
            ? self::PAYMENT_REASONS[$reason]['label']
            : null;
    }
}
