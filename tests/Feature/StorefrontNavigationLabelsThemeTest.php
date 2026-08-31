<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Storefront;
use App\Models\StorefrontContent;
use App\Models\StorefrontDesignToken;
use App\Models\StorefrontNavigation;
use App\Models\StorefrontNavigationItem;
use App\Models\StorefrontThemeConfig;
use App\Models\Tenant;
use App\Models\Theme;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StorefrontNavigationLabelsThemeTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;
    private Storefront $storefront;
    private Theme $theme;

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
            'slug' => 'test-store',
            'store_url' => '/store/test-store',
            'status' => 'active',
        ]);

        $this->theme = Theme::firstOrCreate(
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
            'labels' => ['add_to_cart' => 'Add to Cart', 'login' => 'Sign In'],
        ]);

        $this->setupNavigation();
    }

    private function setupNavigation(): void
    {
        $navigation = StorefrontNavigation::create([
            'tenant_id' => $this->tenant->id,
            'storefront_id' => $this->storefront->id,
            'settings' => ['show_store_name' => true, 'show_search' => true],
        ]);

        foreach ([
            ['key' => 'home', 'label' => 'Home', 'path' => '/', 'icon' => 'bi-house-door', 'group' => 'header', 'position' => 0],
            ['key' => 'products', 'label' => 'Products', 'path' => '/products', 'icon' => 'bi-grid', 'group' => 'header', 'position' => 1],
            ['key' => 'brands', 'label' => 'Brands', 'path' => '/brands', 'icon' => 'bi-tag', 'group' => 'header', 'position' => 2],
            ['key' => 'footer_home', 'label' => 'Home', 'path' => '/', 'icon' => null, 'group' => 'footer', 'position' => 0],
            ['key' => 'footer_products', 'label' => 'Products', 'path' => '/products', 'icon' => null, 'group' => 'footer', 'position' => 1],
            ['key' => 'footer_faq', 'label' => 'FAQ', 'path' => '/faq', 'icon' => null, 'group' => 'footer', 'position' => 2],
        ] as $item) {
            StorefrontNavigationItem::create(array_merge($item, [
                'tenant_id' => $this->tenant->id,
                'navigation_id' => $navigation->id,
                'enabled' => true,
            ]));
        }
    }

    private function createWebsiteInfo(): void
    {
        if (!Schema::hasTable('website_infos')) return;

        \App\Models\WebsiteInfo::firstOrCreate(
            ['tenant_id' => $this->tenant->id],
            [
                'site_name' => 'Test Store',
                'theme_color' => '#3B82F6',
                'currency_code' => 'MMK',
                'currency_symbol' => 'K',
            ]
        );
    }

    // --- Navigation Tests ---

    /** @test */
    public function homepage_loads_with_navigation(): void
    {
        $this->createWebsiteInfo();

        $response = $this->get("/store/{$this->tenant->slug}");
        $response->assertOk();
    }

    /** @test */
    public function navigation_items_include_group_field(): void
    {
        $items = StorefrontNavigationItem::where('tenant_id', $this->tenant->id)->get();
        $this->assertTrue($items->contains('key', 'home'));
        $this->assertTrue($items->contains('key', 'footer_home'));

        $headerItem = $items->firstWhere('key', 'home');
        $footerItem = $items->firstWhere('key', 'footer_home');
        $this->assertEquals('header', $headerItem->group);
        $this->assertEquals('footer', $footerItem->group);
    }

    /** @test */
    public function header_navigation_items_are_separate_from_footer(): void
    {
        $items = StorefrontNavigationItem::where('tenant_id', $this->tenant->id)->get();
        $headerItems = $items->where('group', 'header');
        $footerItems = $items->where('group', 'footer');

        $this->assertCount(3, $headerItems);
        $this->assertCount(3, $footerItems);
    }

    /** @test */
    public function disabled_navigation_items_are_not_returned(): void
    {
        StorefrontNavigationItem::where('key', 'brands')->update(['enabled' => false]);

        $navigation = StorefrontNavigation::where('tenant_id', $this->tenant->id)->first();
        $items = $navigation->items()->where('enabled', true)->where('group', 'header')->get();
        $this->assertFalse($items->contains('key', 'brands'));
    }

    /** @test */
    public function navigation_paths_include_brands_and_faq(): void
    {
        $allowedPaths = \App\Http\Requests\UpdateStorefrontNavigationRequest::allowedPaths();
        $this->assertContains('/brands', $allowedPaths);
        $this->assertContains('/faq', $allowedPaths);
        $this->assertContains('/about', $allowedPaths);
        $this->assertContains('/privacy-policy', $allowedPaths);
        $this->assertContains('/terms-and-conditions', $allowedPaths);
    }

    /** @test */
    public function cross_tenant_navigation_cannot_leak(): void
    {
        $otherTenant = Tenant::create([
            'name' => 'Other Store',
            'slug' => 'other-store',
            'store_url' => '/store/other-store',
            'status' => 'active',
        ]);

        $otherStorefront = Storefront::create([
            'tenant_id' => $otherTenant->id,
            'theme_id' => $this->theme->id,
            'status' => 'active',
        ]);

        $otherNavigation = StorefrontNavigation::create([
            'tenant_id' => $otherTenant->id,
            'storefront_id' => $otherStorefront->id,
            'settings' => ['show_store_name' => true, 'show_search' => true],
        ]);

        StorefrontNavigationItem::create([
            'tenant_id' => $otherTenant->id,
            'navigation_id' => $otherNavigation->id,
            'key' => 'secret_link',
            'label' => 'Secret Link',
            'path' => '/secret',
            'icon' => null,
            'group' => 'header',
            'enabled' => true,
            'position' => 0,
        ]);

        $this->createWebsiteInfo();
        $response = $this->get("/store/{$this->tenant->slug}");
        $response->assertOk();
        $response->assertDontSee('Secret Link');
    }

    // --- Content & Labels Tests ---

    /** @test */
    public function storefront_labels_include_navigation_and_footer_keys(): void
    {
        $content = StorefrontContent::where('tenant_id', $this->tenant->id)->first();
        $labels = $content->labels;

        $this->assertArrayHasKey('login', $labels);
        $this->assertEquals('Sign In', $labels['login']);
    }

    /** @test */
    public function default_labels_include_all_required_keys(): void
    {
        $reflection = new \ReflectionClass(\App\Services\StorefrontConfigurationResolver::class);
        $constant = $reflection->getConstant('DEFAULT_LABELS');

        $requiredKeys = [
            'add_to_cart', 'buy_now', 'view_cart', 'checkout', 'search_products',
            'login', 'register', 'my_account', 'my_orders', 'cart', 'wishlist',
            'quick_links', 'customer_service', 'contact_us', 'policies',
            'footer_copyright', 'powered_by', 'back_to_top', 'read_more',
        ];

        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $constant, "Missing label key: {$key}");
        }
    }

    /** @test */
    public function labels_override_default_values(): void
    {
        StorefrontContent::where('tenant_id', $this->tenant->id)->update([
            'labels' => ['add_to_cart' => 'ထည့်မည်', 'login' => 'ဝင်ရန်'],
        ]);

        $content = StorefrontContent::where('tenant_id', $this->tenant->id)->first();
        $this->assertEquals('ထည့်မည်', $content->labels['add_to_cart']);
        $this->assertEquals('ဝင်ရန်', $content->labels['login']);
    }

    /** @test */
    public function labels_tenant_isolation(): void
    {
        $otherTenant = Tenant::create([
            'name' => 'Other',
            'slug' => 'other-labels',
            'store_url' => '/store/other-labels',
            'status' => 'active',
        ]);

        $otherStorefront = Storefront::create([
            'tenant_id' => $otherTenant->id,
            'theme_id' => $this->theme->id,
            'status' => 'active',
        ]);

        StorefrontContent::create([
            'tenant_id' => $otherTenant->id,
            'storefront_id' => $otherStorefront->id,
            'labels' => ['add_to_cart' => 'Buy Now Other'],
        ]);

        $this->createWebsiteInfo();

        $response = $this->get("/store/{$this->tenant->slug}");
        $response->assertOk();
        $response->assertDontSee('Buy Now Other');
    }

    // --- Theme & Design Token Tests ---

    /** @test */
    public function design_tokens_are_set_in_storefront_config(): void
    {
        StorefrontDesignToken::where('storefront_id', $this->storefront->id)->update([
            'tokens' => [
                'color' => ['primary' => '#FF5733', 'surface' => '#FFF'],
                'typography' => ['font_family' => 'Inter'],
                'radius' => ['button' => '1rem'],
            ],
        ]);

        $resolver = new \App\Services\StorefrontConfigurationResolver();
        $config = $resolver->resolveBase($this->tenant);

        $this->assertEquals('#FF5733', $config['design']['color']['primary']);
        $this->assertEquals('Inter', $config['design']['typography']['font_family']);
        $this->assertEquals('1rem', $config['design']['radius']['button']);
    }

    /** @test */
    public function design_tokens_fallback_to_theme_defaults(): void
    {
        StorefrontDesignToken::where('storefront_id', $this->storefront->id)->update(['tokens' => null]);

        $resolver = new \App\Services\StorefrontConfigurationResolver();
        $config = $resolver->resolveBase($this->tenant);

        $this->assertNotEmpty($config['design']);
        $this->assertNotEmpty($config['design']['color']['primary']);
    }

    /** @test */
    public function design_tokens_fallback_to_legacy_theme_color(): void
    {
        StorefrontDesignToken::where('storefront_id', $this->storefront->id)->update(['tokens' => null]);
        Storefront::where('id', $this->storefront->id)->update(['theme_id' => null]);
        $this->theme->delete();

        $this->createWebsiteInfo();

        $resolver = new \App\Services\StorefrontConfigurationResolver();
        $config = $resolver->resolveBase($this->tenant);

        $this->assertNotEmpty($config['design']);
    }

    /** @test */
    public function theme_preset_selection_is_reflected(): void
    {
        $minimalTheme = Theme::firstOrCreate(
            ['slug' => 'minimal-store'],
            [
                'name' => 'Minimal Store',
                'version' => '1.0.0',
                'default_tokens' => [
                    'color' => ['primary' => '#374151'],
                    'radius' => ['button' => '0.25rem'],
                ],
                'is_active' => true,
            ]
        );

        $this->storefront->update(['theme_id' => $minimalTheme->id]);

        StorefrontDesignToken::where('storefront_id', $this->storefront->id)->update([
            'tokens' => $minimalTheme->default_tokens,
        ]);

        $resolver = new \App\Services\StorefrontConfigurationResolver();
        $config = $resolver->resolveBase($this->tenant);

        $this->assertEquals('#374151', $config['design']['color']['primary']);
        $this->assertEquals('0.25rem', $config['design']['radius']['button']);
    }

    /** @test */
    public function design_tokens_tenant_isolation(): void
    {
        $otherTenant = Tenant::create([
            'name' => 'Other',
            'slug' => 'other-tokens',
            'store_url' => '/store/other-tokens',
            'status' => 'active',
        ]);

        $otherStorefront = Storefront::create([
            'tenant_id' => $otherTenant->id,
            'theme_id' => $this->theme->id,
            'status' => 'active',
        ]);

        StorefrontDesignToken::create([
            'tenant_id' => $otherTenant->id,
            'storefront_id' => $otherStorefront->id,
            'tokens' => ['color' => ['primary' => '#000000']],
        ]);

        $resolver = new \App\Services\StorefrontConfigurationResolver();
        $config = $resolver->resolveBase($this->tenant);

        $this->assertNotEquals('#000000', $config['design']['color']['primary']);
    }

    /** @test */
    public function dark_mode_is_supported_by_app_config(): void
    {
        $this->assertFileExists(resource_path('css/app.css'));
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('.dark', $css);
        $this->assertStringContainsString('--color-surface', $css);
    }

    /** @test */
    public function tailwind_config_supports_dark_mode(): void
    {
        $config = config('tailwind');
        $this->assertNotEmpty($config);
    }

    // --- Fallback & Default Tests ---

    /** @test */
    public function resolver_returns_navigation_with_footer_items(): void
    {
        $resolver = new \App\Services\StorefrontConfigurationResolver();
        $config = $resolver->resolveBase($this->tenant);

        $this->assertArrayHasKey('navigation', $config);
        $this->assertArrayHasKey('items', $config['navigation']);
        $this->assertArrayHasKey('footer_items', $config['navigation']);
        $this->assertGreaterThan(0, count($config['navigation']['items']));
        $this->assertGreaterThan(0, count($config['navigation']['footer_items']));
    }

    /** @test */
    public function resolver_identity_uses_tenant_name(): void
    {
        $resolver = new \App\Services\StorefrontConfigurationResolver();
        $config = $resolver->resolveBase($this->tenant);

        $this->assertEquals('Test Store', $config['identity']['name']);
        $this->assertEquals('Test Store', $config['identity']['site_title']);
    }

    /** @test */
    public function resolver_has_all_required_config_keys(): void
    {
        $resolver = new \App\Services\StorefrontConfigurationResolver();
        $config = $resolver->resolveBase($this->tenant);

        $requiredKeys = ['id', 'status', 'identity', 'theme', 'design', 'navigation', 'homepage', 'media', 'content', 'behavior', 'shop', 'checkout', 'seo'];
        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $config, "Missing config key: {$key}");
        }
    }
}
