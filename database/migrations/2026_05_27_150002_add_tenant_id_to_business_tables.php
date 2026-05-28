<?php

use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $defaultTenantId = Tenant::getDefault()?->id;

        $addColumnIfNotExists = function (string $table, bool $hasId = true) {
            $cols = DB::select('SHOW COLUMNS FROM ' . $table . ' WHERE Field = ?', ['tenant_id']);
            if (count($cols) > 0) {
                return;
            }
            $after = $hasId ? ' AFTER `id`' : '';
            DB::statement('ALTER TABLE `' . $table . '` ADD `tenant_id` BIGINT UNSIGNED NULL' . $after);
            DB::statement('ALTER TABLE `' . $table . '` ADD CONSTRAINT `' . $table . '_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE SET NULL');
        };

        $assignDefault = function (string $table) use ($defaultTenantId) {
            if ($defaultTenantId) {
                DB::table($table)->whereNull('tenant_id')->update(['tenant_id' => $defaultTenantId]);
            }
        };

        $tablesWithId = [
            'categories', 'products', 'product_variants', 'product_combos',
            'orders', 'order_items', 'coupons', 'order_coupon',
            'promotions', 'promotion_usages', 'promotion_banners',
            'payment_methods', 'cities', 'townships', 'messages',
            'settings', 'wishlists', 'activity_logs', 'telegram_integrations',
        ];

        $pivotTablesNoId = [
            'coupon_product', 'coupon_category', 'promotion_product', 'promotion_category',
        ];

        foreach ($tablesWithId as $table) {
            $addColumnIfNotExists($table, true);
            $assignDefault($table);
        }

        foreach ($pivotTablesNoId as $table) {
            $addColumnIfNotExists($table, false);
            $assignDefault($table);
        }

        // Website infos — special handling with unique constraint
        $cols = DB::select('SHOW COLUMNS FROM website_infos WHERE Field = ?', ['tenant_id']);
        if (count($cols) === 0) {
            DB::statement('ALTER TABLE `website_infos` ADD `tenant_id` BIGINT UNSIGNED NULL AFTER `id`');
            DB::statement('ALTER TABLE `website_infos` ADD CONSTRAINT `website_infos_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE SET NULL');
        }
        $assignDefault('website_infos');
        // Add unique constraint if it doesn't exist
        $uniqueExists = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'website_infos' AND CONSTRAINT_TYPE = 'UNIQUE' AND CONSTRAINT_NAME = 'website_infos_tenant_unique'");
        if (count($uniqueExists) === 0) {
            DB::statement('ALTER TABLE `website_infos` ADD UNIQUE KEY `website_infos_tenant_unique` (`tenant_id`)');
        }
    }

    public function down(): void
    {
        $tables = [
            'categories', 'products', 'product_variants', 'product_combos',
            'orders', 'order_items', 'coupons', 'coupon_product', 'coupon_category',
            'order_coupon', 'promotions', 'promotion_product', 'promotion_category',
            'promotion_usages', 'promotion_banners', 'payment_methods', 'cities',
            'townships', 'messages', 'settings', 'wishlists', 'activity_logs',
            'telegram_integrations',
        ];

        foreach ($tables as $table) {
            DB::statement('ALTER TABLE `' . $table . '` DROP FOREIGN KEY IF EXISTS `' . $table . '_tenant_id_foreign`');
            DB::statement('ALTER TABLE `' . $table . '` DROP COLUMN IF EXISTS `tenant_id`');
        }

        DB::statement('ALTER TABLE `website_infos` DROP INDEX IF EXISTS `website_infos_tenant_unique`');
        DB::statement('ALTER TABLE `website_infos` DROP FOREIGN KEY IF EXISTS `website_infos_tenant_id_foreign`');
        DB::statement('ALTER TABLE `website_infos` DROP COLUMN IF EXISTS `tenant_id`');
    }
};
