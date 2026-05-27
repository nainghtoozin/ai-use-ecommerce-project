<?php

namespace App\Enums;

use App\Services\FeatureGate;

/**
 * ProductType constants for the ecommerce product system.
 *
 * Defines the supported product types and their capabilities.
 * This class serves as the single source of truth for product type definitions.
 *
 * Feature gating:
 *   - Single products: always available (base feature)
 *   - Variable products: gated by 'variable_products' feature
 *   - Combo products: gated by 'combo_products' feature
 *
 * @see \App\Models\Product
 * @see \App\Services\ProductService
 * @see \App\Services\FeatureGate
 */
final class ProductType
{
    public const SINGLE = 'single';

    public const VARIABLE = 'variable';

    public const COMBO = 'combo';

    /**
     * Feature keys mapped to product types.
     */
    protected const TYPE_FEATURE_MAP = [
        self::SINGLE => 'single_products',
        self::VARIABLE => 'variable_products',
        self::COMBO => 'combo_products',
    ];

    /**
     * All supported product types.
     *
     * @return string[]
     */
    public static function all(): array
    {
        return [
            self::SINGLE,
            self::VARIABLE,
            self::COMBO,
        ];
    }

    /**
     * Get the human-readable label for a product type.
     */
    public static function label(string $type): string
    {
        return match ($type) {
            self::SINGLE => 'Single Product',
            self::VARIABLE => 'Variable Product',
            self::COMBO => 'Combo Product',
            default => 'Unknown',
        };
    }

    /**
     * Check if a given type string is valid.
     */
    public static function isValid(string $type): bool
    {
        return in_array($type, self::all(), true);
    }

    /**
     * Check if the product type supports variants.
     */
    public static function supportsVariants(string $type): bool
    {
        return $type === self::VARIABLE;
    }

    /**
     * Check if the product type is a bundle of multiple products.
     */
    public static function isBundle(string $type): bool
    {
        return $type === self::COMBO;
    }

    /**
     * Get the feature key for a product type.
     */
    public static function featureKey(string $type): ?string
    {
        return self::TYPE_FEATURE_MAP[$type] ?? null;
    }

    /**
     * Types that are currently available based on the user's plan.
     *
     * Uses FeatureGate to determine which product types are enabled.
     * Returns at minimum 'single' as the base product type.
     */
    public static function availableTypes(): array
    {
        $available = [self::SINGLE];

        foreach (self::TYPE_FEATURE_MAP as $type => $featureKey) {
            if ($type === self::SINGLE) {
                continue;
            }

            if (FeatureGate::enabled($featureKey)) {
                $available[] = $type;
            }
        }

        return $available;
    }

    /**
     * Check if the product type is available for the current user.
     */
    public static function isAvailable(string $type): bool
    {
        $featureKey = self::featureKey($type);

        if (!$featureKey) {
            return false;
        }

        return FeatureGate::enabled($featureKey);
    }

    /**
     * Get upgrade hint for a locked product type.
     * Returns the plan name the user needs to unlock this type.
     */
    public static function getUpgradeHint(string $type): ?string
    {
        $featureKey = self::featureKey($type);

        if (!$featureKey) {
            return null;
        }

        return FeatureGate::forUser()->getUpgradeHint($featureKey);
    }

    /**
     * Check if a product type is locked (not available).
     */
    public static function isLocked(string $type): bool
    {
        return !self::isAvailable($type);
    }
}
