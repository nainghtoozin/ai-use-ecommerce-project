<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class OrderRefund extends Model
{
    use TenantAware;

    protected $table = 'order_refunds';

    protected $fillable = [
        'tenant_id',
        'order_id',
        'refund_number',
        'source_type',
        'source_id',
        'amount',
        'method',
        'status',
        'reference',
        'evidence_path',
        'notes',
        'failure_reason',
        'requested_by_type',
        'requested_by_id',
        'processed_by_type',
        'processed_by_id',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const METHODS = ['manual', 'bank_transfer', 'cash', 'mobile_wallet', 'store_credit'];

    public const RESERVING_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
        self::STATUS_COMPLETED,
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function histories(): MorphMany
    {
        return $this->morphMany(AfterSalesHistory::class, 'subject');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING], true);
    }

    public function getEvidenceUrlAttribute(): ?string
    {
        return $this->evidence_path ? image_url($this->evidence_path) : null;
    }
}
