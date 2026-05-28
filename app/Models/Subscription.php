<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [
        'tenant_id',
        'plan_id',
        'status',
        'starts_at',
        'expires_at',
        'trial_ends_at',
        'cancelled_at',
        'notes',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /* ── Relationships ── */

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    /* ── Status helpers ── */

    public function isTrialing(): bool
    {
        return $this->status === 'trialing';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPastDue(): bool
    {
        return $this->status === 'past_due';
    }

    public function isCanceled(): bool
    {
        return $this->status === 'canceled';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired';
    }

    public function isInGoodStanding(): bool
    {
        return in_array($this->status, ['trialing', 'active']);
    }

    /* ── Trial helpers ── */

    public function onTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function trialExpired(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isPast();
    }

    public function daysLeftInTrial(): int
    {
        if (!$this->trial_ends_at) {
            return 0;
        }
        return max(0, now()->diffInDays($this->trial_ends_at, false));
    }

    /* ── Expiry helpers ── */

    public function hasExpired(): bool
    {
        if ($this->status === 'expired') {
            return true;
        }

        return $this->expires_at && $this->expires_at->isPast()
            && !in_array($this->status, ['canceled']);
    }

    public function daysUntilExpiry(): int
    {
        if (!$this->expires_at) {
            return PHP_INT_MAX;
        }
        return max(0, now()->diffInDays($this->expires_at, false));
    }

    public function daysSinceExpiry(): int
    {
        if (!$this->expires_at) {
            return 0;
        }
        return max(0, $this->expires_at->diffInDays(now(), false));
    }

    /* ── Lifecycle transitions ── */

    public function markAsPastDue(): void
    {
        $this->update(['status' => 'past_due']);
    }

    public function markAsExpired(): void
    {
        $this->update(['status' => 'expired']);
        $this->tenant->update(['status' => 'suspended']);
    }

    public function markAsCanceled(?Carbon $at = null): void
    {
        $this->update([
            'status' => 'canceled',
            'cancelled_at' => $at ?? now(),
        ]);
    }

    public function renew(Carbon $expiresAt, ?string $notes = null): void
    {
        $this->update([
            'status' => 'active',
            'expires_at' => $expiresAt,
            'notes' => $notes ? ($this->notes ? $this->notes . "\n" . $notes : $notes) : $this->notes,
        ]);

        if ($this->tenant->status === 'suspended') {
            $this->tenant->update(['status' => 'active']);
        }
    }

    public function cancelImmediately(): void
    {
        $this->update([
            'status' => 'expired',
            'cancelled_at' => now(),
            'expires_at' => now(),
        ]);
    }

    /* ── Scope ── */

    public function scopeInGoodStanding($query)
    {
        return $query->whereIn('status', ['trialing', 'active']);
    }

    public function scopeExpiringSoon($query, int $days = 7)
    {
        return $query->whereIn('status', ['active', 'past_due'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays($days))
            ->where('expires_at', '>', now());
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'past_due')
            ->where('expires_at', '<', now());
    }

    public function scopeNeedsProcessing($query)
    {
        return $query->whereIn('status', ['active', 'past_due'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDay());
    }
}
