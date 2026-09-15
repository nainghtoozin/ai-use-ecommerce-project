<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\Storefront;
use App\Models\StorefrontRevision;
use App\Models\WebsiteInfo;
use App\Models\Theme;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\FlashSale;
use App\Models\FlashSaleProduct;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StorefrontFeaturedProductsArrayRegressionTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;
    private Storefront $storefront;
    private Product $product;
    private Product $variableProduct;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $requiredTables = [
            'tenants', 'storefronts', 'storefront_revisions', 'website_infos',
            'themes', 'categories', 'brands', 'products', 'product_variants',
            'promotions', 'promotion_product', 'promotion_category',
            'flash_sales', 'flash_sale_products',
        ];
        foreach ($requiredTables as $table) {
            if (!Schema::hasTable($table)) {
                $this->markTestSkipped("Table {$table} not found. Run migrations first.");
            }
        }

        $this->tenant = Tenant::create([
            'name' => 'Array Store',
            'slug' => 'array-store',
            'store_url' => '/store/array-store',
            'status' => 'active',
        ]);
        app()->instance('current.tenant', $this->tenant);

        $theme = Theme::firstOrCreate(
            ['slug' => 'commerce-default'],
            ['name' => 'Commerce Default', 'version' => '1.0.0', 'default_tokens' => ['color' => ['primary' => '#3B82F6']], 'is_active' => true]
        );

        $this->storefront = Storefront::create([
            'tenant_id' => $this->tenant->id,
            'theme_id' => $theme->id,
            'status' => 'active',
        ]);

        WebsiteInfo::create([
            'tenant_id' => $this->tenant->id,
            'site_name' => 'Array Store',
            'currency_code' => 'MMK',
            'currency_symbol' => 'K',
        ]);

        $this->category = Category::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pants',
            'slug' => 'pants',
        ]);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Chino Pants',
            'type' => 'single',
            'price' => 100000,
            'stock' => 50,
            'category_id' => $this->category->id,
            'status' => 'active',
        ]);

        $this->variableProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cargo Pants',
            'type' => 'variable',
            'price' => 120000,
            'stock' => 0,
            'category_id' => $this->category->id,
            'status' => 'active',
        ]);

        ProductVariant::create([
            'product_id' => $this->variableProduct->id,
            'name' => 'Size 30',
            'price' => 120000,
            'stock' => 10,
            'status' => 'active',
        ]);
        ProductVariant::create([
            'product_id' => $this->variableProduct->id,
            'name' => 'Size 32',
            'price' => 130000,
            'stock' => 5,
            'status' => 'active',
        ]);
    }
private function createActivePromotion(): Promotion
    {
        return Promotion::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Sale',
            'type' => Promotion::TYPE_PERCENTAGE,
            'value' => 10,
            'starts_at' => null,
            'ends_at' => null,
            'is_active' => true,
            'is_automatic' => true,
            'applies_to' => Promotion::APPLIES_ALL,
            'priority' => 100,
        ]);
    }

    private function createActiveFlashSale(): void
    {
        $flashSale = FlashSale::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Flash Deal',
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'starts_at' => null,
            'ends_at' => null,
            'is_active' => true,
            'priority' => 10,
        ]);

        FlashSaleProduct::create([
            'flash_sale_id' => $flashSale->id,
            'product_id' => $this->product->id,
            'flash_price' => 80000,
            'quantity_limit' => 10,
            'quantity_sold' => 0,
        ]);
    }

    private function arrayProduct(array $overrides = []): array
    {
        return array_merge([
            'id' => $this->product->id,
            'name' => $this->product->name,
            'price' => (float) $this->product->price,
            'type' => 'single',
            'is_variable' => false,
            'status' => 'active',
            'category' => ['id' => $this->category->id, 'name' => $this->category->name],
            'brand' => null,
            'variants' => [],
            'gallery_images' => [],
        ], $overrides);
    }

    private function arrayVariableProduct(): array
    {
        $variants = $this->variableProduct->variants->map(fn ($v) => [
            'id' => $v->id,
            'name' => $v->name,
            'price' => (float) $v->price,
            'stock' => $v->stock,
            'status' => $v->status,
        ])->values()->all();

        return [
            'id' => $this->variableProduct->id,
            'name' => $this->variableProduct->name,
            'price' => (float) $this->variableProduct->price,
            'type' => 'variable',
            'is_variable' => true,
            'status' => 'active',
            'category' => ['id' => $this->category->id, 'name' => $this->category->name],
            'brand' => null,
            'variants' => $variants,
            'gallery_images' => [],
        ];
    }

    private function publishRevision(array $section): StorefrontRevision
    {
        $revision = StorefrontRevision::create([
            'tenant_id' => $this->tenant->id,
            'storefront_id' => $this->storefront->id,
            'revision_number' => 1,
            'status' => 'published',
            'configuration' => [
                'identity' => ['name' => 'Array Store', 'store_name' => 'Array Store', 'site_title' => 'Array Store'],
                'homepage' => ['sections' => [$section]],
            ],
        ]);

        $this->storefront->update(['published_revision_id' => $revision->id]);

        return $revision;
    }

    private function featuredSection(bool $enabled, array $data): array
    {
        return [
            'id' => 1,
            'type' => 'featured_products',
            'variant' => 'default',
            'enabled' => $enabled,
            'desktop_visible' => true,
            'mobile_visible' => true,
            'position' => 0,
            'configuration' => ['limit' => 8, 'product_ids' => [$data['products'][0]['id']]],
            'data' => $data,
        ];
    }

    private function storefrontProps($response): array
    {
        return $response->viewData('page')['props']['storefront'];
    }
/** @test */
    public function stored_array_featured_products_no_longer_500_and_are_enriched(): void
    {
        $this->createActivePromotion();
        $this->createActiveFlashSale();

        $section = $this->featuredSection(false, ['products' => [$this->arrayProduct()]]);
        $this->publishRevision($section);

        $response = $this->get("/store/{$this->tenant->slug}");

        $response->assertOk();

        $sections = $this->storefrontProps($response)['homepage']['sections'];
        $products = $sections[0]['data']['products'];

        $this->assertIsArray($products[0]);
        $this->assertSame($this->product->id, $products[0]['id']);
        $this->assertArrayHasKey('promotion_badge', $products[0]);
        $this->assertSame('-10%', $products[0]['promotion_badge']);
        $this->assertSame(90000.0, (float) $products[0]['promotion_price']);
        $this->assertTrue($products[0]['is_flash_sale']);
        $this->assertSame(80000.0, (float) $products[0]['flash_sale_price']);
        $this->assertSame('Flash Deal', $products[0]['flash_sale_name']);
    }

    /** @test */
    public function stored_array_variable_product_gets_variant_promotion_prices(): void
    {
        $this->createActivePromotion();

        $section = $this->featuredSection(false, ['products' => [$this->arrayVariableProduct()]]);
        $this->publishRevision($section);

        $response = $this->get("/store/{$this->tenant->slug}");

        $response->assertOk();

        $sections = $this->storefrontProps($response)['homepage']['sections'];
        $product = $sections[0]['data']['products'][0];

        $this->assertTrue($product['is_variable']);
        $this->assertSame(108000.0, (float) $product['promotion_price']);
        $this->assertSame(117000.0, (float) $product['promotion_price_max']);
        foreach ($product['variants'] as $variant) {
            $this->assertArrayHasKey('promotion_price', $variant);
            $this->assertNotNull($variant['promotion_price']);
        }
    }

    /** @test */
    public function enabled_section_with_fresh_products_still_renders_with_promotion_data(): void
    {
        $this->createActivePromotion();
        $this->createActiveFlashSale();

        $section = $this->featuredSection(true, ['products' => [$this->arrayProduct()]]);
        $this->publishRevision($section);

        $response = $this->get("/store/{$this->tenant->slug}");

        $response->assertOk();

        $sections = $this->storefrontProps($response)['homepage']['sections'];
        $products = $sections[0]['data']['products'];

        $this->assertNotEmpty($products);
        $this->assertSame($this->product->id, $products[0]['id']);
        $this->assertSame('-10%', $products[0]['promotion_badge']);
        $this->assertSame(90000.0, (float) $products[0]['promotion_price']);
    }
}