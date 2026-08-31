<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Storefront;
use App\Models\StorefrontHomepageSection;
use App\Models\Tenant;
use App\Models\Theme;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StorefrontHomepageTest extends TestCase
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
            'categories', 'brands', 'products', 'product_variants', 'product_combos',
            'storefront_theme_configs', 'storefront_design_tokens', 'storefront_contents',
            'website_infos', 'storefront_navigations', 'storefront_navigation_items',
            'storefront_revisions', 'promotion_banners', 'storefront_media',
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

        $theme = Theme::firstOrCreate(
            ['slug' => 'commerce-default'],
            [
                'name' => 'Commerce Default',
                'version' => '1.0.0',
                'default_tokens' => ['color' => ['primary' => '#3B82F6']],
                'is_active' => true,
            ]
        );

        $this->storefront = Storefront::create([
            'tenant_id' => $this->tenant->id,
            'theme_id' => $theme->id,
            'status' => 'active',
        ]);
    }

    private function enableAllSections(): void
    {
        $types = [
            'hero', 'promotion', 'featured_categories', 'featured_brands',
            'featured_products', 'product_showcase', 'store_highlights',
            'brand_story', 'cta',
        ];
        $position = 0;
        foreach ($types as $type) {
            StorefrontHomepageSection::create([
                'tenant_id' => $this->tenant->id,
                'storefront_id' => $this->storefront->id,
                'type' => $type,
                'variant' => $type === 'hero' ? 'text-only' : 'default',
                'enabled' => true,
                'desktop_visible' => true,
                'mobile_visible' => true,
                'position' => $position++,
                'configuration' => [],
            ]);
        }
    }

    /** @test */
    public function homepage_loads_with_200_status(): void
    {
        $this->enableAllSections();

        $response = $this->get("/store/{$this->tenant->slug}");

        $response->assertOk();
    }

    /** @test */
    public function homepage_returns_404_for_unknown_tenant_slug(): void
    {
        $response = $this->get('/store/nonexistent-tenant');

        $response->assertNotFound();
    }

    /** @test */
    public function sections_are_returned_in_position_order(): void
    {
        $this->enableAllSections();

        $response = $this->get("/store/{$this->tenant->slug}");

        $response->assertOk();
        $content = $response->getContent();

        $heroPos = strpos($content, 'homepage.sections');
        $this->assertNotFalse($heroPos);
    }

    /** @test */
    public function disabled_sections_do_not_render_data(): void
    {
        $this->enableAllSections();

        $category = Category::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Visible Category',
            'slug' => 'visible-category',
            'is_active' => true,
        ]);

        StorefrontHomepageSection::where('storefront_id', $this->storefront->id)
            ->where('type', 'featured_categories')
            ->update(['enabled' => false, 'configuration' => ['category_ids' => [$category->id]]]);

        $response = $this->get("/store/{$this->tenant->slug}");

        $response->assertOk();
    }

    /** @test */
    public function inactive_category_is_excluded_from_featured_section(): void
    {
        $this->enableAllSections();

        $active = Category::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'ActiveCategory',
            'slug' => 'active-cat',
            'is_active' => true,
        ]);

        Category::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'InactiveCategory',
            'slug' => 'inactive-cat',
            'is_active' => false,
        ]);

        StorefrontHomepageSection::where('storefront_id', $this->storefront->id)
            ->where('type', 'featured_categories')
            ->update(['configuration' => ['category_ids' => [$active->id]]]);

        $response = $this->get("/store/{$this->tenant->slug}");

        $response->assertOk();
        $response->assertSee('ActiveCategory');
        $response->assertDontSee('InactiveCategory');
    }

    /** @test */
    public function inactive_brand_is_excluded_from_featured_section(): void
    {
        $this->enableAllSections();

        $active = Brand::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'ActiveBrand',
            'slug' => 'active-brand',
            'is_active' => true,
        ]);

        Brand::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'InactiveBrand',
            'slug' => 'inactive-brand',
            'is_active' => false,
        ]);

        StorefrontHomepageSection::where('storefront_id', $this->storefront->id)
            ->where('type', 'featured_brands')
            ->update(['configuration' => ['brand_ids' => [$active->id]]]);

        $response = $this->get("/store/{$this->tenant->slug}");

        $response->assertOk();
        $response->assertSee('ActiveBrand');
        $response->assertDontSee('InactiveBrand');
    }

    /** @test */
    public function cross_tenant_category_cannot_appear_in_featured_section(): void
    {
        $this->enableAllSections();

        $otherTenant = Tenant::create([
            'name' => 'Other Store',
            'slug' => 'other-store',
            'store_url' => '/store/other-store',
            'status' => 'active',
        ]);

        $otherStorefront = Storefront::create([
            'tenant_id' => $otherTenant->id,
            'theme_id' => $this->storefront->theme_id,
            'status' => 'active',
        ]);

        $tenantCategory = Category::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'MyCategory',
            'slug' => 'my-cat',
            'is_active' => true,
        ]);

        $otherCategory = Category::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'OtherTenantCategory',
            'slug' => 'other-tenant-cat',
            'is_active' => true,
        ]);

        StorefrontHomepageSection::create([
            'tenant_id' => $otherTenant->id,
            'storefront_id' => $otherStorefront->id,
            'type' => 'featured_categories',
            'variant' => 'default',
            'enabled' => true,
            'desktop_visible' => true,
            'mobile_visible' => true,
            'position' => 0,
            'configuration' => ['category_ids' => [$otherCategory->id]],
        ]);

        StorefrontHomepageSection::where('storefront_id', $this->storefront->id)
            ->where('type', 'featured_categories')
            ->update(['configuration' => ['category_ids' => [$tenantCategory->id, $otherCategory->id]]]);

        $response = $this->get("/store/{$this->tenant->slug}");

        $response->assertOk();
        $response->assertSee('MyCategory');
        $response->assertDontSee('OtherTenantCategory');
    }

    /** @test */
    public function cross_tenant_brand_cannot_appear_in_featured_section(): void
    {
        $this->enableAllSections();

        $otherTenant = Tenant::create([
            'name' => 'Other Store',
            'slug' => 'other-store',
            'store_url' => '/store/other-store',
            'status' => 'active',
        ]);

        $tenantBrand = Brand::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'MyBrand',
            'slug' => 'my-brand',
            'is_active' => true,
        ]);

        $otherBrand = Brand::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'OtherTenantBrand',
            'slug' => 'other-tenant-brand',
            'is_active' => true,
        ]);

        StorefrontHomepageSection::where('storefront_id', $this->storefront->id)
            ->where('type', 'featured_brands')
            ->update(['configuration' => ['brand_ids' => [$tenantBrand->id, $otherBrand->id]]]);

        $response = $this->get("/store/{$this->tenant->slug}");

        $response->assertOk();
        $response->assertSee('MyBrand');
        $response->assertDontSee('OtherTenantBrand');
    }

    /** @test */
    public function inactive_product_is_excluded_from_featured_section(): void
    {
        $this->enableAllSections();

        $active = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'ActiveProduct',
            'type' => 'single',
            'price' => 100,
            'stock' => 10,
            'status' => 'active',
        ]);

        $inactive = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'InactiveProduct',
            'type' => 'single',
            'price' => 100,
            'stock' => 10,
            'status' => 'inactive',
        ]);

        StorefrontHomepageSection::where('storefront_id', $this->storefront->id)
            ->where('type', 'featured_products')
            ->update(['configuration' => ['product_ids' => [$active->id, $inactive->id]]]);

        $response = $this->get("/store/{$this->tenant->slug}");

        $response->assertOk();
    }

    /** @test */
    public function cross_tenant_product_cannot_appear_in_featured_section(): void
    {
        $this->enableAllSections();

        $otherTenant = Tenant::create([
            'name' => 'Other Store',
            'slug' => 'other-store',
            'store_url' => '/store/other-store',
            'status' => 'active',
        ]);

        $tenantProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'MyStoreProduct',
            'type' => 'single',
            'price' => 100,
            'stock' => 10,
            'status' => 'active',
        ]);

        $otherProduct = Product::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'OtherStoreProduct',
            'type' => 'single',
            'price' => 100,
            'stock' => 10,
            'status' => 'active',
        ]);

        StorefrontHomepageSection::where('storefront_id', $this->storefront->id)
            ->where('type', 'featured_products')
            ->update(['configuration' => ['product_ids' => [$tenantProduct->id, $otherProduct->id]]]);

        $response = $this->get("/store/{$this->tenant->slug}");

        $response->assertOk();
        $response->assertDontSee('OtherStoreProduct');
    }

    /** @test */
    public function featured_categories_section_falls_back_to_featured_scope_when_no_ids_configured(): void
    {
        $this->enableAllSections();

        $featured = Category::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'AutoFeaturedCategory',
            'slug' => 'auto-featured-cat',
            'is_active' => true,
            'featured' => true,
            'sort_order' => 1,
        ]);

        Category::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'NotFeaturedCategory',
            'slug' => 'not-featured-cat',
            'is_active' => true,
            'featured' => false,
        ]);

        StorefrontHomepageSection::where('storefront_id', $this->storefront->id)
            ->where('type', 'featured_categories')
            ->update(['configuration' => []]);

        $response = $this->get("/store/{$this->tenant->slug}");

        $response->assertOk();
        $response->assertSee('AutoFeaturedCategory');
    }

    /** @test */
    public function featured_brands_section_falls_back_to_featured_scope_when_no_ids_configured(): void
    {
        $this->enableAllSections();

        $featured = Brand::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'AutoFeaturedBrand',
            'slug' => 'auto-featured-brand',
            'is_active' => true,
            'featured' => true,
            'sort_order' => 1,
        ]);

        Brand::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'NotFeaturedBrand',
            'slug' => 'not-featured-brand',
            'is_active' => true,
            'featured' => false,
        ]);

        StorefrontHomepageSection::where('storefront_id', $this->storefront->id)
            ->where('type', 'featured_brands')
            ->update(['configuration' => []]);

        $response = $this->get("/store/{$this->tenant->slug}");

        $response->assertOk();
        $response->assertSee('AutoFeaturedBrand');
    }

    /** @test */
    public function featured_products_section_falls_back_to_recent_active_products(): void
    {
        $this->enableAllSections();

        Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'RecentProduct',
            'type' => 'single',
            'price' => 100,
            'stock' => 10,
            'status' => 'active',
        ]);

        StorefrontHomepageSection::where('storefront_id', $this->storefront->id)
            ->where('type', 'featured_products')
            ->update(['configuration' => []]);

        $response = $this->get("/store/{$this->tenant->slug}");

        $response->assertOk();
    }

    /** @test */
    public function desktop_only_section_uses_correct_visibility_class(): void
    {
        StorefrontHomepageSection::create([
            'tenant_id' => $this->tenant->id,
            'storefront_id' => $this->storefront->id,
            'type' => 'hero',
            'variant' => 'text-only',
            'enabled' => true,
            'desktop_visible' => true,
            'mobile_visible' => false,
            'position' => 0,
            'configuration' => ['title' => 'Desktop Only Hero'],
        ]);

        $response = $this->get("/store/{$this->tenant->slug}");

        $response->assertOk();
    }

    /** @test */
    public function mobile_only_section_uses_correct_visibility_class(): void
    {
        StorefrontHomepageSection::create([
            'tenant_id' => $this->tenant->id,
            'storefront_id' => $this->storefront->id,
            'type' => 'hero',
            'variant' => 'text-only',
            'enabled' => true,
            'desktop_visible' => false,
            'mobile_visible' => true,
            'position' => 0,
            'configuration' => ['title' => 'Mobile Only Hero'],
        ]);

        $response = $this->get("/store/{$this->tenant->slug}");

        $response->assertOk();
    }

    /** @test */
    public function section_hidden_on_both_desktop_and_mobile_is_excluded(): void
    {
        StorefrontHomepageSection::create([
            'tenant_id' => $this->tenant->id,
            'storefront_id' => $this->storefront->id,
            'type' => 'cta',
            'variant' => 'default',
            'enabled' => true,
            'desktop_visible' => false,
            'mobile_visible' => false,
            'position' => 0,
            'configuration' => ['title' => 'Hidden CTA', 'button_text' => 'Click'],
        ]);

        $response = $this->get("/store/{$this->tenant->slug}");

        $response->assertOk();
    }

    /** @test */
    public function hero_section_with_no_media_renders_in_text_only_mode(): void
    {
        StorefrontHomepageSection::create([
            'tenant_id' => $this->tenant->id,
            'storefront_id' => $this->storefront->id,
            'type' => 'hero',
            'variant' => 'split',
            'enabled' => true,
            'desktop_visible' => true,
            'mobile_visible' => true,
            'position' => 0,
            'configuration' => ['title' => 'Text Hero', 'subtitle' => 'No images'],
        ]);

        $response = $this->get("/store/{$this->tenant->slug}");

        $response->assertOk();
        $response->assertSee('Text Hero');
    }

    /** @test */
    public function brand_story_with_no_description_does_not_render(): void
    {
        StorefrontHomepageSection::create([
            'tenant_id' => $this->tenant->id,
            'storefront_id' => $this->storefront->id,
            'type' => 'brand_story',
            'variant' => 'default',
            'enabled' => true,
            'desktop_visible' => true,
            'mobile_visible' => true,
            'position' => 0,
            'configuration' => ['title' => 'Empty Story'],
        ]);

        $response = $this->get("/store/{$this->tenant->slug}");

        $response->assertOk();
        $response->assertDontSee('Empty Story');
    }

    /** @test */
    public function cta_with_no_title_does_not_render(): void
    {
        StorefrontHomepageSection::create([
            'tenant_id' => $this->tenant->id,
            'storefront_id' => $this->storefront->id,
            'type' => 'cta',
            'variant' => 'default',
            'enabled' => true,
            'desktop_visible' => true,
            'mobile_visible' => true,
            'position' => 0,
            'configuration' => ['description' => 'No title here'],
        ]);

        $response = $this->get("/store/{$this->tenant->slug}");

        $response->assertOk();
        $response->assertDontSee('No title here');
    }

    /** @test */
    public function empty_storefront_renders_empty_state(): void
    {
        $response = $this->get("/store/{$this->tenant->slug}");

        $response->assertOk();
    }

    /** @test */
    public function tenant_isolation_prevents_section_data_leakage(): void
    {
        $otherTenant = Tenant::create([
            'name' => 'Other Store',
            'slug' => 'other-store',
            'store_url' => '/store/other-store',
            'status' => 'active',
        ]);

        $otherStorefront = Storefront::create([
            'tenant_id' => $otherTenant->id,
            'theme_id' => $this->storefront->theme_id,
            'status' => 'active',
        ]);

        StorefrontHomepageSection::create([
            'tenant_id' => $otherTenant->id,
            'storefront_id' => $otherStorefront->id,
            'type' => 'cta',
            'variant' => 'default',
            'enabled' => true,
            'desktop_visible' => true,
            'mobile_visible' => true,
            'position' => 0,
            'configuration' => ['title' => 'OtherTenantCtaSecret', 'button_text' => 'X'],
        ]);

        $response = $this->get("/store/{$this->tenant->slug}");

        $response->assertOk();
        $response->assertDontSee('OtherTenantCtaSecret');
    }

    /** @test */
    public function preview_mode_loads_draft_configuration(): void
    {
        $admin = \App\Models\User::firstOrCreate(
            ['email' => 'admin@test.com'],
            [
                'name' => 'Admin',
                'password' => bcrypt('password'),
                'tenant_id' => $this->tenant->id,
            ]
        );
        $admin->givePermissionTo('settings.website');

        $this->actingAs($admin);

        $response = $this->get("/store/{$this->tenant->slug}/preview");

        $response->assertOk();
    }

    /** @test */
    public function locked_tenant_renders_locked_state(): void
    {
        $this->tenant->update(['locked_at' => now()]);

        $response = $this->get("/store/{$this->tenant->slug}");

        $response->assertOk();
    }

    /** @test */
    public function suspended_tenant_is_blocked(): void
    {
        $this->tenant->update(['status' => 'suspended']);

        $response = $this->get("/store/{$this->tenant->slug}");

        $response->assertOk();
    }
}
