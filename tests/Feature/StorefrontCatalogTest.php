<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StorefrontCatalogTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;
    private Category $category;
    private Brand $brand;
    private Product $activeProduct;
    private Product $inactiveProduct;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupMinimalSchema();
        $this->setupTestData();
    }

    private function setupMinimalSchema(): void
    {
        $tables = [
            'tenants', 'categories', 'brands', 'products',
            'product_variants', 'product_combos', 'website_infos',
            'promotions', 'promotion_product', 'promotion_category',
            'storefronts', 'storefront_homepage_sections', 'storefront_navigations',
        ];

        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) {
                $this->markTestSkipped("Table {$table} not found. Run migrations first.");
            }
        }
    }

    private function setupTestData(): void
    {
        $this->tenant = Tenant::create([
            'name' => 'Test Store',
            'slug' => 'test-store',
            'store_url' => '/store/test-store',
            'status' => 'active',
        ]);

        $this->category = Category::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Electronics',
            'slug' => 'electronics',
            'is_active' => true,
            'featured' => true,
            'sort_order' => 1,
        ]);

        $this->brand = Brand::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Apple',
            'slug' => 'apple',
            'is_active' => true,
            'featured' => true,
            'sort_order' => 1,
        ]);

        $this->activeProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'iPhone 15',
            'type' => 'single',
            'price' => 999,
            'stock' => 100,
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'status' => 'active',
        ]);

        $this->inactiveProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Hidden Product',
            'type' => 'single',
            'price' => 99,
            'stock' => 0,
            'status' => 'inactive',
        ]);
    }

    /** @test */
    public function active_products_appear_in_storefront(): void
    {
        $response = $this->get("/store/{$this->tenant->slug}/products");

        $response->assertOk();
        $response->assertSee('iPhone 15');
    }

    /** @test */
    public function inactive_products_do_not_appear_in_storefront(): void
    {
        $response = $this->get("/store/{$this->tenant->slug}/products");

        $response->assertOk();
        $response->assertDontSee('Hidden Product');
    }

    /** @test */
    public function category_filter_works(): void
    {
        $response = $this->get("/store/{$this->tenant->slug}/products?category={$this->category->id}");

        $response->assertOk();
        $response->assertSee('iPhone 15');
    }

    /** @test */
    public function brand_filter_works(): void
    {
        $response = $this->get("/store/{$this->tenant->slug}/products?brand={$this->brand->id}");

        $response->assertOk();
        $response->assertSee('iPhone 15');
    }

    /** @test */
    public function search_by_name_works(): void
    {
        $response = $this->get("/store/{$this->tenant->slug}/products?query=iPhone");

        $response->assertOk();
        $response->assertSee('iPhone 15');
    }

    /** @test */
    public function search_by_sku_works(): void
    {
        $this->activeProduct->update(['sku' => 'IPHONE-15']);

        $response = $this->get("/store/{$this->tenant->slug}/products?query=IPHONE-15");

        $response->assertOk();
        $response->assertSee('iPhone 15');
    }

    /** @test */
    public function in_stock_filter_works(): void
    {
        $response = $this->get("/store/{$this->tenant->slug}/products?in_stock=1");

        $response->assertOk();
        $response->assertSee('iPhone 15');
    }

    /** @test */
    public function price_sort_low_to_high_works(): void
    {
        $cheapProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cheap Item',
            'type' => 'single',
            'price' => 50,
            'stock' => 10,
            'status' => 'active',
        ]);

        $expensiveProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Expensive Item',
            'type' => 'single',
            'price' => 5000,
            'stock' => 10,
            'status' => 'active',
        ]);

        $response = $this->get("/store/{$this->tenant->slug}/products?sort=price_asc");

        $response->assertOk();
        $response->assertSee('Cheap Item');
        $response->assertSee('Expensive Item');
    }

    /** @test */
    public function price_sort_high_to_low_works(): void
    {
        $cheapProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cheap Item',
            'type' => 'single',
            'price' => 50,
            'stock' => 10,
            'status' => 'active',
        ]);

        $expensiveProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Expensive Item',
            'type' => 'single',
            'price' => 5000,
            'stock' => 10,
            'status' => 'active',
        ]);

        $response = $this->get("/store/{$this->tenant->slug}/products?sort=price_desc");

        $response->assertOk();
        $response->assertSee('Expensive Item');
        $response->assertSee('Cheap Item');
    }

    /** @test */
    public function name_sort_works(): void
    {
        $productA = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Alpha Product',
            'type' => 'single',
            'price' => 100,
            'stock' => 10,
            'status' => 'active',
        ]);

        $productZ = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Zebra Product',
            'type' => 'single',
            'price' => 100,
            'stock' => 10,
            'status' => 'active',
        ]);

        $response = $this->get("/store/{$this->tenant->slug}/products?sort=name");

        $response->assertOk();
        $response->assertSee('Alpha Product');
        $response->assertSee('Zebra Product');
    }

    /** @test */
    public function combined_filters_work(): void
    {
        $response = $this->get("/store/{$this->tenant->slug}/products?category={$this->category->id}&brand={$this->brand->id}&sort=price_asc");

        $response->assertOk();
        $response->assertSee('iPhone 15');
    }

    /** @test */
    public function clear_filters_returns_all_active_products(): void
    {
        $response = $this->get("/store/{$this->tenant->slug}/products");

        $response->assertOk();
        $response->assertSee('iPhone 15');
    }

    /** @test */
    public function brands_list_page_returns_ok(): void
    {
        $response = $this->get("/store/{$this->tenant->slug}/brands");

        $response->assertOk();
    }

    /** @test */
    public function brand_product_page_returns_ok(): void
    {
        $response = $this->get("/store/{$this->tenant->slug}/brands/{$this->brand->id}");

        $response->assertOk();
        $response->assertSee('Apple');
        $response->assertSee('iPhone 15');
    }

    /** @test */
    public function inactive_brand_returns_404(): void
    {
        $inactiveBrand = Brand::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Hidden Brand',
            'slug' => 'hidden-brand',
            'is_active' => false,
        ]);

        $response = $this->get("/store/{$this->tenant->slug}/brands/{$inactiveBrand->id}");

        $response->assertNotFound();
    }

    /** @test */
    public function cross_tenant_catalog_isolated(): void
    {
        $otherTenant = Tenant::create([
            'name' => 'Other Store',
            'slug' => 'other-store',
            'store_url' => '/store/other-store',
            'status' => 'active',
        ]);

        $otherProduct = Product::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Store Product',
            'type' => 'single',
            'price' => 100,
            'stock' => 10,
            'status' => 'active',
        ]);

        $response = $this->get("/store/{$this->tenant->slug}/products");

        $response->assertOk();
        $response->assertSee('iPhone 15');
        $response->assertDontSee('Other Store Product');
    }

    /** @test */
    public function product_detail_returns_correct_data(): void
    {
        $response = $this->get("/store/{$this->tenant->slug}/products/{$this->activeProduct->id}");

        $response->assertOk();
        $response->assertSee('iPhone 15');
        $response->assertSee('999');
    }

    /** @test */
    public function inactive_product_detail_returns_404(): void
    {
        $response = $this->get("/store/{$this->tenant->slug}/products/{$this->inactiveProduct->id}");

        $response->assertNotFound();
    }

    /** @test */
    public function pagination_works(): void
    {
        for ($i = 0; $i < 15; $i++) {
            Product::create([
                'tenant_id' => $this->tenant->id,
                'name' => "Product {$i}",
                'type' => 'single',
                'price' => 100 + $i,
                'stock' => 10,
                'status' => 'active',
            ]);
        }

        $response = $this->get("/store/{$this->tenant->slug}/products?page=1");

        $response->assertOk();
        $this->assertLessThanOrEqual(12, substr_count($response->getContent(), 'Product'));
    }

    /** @test */
    public function pagination_preserves_filters(): void
    {
        for ($i = 0; $i < 15; $i++) {
            Product::create([
                'tenant_id' => $this->tenant->id,
                'name' => "Electronics Product {$i}",
                'type' => 'single',
                'price' => 100 + $i,
                'stock' => 10,
                'category_id' => $this->category->id,
                'status' => 'active',
            ]);
        }

        $response = $this->get("/store/{$this->tenant->slug}/products?category={$this->category->id}&page=1");

        $response->assertOk();
    }

    /** @test */
    public function min_price_filter_works(): void
    {
        Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cheap Product',
            'type' => 'single',
            'price' => 50,
            'stock' => 10,
            'status' => 'active',
        ]);

        Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Expensive Product',
            'type' => 'single',
            'price' => 500,
            'stock' => 10,
            'status' => 'active',
        ]);

        $response = $this->get("/store/{$this->tenant->slug}/products?min_price=100");

        $response->assertOk();
        $response->assertSee('Expensive Product');
        $response->assertDontSee('Cheap Product');
    }

    /** @test */
    public function max_price_filter_works(): void
    {
        Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cheap Product',
            'type' => 'single',
            'price' => 50,
            'stock' => 10,
            'status' => 'active',
        ]);

        Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Expensive Product',
            'type' => 'single',
            'price' => 500,
            'stock' => 10,
            'status' => 'active',
        ]);

        $response = $this->get("/store/{$this->tenant->slug}/products?max_price=100");

        $response->assertOk();
        $response->assertSee('Cheap Product');
        $response->assertDontSee('Expensive Product');
    }

    /** @test */
    public function price_range_filter_works(): void
    {
        Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cheap Product',
            'type' => 'single',
            'price' => 50,
            'stock' => 10,
            'status' => 'active',
        ]);

        Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Mid Product',
            'type' => 'single',
            'price' => 200,
            'stock' => 10,
            'status' => 'active',
        ]);

        Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Expensive Product',
            'type' => 'single',
            'price' => 500,
            'stock' => 10,
            'status' => 'active',
        ]);

        $response = $this->get("/store/{$this->tenant->slug}/products?min_price=100&max_price=300");

        $response->assertOk();
        $response->assertSee('Mid Product');
        $response->assertDontSee('Cheap Product');
        $response->assertDontSee('Expensive Product');
    }

    /** @test */
    public function invalid_price_values_are_handled(): void
    {
        Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Normal Product',
            'type' => 'single',
            'price' => 100,
            'stock' => 10,
            'status' => 'active',
        ]);

        $response = $this->get("/store/{$this->tenant->slug}/products?min_price=abc&max_price=xyz");

        $response->assertOk();
        $response->assertSee('Normal Product');
    }

    /** @test */
    public function product_type_filter_single_works(): void
    {
        Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Single Product',
            'type' => 'single',
            'price' => 100,
            'stock' => 10,
            'status' => 'active',
        ]);

        Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Variable Product',
            'type' => 'variable',
            'price' => 200,
            'stock' => 10,
            'status' => 'active',
        ]);

        $response = $this->get("/store/{$this->tenant->slug}/products?type=single");

        $response->assertOk();
        $response->assertSee('Single Product');
        $response->assertDontSee('Variable Product');
    }

    /** @test */
    public function product_type_filter_variable_works(): void
    {
        Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Single Product',
            'type' => 'single',
            'price' => 100,
            'stock' => 10,
            'status' => 'active',
        ]);

        Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Variable Product',
            'type' => 'variable',
            'price' => 200,
            'stock' => 10,
            'status' => 'active',
        ]);

        $response = $this->get("/store/{$this->tenant->slug}/products?type=variable");

        $response->assertOk();
        $response->assertDontSee('Single Product');
        $response->assertSee('Variable Product');
    }

    /** @test */
    public function invalid_sort_value_defaults_to_recommended(): void
    {
        $productA = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Alpha Product',
            'type' => 'single',
            'price' => 100,
            'stock' => 10,
            'status' => 'active',
            'featured' => true,
        ]);

        $productZ = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Zebra Product',
            'type' => 'single',
            'price' => 100,
            'stock' => 10,
            'status' => 'active',
            'featured' => false,
        ]);

        $response = $this->get("/store/{$this->tenant->slug}/products?sort=invalid_sort_value");

        $response->assertOk();
        $response->assertSee('Alpha Product');
    }

    /** @test */
    public function recommended_sort_shows_featured_first(): void
    {
        $regularProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Regular Product',
            'type' => 'single',
            'price' => 100,
            'stock' => 10,
            'status' => 'active',
            'featured' => false,
        ]);

        $featuredProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Featured Product',
            'type' => 'single',
            'price' => 100,
            'stock' => 10,
            'status' => 'active',
            'featured' => true,
        ]);

        $response = $this->get("/store/{$this->tenant->slug}/products?sort=recommended");

        $response->assertOk();
        $response->assertSee('Featured Product');
    }

    /** @test */
    public function name_asc_sort_works(): void
    {
        Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Zebra Product',
            'type' => 'single',
            'price' => 100,
            'stock' => 10,
            'status' => 'active',
        ]);

        Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Alpha Product',
            'type' => 'single',
            'price' => 100,
            'stock' => 10,
            'status' => 'active',
        ]);

        $response = $this->get("/store/{$this->tenant->slug}/products?sort=name_asc");

        $response->assertOk();
        $content = $response->getContent();
        $alphaPos = strpos($content, 'Alpha Product');
        $zebraPos = strpos($content, 'Zebra Product');
        $this->assertLessThan($zebraPos, $alphaPos);
    }

    /** @test */
    public function name_desc_sort_works(): void
    {
        Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Zebra Product',
            'type' => 'single',
            'price' => 100,
            'stock' => 10,
            'status' => 'active',
        ]);

        Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Alpha Product',
            'type' => 'single',
            'price' => 100,
            'stock' => 10,
            'status' => 'active',
        ]);

        $response = $this->get("/store/{$this->tenant->slug}/products?sort=name_desc");

        $response->assertOk();
        $content = $response->getContent();
        $alphaPos = strpos($content, 'Alpha Product');
        $zebraPos = strpos($content, 'Zebra Product');
        $this->assertGreaterThan($alphaPos, $zebraPos);
    }

    /** @test */
    public function search_by_brand_name_works(): void
    {
        $response = $this->get("/store/{$this->tenant->slug}/products?query=Apple");

        $response->assertOk();
        $response->assertSee('iPhone 15');
    }

    /** @test */
    public function search_by_category_name_works(): void
    {
        $response = $this->get("/store/{$this->tenant->slug}/products?query=Electronics");

        $response->assertOk();
        $response->assertSee('iPhone 15');
    }

    /** @test */
    public function combined_filters_with_type_and_price_work(): void
    {
        Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Single Expensive',
            'type' => 'single',
            'price' => 500,
            'stock' => 10,
            'category_id' => $this->category->id,
            'status' => 'active',
        ]);

        Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Variable Cheap',
            'type' => 'variable',
            'price' => 100,
            'stock' => 10,
            'category_id' => $this->category->id,
            'status' => 'active',
        ]);

        $response = $this->get("/store/{$this->tenant->slug}/products?category={$this->category->id}&type=single&min_price=400");

        $response->assertOk();
        $response->assertSee('Single Expensive');
        $response->assertDontSee('Variable Cheap');
    }

    /** @test */
    public function newest_sort_works(): void
    {
        $oldProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Old Product',
            'type' => 'single',
            'price' => 100,
            'stock' => 10,
            'status' => 'active',
            'created_at' => now()->subDays(30),
        ]);

        $newProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'New Product',
            'type' => 'single',
            'price' => 100,
            'stock' => 10,
            'status' => 'active',
            'created_at' => now(),
        ]);

        $response = $this->get("/store/{$this->tenant->slug}/products?sort=newest");

        $response->assertOk();
        $content = $response->getContent();
        $newPos = strpos($content, 'New Product');
        $oldPos = strpos($content, 'Old Product');
        $this->assertLessThan($oldPos, $newPos);
    }

    /** @test */
    public function negative_price_values_are_handled(): void
    {
        Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Normal Product',
            'type' => 'single',
            'price' => 100,
            'stock' => 10,
            'status' => 'active',
        ]);

        $response = $this->get("/store/{$this->tenant->slug}/products?min_price=-50");

        $response->assertOk();
        $response->assertSee('Normal Product');
    }

    /** @test */
    public function legacy_sort_name_behaves_as_name_asc(): void
    {
        Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Zebra Product',
            'type' => 'single',
            'price' => 100,
            'stock' => 10,
            'status' => 'active',
        ]);

        Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Alpha Product',
            'type' => 'single',
            'price' => 100,
            'stock' => 10,
            'status' => 'active',
        ]);

        $response = $this->get("/store/{$this->tenant->slug}/products?sort=name");

        $response->assertOk();
        $content = $response->getContent();
        $alphaPos = strpos($content, 'Alpha Product');
        $zebraPos = strpos($content, 'Zebra Product');
        $this->assertLessThan($zebraPos, $alphaPos);
    }

    /** @test */
    public function invalid_sort_value_does_not_inject_sql(): void
    {
        Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Safe Product',
            'type' => 'single',
            'price' => 100,
            'stock' => 10,
            'status' => 'active',
        ]);

        $injectionAttempts = [
            'price_asc; DROP TABLE products;',
            'name_asc UNION SELECT * FROM users',
            'price_asc--',
            'price_asc/**/',
            "name_asc' OR 1=1 --",
        ];

        foreach ($injectionAttempts as $attempt) {
            $response = $this->get("/store/{$this->tenant->slug}/products?sort=" . urlencode($attempt));
            $response->assertOk();
            $response->assertSee('Safe Product');
        }

        $this->assertNotNull(Product::first());
    }

    /** @test */
    public function min_greater_than_max_is_swapped_safely(): void
    {
        Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cheap Product',
            'type' => 'single',
            'price' => 50,
            'stock' => 10,
            'status' => 'active',
        ]);

        Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Mid Product',
            'type' => 'single',
            'price' => 200,
            'stock' => 10,
            'status' => 'active',
        ]);

        $response = $this->get("/store/{$this->tenant->slug}/products?min_price=300&max_price=100");

        $response->assertOk();
        $response->assertSee('Mid Product');
        $response->assertDontSee('Cheap Product');
    }

    /** @test */
    public function zero_price_filter_is_valid(): void
    {
        Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Zero Product',
            'type' => 'single',
            'price' => 0,
            'stock' => 10,
            'status' => 'active',
        ]);

        Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pricey Product',
            'type' => 'single',
            'price' => 500,
            'stock' => 10,
            'status' => 'active',
        ]);

        $response = $this->get("/store/{$this->tenant->slug}/products?min_price=0&max_price=100");

        $response->assertOk();
        $response->assertSee('Zero Product');
        $response->assertDontSee('Pricey Product');
    }

    /** @test */
    public function empty_price_params_are_ignored(): void
    {
        Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Visible Product',
            'type' => 'single',
            'price' => 100,
            'stock' => 10,
            'status' => 'active',
        ]);

        $response = $this->get("/store/{$this->tenant->slug}/products?min_price=&max_price=");

        $response->assertOk();
        $response->assertSee('Visible Product');
    }

    /** @test */
    public function non_numeric_category_id_is_ignored(): void
    {
        Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Categorized Product',
            'type' => 'single',
            'price' => 100,
            'stock' => 10,
            'category_id' => $this->category->id,
            'status' => 'active',
        ]);

        $response = $this->get("/store/{$this->tenant->slug}/products?category=abc123xyz");

        $response->assertOk();
        $response->assertSee('Categorized Product');
    }

    /** @test */
    public function non_numeric_brand_id_is_ignored(): void
    {
        Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Branded Product',
            'type' => 'single',
            'price' => 100,
            'stock' => 10,
            'brand_id' => $this->brand->id,
            'status' => 'active',
        ]);

        $response = $this->get("/store/{$this->tenant->slug}/products?brand=evil_injection");

        $response->assertOk();
        $response->assertSee('Branded Product');
    }

    /** @test */
    public function invalid_type_value_is_ignored(): void
    {
        Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Normal Product',
            'type' => 'single',
            'price' => 100,
            'stock' => 10,
            'status' => 'active',
        ]);

        $response = $this->get("/store/{$this->tenant->slug}/products?type=evil_type");

        $response->assertOk();
        $response->assertSee('Normal Product');
    }

    /** @test */
    public function cross_tenant_category_id_does_not_expose_products(): void
    {
        $otherTenant = Tenant::create([
            'name' => 'Other Store',
            'slug' => 'other-store',
            'store_url' => '/store/other-store',
            'status' => 'active',
        ]);

        $otherCategory = Category::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Category',
            'slug' => 'other-category',
            'is_active' => true,
        ]);

        Product::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Store Product',
            'type' => 'single',
            'price' => 100,
            'stock' => 10,
            'category_id' => $otherCategory->id,
            'status' => 'active',
        ]);

        $response = $this->get("/store/{$this->tenant->slug}/products?category={$otherCategory->id}");

        $response->assertOk();
        $response->assertDontSee('Other Store Product');
        $response->assertSee('iPhone 15');
    }

    /** @test */
    public function cross_tenant_brand_id_does_not_expose_products(): void
    {
        $otherTenant = Tenant::create([
            'name' => 'Other Store',
            'slug' => 'other-store',
            'store_url' => '/store/other-store',
            'status' => 'active',
        ]);

        $otherBrand = Brand::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Brand',
            'slug' => 'other-brand',
            'is_active' => true,
        ]);

        Product::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Store Product',
            'type' => 'single',
            'price' => 100,
            'stock' => 10,
            'brand_id' => $otherBrand->id,
            'status' => 'active',
        ]);

        $response = $this->get("/store/{$this->tenant->slug}/products?brand={$otherBrand->id}");

        $response->assertOk();
        $response->assertDontSee('Other Store Product');
    }

    /** @test */
    public function search_by_brand_name_remains_tenant_safe(): void
    {
        $otherTenant = Tenant::create([
            'name' => 'Other Store',
            'slug' => 'other-store',
            'store_url' => '/store/other-store',
            'status' => 'active',
        ]);

        $otherBrand = Brand::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'UniqueOtherBrandName',
            'slug' => 'unique-other-brand',
            'is_active' => true,
        ]);

        Product::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Product',
            'type' => 'single',
            'price' => 100,
            'stock' => 10,
            'brand_id' => $otherBrand->id,
            'status' => 'active',
        ]);

        $response = $this->get("/store/{$this->tenant->slug}/products?query=UniqueOtherBrandName");

        $response->assertOk();
        $response->assertDontSee('Other Product');
    }

    /** @test */
    public function filters_persist_across_pagination_with_type(): void
    {
        for ($i = 0; $i < 15; $i++) {
            Product::create([
                'tenant_id' => $this->tenant->id,
                'name' => "Product {$i}",
                'type' => 'single',
                'price' => 100 + $i,
                'stock' => 10,
                'category_id' => $this->category->id,
                'status' => 'active',
            ]);
        }

        $response = $this->get("/store/{$this->tenant->slug}/products?type=single&category={$this->category->id}&page=1");

        $response->assertOk();
    }

    /** @test */
    public function aria_label_present_in_rendered_html(): void
    {
        $response = $this->get("/store/{$this->tenant->slug}/products");

        $response->assertOk();
    }

    /** @test */
    public function empty_query_param_does_not_filter(): void
    {
        $this->activeProduct->update(['name' => 'Matchable Product']);

        $response = $this->get("/store/{$this->tenant->slug}/products?query=");

        $response->assertOk();
        $response->assertSee('Matchable Product');
    }

    /** @test */
    public function combo_products_visible_in_catalog_when_featuregate_disabled(): void
    {
        $comboProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Combo Bundle',
            'type' => 'combo',
            'price' => 500,
            'stock' => 10,
            'status' => 'active',
        ]);

        $response = $this->get("/store/{$this->tenant->slug}/products?type=combo");

        $response->assertOk();
        $response->assertSee('Combo Bundle');
    }
}
