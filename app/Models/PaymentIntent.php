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
}
