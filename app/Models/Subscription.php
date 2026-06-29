<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use App\Notifications\SubscriptionRenewed;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use TenantAware;

    protected $fillable = [
        'plan_id',
        'billing_interval',
        'status',
        'starts_at',
        'expires_at',
        'trial_ends_at',
        'trial_renewals_count',
        'cancelled_at',
        'suspended_at',
        'notes',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'trial_renewals_count' => 'integer',
        'cancelled_at' => 'datetime',
        'suspended_at' => 'datetime',
    ];

    /* ── Billing helpers ── */

    public function billedPrice(): ?float
    {
        return $this->plan?->getPriceForInterval($this->billing_interval ?? 'monthly');
    }

    /* ── Relationships ── */

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(\App\Models\SubscriptionAuditLog::class)->latest();
    }

    /* ── Status helpers ── */

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

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

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function isInGoodStanding(): bool
    {
        return in_array($this->status, ['trialing', 'active', 'pending']);
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

        if (in_array($this->status, ['suspended', 'pending'])) {
            return false;
        }

        if ($this->status === 'trialing') {
            return $this->trial_ends_at && $this->trial_ends_at->isPast();
        }

        return $this->expires_at && $this->expires_at->isPast()
            && $this->status !== 'canceled';
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
    }

    public function suspend(): void
    {
        $this->update([
            'status' => 'suspended',
            'suspended_at' => now(),
        ]);
    }

    public function activate(): void
    {
        if (!$this->suspended_at) {
            $this->update(['status' => 'active']);
            $this->tenant->unlock();
            return;
        }

        if (!$this->expires_at) {
            $this->update([
                'status' => 'active',
                'suspended_at' => null,
            ]);
            $this->tenant->unlock();
            return;
        }

        $remainingSeconds = max(0, abs($this->expires_at->diffInSeconds($this->suspended_at)));

        $newExpiry = now()->addSeconds($remainingSeconds);

        $this->update([
            'status' => 'active',
            'expires_at' => $newExpiry,
            'suspended_at' => null,
        ]);

        $this->tenant->unlock();
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
            'expires_at' => $expiresAt->endOfDay(),
            'notes' => $notes ? ($this->notes ? $this->notes . "\n" . $notes : $notes) : $notes,
        ]);

        if ($this->tenant->status === 'suspended') {
            $this->tenant->update(['status' => 'active']);
        }

        $this->tenant->unlock();
        $this->tenant->notifyAdmins(new SubscriptionRenewed($this));
    }

    public function cancelImmediately(): void
    {
        $this->update([
            'status' => 'expired',
            'cancelled_at' => now(),
            'expires_at' => now(),
        ]);
    }

    /* ── Renewal via billing interval ── */

    /**
     * Renew the subscription by adding one billing interval.
     *
     * Active  → extends current expires_at by one billing cycle.
     * Expired → starts fresh from today plus one billing cycle.
     * Free plans → no-op (no expiry).
     * Suspended tenant → reactivated.
     */
    public function renewFromInterval(?string $notes = null): void
    {
        if ($this->plan?->isFree()) {
            return;
        }

        $interval = $this->billing_interval ?? 'monthly';

        $baseDate = $this->expires_at?->isFuture()
            ? $this->expires_at
            : now();

        $newExpiry = $this->plan?->calculateExpiryDate($baseDate, $interval);

        if (!$newExpiry) {
            return;
        }

        $note = sprintf(
            "[%s] Renewed (%s) — %s → %s",
            now()->toDateTimeString(),
            $interval,
            $baseDate->format('Y-m-d'),
            $newExpiry->format('Y-m-d')
        );

        if ($notes) {
            $note .= " — {$notes}";
        }

        $this->update([
            'status' => 'active',
            'expires_at' => $newExpiry,
            'notes' => $this->notes ? $this->notes . "\n" . $note : $note,
        ]);

        if ($this->tenant->status === 'suspended') {
            $this->tenant->update(['status' => 'active']);
        }

        $this->tenant->unlock();
        $this->tenant->notifyAdmins(new SubscriptionRenewed($this));
    }

    /**
     * Grace period used by markAsExpired. Number of days after expires_at
     * before the tenant is automatically suspended.
     */
    public const GRACE_DAYS = 7;

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
