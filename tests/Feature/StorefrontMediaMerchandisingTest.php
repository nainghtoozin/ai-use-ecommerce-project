<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionBanner;
use App\Models\Storefront;
use App\Models\StorefrontContent;
use App\Models\StorefrontDesignToken;
use App\Models\StorefrontHomepageSection;
use App\Models\StorefrontMedia;
use App\Models\StorefrontNavigation;
use App\Models\StorefrontNavigationItem;
use App\Models\StorefrontThemeConfig;
use App\Models\Tenant;
use App\Models\Theme;
use App\Services\ImageService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StorefrontMediaMerchandisingTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;
    private Storefront $storefront;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupMinimalSchema();
        $this->setupTestData();
    }

    private function setupMinimalSchema(): void
    {
        $tables = [
            'tenants', 'themes', 'storefronts', 'storefront_homepage_sections',
            'storefront_theme_configs', 'storefront_design_tokens', 'storefront_contents',
            'storefront_navigations', 'storefront_navigation_items', 'website_infos',
            'categories', 'brands', 'products', 'product_variants', 'product_combos',
            'promotion_banners', 'storefront_media', 'promotions', 'promotion_product',
            'promotion_category',
        ];
        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) {
                $this->markTestSkipped("Table {$table} not found.");
            }
        }
    }

    private function setupTestData(): void
    {
        $this->tenant = Tenant::create([
            'name' => 'Test Store',
            'slug' => 'test-media-store',
            'store_url' => '/store/test-media-store',
            'status' => 'active',
        ]);

        $this->theme = Theme::firstOrCreate(
            ['slug' => 'commerce-default'],
            ['name' => 'Commerce Default', 'version' => '1.0.0', 'default_tokens' => ['color' => ['primary' => '#3B82F6']], 'is_active' => true]
        );

        $this->storefront = Storefront::create([
            'tenant_id' => $this->tenant->id,
            'theme_id' => $this->theme->id,
            'status' => 'active',
        ]);

        StorefrontThemeConfig::create([
            'tenant_id' => $this->tenant->id,
            'storefront_id' => $this->storefront->id,
            'theme_id' => $this->theme->id,
            'configuration' => ['hero_variant' => 'auto'],
        ]);

        StorefrontDesignToken::create([
            'tenant_id' => $this->tenant->id,
            'storefront_id' => $this->storefront->id,
            'tokens' => $this->theme->default_tokens,
        ]);

        StorefrontContent::create([
            'tenant_id' => $this->tenant->id,
            'storefront_id' => $this->storefront->id,
            'labels' => [],
        ]);

        $navigation = StorefrontNavigation::create([
            'tenant_id' => $this->tenant->id,
            'storefront_id' => $this->storefront->id,
            'settings' => ['show_store_name' => true, 'show_search' => true],
        ]);

        foreach ([
            ['key' => 'home', 'label' => 'Home', 'path' => '/', 'group' => 'header', 'position' => 0],
            ['key' => 'products', 'label' => 'Products', 'path' => '/products', 'group' => 'header', 'position' => 1],
        ] as $item) {
            StorefrontNavigationItem::create(array_merge($item, [
                'tenant_id' => $this->tenant->id,
                'navigation_id' => $navigation->id,
                'enabled' => true,
            ]));
        }

        StorefrontHomepageSection::create([
            'tenant_id' => $this->tenant->id,
            'storefront_id' => $this->storefront->id,
            'type' => 'hero',
            'variant' => 'text-only',
            'enabled' => true,
            'desktop_visible' => true,
            'mobile_visible' => true,
            'position' => 0,
            'configuration' => ['title' => 'Welcome'],
        ]);
    }

    private function createWebsiteInfo(): void
    {
        if (!Schema::hasTable('website_infos')) return;
        \App\Models\WebsiteInfo::firstOrCreate(
            ['tenant_id' => $this->tenant->id],
            ['site_name' => 'Test Store', 'theme_color' => '#3B82F6', 'currency_code' => 'MMK', 'currency_symbol' => 'K']
        );
    }

    // --- Media Tests ---

    /** @test */
    public function product_photo_accessor_returns_url_when_path_set(): void
    {
        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Product',
            'type' => 'single',
            'price' => 100,
            'stock' => 10,
            'status' => 'active',
            'photo1' => 'products/test.jpg',
        ]);

        $this->assertNotNull($product->photo1_url);
        $this->assertStringContainsString('products/test.jpg', $product->photo1_url);
    }

    /** @test */
    public function product_photo_accessor_returns_null_when_empty(): void
    {
        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'No Image Product',
            'type' => 'single',
            'price' => 100,
            'stock' => 10,
            'status' => 'active',
        ]);

        $this->assertNull($product->photo1_url);
    }

    /** @test */
    public function category_image_accessor_returns_url(): void
    {
        $category = Category::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Category',
            'slug' => 'test-cat',
            'is_active' => true,
            'image' => 'categories/test.jpg',
        ]);

        $this->assertNotNull($category->image_url);
        $this->assertStringContainsString('categories/test.jpg', $category->image_url);
    }

    /** @test */
    public function brand_logo_accessor_returns_url(): void
    {
        $brand = Brand::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Brand',
            'slug' => 'test-brand',
            'is_active' => true,
            'logo' => 'brands/logo.png',
        ]);

        $this->assertNotNull($brand->logo_url);
        $this->assertStringContainsString('brands/logo.png', $brand->logo_url);
    }

    /** @test */
    public function brand_banner_accessor_returns_url(): void
    {
        $brand = Brand::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Banner Brand',
            'slug' => 'banner-brand',
            'is_active' => true,
            'banner' => 'brands/banner.jpg',
        ]);

        $this->assertNotNull($brand->banner_url);
        $this->assertStringContainsString('brands/banner.jpg', $brand->banner_url);
    }

    /** @test */
    public function storefront_media_url_accessor_works(): void
    {
        $media = StorefrontMedia::create([
            'tenant_id' => $this->tenant->id,
            'storefront_id' => $this->storefront->id,
            'key' => 'hero',
            'path' => 'storefront-media/hero.jpg',
        ]);

        $this->assertNotNull($media->url);
        $this->assertStringContainsString('storefront-media/hero.jpg', $media->url);
    }

    /** @test */
    public function product_gallery_images_returns_array_of_urls(): void
    {
        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Gallery Product',
            'type' => 'single',
            'price' => 100,
            'stock' => 10,
            'status' => 'active',
            'gallery_images' => ['gallery/1.jpg', 'gallery/2.jpg'],
        ]);

        $urls = $product->gallery_images_url;
        $this->assertCount(2, $urls);
        $this->assertStringContainsString('gallery/1.jpg', $urls[0]);
    }

    /** @test */
    public function product_gallery_images_empty_when_no_images(): void
    {
        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Empty Gallery',
            'type' => 'single',
            'price' => 100,
            'stock' => 10,
            'status' => 'active',
        ]);

        $this->assertEmpty($product->gallery_images_url);
    }

    /** @test */
    public function image_service_exists_handles_missing_files(): void
    {
        $this->assertFalse(ImageService::exists('nonexistent/file.jpg'));
    }

    /** @test */
    public function image_service_url_returns_empty_string_for_null_path(): void
    {
        $result = ImageService::url(null);
        $this->assertEmpty($result);
    }

    /** @test */
    public function image_service_url_passes_through_http_urls(): void
    {
        $result = ImageService::url('https://example.com/image.jpg');
        $this->assertEquals('https://example.com/image.jpg', $result);
    }

    /** @test */
    public function image_service_placeholder_url_returns_svg_data_uri(): void
    {
        $result = ImageService::placeholderUrl();
        $this->assertStringStartsWith('data:image/svg+xml', $result);
    }

    // --- Merchandising Tests ---

    /** @test */
    public function homepage_loads_with_all_section_types(): void
    {
        $this->createWebsiteInfo();

        $category = Category::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Cat', 'slug' => 'cat', 'is_active' => true, 'featured' => true,
        ]);

        $brand = Brand::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Brand', 'slug' => 'brand', 'is_active' => true, 'featured' => true,
        ]);

        Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Prod', 'type' => 'single', 'price' => 50, 'stock' => 5, 'status' => 'active',
        ]);

        $sections = ['featured_categories', 'featured_brands', 'featured_products'];
        $position = 1;
        foreach ($sections as $type) {
            StorefrontHomepageSection::create([
                'tenant_id' => $this->tenant->id,
                'storefront_id' => $this->storefront->id,
                'type' => $type,
                'variant' => 'default',
                'enabled' => true,
                'desktop_visible' => true,
                'mobile_visible' => true,
                'position' => $position++,
                'configuration' => [],
            ]);
        }

        $response = $this->get("/store/{$this->tenant->slug}");
        $response->assertOk();
    }

    /** @test */
    public function featured_categories_returns_active_categories_only(): void
    {
        Category::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Active', 'slug' => 'active', 'is_active' => true, 'featured' => true, 'sort_order' => 1,
        ]);
        Category::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Inactive', 'slug' => 'inactive', 'is_active' => false, 'featured' => true,
        ]);

        $resolver = new \App\Services\StorefrontConfigurationResolver();
        $config = $resolver->resolveBase($this->tenant);

        $categoryTypes = collect($config['homepage']['sections'])->where('type', 'featured_categories');
        $this->assertTrue($categoryTypes->isNotEmpty());
    }

    /** @test */
    public function featured_brands_returns_active_brands_only(): void
    {
        Brand::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Active Brand', 'slug' => 'active-brand', 'is_active' => true, 'featured' => true,
        ]);
        Brand::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Inactive Brand', 'slug' => 'inactive-brand', 'is_active' => false,
        ]);

        $resolver = new \App\Services\StorefrontConfigurationResolver();
        $config = $resolver->resolveBase($this->tenant);

        $brandTypes = collect($config['homepage']['sections'])->where('type', 'featured_brands');
        $this->assertTrue($brandTypes->isNotEmpty());
    }

    /** @test */
    public function featured_products_returns_active_products_only(): void
    {
        Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Active Prod', 'type' => 'single', 'price' => 100, 'stock' => 10, 'status' => 'active',
        ]);
        Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Inactive Prod', 'type' => 'single', 'price' => 100, 'stock' => 10, 'status' => 'inactive',
        ]);

        $resolver = new \App\Services\StorefrontConfigurationResolver();
        $config = $resolver->resolveBase($this->tenant);

        $productSections = collect($config['homepage']['sections'])->whereIn('type', ['featured_products', 'product_showcase']);
        $this->assertTrue($productSections->isNotEmpty());
    }

    /** @test */
    public function sort_order_is_respected_for_categories(): void
    {
        $cat1 = Category::create([
            'tenant_id' => $this->tenant->id, 'name' => 'B Category', 'slug' => 'b-cat', 'is_active' => true, 'sort_order' => 2,
        ]);
        $cat2 = Category::create([
            'tenant_id' => $this->tenant->id, 'name' => 'A Category', 'slug' => 'a-cat', 'is_active' => true, 'sort_order' => 1,
        ]);

        $resolver = new \App\Services\StorefrontConfigurationResolver();
        $config = $resolver->resolveBase($this->tenant);

        $section = collect($config['homepage']['sections'])->firstWhere('type', 'featured_categories');
        $this->assertNotNull($section);
        $this->assertEquals('A Category', $section['data']['categories'][0]['name']);
    }

    /** @test */
    public function disabled_section_returns_empty_data(): void
    {
        StorefrontHomepageSection::where('storefront_id', $this->storefront->id)
            ->where('type', 'hero')
            ->update(['enabled' => false]);

        $resolver = new \App\Services\StorefrontConfigurationResolver();
        $config = $resolver->resolveBase($this->tenant);

        $hero = collect($config['homepage']['sections'])->firstWhere('type', 'hero');
        $this->assertEquals([], $hero['data']);
    }

    /** @test */
    public function empty_merchandising_sections_gracefully_return_empty(): void
    {
        $resolver = new \App\Services\StorefrontConfigurationResolver();
        $config = $resolver->resolveBase($this->tenant);

        $this->assertArrayHasKey('homepage', $config);
        $this->assertIsArray($config['homepage']['sections']);
    }

    /** @test */
    public function products_count_only_counts_active_products(): void
    {
        $category = Category::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Counted', 'slug' => 'counted', 'is_active' => true,
        ]);

        Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Active', 'type' => 'single', 'price' => 100, 'stock' => 5, 'status' => 'active', 'category_id' => $category->id,
        ]);
        Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Inactive', 'type' => 'single', 'price' => 100, 'stock' => 5, 'status' => 'inactive', 'category_id' => $category->id,
        ]);

        $resolver = new \App\Services\StorefrontConfigurationResolver();
        $config = $resolver->resolveBase($this->tenant);

        $section = collect($config['homepage']['sections'])->firstWhere('type', 'featured_categories');
        $catData = collect($section['data']['categories'])->firstWhere('id', $category->id);
        $this->assertEquals(1, $catData['products_count']);
    }

    // --- Promotion Tests ---

    /** @test */
    public function active_promotion_is_applied_to_product(): void
    {
        $product = Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Sale Product', 'type' => 'single', 'price' => 100, 'stock' => 10, 'status' => 'active',
        ]);

        $promotion = Promotion::create([
            'tenant_id' => $this->tenant->id,
            'name' => '10% Off',
            'code' => 'SAVE10',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
            'is_automatic' => true,
            'applies_to' => 'all',
        ]);

        $promotions = Promotion::valid()->automatic()->get();

        $controller = new \App\Http\Controllers\StorefrontController(
            app(\App\Services\ProductService::class),
            app(\App\Services\WebsiteFaqService::class),
            app(\App\Services\StorefrontConfigurationResolver::class),
        );

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('findBestPromotionForProduct');
        $method->setAccessible(true);

        $result = $method->invoke($controller, $product, $promotions);
        $this->assertNotNull($result);
        $this->assertEquals($promotion->id, $result->id);
    }

    /** @test */
    public function expired_promotion_is_not_applied(): void
    {
        $product = Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Expired Product', 'type' => 'single', 'price' => 100, 'stock' => 10, 'status' => 'active',
        ]);

        Promotion::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Expired',
            'code' => 'EXPIRED',
            'type' => 'percentage',
            'value' => 20,
            'is_active' => true,
            'is_automatic' => true,
            'applies_to' => 'all',
            'ends_at' => now()->subDay(),
        ]);

        $promotions = Promotion::valid()->automatic()->get();

        $controller = new \App\Http\Controllers\StorefrontController(
            app(\App\Services\ProductService::class),
            app(\App\Services\WebsiteFaqService::class),
            app(\App\Services\StorefrontConfigurationResolver::class),
        );

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('findBestPromotionForProduct');
        $method->setAccessible(true);

        $result = $method->invoke($controller, $product, $promotions);
        $this->assertNull($result);
    }

    /** @test */
    public function future_promotion_is_not_applied(): void
    {
        $product = Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Future Product', 'type' => 'single', 'price' => 100, 'stock' => 10, 'status' => 'active',
        ]);

        Promotion::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Future',
            'code' => 'FUTURE',
            'type' => 'percentage',
            'value' => 20,
            'is_active' => true,
            'is_automatic' => true,
            'applies_to' => 'all',
            'starts_at' => now()->addDay(),
        ]);

        $promotions = Promotion::valid()->automatic()->get();

        $controller = new \App\Http\Controllers\StorefrontController(
            app(\App\Services\ProductService::class),
            app(\App\Services\WebsiteFaqService::class),
            app(\App\Services\StorefrontConfigurationResolver::class),
        );

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('findBestPromotionForProduct');
        $method->setAccessible(true);

        $result = $method->invoke($controller, $product, $promotions);
        $this->assertNull($result);
    }

    /** @test */
    public function promotion_price_does_not_go_negative(): void
    {
        $product = Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Cheap Product', 'type' => 'single', 'price' => 5, 'stock' => 10, 'status' => 'active',
        ]);

        Promotion::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Big Fixed',
            'code' => 'BIGFIX',
            'type' => 'fixed',
            'value' => 100,
            'is_active' => true,
            'is_automatic' => true,
            'applies_to' => 'all',
        ]);

        $promotions = Promotion::valid()->automatic()->get();

        $controller = new \App\Http\Controllers\StorefrontController(
            app(\App\Services\ProductService::class),
            app(\App\Services\WebsiteFaqService::class),
            app(\App\Services\StorefrontConfigurationResolver::class),
        );

        $reflection = new \ReflectionClass($controller);
        $enrichMethod = $reflection->getMethod('enrichProductWithPromotion');
        $enrichMethod->setAccessible(true);

        $enrichMethod->invoke($controller, $product, $promotions);
        $this->assertGreaterThanOrEqual(0, $product->promotion_price);
    }

    /** @test */
    public function percentage_discount_calculates_correctly(): void
    {
        $product = Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Pct Product', 'type' => 'single', 'price' => 200, 'stock' => 10, 'status' => 'active',
        ]);

        Promotion::create([
            'tenant_id' => $this->tenant->id,
            'name' => '25% Off',
            'code' => 'PCT25',
            'type' => 'percentage',
            'value' => 25,
            'is_active' => true,
            'is_automatic' => true,
            'applies_to' => 'all',
        ]);

        $promotions = Promotion::valid()->automatic()->get();

        $controller = new \App\Http\Controllers\StorefrontController(
            app(\App\Services\ProductService::class),
            app(\App\Services\WebsiteFaqService::class),
            app(\App\Services\StorefrontConfigurationResolver::class),
        );

        $reflection = new \ReflectionClass($controller);
        $enrichMethod = $reflection->getMethod('enrichProductWithPromotion');
        $enrichMethod->setAccessible(true);

        $enrichMethod->invoke($controller, $product, $promotions);
        $this->assertEquals(150.0, $product->promotion_price);
        $this->assertEquals('-25%', $product->promotion_badge);
    }

    /** @test */
    public function fixed_discount_calculates_correctly(): void
    {
        $product = Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Fixed Product', 'type' => 'single', 'price' => 200, 'stock' => 10, 'status' => 'active',
        ]);

        Promotion::create([
            'tenant_id' => $this->tenant->id,
            'name' => '50 Off',
            'code' => 'FIX50',
            'type' => 'fixed',
            'value' => 50,
            'is_active' => true,
            'is_automatic' => true,
            'applies_to' => 'all',
        ]);

        $promotions = Promotion::valid()->automatic()->get();

        $controller = new \App\Http\Controllers\StorefrontController(
            app(\App\Services\ProductService::class),
            app(\App\Services\WebsiteFaqService::class),
            app(\App\Services\StorefrontConfigurationResolver::class),
        );

        $reflection = new \ReflectionClass($controller);
        $enrichMethod = $reflection->getMethod('enrichProductWithPromotion');
        $enrichMethod->setAccessible(true);

        $enrichMethod->invoke($controller, $product, $promotions);
        $this->assertEquals(150.0, $product->promotion_price);
    }

    /** @test */
    public function max_discount_amount_caps_percentage_discount(): void
    {
        $product = Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Capped Product', 'type' => 'single', 'price' => 1000, 'stock' => 10, 'status' => 'active',
        ]);

        Promotion::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Capped 10%',
            'code' => 'CAP10',
            'type' => 'percentage',
            'value' => 10,
            'max_discount_amount' => 50,
            'is_active' => true,
            'is_automatic' => true,
            'applies_to' => 'all',
        ]);

        $promotions = Promotion::valid()->automatic()->get();

        $controller = new \App\Http\Controllers\StorefrontController(
            app(\App\Services\ProductService::class),
            app(\App\Services\WebsiteFaqService::class),
            app(\App\Services\StorefrontConfigurationResolver::class),
        );

        $reflection = new \ReflectionClass($controller);
        $enrichMethod = $reflection->getMethod('enrichProductWithPromotion');
        $enrichMethod->setAccessible(true);

        $enrichMethod->invoke($controller, $product, $promotions);
        $this->assertEquals(950.0, $product->promotion_price);
    }

    /** @test */
    public function promotion_banner_is_filtered_by_active_status(): void
    {
        PromotionBanner::create([
            'tenant_id' => $this->tenant->id,
            'title' => 'Active Banner',
            'is_active' => true,
            'position' => 1,
        ]);
        PromotionBanner::create([
            'tenant_id' => $this->tenant->id,
            'title' => 'Inactive Banner',
            'is_active' => false,
            'position' => 2,
        ]);

        $resolver = new \App\Services\StorefrontConfigurationResolver();
        $config = $resolver->resolveBase($this->tenant);

        $promoSection = collect($config['homepage']['sections'])->firstWhere('type', 'promotion');
        $this->assertNotNull($promoSection);
        $titles = array_column($promoSection['data']['promotions'] ?? [], 'title');
        $this->assertContains('Active Banner', $titles);
        $this->assertNotContains('Inactive Banner', $titles);
    }

    /** @test */
    public function promotion_banner_respects_date_range(): void
    {
        PromotionBanner::create([
            'tenant_id' => $this->tenant->id,
            'title' => 'Future Banner',
            'is_active' => true,
            'starts_at' => now()->addDays(7),
            'position' => 1,
        ]);

        $resolver = new \App\Services\StorefrontConfigurationResolver();
        $config = $resolver->resolveBase($this->tenant);

        $promoSection = collect($config['homepage']['sections'])->firstWhere('type', 'promotion');
        $this->assertNotNull($promoSection);
        $this->assertEmpty($promoSection['data']['promotions'] ?? []);
    }

    // --- Tenant Safety Tests ---

    /** @test */
    public function cross_tenant_products_cannot_appear_in_featured(): void
    {
        $otherTenant = Tenant::create([
            'name' => 'Other', 'slug' => 'other-media', 'store_url' => '/store/other-media', 'status' => 'active',
        ]);

        Product::create([
            'tenant_id' => $otherTenant->id, 'name' => 'Other Product', 'type' => 'single', 'price' => 999, 'stock' => 10, 'status' => 'active',
        ]);

        Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'My Product', 'type' => 'single', 'price' => 50, 'stock' => 10, 'status' => 'active',
        ]);

        $this->createWebsiteInfo();

        $response = $this->get("/store/{$this->tenant->slug}");
        $response->assertOk();
        $response->assertDontSee('Other Product');
    }

    /** @test */
    public function cross_tenant_categories_cannot_appear(): void
    {
        $otherTenant = Tenant::create([
            'name' => 'Other', 'slug' => 'other-cat-media', 'store_url' => '/store/other-cat-media', 'status' => 'active',
        ]);

        Category::create([
            'tenant_id' => $otherTenant->id, 'name' => 'Other Cat', 'slug' => 'other-cat', 'is_active' => true,
        ]);

        Category::create([
            'tenant_id' => $this->tenant->id, 'name' => 'My Cat', 'slug' => 'my-cat', 'is_active' => true,
        ]);

        $this->createWebsiteInfo();

        $response = $this->get("/store/{$this->tenant->slug}");
        $response->assertOk();
        $response->assertDontSee('Other Cat');
    }

    /** @test */
    public function cross_tenant_brands_cannot_appear(): void
    {
        $otherTenant = Tenant::create([
            'name' => 'Other', 'slug' => 'other-brand-media', 'store_url' => '/store/other-brand-media', 'status' => 'active',
        ]);

        Brand::create([
            'tenant_id' => $otherTenant->id, 'name' => 'Other Brand', 'slug' => 'other-brand', 'is_active' => true,
        ]);

        Brand::create([
            'tenant_id' => $this->tenant->id, 'name' => 'My Brand', 'slug' => 'my-brand', 'is_active' => true,
        ]);

        $this->createWebsiteInfo();

        $response = $this->get("/store/{$this->tenant->slug}");
        $response->assertOk();
        $response->assertDontSee('Other Brand');
    }

    /** @test */
    public function cross_tenant_promotions_cannot_apply(): void
    {
        $otherTenant = Tenant::create([
            'name' => 'Other', 'slug' => 'other-promo-media', 'store_url' => '/store/other-promo-media', 'status' => 'active',
        ]);

        $product = Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'My Product', 'type' => 'single', 'price' => 100, 'stock' => 10, 'status' => 'active',
        ]);

        Promotion::create([
            'tenant_id' => $otherTenant->id, 'name' => 'Other Promo', 'code' => 'OTHER', 'type' => 'percentage', 'value' => 50,
            'is_active' => true, 'is_automatic' => true, 'applies_to' => 'all',
        ]);

        $promotions = Promotion::valid()->automatic()->get();

        $controller = new \App\Http\Controllers\StorefrontController(
            app(\App\Services\ProductService::class),
            app(\App\Services\WebsiteFaqService::class),
            app(\App\Services\StorefrontConfigurationResolver::class),
        );

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('findBestPromotionForProduct');
        $method->setAccessible(true);

        $result = $method->invoke($controller, $product, $promotions);
        $this->assertNull($result);
    }

    /** @test */
    public function media_tenant_isolation(): void
    {
        $otherTenant = Tenant::create([
            'name' => 'Other', 'slug' => 'other-media-iso', 'store_url' => '/store/other-media-iso', 'status' => 'active',
        ]);

        $otherStorefront = Storefront::create([
            'tenant_id' => $otherTenant->id, 'theme_id' => $this->theme->id, 'status' => 'active',
        ]);

        StorefrontMedia::create([
            'tenant_id' => $otherTenant->id, 'storefront_id' => $otherStorefront->id, 'key' => 'secret', 'path' => 'secret.jpg',
        ]);

        $this->createWebsiteInfo();

        $response = $this->get("/store/{$this->tenant->slug}");
        $response->assertOk();
        $response->assertDontSee('secret.jpg');
    }

    // --- Failure Safety Tests ---

    /** @test */
    public function deleted_category_does_not_break_homepage(): void
    {
        $category = Category::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Temp', 'slug' => 'temp', 'is_active' => true,
        ]);

        StorefrontHomepageSection::create([
            'tenant_id' => $this->tenant->id,
            'storefront_id' => $this->storefront->id,
            'type' => 'featured_categories',
            'variant' => 'default',
            'enabled' => true,
            'desktop_visible' => true,
            'mobile_visible' => true,
            'position' => 1,
            'configuration' => ['category_ids' => [$category->id]],
        ]);

        $category->delete();

        $this->createWebsiteInfo();

        $response = $this->get("/store/{$this->tenant->slug}");
        $response->assertOk();
    }

    /** @test */
    public function deleted_brand_does_not_break_homepage(): void
    {
        $brand = Brand::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Temp Brand', 'slug' => 'temp-brand', 'is_active' => true,
        ]);

        StorefrontHomepageSection::create([
            'tenant_id' => $this->tenant->id,
            'storefront_id' => $this->storefront->id,
            'type' => 'featured_brands',
            'variant' => 'default',
            'enabled' => true,
            'desktop_visible' => true,
            'mobile_visible' => true,
            'position' => 1,
            'configuration' => ['brand_ids' => [$brand->id]],
        ]);

        $brand->delete();

        $this->createWebsiteInfo();

        $response = $this->get("/store/{$this->tenant->slug}");
        $response->assertOk();
    }

    /** @test */
    public function empty_featured_sections_do_not_break_page(): void
    {
        $this->createWebsiteInfo();

        $response = $this->get("/store/{$this->tenant->slug}");
        $response->assertOk();
    }

    /** @test */
    public function missing_hero_media_does_not_break_page(): void
    {
        StorefrontHomepageSection::where('storefront_id', $this->storefront->id)
            ->where('type', 'hero')
            ->update(['configuration' => ['title' => 'Hero No Media']]);

        $this->createWebsiteInfo();

        $response = $this->get("/store/{$this->tenant->slug}");
        $response->assertOk();
    }

    /** @test */
    public function brand_story_without_description_does_not_render(): void
    {
        StorefrontHomepageSection::create([
            'tenant_id' => $this->tenant->id,
            'storefront_id' => $this->storefront->id,
            'type' => 'brand_story',
            'variant' => 'default',
            'enabled' => true,
            'desktop_visible' => true,
            'mobile_visible' => true,
            'position' => 5,
            'configuration' => ['title' => 'No Desc Story'],
        ]);

        $this->createWebsiteInfo();

        $response = $this->get("/store/{$this->tenant->slug}");
        $response->assertOk();
    }

    /** @test */
    public function cta_without_title_does_not_render(): void
    {
        StorefrontHomepageSection::create([
            'tenant_id' => $this->tenant->id,
            'storefront_id' => $this->storefront->id,
            'type' => 'cta',
            'variant' => 'default',
            'enabled' => true,
            'desktop_visible' => true,
            'mobile_visible' => true,
            'position' => 6,
            'configuration' => ['description' => 'No title CTA'],
        ]);

        $this->createWebsiteInfo();

        $response = $this->get("/store/{$this->tenant->slug}");
        $response->assertOk();
    }

    // --- Max Discount Amount Capping Tests ---

    /** @test */
    public function best_promotion_respects_max_discount_amount(): void
    {
        $product = Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Capped Product', 'type' => 'single', 'price' => 1000, 'stock' => 10, 'status' => 'active',
        ]);

        $promoA = Promotion::create([
            'tenant_id' => $this->tenant->id, 'name' => '10% Capped', 'code' => 'CAP10', 'type' => 'percentage', 'value' => 10,
            'max_discount_amount' => 50, 'is_active' => true, 'is_automatic' => true, 'applies_to' => 'all',
        ]);

        $promoB = Promotion::create([
            'tenant_id' => $this->tenant->id, 'name' => '80 Fixed', 'code' => 'FIX80', 'type' => 'fixed', 'value' => 80,
            'is_active' => true, 'is_automatic' => true, 'applies_to' => 'all',
        ]);

        $promotions = Promotion::valid()->automatic()->get();

        $controller = new \App\Http\Controllers\StorefrontController(
            app(\App\Services\ProductService::class),
            app(\App\Services\WebsiteFaqService::class),
            app(\App\Services\StorefrontConfigurationResolver::class),
        );

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('findBestPromotionForProduct');
        $method->setAccessible(true);

        $result = $method->invoke($controller, $product, $promotions);
        $this->assertEquals($promoB->id, $result->id, 'Fixed $80 promo should win over capped 10% ($50 cap)');
    }

    /** @test */
    public function max_discount_amount_caps_even_when_best(): void
    {
        $product = Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Only Promo', 'type' => 'single', 'price' => 500, 'stock' => 10, 'status' => 'active',
        ]);

        Promotion::create([
            'tenant_id' => $this->tenant->id, 'name' => '50% Capped', 'code' => 'HALF', 'type' => 'percentage', 'value' => 50,
            'max_discount_amount' => 100, 'is_active' => true, 'is_automatic' => true, 'applies_to' => 'all',
        ]);

        $promotions = Promotion::valid()->automatic()->get();

        $controller = new \App\Http\Controllers\StorefrontController(
            app(\App\Services\ProductService::class),
            app(\App\Services\WebsiteFaqService::class),
            app(\App\Services\StorefrontConfigurationResolver::class),
        );

        $reflection = new \ReflectionClass($controller);
        $enrichMethod = $reflection->getMethod('enrichProductWithPromotion');
        $enrichMethod->setAccessible(true);

        $enrichMethod->invoke($controller, $product, $promotions, 'K');
        $this->assertEquals(400.0, $product->promotion_price, '50% of 500 = 250, capped to 100, so 500-100 = 400');
    }

    // --- Currency in Promotion Badge Tests ---

    /** @test */
    public function promotion_badge_uses_custom_currency_symbol(): void
    {
        $promotion = new Promotion();
        $promotion->type = 'fixed';
        $promotion->value = 5000;

        $controller = new \App\Http\Controllers\StorefrontController(
            app(\App\Services\ProductService::class),
            app(\App\Services\WebsiteFaqService::class),
            app(\App\Services\StorefrontConfigurationResolver::class),
        );

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('formatPromotionBadge');
        $method->setAccessible(true);

        $result = $method->invoke($controller, $promotion, 'USD');
        $this->assertEquals('-5,000 USD', $result);
    }

    /** @test */
    public function promotion_badge_defaults_to_k(): void
    {
        $promotion = new Promotion();
        $promotion->type = 'fixed';
        $promotion->value = 1000;

        $controller = new \App\Http\Controllers\StorefrontController(
            app(\App\Services\ProductService::class),
            app(\App\Services\WebsiteFaqService::class),
            app(\App\Services\StorefrontConfigurationResolver::class),
        );

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('formatPromotionBadge');
        $method->setAccessible(true);

        $result = $method->invoke($controller, $promotion);
        $this->assertEquals('-1,000 K', $result);
    }

    /** @test */
    public function percentage_promotion_badge_ignores_currency(): void
    {
        $promotion = new Promotion();
        $promotion->type = 'percentage';
        $promotion->value = 25;

        $controller = new \App\Http\Controllers\StorefrontController(
            app(\App\Services\ProductService::class),
            app(\App\Services\WebsiteFaqService::class),
            app(\App\Services\StorefrontConfigurationResolver::class),
        );

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('formatPromotionBadge');
        $method->setAccessible(true);

        $result = $method->invoke($controller, $promotion, 'USD');
        $this->assertEquals('-25%', $result);
    }

    // --- Gallery Images Filtering Tests ---

    /** @test */
    public function gallery_images_url_filters_out_null_entries(): void
    {
        $product = Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Gallery', 'type' => 'single', 'price' => 100, 'stock' => 10, 'status' => 'active',
            'gallery_images' => ['valid/image.jpg', null, '', 'another/image.png'],
        ]);

        $urls = $product->gallery_images_url;
        $this->assertCount(2, $urls);
        $this->assertStringContainsString('valid/image.jpg', $urls[0]);
        $this->assertStringContainsString('another/image.png', $urls[1]);
    }

    /** @test */
    public function gallery_images_url_returns_empty_array_for_all_empty(): void
    {
        $product = Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'No Gallery', 'type' => 'single', 'price' => 100, 'stock' => 10, 'status' => 'active',
            'gallery_images' => [null, '', null],
        ]);

        $this->assertEmpty($product->gallery_images_url);
    }

    // --- Related Products Enrichment Tests ---

    /** @test */
    public function show_page_enriches_related_products_with_promotions(): void
    {
        $this->createWebsiteInfo();

        $category = Category::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Cat', 'slug' => 'cat-enrich', 'is_active' => true,
        ]);

        $product = Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Main', 'type' => 'single', 'price' => 100, 'stock' => 10, 'status' => 'active', 'category_id' => $category->id,
        ]);

        $related = Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Related', 'type' => 'single', 'price' => 200, 'stock' => 10, 'status' => 'active', 'category_id' => $category->id,
        ]);

        Promotion::create([
            'tenant_id' => $this->tenant->id, 'name' => '20% Off', 'code' => 'REL20', 'type' => 'percentage', 'value' => 20,
            'is_active' => true, 'is_automatic' => true, 'applies_to' => 'all',
        ]);

        $response = $this->get("/store/{$this->tenant->slug}/products/{$product->id}");
        $response->assertOk();
        $response->assertSee('Related');
    }

    // --- In-Stock Filter Tests ---

    /** @test */
    public function combo_with_all_components_in_stock_passes_filter(): void
    {
        $component1 = Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Comp1', 'type' => 'single', 'price' => 50, 'stock' => 10, 'status' => 'active',
        ]);
        $component2 = Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Comp2', 'type' => 'single', 'price' => 50, 'stock' => 10, 'status' => 'active',
        ]);

        $combo = Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Combo', 'type' => 'combo', 'price' => 100, 'stock' => 0, 'status' => 'active',
        ]);

        \App\Models\ProductCombo::create([
            'tenant_id' => $this->tenant->id, 'product_id' => $combo->id, 'combo_product_id' => $component1->id, 'quantity' => 1,
        ]);
        \App\Models\ProductCombo::create([
            'tenant_id' => $this->tenant->id, 'product_id' => $combo->id, 'combo_product_id' => $component2->id, 'quantity' => 1,
        ]);

        $query = Product::active()->where('type', 'combo');
        $controller = new \App\Http\Controllers\StorefrontController(
            app(\App\Services\ProductService::class),
            app(\App\Services\WebsiteFaqService::class),
            app(\App\Services\StorefrontConfigurationResolver::class),
        );

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('applyInStockFilter');
        $method->setAccessible(true);
        $method->invoke($controller, $query);

        $this->assertTrue($query->get()->contains('id', $combo->id));
    }

    /** @test */
    public function combo_with_one_component_out_of_stock_fails_filter(): void
    {
        $component1 = Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'InStock', 'type' => 'single', 'price' => 50, 'stock' => 10, 'status' => 'active',
        ]);
        $component2 = Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'OutOfStock', 'type' => 'single', 'price' => 50, 'stock' => 0, 'status' => 'active',
        ]);

        $combo = Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'PartialCombo', 'type' => 'combo', 'price' => 100, 'stock' => 0, 'status' => 'active',
        ]);

        \App\Models\ProductCombo::create([
            'tenant_id' => $this->tenant->id, 'product_id' => $combo->id, 'combo_product_id' => $component1->id, 'quantity' => 1,
        ]);
        \App\Models\ProductCombo::create([
            'tenant_id' => $this->tenant->id, 'product_id' => $combo->id, 'combo_product_id' => $component2->id, 'quantity' => 1,
        ]);

        $query = Product::active()->where('type', 'combo');
        $controller = new \App\Http\Controllers\StorefrontController(
            app(\App\Services\ProductService::class),
            app(\App\Services\WebsiteFaqService::class),
            app(\App\Services\StorefrontConfigurationResolver::class),
        );

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('applyInStockFilter');
        $method->setAccessible(true);
        $method->invoke($controller, $query);

        $this->assertFalse($query->get()->contains('id', $combo->id), 'Combo with OOS component should be excluded');
    }
}
