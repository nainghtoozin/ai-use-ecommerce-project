<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\PlanFeature;
use Illuminate\Database\Seeder;

/**
 * PlanSeeder — seeds default subscription plans and their features.
 *
 * Plan hierarchy:
 *   Free      → single_products only
 *   Starter   → single_products + variable_products
 *   Business  → single_products + variable_products + combo_products
 *
 * Feature keys:
 *   single_products    — create standard single-variant products
 *   variable_products  — create products with size/color/etc variants
 *   combo_products     — create bundle/combo products
 *
 * Future features:
 *   digital_products   — digital/downloadable products
 *   subscription_products — recurring subscription products
 *   booking_products   — booking/appointment products
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'price' => 0,
                'currency' => 'USD',
                'interval' => 'monthly',
                'description' => 'Get started with standard products.',
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 1,
                'features' => [
                    'single_products' => [
                        'label' => 'Standard Products',
                        'description' => 'Create products with fixed pricing and inventory.',
                    ],
                    'variable_products' => [
                        'label' => 'Variable Products',
                        'description' => null,
                    ],
                    'combo_products' => [
                        'label' => 'Bundle / Combo Products',
                        'description' => null,
                    ],
                ],
            ],
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'price' => 9.99,
                'currency' => 'USD',
                'interval' => 'monthly',
                'description' => 'Unlock variable products with size, color, and more options.',
                'is_default' => false,
                'is_active' => true,
                'sort_order' => 2,
                'features' => [
                    'single_products' => [
                        'label' => 'Standard Products',
                        'description' => 'Create products with fixed pricing and inventory.',
                    ],
                    'variable_products' => [
                        'label' => 'Variable Products',
                        'description' => 'Create products with multiple options like size, color, material.',
                    ],
                    'combo_products' => [
                        'label' => 'Bundle / Combo Products',
                        'description' => null,
                    ],
                ],
            ],
            [
                'name' => 'Business',
                'slug' => 'business',
                'price' => 29.99,
                'currency' => 'USD',
                'interval' => 'monthly',
                'description' => 'Full product suite including bundles and combos.',
                'is_default' => false,
                'is_active' => true,
                'sort_order' => 3,
                'features' => [
                    'single_products' => [
                        'label' => 'Standard Products',
                        'description' => 'Create products with fixed pricing and inventory.',
                    ],
                    'variable_products' => [
                        'label' => 'Variable Products',
                        'description' => 'Create products with multiple options like size, color, material.',
                    ],
                    'combo_products' => [
                        'label' => 'Bundle / Combo Products',
                        'description' => 'Create product bundles and combo deals with custom pricing.',
                    ],
                ],
            ],
        ];

        foreach ($plans as $planData) {
            $features = $planData['features'];
            unset($planData['features']);

            $plan = Plan::updateOrCreate(
                ['slug' => $planData['slug']],
                $planData
            );

            foreach ($features as $featureKey => $featureData) {
                PlanFeature::updateOrCreate(
                    [
                        'plan_id' => $plan->id,
                        'feature_key' => $featureKey,
                    ],
                    [
                        'is_enabled' => $featureData['description'] !== null,
                        'display_label' => $featureData['label'],
                        'description' => $featureData['description'],
                    ]
                );
            }

            \App\Services\FeatureGate::clearCache($plan);
        }
    }
}
