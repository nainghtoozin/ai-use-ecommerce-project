<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Services\FeatureGate;
use Inertia\Inertia;

class PublicLandingController extends Controller
{
    public function index()
    {
        $allPlans = Plan::active()->ordered()->get();
        $allFeatureDefs = FeatureGate::getAllFeatureDefinitions();
        $featureKeys = array_column($allFeatureDefs, 'key');

        $plans = $allPlans->map(function ($plan) use ($featureKeys) {
            if (!$plan) {
                return null;
            }
            $enabledFeatures = $plan->getEnabledFeatures();
            $features = array_map(fn($key) => [
                'key' => $key,
                'enabled' => in_array($key, $enabledFeatures),
            ], $featureKeys);

            return [
                'id' => $plan->id,
                'name' => $plan->name ?? '',
                'slug' => $plan->slug ?? '',
                'description' => $plan->description ?? '',
                'monthly_price' => $plan->monthly_price,
                'yearly_price' => $plan->yearly_price,
                'yearly_savings_percent' => $plan->yearlySavingsPercent(),
                'limits' => [
                    'product_limit' => $plan->productLimit(),
                    'staff_limit' => $plan->staffLimit(),
                    'storage_limit' => $plan->storageLimitMb(),
                    'orders_monthly_limit' => $plan->limitValue('orders_monthly_limit'),
                    'coupon_limit' => $plan->limitValue('coupon_limit'),
                    'promotion_limit' => $plan->limitValue('promotion_limit'),
                    'flash_sale_limit' => $plan->limitValue('flash_sale_limit'),
                    'api_request_limit' => $plan->api_request_limit,
                    'image_limit' => $plan->image_limit,
                    'image_max_size_kb' => $plan->image_max_size_kb,
                    'branch_limit' => $plan->limitValue('branch_limit'),
                    'warehouse_limit' => $plan->limitValue('warehouse_limit'),
                    'pos_device_limit' => $plan->limitValue('pos_device_limit'),
                ],
                'features' => $features,
            ];
        })->filter()->values();

        $featureCategories = [
            ['label' => 'Product Features', 'keys' => ['single_products', 'variable_products', 'combo_products', 'digital_products']],
            ['label' => 'Analytics', 'keys' => ['reports']],
            ['label' => 'Store Features', 'keys' => ['custom_domain', 'advanced_seo', 'theme_editor', 'custom_css', 'maintenance_mode']],
            ['label' => 'Customer Features', 'keys' => ['reviews', 'wishlist', 'compare']],
            ['label' => 'Marketing', 'keys' => ['coupons', 'promotions', 'flash_sales']],
            ['label' => 'Integrations', 'keys' => ['telegram_integration', 'whatsapp_integration', 'social_media_integration', 'google_analytics', 'meta_pixel', 'mailchimp_integration']],
            ['label' => 'AI', 'keys' => ['ai_product_generator', 'ai_description', 'ai_seo', 'ai_translation']],
            ['label' => 'Payment Gateways', 'keys' => ['payment_gateways_cod', 'payment_gateways_kbzpay', 'payment_gateways_wavepay', 'payment_gateways_stripe', 'payment_gateways_paypal', 'payment_gateways_manual']],
        ];

        $featureCategories = array_map(function ($cat) use ($allFeatureDefs) {
            $cat['features'] = array_values(array_filter(array_map(function ($key) use ($allFeatureDefs) {
                $def = current(array_filter($allFeatureDefs, fn($d) => ($d['key'] ?? '') === $key));
                return $def ? $def : null;
            }, $cat['keys'] ?? [])));
            return $cat;
        }, $featureCategories);

        return Inertia::render('Public/Landing', [
            'plans' => $plans,
            'featureCategories' => $featureCategories,
            'allFeatureDefs' => $allFeatureDefs,
        ]);
    }
}
