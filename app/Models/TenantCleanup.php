<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantCleanup extends Model
{
    public const STATUS_GRACE = 'grace';
    public const STATUS_RESTRICTED = 'restricted';
    public const STATUS_FLAGGED = 'flagged';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_ARCHIVED = 'archived';

    public const TERMINAL_STATUSES = [self::STATUS_CANCELLED, self::STATUS_ARCHIVED];

    protected $fillable = [
        'tenant_id',
        'subscription_id',
        'status',
        'expires_at',
        'grace_ends_at',
        'restricted_at',
        'archived_at',
        'last_reminder_at',
        'reminders_sent',
        'attempts',
        'reason',
        'last_error',
        'metadata',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'grace_ends_at' => 'datetime',
        'restricted_at' => 'datetime',
        'archived_at' => 'datetime',
        'last_reminder_at' => 'datetime',
        'reminders_sent' => 'array',
        'metadata' => 'array',
        'attempts' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    public function scopeAwaitingCleanup($query)
    {
        return $query->whereIn('status', [
            self::STATUS_GRACE,
            self::STATUS_RESTRICTED,
            self::STATUS_FLAGGED,
        ]);
    }
}
