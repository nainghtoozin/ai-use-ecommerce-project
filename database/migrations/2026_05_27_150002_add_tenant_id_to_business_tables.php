<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $defaultTenantId = DB::table('tenants')->value('id');

        $addColumnIfNotExists = function (string $table, bool $hasId = true) {
            if (Schema::hasColumn($table, 'tenant_id')) {
                return;
            }
            Schema::table($table, function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            });
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
            if (Schema::hasTable($table)) {
                $addColumnIfNotExists($table, true);
                $assignDefault($table);
            }
        }

        foreach ($pivotTablesNoId as $table) {
            if (Schema::hasTable($table)) {
                $addColumnIfNotExists($table, false);
                $assignDefault($table);
            }
        }

        // Website infos — special handling with unique constraint
        if (Schema::hasTable('website_infos') && !Schema::hasColumn('website_infos', 'tenant_id')) {
            Schema::table('website_infos', function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable()->unique()->constrained('tenants')->cascadeOnDelete();
            });
            $assignDefault('website_infos');
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
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropConstrainedForeignId('tenant_id');
                });
            }
        }

        if (Schema::hasTable('website_infos') && Schema::hasColumn('website_infos', 'tenant_id')) {
            Schema::table('website_infos', function (Blueprint $table) {
                $table->dropConstrainedForeignId('tenant_id');
            });
        }
    }
};
