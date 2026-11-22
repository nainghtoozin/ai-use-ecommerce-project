<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class OrderCancellation extends Model
{
    use TenantAware;

    protected $table = 'order_cancellations';

    protected $fillable = [
        'tenant_id',
        'order_id',
        'cancellation_number',
        'requested_by_type',
        'requested_by_id',
        'requested_by_role',
        'reason_code',
        'reason_label',
        'customer_note',
        'status',
        'mode',
        'decision_by_type',
        'decision_by_id',
        'decision_note',
        'rejection_reason',
        'refund_required',
        'refund_id',
        'stock_restored_at',
        'requested_at',
        'decided_at',
        'completed_at',
        'policy_snapshot',
    ];

    protected $casts = [
        'refund_required' => 'boolean',
        'stock_restored_at' => 'datetime',
        'requested_at' => 'datetime',
        'decided_at' => 'datetime',
        'completed_at' => 'datetime',
        'policy_snapshot' => 'array',
    ];

    public const STATUS_REQUESTED = 'requested';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_COMPLETED = 'completed';

    public const ROLE_CUSTOMER = 'customer';
    public const ROLE_MERCHANT = 'merchant';
    public const ROLE_SYSTEM = 'system';

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(OrderRefund::class, 'refund_id');
    }

    public function requester(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo('requested_by', 'requested_by_type', 'requested_by_id');
    }

    public function histories(): MorphMany
    {
        return $this->morphMany(AfterSalesHistory::class, 'subject');
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_REQUESTED, self::STATUS_APPROVED], true);
    }

    public function isCustomerInitiated(): bool
    {
        return $this->requested_by_role === self::ROLE_CUSTOMER;
    }
}
