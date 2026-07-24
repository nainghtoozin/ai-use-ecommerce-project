<?php

namespace App\Services;

use App\Contracts\HasSubscription;
use App\Models\Plan;
use App\Models\PlanFeature;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * FeatureGate — centralized service for SaaS feature access control.
 *
 * Provides a clean API for checking whether a user or plan has access
 * to specific platform features (product types, modules, etc).
 *
 * Usage:
 *   FeatureGate::enabled('variable_products')
 *   FeatureGate::forUser($user)->enabled('combo_products')
 *   FeatureGate::forPlan($plan)->isLocked('digital_products')
 *
 * Feature keys follow a consistent naming convention:
 *   - variable_products
 *   - combo_products
 *   - digital_products (future)
 *   - subscription_products (future)
 *   - booking_products (future)
 */
class FeatureGate
{
    /**
     * Cache TTL for feature checks (5 minutes).
     */
    protected const CACHE_TTL = 300;

    /**
     * Check if development mode is active (bypasses all feature restrictions).
     *
     * Controlled via DEV_MODE env variable — enables unrestricted feature access
     * during development without requiring seeded plan features.
     */
    public static function isDevMode(): bool
    {
        return (bool) config('app.dev_mode', false);
    }

    /**
     * Feature key to product type mapping.
     */
    protected const FEATURE_TYPE_MAP = [
        'single_products' => 'single',
        'variable_products' => 'variable',
        'combo_products' => 'combo',
        'digital_products' => 'digital',
    ];

    /**
     * Feature key descriptions for UI display.
     *
     * === CATEGORIES ===
     * Product Features   → single_products, variable_products, combo_products, digital_products
     * Analytics          → reports
     * Store Features     → custom_domain, advanced_seo, theme_editor, custom_css, maintenance_mode
     * Customer Features  → reviews, wishlist, compare
     * Marketing          → coupons, promotions, flash_sales
     * Integrations       → telegram_integration, whatsapp_integration
     * AI                 → ai_product_generator, ai_description, ai_seo, ai_translation
     * Payments           → payment_gateways_cod, payment_gateways_kbzpay, payment_gateways_wavepay,
     *                      payment_gateways_stripe, payment_gateways_paypal, payment_gateways_manual
     */
    protected const FEATURE_LABELS = [
        'single_products' => 'Standard Products',
        'variable_products' => 'Variable Products (Size, Color, etc.)',
        'combo_products' => 'Bundle / Combo Products',
        'digital_products' => 'Digital / Downloadable Products',
        'reports' => 'Analytics & Reports',
        'custom_domain' => 'Custom Domain',
        'advanced_seo' => 'Advanced SEO',
        'theme_editor' => 'Theme Editor',
        'custom_css' => 'Custom CSS',
        'maintenance_mode' => 'Maintenance Mode',
        'reviews' => 'Customer Reviews',
        'wishlist' => 'Wishlist',
        'compare' => 'Product Compare',
        'coupons' => 'Coupons',
        'promotions' => 'Promotions & Discounts',
        'flash_sales' => 'Flash Sales',
        'telegram_integration' => 'Telegram Integration',
        'whatsapp_integration' => 'WhatsApp Integration',
        'social_media_integration' => 'Social Media Integration',
        'google_analytics' => 'Google Analytics',
        'meta_pixel' => 'Meta Pixel',
        'mailchimp_integration' => 'Mailchimp Integration',
        'ai_product_generator' => 'AI Product Generator',
        'ai_description' => 'AI Product Description',
        'ai_seo' => 'AI SEO',
        'ai_translation' => 'AI Translation',
        'payment_gateways_cod' => 'Cash on Delivery',
        'payment_gateways_kbzpay' => 'KBZPay',
        'payment_gateways_wavepay' => 'WavePay',
        'payment_gateways_stripe' => 'Stripe',
        'payment_gateways_paypal' => 'PayPal',
        'payment_gateways_manual' => 'Manual Transfer',
        'gift_cards' => 'Gift Cards',
        'loyalty_points' => 'Loyalty Points Program',
        'referral_system' => 'Referral System',
        'inventory_management' => 'Inventory Management',
        'warehouse_management' => 'Warehouse Management',
    ];

    /**
     * Feature key to required plan upgrade hint.
     */
    protected const UPGRADE_HINTS = [
        'single_products' => null,
        'variable_products' => 'Starter',
        'combo_products' => 'Business',
        'digital_products' => 'Business',
        'reports' => 'Starter',
        'custom_domain' => 'Starter',
        'advanced_seo' => 'Starter',
        'theme_editor' => 'Business',
        'custom_css' => 'Business',
        'maintenance_mode' => 'Starter',
        'reviews' => null,
        'wishlist' => null,
        'compare' => 'Starter',
        'coupons' => 'Starter',
        'promotions' => 'Business',
        'flash_sales' => 'Business',
        'telegram_integration' => 'Starter',
        'whatsapp_integration' => 'Starter',
        'social_media_integration' => 'Starter',
        'google_analytics' => 'Starter',
        'meta_pixel' => 'Starter',
        'mailchimp_integration' => 'Business',
        'ai_product_generator' => 'Business',
        'ai_description' => 'Business',
        'ai_seo' => 'Business',
        'ai_translation' => 'Business',
        'payment_gateways_cod' => null,
        'payment_gateways_kbzpay' => 'Starter',
        'payment_gateways_wavepay' => 'Starter',
        'payment_gateways_stripe' => 'Starter',
        'payment_gateways_paypal' => 'Starter',
        'payment_gateways_manual' => 'Starter',
        'gift_cards' => 'Business',
        'loyalty_points' => 'Business',
        'referral_system' => 'Business',
        'inventory_management' => 'Starter',
        'warehouse_management' => 'Starter',
    ];

    protected ?HasSubscription $user = null;
    protected ?Plan $plan = null;

    /**
     * Set the user to check features for.
     */
    public static function forUser(?HasSubscription $user = null): static
    {
        $gate = new static();
        $gate->user = $user ?? auth()->user();
        return $gate;
    }

    /**
     * Set the plan to check features for.
     */
    public static function forPlan(Plan $plan): static
    {
        $gate = new static();
        $gate->plan = $plan;
        return $gate;
    }

    /**
     * Check if a feature is enabled for the current context.
     */
    public static function enabled(string $featureKey): bool
    {
        return static::forUser()->isEnabled($featureKey);
    }

    /**
     * Check if a feature is enabled.
     *
     * TODO: Re-enable subscription restrictions after SaaS billing implementation.
     * When DEV_MODE is true, all features are unlocked for development testing.
     */
    public function isEnabled(string $featureKey): bool
    {
        if (self::isDevMode()) {
            return true;
        }

        $plan = $this->resolvePlan();

        if (!$plan) {
            return false;
        }

        $cacheKey = "feature_{$plan->id}_{$featureKey}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($plan, $featureKey) {
            return $plan->features()
                ->where('feature_key', $featureKey)
                ->where('is_enabled', true)
                ->exists();
        });
    }

    /**
     * Check if a feature is disabled (locked).
     */
    public function disabled(string $featureKey): bool
    {
        return !$this->isEnabled($featureKey);
    }

    /**
     * Check if a feature is locked and get upgrade info.
     */
    public function isLocked(string $featureKey): bool
    {
        return $this->disabled($featureKey);
    }

    /**
     * Get the upgrade hint for a locked feature.
     * Returns the plan slug/label the user needs to upgrade to.
     */
    public function getUpgradeHint(string $featureKey): ?string
    {
        return self::UPGRADE_HINTS[$featureKey] ?? null;
    }

    /**
     * Static shortcut for upgrade hint.
     */
    public static function getUpgradeHintStatic(string $featureKey): ?string
    {
        return self::UPGRADE_HINTS[$featureKey] ?? null;
    }

    /**
     * Get the display label for a feature key (instance method).
     */
    public function getLabel(string $featureKey): string
    {
        return self::FEATURE_LABELS[$featureKey] ?? $featureKey;
    }

    /**
     * Get the display label for a feature key (static).
     */
    public static function getLabelStatic(string $featureKey): string
    {
        return self::FEATURE_LABELS[$featureKey] ?? $featureKey;
    }

    /**
     * Get all feature keys with their labels.
     */
    public static function getAllFeatureDefinitions(): array
    {
        $definitions = [];
        foreach (self::FEATURE_LABELS as $key => $label) {
            $definitions[] = [
                'key' => $key,
                'label' => $label,
                'upgrade_hint' => self::UPGRADE_HINTS[$key] ?? null,
            ];
        }
        return $definitions;
    }

    /**
     * Get all enabled features for the current context.
     *
     * TODO: Re-enable subscription restrictions after SaaS billing implementation.
     */
    public function getEnabledFeatures(): array
    {
        if (self::isDevMode()) {
            return array_keys(self::FEATURE_LABELS);
        }

        $plan = $this->resolvePlan();

        if (!$plan) {
            return [];
        }

        $cacheKey = "features_plan_{$plan->id}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($plan) {
            return $plan->getEnabledFeatures();
        });
    }

    /**
     * Get all features with their enabled/disabled status.
     *
     * TODO: Re-enable subscription restrictions after SaaS billing implementation.
     */
    public function getAllFeaturesStatus(): array
    {
        if (self::isDevMode()) {
            $features = [];
            foreach (array_keys(self::FEATURE_LABELS) as $key) {
                $features[$key] = [
                    'key' => $key,
                    'label' => $this->getLabel($key),
                    'enabled' => true,
                    'locked' => false,
                    'upgrade_hint' => null,
                ];
            }
            return $features;
        }

        $plan = $this->resolvePlan();
        $enabled = $this->getEnabledFeatures();
        $features = [];

        foreach (array_keys(self::FEATURE_LABELS) as $key) {
            $features[$key] = [
                'key' => $key,
                'label' => $this->getLabel($key),
                'enabled' => in_array($key, $enabled),
                'locked' => !in_array($key, $enabled),
                'upgrade_hint' => $this->getUpgradeHint($key),
            ];
        }

        return $features;
    }

    /**
     * Get the product type associated with a feature key.
     */
    public static function getTypeForFeature(string $featureKey): ?string
    {
        return self::FEATURE_TYPE_MAP[$featureKey] ?? null;
    }

    /**
     * Get the feature key associated with a product type.
     */
    public static function getFeatureForType(string $type): ?string
    {
        return array_search($type, self::FEATURE_TYPE_MAP, true) ?: null;
    }

    /**
     * Check if a product type is enabled.
     */
    public function typeEnabled(string $type): bool
    {
        $featureKey = self::getFeatureForType($type);

        if (!$featureKey) {
            return false;
        }

        return $this->isEnabled($featureKey);
    }

    /**
     * Get all enabled product types.
     */
    public function enabledTypes(): array
    {
        $types = [];

        foreach (self::FEATURE_TYPE_MAP as $featureKey => $type) {
            if ($this->isEnabled($featureKey)) {
                $types[] = $type;
            }
        }

        return $types;
    }

    /**
     * Require a feature or throw an exception.
     *
     * TODO: Re-enable subscription restrictions after SaaS billing implementation.
     * @throws \InvalidArgumentException if feature is disabled
     */
    public function require(string $featureKey): void
    {
        if (self::isDevMode()) {
            return;
        }

        if ($this->disabled($featureKey)) {
            $label = $this->getLabel($featureKey);
            $hint = $this->getUpgradeHint($featureKey);
            $message = "{$label} is not available on your current plan.";

            if ($hint) {
                $message .= " Upgrade to {$hint} plan to unlock this feature.";
            }

            throw new \InvalidArgumentException($message);
        }
    }

    /**
     * Clear the feature cache for a plan.
     */
    public static function clearCache(Plan $plan): void
    {
        Cache::forget("features_plan_{$plan->id}");

        foreach (array_keys(self::FEATURE_LABELS) as $key) {
            Cache::forget("feature_{$plan->id}_{$key}");
        }
    }

    /**
     * Resolve the plan to check features against.
     */
    protected function resolvePlan(): ?Plan
    {
        if ($this->plan) {
            return $this->plan;
        }

        if ($this->user) {
            return $this->user->getActivePlan();
        }

        if (auth()->check()) {
            $user = auth()->user();
            if ($user instanceof HasSubscription) {
                return $user->getActivePlan();
            }
        }

        return Plan::free();
    }
}
