<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TenantSeeder extends Seeder
{
    private array $tablesWithTenantId;

    public function __construct()
    {
        $this->tablesWithTenantId = [
            'users',
            'categories',
            'products',
            'product_variants',
            'product_combos',
            'orders',
            'order_items',
            'order_coupon',
            'coupons',
            'coupon_product',
            'coupon_category',
            'promotions',
            'promotion_banners',
            'promotion_product',
            'promotion_category',
            'promotion_usages',
            'payment_methods',
            'cities',
            'townships',
            'messages',
            'website_infos',
            'settings',
            'wishlists',
            'telegram_integrations',
            'notifications',
            'activity_logs',
        ];
    }

    public function run(): void
    {
        $this->ensureDefaultTenant();
        $this->backfillNullTenantIds();
        $this->clearCaches();
    }

    private function ensureDefaultTenant(): void
    {
        $host = parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost';

        DB::table('tenants')->updateOrInsert(
            ['id' => 1],
            [
                'name' => 'Default Store',
                'slug' => 'default',
                'domain' => $host,
                'email' => config('app.name') . '@example.com',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->command?->info('Default tenant (ID=1) ensured.');
    }

    private function backfillNullTenantIds(): void
    {
        $fixed = 0;

        foreach ($this->tablesWithTenantId as $table) {
            if (!DB::getSchemaBuilder()->hasColumn($table, 'tenant_id')) {
                continue;
            }

            $affected = DB::table($table)
                ->whereNull('tenant_id')
                ->update(['tenant_id' => 1]);

            if ($affected > 0) {
                $fixed += $affected;
                $this->command?->warn("  {$table}: {$affected} row(s) backfilled.");
            }
        }

        if ($fixed === 0) {
            $this->command?->info('No null tenant_id values found. All data is already assigned.');
        } else {
            $this->command?->info("Total: {$fixed} row(s) backfilled to default tenant.");
        }
    }

    private function clearCaches(): void
    {
        cache()->forget('tenant_default');
        $this->command?->info('Tenant cache cleared.');
    }
}
