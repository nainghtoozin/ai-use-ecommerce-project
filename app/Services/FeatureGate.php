<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\User;
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
     * Development mode: bypasses all feature restrictions.
     *
     * TODO: Re-enable subscription restrictions after SaaS billing implementation.
     * Set this to false once plans, billing, and user subscriptions are live.
     */
    protected const DEV_MODE = true;

    /**
     * Feature key to product type mapping.
     */
    protected const FEATURE_TYPE_MAP = [
        'single_products' => 'single',
        'variable_products' => 'variable',
        'combo_products' => 'combo',
    ];

    /**
     * Feature key descriptions for UI display.
     */
    protected const FEATURE_LABELS = [
        'single_products' => 'Standard Products',
        'variable_products' => 'Variable Products (Size, Color, etc.)',
        'combo_products' => 'Bundle / Combo Products',
    ];

    /**
     * Feature key to required plan upgrade hint.
     */
    protected const UPGRADE_HINTS = [
        'single_products' => null,
        'variable_products' => 'Starter',
        'combo_products' => 'Business',
    ];

    protected ?User $user = null;
    protected ?Plan $plan = null;

    /**
     * Set the user to check features for.
     */
    public static function forUser(?User $user = null): static
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
        // TODO: Remove this dev mode bypass once billing/subscriptions are live.
        if (self::DEV_MODE) {
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
     * Get the display label for a feature key.
     */
    public function getLabel(string $featureKey): string
    {
        return self::FEATURE_LABELS[$featureKey] ?? $featureKey;
    }

    /**
     * Get all enabled features for the current context.
     *
     * TODO: Re-enable subscription restrictions after SaaS billing implementation.
     */
    public function getEnabledFeatures(): array
    {
        // TODO: Remove this dev mode bypass once billing/subscriptions are live.
        if (self::DEV_MODE) {
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
        // TODO: Remove this dev mode bypass once billing/subscriptions are live.
        if (self::DEV_MODE) {
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
        // TODO: Remove this dev mode bypass once billing/subscriptions are live.
        if (self::DEV_MODE) {
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

        foreach (array_keys(self::FEATURE_TYPE_MAP) as $key) {
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
            /** @var User $user */
            $user = auth()->user();
            return $user->getActivePlan();
        }

        return Plan::free();
    }
}
