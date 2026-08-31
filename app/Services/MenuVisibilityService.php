<?php

namespace App\Services;

use App\Models\Tenant;

class MenuVisibilityService
{
    private const SETTING_KEY = 'admin_menu_visibility';

    private const DEFAULTS = [
        'overview' => true,
        'catalog' => true,
        'catalog.products' => true,
        'catalog.categories' => true,
        'catalog.brands' => true,
        'catalog.units' => true,
        'inventory' => false,
        'inventory.dashboard' => false,
        'inventory.products' => false,
        'inventory.stock_history' => false,
        'inventory.movements' => false,
        'inventory.adjustments' => false,
        'sales' => true,
        'sales.orders' => true,
        'sales.payment_methods' => true,
        'marketing' => false,
        'marketing.coupons' => false,
        'marketing.promotions' => false,
        'marketing.flash_sales' => false,
        'billing' => true,
        'billing.overview' => true,
        'billing.subscription' => false,
        'billing.upgrade' => true,
        'billing.invoices' => true,
        'billing.history' => false,
        'billing.settings' => false,
        'analytics' => false,
        'analytics.sales' => false,
        'analytics.products' => false,
        'analytics.payments' => false,
        'locations' => true,
        'locations.cities' => true,
        'locations.townships' => true,
        'staff' => true,
        'staff.members' => true,
        'staff.staff' => false,
        'staff.roles' => true,
        'staff.activity' => false,
        'staff.audit' => false,
        'staff.notifications' => false,
        'content' => true,
        'content.faq' => true,
        'storefront' => true,
        'storefront.overview' => true,
        'storefront.homepage' => true,
        'storefront.navigation' => true,
        'storefront.media' => true,
        'storefront.promotions' => true,
        'settings' => true,
        'settings.website' => true,
        'settings.notifications' => false,
        'settings.telegram' => false,
        'settings.setup_guide' => false,
        'settings.general' => false,
    ];

    private const ALL_ENABLED = [
        'overview' => true,
        'catalog' => true,
        'catalog.products' => true,
        'catalog.categories' => true,
        'catalog.brands' => true,
        'catalog.units' => true,
        'inventory' => true,
        'inventory.dashboard' => true,
        'inventory.products' => true,
        'inventory.stock_history' => true,
        'inventory.movements' => true,
        'inventory.adjustments' => true,
        'sales' => true,
        'sales.orders' => true,
        'sales.payment_methods' => true,
        'marketing' => true,
        'marketing.coupons' => true,
        'marketing.promotions' => true,
        'marketing.flash_sales' => true,
        'billing' => true,
        'billing.overview' => true,
        'billing.subscription' => true,
        'billing.upgrade' => true,
        'billing.invoices' => true,
        'billing.history' => true,
        'billing.settings' => true,
        'analytics' => true,
        'analytics.sales' => true,
        'analytics.products' => true,
        'analytics.payments' => true,
        'locations' => true,
        'locations.cities' => true,
        'locations.townships' => true,
        'staff' => true,
        'staff.members' => true,
        'staff.staff' => true,
        'staff.roles' => true,
        'staff.activity' => true,
        'staff.audit' => true,
        'staff.notifications' => true,
        'content' => true,
        'content.faq' => true,
        'storefront' => true,
        'storefront.overview' => true,
        'storefront.homepage' => true,
        'storefront.navigation' => true,
        'storefront.media' => true,
        'storefront.promotions' => true,
        'settings' => true,
        'settings.website' => true,
        'settings.notifications' => true,
        'settings.telegram' => true,
        'settings.setup_guide' => true,
        'settings.general' => true,
    ];

    public static function getDefaults(): array
    {
        return self::DEFAULTS;
    }

    public static function getAllEnabled(): array
    {
        return self::ALL_ENABLED;
    }

    public static function getVisibility(?Tenant $tenant = null): array
    {
        $tenant = $tenant ?? Tenant::getCurrent();

        if (!$tenant) {
            return self::DEFAULTS;
        }

        $settings = $tenant->settings ?? [];
        $stored = $settings[self::SETTING_KEY] ?? null;

        if (!is_array($stored) || empty($stored)) {
            return self::DEFAULTS;
        }

        return array_merge(self::DEFAULTS, $stored);
    }

    public static function isVisible(string $key, ?Tenant $tenant = null): bool
    {
        $visibility = self::getVisibility($tenant);
        return $visibility[$key] ?? false;
    }

    public static function saveVisibility(array $visibility, ?Tenant $tenant = null): bool
    {
        $tenant = $tenant ?? Tenant::getCurrent();

        if (!$tenant) {
            return false;
        }

        $settings = $tenant->settings ?? [];
        $settings[self::SETTING_KEY] = array_merge(self::DEFAULTS, $visibility);

        $tenant->settings = $settings;
        $tenant->save();

        app(StoreResolver::class)->clearCache($tenant->slug);

        return true;
    }

    public static function initializeDefaults(Tenant $tenant): void
    {
        $settings = $tenant->settings ?? [];

        if (empty($settings[self::SETTING_KEY])) {
            $settings[self::SETTING_KEY] = self::DEFAULTS;
            $tenant->settings = $settings;
            $tenant->saveQuietly();
        }
    }

    public static function hasAnyChildVisible(string $section, ?Tenant $tenant = null): bool
    {
        $visibility = self::getVisibility($tenant);

        foreach ($visibility as $key => $value) {
            if (str_starts_with($key, $section . '.') && $value) {
                return true;
            }
        }

        return false;
    }
}
