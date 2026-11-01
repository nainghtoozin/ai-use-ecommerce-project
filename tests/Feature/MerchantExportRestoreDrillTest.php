<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Services\Export\MerchantDataExportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MerchantRestoreDrill\ArchiveImporter;
use Tests\Support\MerchantRestoreDrill\DrillFixture;
use Tests\TestCase;

class MerchantExportRestoreDrillTest extends TestCase
{
    use DatabaseTransactions;

    private const TARGET_DATABASE = 'ecommerc_restore_drill_4b5d';

    private bool $targetCreated = false;
    private ?string $archivePath = null;
    private ?DrillFixture $fixture = null;
    private ?ArchiveImporter $importer = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('RESTORE_DRILL') !== '1') {
            $this->markTestSkipped('Set RESTORE_DRILL=1 to run the isolated restore drill.');
        }
    }

    protected function tearDown(): void
    {
        $this->fixture?->purge();
        $this->fixture?->cleanupAsset();
        $this->importer?->cleanup();

        if ($this->archivePath !== null && is_file($this->archivePath)) {
            @unlink($this->archivePath);
        }

        if ($this->targetCreated) {
            DB::disconnect('restore_drill');
            DB::purge('restore_drill');
            DB::statement('DROP DATABASE IF EXISTS `' . self::TARGET_DATABASE . '`');
            $this->targetCreated = false;
        }

        parent::tearDown();
    }

    public function test_merchant_export_reconstructs_documented_data_in_an_isolated_database(): void
    {
        $this->assertSafeSource();
        config(['identity.use_accounts' => false]);
        $this->assertFalse(app(\App\Auth\IdentityResolver::class)->supportsAccount());
        $this->fixture = new DrillFixture();
        $this->fixture->create();

        Tenant::setCurrent(Tenant::findOrFail($this->fixture->tenantA));

        $customerIds = [$this->fixture->customerA, $this->fixture->customerWithoutOrders];
        $this->assertSame(2, DB::table('model_has_roles')->where('model_type', \App\Models\User::class)->whereIn('model_id', $customerIds)->count());
        $this->assertSame(2, \App\Models\User::where('tenant_id', $this->fixture->tenantA)->count());
        $this->assertSame(2, \App\Models\User::role('customer')->whereIn('users.id', $customerIds)->count());

        $service = app(MerchantDataExportService::class);
        $response = $service->downloadArchive(Tenant::findOrFail($this->fixture->tenantA));
        $this->archivePath = $response->getFile()->getPathname();

        $this->assertFileExists($this->archivePath);

        $this->fixture->cleanupAsset();
        $this->assertFileDoesNotExist($this->fixture->assetAbsolute);

        $this->createTargetDatabase();
        $targetTenantId = DB::connection('restore_drill')->table('tenants')->insertGetId([
            'name' => 'Restored ' . $this->fixture->marker,
            'slug' => 'restored-' . strtolower($this->fixture->marker),
            'email' => 'restored@example.test',
            'status' => 'active',
            'settings' => json_encode(['language' => 'en']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->importer = new ArchiveImporter(
            $this->archivePath,
            'restore_drill',
            config('exports.categories', [])
        );
        $result = $this->importer->import($targetTenantId);

        $this->assertArchiveIntegrity($result);
        $this->assertTargetCounts($targetTenantId);
        $this->assertTargetRelationships($targetTenantId, $result['placeholder_user_id']);
        $this->assertInventoryReconstruction($targetTenantId);
        $this->assertTenantIsolation($targetTenantId);
        $this->assertKnownLosses($targetTenantId, $result['placeholder_user_id']);
        $this->assertMediaIsNotRecovered();
    }

    private function assertSafeSource(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->assertSame('ecommerc_test', DB::connection()->getDatabaseName());
    }

    private function createTargetDatabase(): void
    {
        $exists = collect(DB::select('SHOW DATABASES'))
            ->map(fn ($row) => array_values((array) $row)[0])
            ->contains(self::TARGET_DATABASE);

        $this->assertFalse($exists, 'The fixed drill database already exists; refusing to overwrite it.');

        DB::statement('CREATE DATABASE `' . self::TARGET_DATABASE . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->targetCreated = true;

        $connection = config('database.connections.mysql');
        $connection['database'] = self::TARGET_DATABASE;
        config(['database.connections.restore_drill' => $connection]);
        DB::purge('restore_drill');

        $exitCode = Artisan::call('migrate', [
            '--database' => 'restore_drill',
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());
        $this->assertTrue(Schema::connection('restore_drill')->hasTable('orders'));
    }

    private function assertArchiveIntegrity(array $result): void
    {
        $manifest = $result['manifest'];
        $categories = array_keys(config('exports.categories', []));

        $this->assertCount(48, $categories);
        $this->assertSame(48, count($manifest['categories'] ?? []));
        $this->assertSame(48, count(array_filter($manifest['files'] ?? [], fn ($name) => str_ends_with($name, '.csv'), ARRAY_FILTER_USE_KEY)));
        $this->assertArrayHasKey('assets.json', $manifest['files']);
        $this->assertGreaterThanOrEqual(1, $manifest['asset_summary']['total'] ?? 0);
        $this->assertGreaterThanOrEqual(1, $manifest['asset_summary']['present'] ?? 0);

        foreach ($categories as $category) {
            $this->assertArrayHasKey($category, $manifest['categories']);
        }
    }

    private function assertTargetCounts(int $targetTenantId): void
    {
        $manifestCounts = $this->importer->manifest()['categories'];
        $conn = DB::connection('restore_drill');
        $customerCount = $conn->table('users')
            ->where('tenant_id', $targetTenantId)
            ->where('id', '!=', $this->importer->placeholderUserId())
            ->count();

        $this->assertSame((int) $manifestCounts['customers'], $customerCount);
        $this->assertSame(0, $conn->table('tenant_memberships')->count());
        $this->assertSame(0, $conn->table('customer_profiles')->count());
        $this->assertSame(0, $conn->table('accounts')->count());
        $this->assertSame((int) $manifestCounts['products'], $this->countTenant('products', $targetTenantId));
        $this->assertSame((int) $manifestCounts['product_variants'], $this->countTenant('product_variants', $targetTenantId));
        $this->assertSame((int) $manifestCounts['product_combos'], $this->countTenant('product_combos', $targetTenantId));
        $this->assertSame((int) $manifestCounts['inventory_movements'], $this->countTenant('stock_movements', $targetTenantId));
        $this->assertSame((int) $manifestCounts['orders'], $this->countTenant('orders', $targetTenantId));
        $this->assertSame((int) $manifestCounts['order_items'], $this->countTenant('order_items', $targetTenantId));
        $this->assertSame((int) $manifestCounts['order_coupons'], $this->countTenant('order_coupon', $targetTenantId));
        $this->assertSame((int) $manifestCounts['order_override_logs'], $this->countTenant('order_override_logs', $targetTenantId));
        $this->assertSame((int) $manifestCounts['customer_addresses'], $this->countTenant('customer_addresses', $targetTenantId));
        $this->assertSame((int) $manifestCounts['wishlists'], $this->countTenant('wishlists', $targetTenantId));
        $this->assertSame((int) $manifestCounts['categories'], $this->countTenant('categories', $targetTenantId));
        $this->assertSame((int) $manifestCounts['brands'], $this->countTenant('brands', $targetTenantId));
        $this->assertSame((int) $manifestCounts['units'], $this->countTenant('units', $targetTenantId));
        $this->assertSame((int) $manifestCounts['coupons'], $this->countTenant('coupons', $targetTenantId));
        $this->assertSame((int) $manifestCounts['coupon_products'], $this->countRows('coupon_product'));
        $this->assertSame((int) $manifestCounts['coupon_categories'], $this->countRows('coupon_category'));
        $this->assertSame((int) $manifestCounts['promotions'], $this->countTenant('promotions', $targetTenantId));
        $this->assertSame((int) $manifestCounts['promotion_products'], $this->countRows('promotion_product'));
        $this->assertSame((int) $manifestCounts['promotion_categories'], $this->countRows('promotion_category'));
        $this->assertSame((int) $manifestCounts['promotion_usages'], $this->countTenant('promotion_usages', $targetTenantId));
        $this->assertSame((int) $manifestCounts['promotion_banners'], $this->countTenant('promotion_banners', $targetTenantId));
        $this->assertSame((int) $manifestCounts['flash_sales'], $this->countTenant('flash_sales', $targetTenantId));
        $this->assertSame((int) $manifestCounts['flash_sale_products'], $this->countRows('flash_sale_products'));
        $this->assertSame((int) $manifestCounts['delivery_services'], $this->countTenant('delivery_services', $targetTenantId));
        $this->assertSame((int) $manifestCounts['delivery_pricing'], $this->countRows('delivery_pricing'));
        $this->assertSame((int) $manifestCounts['cod_rules'], $this->countTenant('cod_rules', $targetTenantId));
        $this->assertSame((int) $manifestCounts['packaging_options'], $this->countTenant('packaging_options', $targetTenantId));
        $this->assertSame((int) $manifestCounts['warehouses'], $this->countTenant('warehouses', $targetTenantId));
        $this->assertSame((int) $manifestCounts['payment_methods'], $this->countTenant('payment_methods', $targetTenantId));
        $this->assertSame((int) $manifestCounts['cities'], $this->countTenant('cities', $targetTenantId));
        $this->assertSame((int) $manifestCounts['townships'], $this->countTenant('townships', $targetTenantId));
        $this->assertSame((int) $manifestCounts['storefronts'], $this->countTenant('storefronts', $targetTenantId));
        $this->assertSame((int) $manifestCounts['storefront_contents'], $this->countTenant('storefront_contents', $targetTenantId));
        $this->assertSame((int) $manifestCounts['storefront_design_tokens'], $this->countTenant('storefront_design_tokens', $targetTenantId));
        $this->assertSame((int) $manifestCounts['storefront_homepage_sections'], $this->countTenant('storefront_homepage_sections', $targetTenantId));
        $this->assertSame((int) $manifestCounts['storefront_media'], $this->countTenant('storefront_media', $targetTenantId));
        $this->assertSame((int) $manifestCounts['storefront_navigations'], $this->countTenant('storefront_navigations', $targetTenantId));
        $this->assertSame((int) $manifestCounts['storefront_navigation_items'], $this->countTenant('storefront_navigation_items', $targetTenantId));
        $this->assertSame((int) $manifestCounts['storefront_revisions'], $this->countTenant('storefront_revisions', $targetTenantId));
        $this->assertSame((int) $manifestCounts['storefront_theme_configs'], $this->countTenant('storefront_theme_configs', $targetTenantId));
        $this->assertSame((int) $manifestCounts['storefront_checkout_configs'], $this->countTenant('storefront_checkout_configs', $targetTenantId));
        $this->assertSame((int) $manifestCounts['storefront_product_display_configs'], $this->countTenant('storefront_product_display_configs', $targetTenantId));
        $this->assertSame((int) $manifestCounts['website_faqs'], $this->countTenant('website_faqs', $targetTenantId));
        $this->assertSame(1, $this->countTenant('website_infos', $targetTenantId));
        $this->assertSame((int) $manifestCounts['settings'], $this->countTenant('settings', $targetTenantId));
        $this->assertSame((int) $manifestCounts['import_histories'], $this->countTenant('import_histories', $targetTenantId));
    }

    private function assertTargetRelationships(int $targetTenantId, int $placeholderUserId): void
    {
        $conn = DB::connection('restore_drill');

        $order = $conn->table('orders')->where('tenant_id', $targetTenantId)->first();
        $this->assertNotNull($order);
        $this->assertSame($placeholderUserId, (int) $order->user_id);
        $this->assertSame(\App\Models\User::class, $order->user_type);
        $this->assertNull($order->tenant_membership_id);
        $this->assertNotSame((int) $conn->table('users')->where('tenant_id', $targetTenantId)->where('id', '!=', $placeholderUserId)->value('id'), (int) $order->user_id);
        $this->assertNotNull($order->city_id);
        $this->assertNotNull($order->township_id);
        $this->assertNotNull($order->payment_method_id);
        $this->assertNotNull($order->delivery_service_id);
        $this->assertNotNull($order->packaging_id);
        $this->assertNotNull($order->promotion_id);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('confirmed', $order->order_status);
        $this->assertSame(53800.0, (float) $order->total_amount);

        $this->assertSame(2, $conn->table('order_items')->where('order_id', $order->id)->count());
        $this->assertSame(1, $conn->table('order_coupon')->where('order_id', $order->id)->count());

        $child = $conn->table('categories')->where('tenant_id', $targetTenantId)->whereNotNull('parent_id')->first();
        $this->assertNotNull($child);
        $this->assertTrue($conn->table('categories')->where('id', $child->parent_id)->exists());

        $product = $conn->table('products')->where('tenant_id', $targetTenantId)->where('type', 'single')->first();
        $this->assertNotNull($product);
        $this->assertNotNull($product->category_id);
        $this->assertNotNull($product->brand_id);
        $this->assertNotNull($product->unit_id);

        $variant = $conn->table('product_variants')->where('tenant_id', $targetTenantId)->first();
        $this->assertNotNull($variant);
        $this->assertTrue($conn->table('products')->where('id', $variant->product_id)->exists());

        $combo = $conn->table('product_combos')->where('tenant_id', $targetTenantId)->first();
        $this->assertNotNull($combo);
        $this->assertTrue($conn->table('products')->where('id', $combo->product_id)->exists());
        $this->assertTrue($conn->table('products')->where('id', $combo->combo_product_id)->exists());

        $storefront = $conn->table('storefronts')->where('tenant_id', $targetTenantId)->first();
        $this->assertNotNull($storefront);
        $this->assertNotNull($storefront->draft_revision_id);
        $this->assertNotNull($storefront->published_revision_id);
        $this->assertTrue($conn->table('storefront_revisions')->where('id', $storefront->published_revision_id)->exists());

        $address = $conn->table('customer_addresses')->where('tenant_id', $targetTenantId)->first();
        $this->assertNotNull($address);
        $this->assertNotNull($address->city_id);
        $this->assertNotNull($address->township_id);
        $this->assertNull($address->tenant_membership_id);

        $banner = $conn->table('promotion_banners')->where('tenant_id', $targetTenantId)->first();
        $this->assertNotNull($banner);
        $this->assertNotNull($banner->promotion_id);
        $this->assertNotNull($banner->storefront_media_id);

        $this->assertSame(
            'Drill Store ' . $this->fixture->marker,
            $conn->table('website_infos')->where('tenant_id', $targetTenantId)->value('site_name')
        );
        $this->assertSame(
            'SAFE-' . $this->fixture->marker,
            $conn->table('settings')->where('tenant_id', $targetTenantId)->where('key', 'restore_drill_safe_setting')->value('value')
        );
        $this->assertFalse(
            $conn->table('settings')->where('tenant_id', $targetTenantId)->where('key', 'smtp_password')->exists()
        );
    }

    private function assertInventoryReconstruction(int $targetTenantId): void
    {
        $levels = collect($this->importer->rows('inventory_levels'))
            ->map(fn (array $row) => array_map(fn ($value) => $value === '' ? null : $value, $row))
            ->values();
        $conn = DB::connection('restore_drill');

        foreach ($levels->whereNull('variant_id')->groupBy('product_id') as $productId => $rows) {
            $expected = (int) $rows->sum(fn ($row) => (int) $row['quantity']);
            $actual = (int) $conn->table('stock_movements')
                ->where('tenant_id', $targetTenantId)
                ->where('product_id', (int) $productId)
                ->whereNull('product_variant_id')
                ->sum('quantity');

            $this->assertSame($expected, $actual);
        }

        foreach ($levels->whereNotNull('variant_id')->groupBy('variant_id') as $variantId => $rows) {
            $expected = (int) $rows->sum(fn ($row) => (int) $row['quantity']);
            $actual = (int) $conn->table('stock_movements')
                ->where('tenant_id', $targetTenantId)
                ->where('product_variant_id', (int) $variantId)
                ->sum('quantity');

            $this->assertSame($expected, $actual);
        }
    }

    private function assertTenantIsolation(int $targetTenantId): void
    {
        $conn = DB::connection('restore_drill');

        $defaultTenantId = (int) $conn->table('tenants')->where('slug', 'default')->value('id');

        $tenantTables = [
            'users', 'tenant_memberships', 'categories', 'brands', 'units', 'cities', 'townships', 'warehouses',
            'delivery_services', 'payment_methods', 'packaging_options', 'products',
            'product_variants', 'product_combos', 'stock_movements', 'coupons', 'promotions',
            'promotion_usages', 'promotion_banners', 'flash_sales', 'cod_rules', 'orders',
            'order_items', 'order_coupon', 'order_override_logs', 'customer_addresses',
            'wishlists', 'storefronts', 'storefront_contents', 'storefront_design_tokens',
            'storefront_homepage_sections', 'storefront_media', 'storefront_navigations',
            'storefront_navigation_items', 'storefront_revisions', 'storefront_theme_configs',
            'storefront_checkout_configs', 'storefront_product_display_configs', 'website_faqs',
            'website_infos', 'settings', 'import_histories',
        ];

        foreach ($tenantTables as $table) {
            $foreign = $conn->table($table)
                ->where('tenant_id', '!=', $targetTenantId)
                ->where('tenant_id', '!=', $defaultTenantId)
                ->count();
            $this->assertSame(0, $foreign, 'Cross-tenant rows found in ' . $table);
        }

        $this->assertSame(0, $conn->table('customer_profiles')->count());
        $this->assertSame(0, $conn->table('accounts')->count());
        $this->assertSame(0, $conn->table('products')->where('name', 'like', '%' . $this->fixture->marker . '%')->where('tenant_id', '!=', $targetTenantId)->count());
        $this->assertSame(0, $conn->table('settings')->where('value', 'like', '%TENANT-B-%')->count());
        $this->assertTrue($conn->table('tenants')->where('id', $targetTenantId)->exists());
    }

    private function assertKnownLosses(int $targetTenantId, int $placeholderUserId): void
    {
        $conn = DB::connection('restore_drill');
        $order = $conn->table('orders')->where('tenant_id', $targetTenantId)->first();

        $this->assertSame($placeholderUserId, (int) $order->user_id);
        $this->assertNull($conn->table('order_coupon')->where('order_id', $order->id)->value('promotion_id'));
        $this->assertNull($order->idempotency_key);
        $this->assertNull($order->transaction_id);
        $this->assertNull($order->payment_screenshot);
        $this->assertNull($order->payment_proof);

        $item = $conn->table('order_items')->where('order_id', $order->id)->first();
        $this->assertNotNull($item);
        $this->assertSame($item->created_at, $item->updated_at);

        $this->assertSame(0, $conn->table('product_variants')->where('tenant_id', $targetTenantId)->whereNull('sku')->count());
        $this->assertSame(0, $conn->table('roles')->where('tenant_id', $targetTenantId)->count());
        $this->assertSame(0, $conn->table('model_has_roles')->count());
        $this->assertSame(0, $conn->table('storefronts')->where('tenant_id', $targetTenantId)->whereNotNull('theme_id')->count());
        $this->assertSame(0, $conn->table('users')->where('tenant_id', $targetTenantId)->whereNotNull('email_verified_at')->count());
        $this->assertSame(0, $conn->table('accounts')->count());
    }

    private function assertMediaIsNotRecovered(): void
    {
        $this->assertFalse(Storage::disk((string) config('filesystems.default', 'public'))->exists($this->fixture->assetPath));
        $this->assertFileDoesNotExist($this->fixture->assetAbsolute);

        $media = DB::connection('restore_drill')->table('storefront_media')->first();
        $this->assertNotNull($media);
        $this->assertSame($this->fixture->assetPath, $media->path);
    }

    private function countRows(string $table): int
    {
        return DB::connection('restore_drill')->table($table)->count();
    }

    private function countTenant(string $table, int $tenantId): int
    {
        return DB::connection('restore_drill')->table($table)->where('tenant_id', $tenantId)->count();
    }
}
