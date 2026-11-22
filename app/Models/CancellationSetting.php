<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;

class CancellationSetting extends Model
{
    use TenantAware;

    protected $table = 'cancellation_settings';

    protected $fillable = [
        'tenant_id',
        'customer_cancel_enabled',
        'merchant_cancel_enabled',
        'status_rules',
        'instant_cancel_window_hours',
        'max_cancellations',
        'limit_period_days',
        'limit_exceeded_action',
        'allow_request_after_limit',
        'require_reason',
        'require_note',
        'reasons',
        'refund_shipping_fee',
    ];

    protected $casts = [
        'customer_cancel_enabled' => 'boolean',
        'merchant_cancel_enabled' => 'boolean',
        'status_rules' => 'array',
        'reasons' => 'array',
        'instant_cancel_window_hours' => 'integer',
        'max_cancellations' => 'integer',
        'limit_period_days' => 'integer',
        'allow_request_after_limit' => 'boolean',
        'require_reason' => 'boolean',
        'require_note' => 'boolean',
        'refund_shipping_fee' => 'boolean',
    ];

    public const MODE_INSTANT = 'instant';
    public const MODE_APPROVAL = 'approval';
    public const MODE_NOT_ALLOWED = 'not_allowed';
    public const MODE_RETURN_ONLY = 'return_only';

    public const DEFAULT_RULES = [
        'pending' => self::MODE_INSTANT,
        'confirmed' => self::MODE_APPROVAL,
        'processing' => self::MODE_APPROVAL,
        'shipped' => self::MODE_NOT_ALLOWED,
        'delivered' => self::MODE_RETURN_ONLY,
    ];

    public const DEFAULT_REASONS = [
        ['code' => 'changed_mind', 'label' => 'Changed my mind'],
        ['code' => 'ordered_by_mistake', 'label' => 'Ordered by mistake'],
        ['code' => 'found_better_price', 'label' => 'Found a better price'],
        ['code' => 'delivery_too_slow', 'label' => 'Delivery is taking too long'],
        ['code' => 'other', 'label' => 'Other'],
    ];

    public static function defaults(): array
    {
        return [
            'customer_cancel_enabled' => true,
            'merchant_cancel_enabled' => true,
            'status_rules' => self::DEFAULT_RULES,
            'instant_cancel_window_hours' => 24,
            'max_cancellations' => 3,
            'limit_period_days' => 30,
            'limit_exceeded_action' => 'require_approval',
            'allow_request_after_limit' => true,
            'require_reason' => true,
            'require_note' => false,
            'reasons' => self::DEFAULT_REASONS,
            'refund_shipping_fee' => false,
        ];
    }
}
