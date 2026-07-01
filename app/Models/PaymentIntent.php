<?php

namespace App\Models;

use App\Enums\Payment\TransactionStatus;
use App\Models\Traits\TenantAware;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expires_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
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

    public static function findByReference(string $reference): ?self
    {
        return static::whereReference($reference)->first();
    }
}
