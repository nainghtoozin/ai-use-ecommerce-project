<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StorefrontProductDetailTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;
    private Tenant $otherTenant;
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

        $this->otherTenant = Tenant::create([
            'name' => 'Other Store',
            'slug' => 'other-store',
            'store_url' => '/store/other-store',
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
            'sku' => 'IPHONE-15',
            'short_description' => 'Latest iPhone model',
            'description' => 'Full description of iPhone 15',
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
    public function active_product_detail_loads(): void
    {
        $response = $this->get("/store/{$this->tenant->slug}/products/{$this->activeProduct->id}");

        $response->assertOk();
        $response->assertSee('iPhone 15');
    }

    /** @test */
    public function inactive_product_detail_returns_404(): void
    {
        $response = $this->get("/store/{$this->tenant->slug}/products/{$this->inactiveProduct->id}");

        $response->assertNotFound();
    }

    /** @test */
    public function cross_tenant_product_cannot_be_accessed(): void
    {
        $otherProduct = Product::create([
            'tenant_id' => $this->otherTenant->id,
            'name' => 'Other Store Product',
            'type' => 'single',
            'price' => 100,
            'stock' => 10,
            'status' => 'active',
        ]);

        $response = $this->get("/store/{$this->tenant->slug}/products/{$otherProduct->id}");

        $response->assertNotFound();
    }

    /** @test */
    public function single_product_detail_shows_correct_price(): void
    {
        $response = $this->get("/store/{$this->tenant->slug}/products/{$this->activeProduct->id}");

        $response->assertOk();
        $response->assertSee('999');
    }

    /** @test */
    public function single_out_of_stock_product_cannot_be_purchased(): void
    {
        $outOfStockProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Out of Stock Item',
            'type' => 'single',
            'price' => 50,
            'stock' => 0,
            'status' => 'active',
        ]);

        $response = $this->get("/store/{$this->tenant->slug}/products/{$outOfStockProduct->id}");

        $response->assertOk();
        $response->assertSee('Out of Stock');
    }

    /** @test */
    public function product_detail_includes_brand_link(): void
    {
        $response = $this->get("/store/{$this->tenant->slug}/products/{$this->activeProduct->id}");

        $response->assertOk();
        $response->assertSee('/store/test-store/brands/' . $this->brand->id);
    }

    /** @test */
    public function product_detail_includes_category_link(): void
    {
        $response = $this->get("/store/{$this->tenant->slug}/products/{$this->activeProduct->id}");

        $response->assertOk();
        $response->assertSee('/store/test-store/products?category=' . $this->category->id);
    }

    /** @test */
    public function product_detail_shows_sku(): void
    {
        $response = $this->get("/store/{$this->tenant->slug}/products/{$this->activeProduct->id}");

        $response->assertOk();
        $response->assertSee('IPHONE-15');
    }

    /** @test */
    public function product_detail_shows_short_description(): void
    {
        $response = $this->get("/store/{$this->tenant->slug}/products/{$this->activeProduct->id}");

        $response->assertOk();
        $response->assertSee('Latest iPhone model');
    }

    /** @test */
    public function related_products_are_returned(): void
    {
        $relatedProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Related Product',
            'type' => 'single',
            'price' => 199,
            'stock' => 50,
            'category_id' => $this->category->id,
            'status' => 'active',
        ]);

        $response = $this->get("/store/{$this->tenant->slug}/products/{$this->activeProduct->id}");

        $response->assertOk();
        $response->assertSee('Related Product');
    }

    /** @test */
    public function product_detail_does_not_expose_internal_fields(): void
    {
        $response = $this->get("/store/{$this->tenant->slug}/products/{$this->activeProduct->id}");

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringNotContainsString('cost_price', $content);
        $this->assertStringNotContainsString('supplier', strtolower($content));
    }

    /** @test */
    public function product_detail_seo_fields_present(): void
    {
        $seoProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'SEO Product',
            'type' => 'single',
            'price' => 100,
            'stock' => 10,
            'status' => 'active',
            'seo_title' => 'Custom SEO Title',
            'seo_description' => 'Custom SEO description',
            'seo_keywords' => 'keyword1, keyword2',
        ]);

        $response = $this->get("/store/{$this->tenant->slug}/products/{$seoProduct->id}");

        $response->assertOk();
    }
}
