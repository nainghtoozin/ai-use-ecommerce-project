<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;

class SubscriptionCancellationRequest extends Model
{
    use TenantAware;

    // Self-service cancellation: scheduled immediately by the merchant, effective
    // at the end of the paid period. No SuperAdmin approval is required.
    public const STATUS_SCHEDULED = 'scheduled';

    // Legacy statuses retained for historical rows and audit.
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_WITHDRAWN = 'withdrawn';
    public const STATUS_SUPERSEDED = 'superseded';

    /**
     * Statuses that represent a cancellation currently in effect (or awaiting no
     * further action). Used for duplicate prevention and auto-resolution.
     */
    public const EFFECTIVE_STATUSES = [
        self::STATUS_SCHEDULED,
        self::STATUS_APPROVED,
        self::STATUS_PENDING,
    ];

    public const REASONS = [
        'too_expensive' => 'Too expensive',
        'business_closed' => 'Business temporarily closed',
        'no_longer_needed' => 'No longer need the service',
        'missing_features' => 'Missing features',
        'switching_platform' => 'Switching to another platform',
        'other' => 'Other',
    ];

    protected $fillable = [
        'tenant_id',
        'subscription_id',
        'reason',
        'comment',
        'status',
        'requested_by',
        'reviewed_by',
        'review_note',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? $this->reason;
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
