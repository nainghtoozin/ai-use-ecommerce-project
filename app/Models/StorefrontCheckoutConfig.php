<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;

class StorefrontCheckoutConfig extends Model
{
    use TenantAware;

    protected $fillable = ['tenant_id', 'storefront_id', 'configuration'];

    protected $casts = ['configuration' => 'array'];

    public function storefront()
    {
        return $this->belongsTo(Storefront::class);
    }

    public static function getDefaults(): array
    {
        return [
            'title' => 'Checkout',
            'subtitle' => 'Complete your order',
            'show_branding' => true,
            'sections' => [
                'address' => ['visible' => true, 'title' => 'Delivery Address', 'order' => 1],
                'delivery' => ['visible' => true, 'title' => 'Delivery Options', 'order' => 2],
                'payment' => ['visible' => true, 'title' => 'Payment Method', 'order' => 3],
            ],
            'button_labels' => [
                'continue_to_delivery' => 'Continue to Delivery',
                'continue_to_payment' => 'Continue to Payment',
                'place_order' => 'Place Order',
            ],
            'appearance' => [
                'card_style' => 'bordered',
                'section_spacing' => 'normal',
                'compact_mode' => false,
                'border_radius' => 'medium',
                'button_style' => 'solid',
            ],
            'layout' => [
                'order_summary_position' => 'right',
                'show_order_summary_on_mobile' => true,
            ],
            'visual' => [
                'preset' => 'modern',
                'show_store_logo' => true,
                'order_summary_style' => 'card',
            ],
        ];
    }

    public static function getPresetDefaults(string $preset): array
    {
        $presets = [
            'modern' => [
                'card_style' => 'bordered',
                'section_spacing' => 'normal',
                'border_radius' => 'medium',
                'button_style' => 'solid',
                'shadow' => false,
            ],
            'minimal' => [
                'card_style' => 'flat',
                'section_spacing' => 'compact',
                'border_radius' => 'none',
                'button_style' => 'outline',
                'shadow' => false,
            ],
            'compact' => [
                'card_style' => 'bordered',
                'section_spacing' => 'compact',
                'border_radius' => 'small',
                'button_style' => 'solid',
                'shadow' => false,
            ],
        ];

        return $presets[$preset] ?? $presets['modern'];
    }
}
