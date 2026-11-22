<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;

class ReturnSetting extends Model
{
    use TenantAware;

    protected $table = 'return_settings';

    protected $fillable = [
        'tenant_id',
        'returns_enabled',
        'return_window_days',
        'allow_full_return',
        'allow_partial_return',
        'require_reason',
        'require_note',
        'require_evidence',
        'allow_refund',
        'allow_exchange',
        'allow_replacement',
        'return_shipping_responsibility',
        'shipping_responsibility_by_reason',
        'refund_shipping_fee',
        'reasons',
        'instructions',
        'policy_text',
    ];

    protected $casts = [
        'returns_enabled' => 'boolean',
        'return_window_days' => 'integer',
        'allow_full_return' => 'boolean',
        'allow_partial_return' => 'boolean',
        'require_reason' => 'boolean',
        'require_note' => 'boolean',
        'require_evidence' => 'boolean',
        'allow_refund' => 'boolean',
        'allow_exchange' => 'boolean',
        'allow_replacement' => 'boolean',
        'shipping_responsibility_by_reason' => 'array',
        'refund_shipping_fee' => 'boolean',
        'reasons' => 'array',
    ];

    public const DEFAULT_REASONS = [
        ['code' => 'damaged', 'label' => 'Item arrived damaged', 'shipping' => 'merchant'],
        ['code' => 'wrong_product', 'label' => 'Wrong product delivered', 'shipping' => 'merchant'],
        ['code' => 'missing_parts', 'label' => 'Missing parts or accessories', 'shipping' => 'merchant'],
        ['code' => 'not_as_described', 'label' => 'Not as described', 'shipping' => 'merchant'],
        ['code' => 'changed_mind', 'label' => 'Changed my mind', 'shipping' => 'customer'],
        ['code' => 'other', 'label' => 'Other', 'shipping' => 'merchant'],
    ];

    public static function defaults(): array
    {
        return [
            'returns_enabled' => true,
            'return_window_days' => 7,
            'allow_full_return' => true,
            'allow_partial_return' => true,
            'require_reason' => true,
            'require_note' => false,
            'require_evidence' => false,
            'allow_refund' => true,
            'allow_exchange' => false,
            'allow_replacement' => false,
            'return_shipping_responsibility' => 'customer',
            'shipping_responsibility_by_reason' => [
                'damaged' => 'merchant',
                'wrong_product' => 'merchant',
                'missing_parts' => 'merchant',
                'not_as_described' => 'merchant',
                'changed_mind' => 'customer',
                'other' => 'merchant',
            ],
            'refund_shipping_fee' => false,
            'reasons' => self::DEFAULT_REASONS,
            'instructions' => null,
            'policy_text' => null,
        ];
    }
}
