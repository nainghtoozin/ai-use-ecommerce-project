<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\CustomerProfile;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MerchantDataExportTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $adminA;
    private User $adminB;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'billing.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'billing.manage', 'guard_name' => 'web']);

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions(['billing.view']);

        $plan = Plan::firstOrCreate(
            ['slug' => 'export-test-plan'],
            ['name' => 'Export Test Plan', 'monthly_price' => 0, 'yearly_price' => 0, 'status' => 'active']
        );

        $this->tenantA = Tenant::create(['name' => 'Export Store A', 'slug' => 'export-store-a-' . uniqid()]);
        $this->tenantB = Tenant::create(['name' => 'Export Store B', 'slug' => 'export-store-b-' . uniqid()]);

        $this->tenantA->subscription_plan_id = $plan->id;
        $this->tenantA->save();

        $this->adminA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Export Admin A',
            'email' => 'export-a-' . uniqid() . '@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $this->adminA->assignRole($adminRole);
        $this->adminA->givePermissionTo('billing.view');

        $this->adminB = User::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Export Admin B',
            'email' => 'export-b-' . uniqid() . '@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $this->adminB->assignRole($adminRole);
        $this->adminB->givePermissionTo('billing.view');
    }

    protected function tearDown(): void
    {
        Tenant::setCurrent(null);
        parent::tearDown();
    }

    private function actAsA(): void
    {
        $this->actingAs($this->adminA);
        Tenant::setCurrent($this->tenantA);
    }

    private function makeProduct(Tenant $tenant, string $name): Product
    {
        Tenant::setCurrent($tenant);

        $category = Category::firstOrCreate([
            'tenant_id' => $tenant->id,
            'name' => 'Export Category ' . $tenant->id,
        ]);

        return Product::create([
            'name' => $name,
            'price' => 100,
            'stock' => 5,
            'status' => 'active',
            'type' => 'single',
            'category_id' => $category->id,
        ]);
    }

    public function test_index_page_renders(): void
    {
        $this->actAsA();

        $this->get('/admin/data-export')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/DataExport/Index')
                ->has('categoryGroups')
                ->has('formats'));
    }

    public function test_csv_export_is_tenant_scoped(): void
    {
        $this->makeProduct($this->tenantA, 'ALPHA-ONLY-PRODUCT');
        $this->makeProduct($this->tenantB, 'BETA-ONLY-PRODUCT');

        $this->actAsA();

        $response = $this->get('/admin/data-export/category/products?format=csv');
        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('ALPHA-ONLY-PRODUCT', $content);
        $this->assertStringNotContainsString('BETA-ONLY-PRODUCT', $content);
    }

    public function test_json_export_is_valid_and_scoped(): void
    {
        $this->makeProduct($this->tenantA, 'JSON-ALPHA-PRODUCT');
        $this->makeProduct($this->tenantB, 'JSON-BETA-PRODUCT');

        $this->actAsA();

        $response = $this->get('/admin/data-export/category/products?format=json');
        $response->assertOk();

        $decoded = json_decode($response->streamedContent(), true);
        $this->assertIsArray($decoded);
        $names = array_column($decoded, 'name');
        $this->assertContains('JSON-ALPHA-PRODUCT', $names);
        $this->assertNotContains('JSON-BETA-PRODUCT', $names);
    }

    public function test_xlsx_export_streams_a_spreadsheet(): void
    {
        $this->makeProduct($this->tenantA, 'XLSX-PRODUCT');

        $this->actAsA();

        $response = $this->get('/admin/data-export/category/products?format=xlsx');
        $response->assertOk();
        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('content-disposition'));
    }

    public function test_csv_export_neutralises_formula_injection(): void
    {
        $this->makeProduct($this->tenantA, '=SUM(1+1)');

        $this->actAsA();

        $content = $this->get('/admin/data-export/category/products?format=csv')->streamedContent();
        $this->assertStringContainsString("'=SUM(1+1)", $content);
    }

    public function test_full_archive_downloads_as_zip(): void
    {
        $this->makeProduct($this->tenantA, 'ARCHIVE-PRODUCT');

        $this->actAsA();

        $response = $this->get('/admin/data-export/all');
        $response->assertOk();
        $this->assertStringContainsString('.zip', (string) $response->headers->get('content-disposition'));
    }

    public function test_every_category_produces_rows_without_error(): void
    {
        $this->makeProduct($this->tenantA, 'ROW-PRODUCT');

        $this->actAsA();

        $service = app(\App\Services\Export\MerchantDataExportService::class);

        foreach (array_keys($service->categories()) as $category) {
            $this->assertNotEmpty($service->headingsFor($category), "Category {$category} has no headings");
            foreach ($service->rowsFor($category, $this->tenantA->id) as $ignored) {
                // Force the generator to run so query errors surface.
            }
        }

        $this->assertTrue(true);
    }

    public function test_storefront_prefix_serves_exports(): void
    {
        $this->makeProduct($this->tenantA, 'STOREFRONT-PRODUCT');

        $this->actAsA();

        $this->get("/store/{$this->tenantA->slug}/admin/data-export")->assertOk();

        $content = $this->get("/store/{$this->tenantA->slug}/admin/data-export/category/products?format=csv")
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('STOREFRONT-PRODUCT', $content);
    }

    public function test_unknown_category_returns_404(): void
    {
        $this->actAsA();

        $this->get('/admin/data-export/category/does-not-exist')->assertNotFound();
    }

    public function test_unsupported_format_returns_404(): void
    {
        $this->actAsA();

        $this->get('/admin/data-export/category/products?format=pdf')->assertNotFound();
    }

    public function test_export_requires_billing_view_permission(): void
    {
        $user = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'No Billing Permission',
            'email' => 'no-billing-' . uniqid() . '@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $user->givePermissionTo('billing.manage');

        $this->actingAs($user);
        Tenant::setCurrent($this->tenantA);

        $this->get('/admin/data-export')->assertForbidden();
    }

    public function test_routes_are_registered_on_both_prefixes_and_are_account_level(): void
    {
        $routes = app('router')->getRoutes();

        $this->assertNotNull($routes->getByName('admin.data-export.index'));
        $this->assertNotNull($routes->getByName('admin.data-export.all'));
        $this->assertNotNull($routes->getByName('admin.data-export.category'));
        $this->assertNotNull($routes->getByName('storefront.admin.data-export.index'));
        $this->assertNotNull($routes->getByName('storefront.admin.data-export.all'));
        $this->assertNotNull($routes->getByName('storefront.admin.data-export.category'));

        $middleware = $routes->getByName('admin.data-export.index')->gatherMiddleware();
        $this->assertNotContains('tenant.active', $middleware);
        $this->assertContains('tenant.access', $middleware);
    }

    public function test_merchant_owned_dataset_coverage_includes_expected_categories(): void
    {
        $categories = array_keys(app(\App\Services\Export\MerchantDataExportService::class)->categories());

        foreach ([
            'products', 'product_variants', 'product_combos', 'inventory_levels',
            'inventory_movements', 'orders', 'order_items', 'order_coupons',
            'order_override_logs', 'customers', 'customer_addresses', 'wishlists',
            'categories', 'brands', 'units', 'coupons', 'coupon_products',
            'coupon_categories', 'promotions', 'promotion_products',
            'promotion_categories', 'promotion_usages', 'promotion_banners',
            'flash_sales', 'flash_sale_products', 'delivery_services',
            'delivery_pricing', 'cod_rules', 'packaging_options', 'warehouses',
            'payment_methods', 'cities', 'townships', 'storefronts',
            'storefront_contents', 'storefront_design_tokens',
            'storefront_homepage_sections', 'storefront_media',
            'storefront_navigations', 'storefront_navigation_items',
            'storefront_revisions', 'storefront_theme_configs',
            'storefront_checkout_configs', 'storefront_product_display_configs',
            'website_faqs', 'store_settings', 'settings', 'import_histories',
        ] as $category) {
            $this->assertContains($category, $categories);
        }
    }

    public function test_customers_export_includes_customers_without_orders(): void
    {
        $customerRole = \App\Models\Role::firstOrCreate([
            'name' => 'customer',
            'guard_name' => 'web',
            'tenant_id' => $this->tenantA->id,
        ]);

        $account = Account::create([
            'name' => 'No Order Customer',
            'email' => 'no-order-' . uniqid() . '@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        $membership = TenantMembership::create([
            'account_id' => $account->id,
            'tenant_id' => $this->tenantA->id,
            'role_id' => $customerRole->id,
            'is_owner' => false,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        CustomerProfile::create([
            'tenant_membership_id' => $membership->id,
            'name' => 'NO-ORDER-CUSTOMER',
            'phone' => '09123456789',
        ]);

        $this->actAsA();

        $decoded = json_decode(
            $this->get('/admin/data-export/category/customers?format=json')->streamedContent(),
            true
        );

        $names = array_column($decoded, 'name');
        $this->assertContains('NO-ORDER-CUSTOMER', $names);
        $this->assertSame(0, $decoded[array_search('NO-ORDER-CUSTOMER', $names, true)]['orders_count']);
    }

    public function test_archive_asset_manifest_verifies_local_files(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/export-test.jpg', 'asset-bytes');

        $product = $this->makeProduct($this->tenantA, 'ASSET-MANIFEST-PRODUCT');
        $product->photo1 = 'products/export-test.jpg';
        $product->save();

        $this->actAsA();

        $response = $this->get('/admin/data-export/all')->assertOk();
        $path = $response->getFile()->getPathname();

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path) === true);

        $assetsJson = $zip->getFromName('assets.json');
        $assets = json_decode($assetsJson, true);
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $zip->close();

        $entry = collect($assets['assets'])->firstWhere('field', 'photo1');

        $this->assertNotNull($entry);
        $this->assertSame('present', $entry['status']);
        $this->assertSame(hash('sha256', 'asset-bytes'), $entry['sha256']);
        $this->assertSame(1, $manifest['schema_version']);
        $this->assertArrayHasKey('products.csv', $manifest['files']);
        $this->assertArrayHasKey('products', $manifest['categories']);
        $this->assertSame(hash('sha256', $assetsJson), $manifest['files']['assets.json']['sha256']);
        $this->assertSame(strlen($assetsJson), $manifest['files']['assets.json']['bytes']);
    }

    public function test_asset_manifest_marks_external_and_invalid_paths(): void
    {
        Storage::fake('public');

        $product = $this->makeProduct($this->tenantA, 'ASSET-STATUS-PRODUCT');
        $product->photo1 = 'https://cdn.example.com/product.jpg';
        $product->photo2 = '../outside.jpg';
        $product->save();

        $this->actAsA();

        $path = $this->get('/admin/data-export/all')->assertOk()->getFile()->getPathname();

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path) === true);
        $assets = json_decode($zip->getFromName('assets.json'), true);
        $zip->close();

        $entries = collect($assets['assets'])->where('id', $product->id);

        $this->assertSame('external', $entries->firstWhere('field', 'photo1')['status']);
        $this->assertSame('invalid', $entries->firstWhere('field', 'photo2')['status']);
    }

    public function test_archive_requests_use_unique_working_paths(): void
    {
        $this->actAsA();

        $first = $this->get('/admin/data-export/all')->assertOk()->getFile()->getPathname();
        $second = $this->get('/admin/data-export/all')->assertOk()->getFile()->getPathname();

        $this->assertNotSame($first, $second);
        $this->assertFileExists($first);
        $this->assertFileExists($second);

        @unlink($first);
        @unlink($second);
    }

    public function test_inventory_levels_match_stock_movement_sum(): void
    {
        $this->actAsA();

        $product = $this->makeProduct($this->tenantA, 'INVENTORY-PRODUCT');

        StockMovement::create([
            'product_id' => $product->id,
            'type' => StockMovement::TYPE_OPENING_STOCK,
            'quantity' => 10,
        ]);
        StockMovement::create([
            'product_id' => $product->id,
            'type' => StockMovement::TYPE_SALE,
            'quantity' => -3,
        ]);

        $rows = collect(
            app(\App\Services\Export\MerchantDataExportService::class)
                ->rowsFor('inventory_levels', $this->tenantA->id)
        );
        $row = $rows->firstWhere(0, $product->id);

        $this->assertNotNull($row);
        $this->assertSame(7.0, (float) $row[7]);
    }

    public function test_settings_export_excludes_sensitive_keys_and_other_tenants(): void
    {
        Tenant::setCurrent($this->tenantA);
        Setting::create(['key' => 'store_safe_setting', 'value' => 'SAFE-SETTING-VALUE']);
        Setting::create(['key' => 'smtp_password', 'value' => 'SECRET-SETTING-VALUE']);

        Tenant::setCurrent($this->tenantB);
        Setting::create(['key' => 'other_tenant_setting', 'value' => 'BETA-SETTING-VALUE']);

        $this->actAsA();

        $content = $this->get('/admin/data-export/category/settings?format=json')
            ->assertOk()
            ->streamedContent();

        $keys = array_column(json_decode($content, true), 'key');

        $this->assertContains('store_safe_setting', $keys);
        $this->assertNotContains('smtp_password', $keys);
        $this->assertStringNotContainsString('SECRET-SETTING-VALUE', $content);
        $this->assertStringNotContainsString('BETA-SETTING-VALUE', $content);
    }

    public function test_archive_cleanup_removes_stale_files(): void
    {
        $this->actAsA();

        $dir = Storage::disk('local')->path('exports/' . $this->tenantA->id);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $stale = $dir . DIRECTORY_SEPARATOR . 'stale.zip';
        file_put_contents($stale, 'old');
        touch($stale, time() - (72 * 3600));

        app(\App\Services\Export\MerchantDataExportService::class)->cleanupExpired($this->tenantA->id);

        $this->assertFileDoesNotExist($stale);
    }
}
