<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class OrderReturn extends Model
{
    use TenantAware;

    protected $table = 'order_returns';

    protected $hidden = ['tracking_url'];

    protected $fillable = [
        'tenant_id',
        'order_id',
        'rma_number',
        'requested_by_type',
        'requested_by_id',
        'status',
        'resolution',
        'reason_code',
        'reason_label',
        'customer_note',
        'merchant_note',
        'evidence',
        'shipping_responsibility',
        'shipping_fee_amount',
        'refund_shipping_fee',
        'carrier_name',
        'tracking_number',
        'tracking_url',
        'shipped_at',
        'tracking_note',
        'policy_snapshot',
        'decision_by_type',
        'decision_by_id',
        'decision_note',
        'rejection_reason',
        'refund_id',
        'replacement_order_id',
        'requested_at',
        'approved_at',
        'rejected_at',
        'received_at',
        'inspected_at',
        'completed_at',
        'expires_at',
    ];

    protected $casts = [
        'evidence' => 'array',
        'policy_snapshot' => 'array',
        'shipping_fee_amount' => 'decimal:2',
        'refund_shipping_fee' => 'boolean',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'received_at' => 'datetime',
        'inspected_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
        'shipped_at' => 'datetime',
    ];

    public const STATUS_REQUESTED = 'requested';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_AWAITING_SHIPMENT = 'awaiting_shipment';
    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_INSPECTION = 'inspection';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    public const RESOLUTION_REFUND = 'refund';
    public const RESOLUTION_EXCHANGE = 'exchange';
    public const RESOLUTION_REPLACEMENT = 'replacement';

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderReturnItem::class);
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(OrderRefund::class, 'refund_id');
    }

    public function replacementOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'replacement_order_id');
    }

    public function histories(): MorphMany
    {
        return $this->morphMany(AfterSalesHistory::class, 'subject');
    }

    public function isOpen(): bool
    {
        return !in_array($this->status, [
            self::STATUS_REJECTED,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
            self::STATUS_EXPIRED,
        ], true);
    }

    public function getEvidenceUrlsAttribute(): array
    {
        return collect($this->evidence ?? [])
            ->filter()
            ->map(fn ($path) => image_url($path))
            ->values()
            ->all();
    }

    public static function sanitizeTrackingUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return null;
        }

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    public function getSafeTrackingUrlAttribute(): ?string
    {
        return static::sanitizeTrackingUrl($this->tracking_url);
    }

    public function hasTracking(): bool
    {
        return filled($this->carrier_name)
            || filled($this->tracking_number)
            || $this->getSafeTrackingUrlAttribute() !== null
            || $this->shipped_at !== null
            || filled($this->tracking_note);
    }

    public function trackingPayload(): array
    {
        return [
            'carrier_name' => $this->carrier_name,
            'tracking_number' => $this->tracking_number,
            'tracking_url' => $this->getSafeTrackingUrlAttribute(),
            'shipped_at' => $this->shipped_at?->toIso8601String(),
            'tracking_note' => $this->tracking_note,
        ];
    }

    public function canTransitionTo(string $status): bool
    {
        $map = [
            self::STATUS_REQUESTED => [self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_CANCELLED, self::STATUS_EXPIRED],
            self::STATUS_APPROVED => [self::STATUS_AWAITING_SHIPMENT, self::STATUS_IN_TRANSIT, self::STATUS_RECEIVED, self::STATUS_CANCELLED, self::STATUS_EXPIRED],
            self::STATUS_AWAITING_SHIPMENT => [self::STATUS_IN_TRANSIT, self::STATUS_RECEIVED, self::STATUS_CANCELLED],
            self::STATUS_IN_TRANSIT => [self::STATUS_RECEIVED, self::STATUS_CANCELLED],
            self::STATUS_RECEIVED => [self::STATUS_INSPECTION, self::STATUS_COMPLETED, self::STATUS_CANCELLED],
            self::STATUS_INSPECTION => [self::STATUS_COMPLETED],
        ];

        return in_array($status, $map[$this->status] ?? [], true);
    }
}
