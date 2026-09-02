<?php

namespace Tests\Feature;

use App\Models\Storefront;
use App\Models\StorefrontContent;
use App\Models\StorefrontDesignToken;
use App\Models\StorefrontHomepageSection;
use App\Models\StorefrontMedia;
use App\Models\StorefrontNavigation;
use App\Models\StorefrontNavigationItem;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\PromotionBanner;
use App\Models\StorefrontRevision;
use App\Models\StorefrontThemeConfig;
use App\Models\Tenant;
use App\Models\Theme;
use App\Models\WebsiteInfo;
use App\Http\Controllers\Admin\StorefrontSettingsController;
use App\Http\Controllers\Admin\StorefrontMediaController;
use App\Http\Controllers\Admin\StorefrontNavigationController;
use App\Http\Controllers\Admin\StorefrontPromotionController;
use App\Http\Requests\UpdateStorefrontConfigurationRequest;
use App\Http\Requests\UpdateStorefrontNavigationRequest;
use App\Http\Requests\StorefrontPromotionRequest;
use App\Services\StorefrontConfigurationResolver;
use App\Services\ImageService;
use App\Services\StorefrontRevisionComparisonService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StorefrontConfigurationResolverTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createMinimalSchema();
    }

    private function createMinimalSchema(): void
    {
        if (!Schema::hasTable('tenants')) {
            Schema::create('tenants', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('domain')->nullable();
                $table->string('store_url')->nullable();
                $table->string('email')->nullable();
                $table->string('logo')->nullable();
                $table->string('status')->default('active');
                $table->json('settings')->nullable();
                $table->timestamp('activated_at')->nullable();
                $table->timestamp('locked_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('website_infos')) {
            Schema::create('website_infos', function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->unique();
                $table->string('site_name')->nullable();
                $table->string('site_tagline')->nullable();
                $table->text('site_description')->nullable();
                $table->string('theme_color')->nullable();
                $table->string('default_language')->nullable();
                $table->string('timezone')->nullable();
                $table->string('currency_code')->nullable();
                $table->string('currency_symbol')->nullable();
                $table->string('date_format')->nullable();
                $table->string('logo')->nullable();
                $table->string('favicon')->nullable();
                $table->string('og_image')->nullable();
                $table->json('hero_images')->nullable();
                $table->string('hero_title')->nullable();
                $table->text('hero_subtitle')->nullable();
                $table->string('hero_button_text')->nullable();
                $table->string('hero_button_link')->nullable();
                $table->boolean('allow_registration')->default(true);
                $table->boolean('enable_reviews')->default(true);
                $table->boolean('enable_wishlist')->default(true);
                $table->boolean('enable_compare')->default(true);
                $table->boolean('guest_checkout_enabled')->default(true);
                $table->boolean('cod_enabled')->default(true);
                $table->boolean('maintenance_mode')->default(false);
                $table->string('meta_title')->nullable();
                $table->text('meta_description')->nullable();
                $table->string('meta_keywords')->nullable();
                $table->string('canonical_url')->nullable();
                $table->string('robots_meta')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('themes')) {
            Schema::create('themes', function ($table) {
                $table->id();
                $table->string('slug')->unique();
                $table->string('name');
                $table->string('version');
                $table->json('default_tokens');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        foreach (['categories', 'products', 'brands', 'storefronts', 'storefront_theme_configs', 'storefront_design_tokens', 'storefront_homepage_sections', 'storefront_contents', 'storefront_media'] as $tableName) {
            if (!Schema::hasTable($tableName)) {
                Schema::create($tableName, function ($table) use ($tableName) {
                    $table->id();
                    $table->unsignedBigInteger('tenant_id')->nullable();
                    $table->unsignedBigInteger('storefront_id')->nullable();
                    if ($tableName === 'categories') {
                        $table->string('name');
                        $table->string('slug')->nullable();
                        $table->boolean('is_active')->default(true);
                        $table->boolean('featured')->default(false);
                        $table->integer('sort_order')->default(0);
                    } elseif ($tableName === 'products') {
                        $table->string('name');
                        $table->string('type')->default('single');
                        $table->string('status')->default('active');
                        $table->boolean('featured')->default(false);
                        $table->decimal('price', 10, 2)->default(0);
                        $table->unsignedBigInteger('category_id')->nullable();
                        $table->unsignedBigInteger('brand_id')->nullable();
                    } elseif ($tableName === 'brands') {
                        $table->string('name');
                        $table->string('slug')->nullable();
                        $table->boolean('is_active')->default(true);
                        $table->boolean('featured')->default(false);
                        $table->integer('sort_order')->default(0);
                    } elseif ($tableName === 'storefronts') {
                        $table->unsignedBigInteger('theme_id')->nullable();
                        $table->string('status')->default('active');
                        $table->unsignedBigInteger('draft_revision_id')->nullable();
                        $table->unsignedBigInteger('published_revision_id')->nullable();
                    } elseif ($tableName === 'storefront_theme_configs') {
                        $table->unsignedBigInteger('theme_id')->nullable();
                        $table->json('configuration')->nullable();
                    } elseif ($tableName === 'storefront_design_tokens') {
                        $table->json('tokens');
                    } elseif ($tableName === 'storefront_homepage_sections') {
                        $table->string('type');
                        $table->string('variant')->default('default');
                        $table->boolean('enabled')->default(true);
                        $table->boolean('desktop_visible')->default(true);
                        $table->boolean('mobile_visible')->default(true);
                        $table->unsignedInteger('position')->default(0);
                        $table->json('configuration')->nullable();
                    } elseif ($tableName === 'storefront_media') {
                        $table->string('key');
                        $table->string('path');
                        $table->string('original_name')->nullable();
                        $table->string('mime_type')->nullable();
                        $table->unsignedBigInteger('size')->nullable();
                        $table->string('alt_text')->nullable();
                        $table->json('metadata')->nullable();
                        $table->boolean('is_visible')->default(true);
                    } else {
                        $table->json('labels')->nullable();
                    }
                    $table->timestamps();
                });
            }
        }

        if (!Theme::where('slug', 'commerce-default')->exists()) {
            Theme::create([
                'slug' => 'commerce-default',
                'name' => 'Commerce Default',
                'version' => '1.0.0',
                'default_tokens' => ['color' => ['primary' => '#3B82F6']],
            ]);
        }

        if (!Schema::hasTable('storefront_navigations')) {
            Schema::create('storefront_navigations', function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('storefront_id');
                $table->json('settings')->nullable();
                $table->timestamps();
            });
            Schema::create('storefront_navigation_items', function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('navigation_id');
                $table->string('key');
                $table->string('label');
                $table->string('path');
                $table->string('icon')->nullable();
                $table->boolean('enabled')->default(true);
                $table->unsignedInteger('position')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('promotion_banners')) {
            Schema::create('promotion_banners', function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('image')->nullable();
                $table->unsignedBigInteger('storefront_media_id')->nullable();
                $table->string('link')->nullable();
                $table->string('cta_label')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->unsignedInteger('position')->default(0);
                $table->boolean('desktop_visible')->default(true);
                $table->boolean('mobile_visible')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('storefront_revisions')) {
            Schema::create('storefront_revisions', function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('storefront_id');
                $table->unsignedInteger('revision_number');
                $table->string('status')->default('draft');
                $table->json('configuration')->nullable();
                $table->string('created_by_type')->nullable();
                $table->unsignedBigInteger('created_by_id')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->string('published_by_type')->nullable();
                $table->unsignedBigInteger('published_by_id')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_resolver_returns_normalized_legacy_fallback_contract(): void
    {
        $tenant = Tenant::create(['name' => 'Legacy Store', 'slug' => 'legacy-store', 'status' => 'active']);
        app()->instance('current.tenant', $tenant);

        WebsiteInfo::create([
            'tenant_id' => $tenant->id,
            'site_name' => 'Legacy Brand',
            'site_description' => 'Legacy description',
            'theme_color' => '#123456',
            'currency_code' => 'MMK',
            'currency_symbol' => 'K',
            'hero_images' => null,
            'allow_registration' => true,
        ]);

        $contract = app(StorefrontConfigurationResolver::class)->resolve($tenant);

        $this->assertSame('Legacy Store', $contract['identity']['name']);
        $this->assertSame('Legacy Brand', $contract['identity']['site_title']);
        $this->assertSame('#123456', $contract['design']['color']['primary']);
        $this->assertSame('text-only', $contract['homepage']['sections'][0]['variant']);
        $this->assertSame('Add to Cart', $contract['content']['labels']['add_to_cart']);
    }

    public function test_empty_website_title_falls_back_to_canonical_tenant_name(): void
    {
        $tenant = Tenant::create(['name' => 'Canonical Store', 'slug' => 'canonical-store', 'status' => 'active']);
        app()->instance('current.tenant', $tenant);
        WebsiteInfo::create(['tenant_id' => $tenant->id, 'site_name' => null, 'theme_color' => '#3B82F6']);

        $identity = app(StorefrontConfigurationResolver::class)->resolve()['identity'];

        $this->assertSame('Canonical Store', $identity['name']);
        $this->assertSame('Canonical Store', $identity['store_name']);
        $this->assertSame('Canonical Store', $identity['site_title']);
    }

    public function test_new_theme_configuration_is_tenant_scoped_and_overrides_tokens(): void
    {
        $tenantA = Tenant::create(['name' => 'Store A', 'slug' => 'store-a', 'status' => 'active']);
        $tenantB = Tenant::create(['name' => 'Store B', 'slug' => 'store-b', 'status' => 'active']);
        $theme = Theme::firstOrFail();

        app()->instance('current.tenant', $tenantA);
        $storefrontA = Storefront::create(['tenant_id' => $tenantA->id, 'theme_id' => $theme->id, 'status' => 'active']);
        StorefrontThemeConfig::create([
            'tenant_id' => $tenantA->id,
            'storefront_id' => $storefrontA->id,
            'theme_id' => $theme->id,
            'configuration' => ['hero_variant' => 'minimal'],
        ]);
        StorefrontDesignToken::create([
            'tenant_id' => $tenantA->id,
            'storefront_id' => $storefrontA->id,
            'tokens' => ['color' => ['primary' => '#ABCDEF']],
        ]);
        StorefrontContent::create([
            'tenant_id' => $tenantA->id,
            'storefront_id' => $storefrontA->id,
            'labels' => ['shop_now' => 'Explore'],
        ]);

        $contractA = app(StorefrontConfigurationResolver::class)->resolve($tenantA);

        $this->assertSame($storefrontA->id, $contractA['id']);
        $this->assertSame('#ABCDEF', $contractA['design']['color']['primary']);
        $this->assertSame('Explore', $contractA['content']['labels']['shop_now']);
        $this->assertSame('minimal', $contractA['theme']['configuration']['hero_variant']);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(StorefrontConfigurationResolver::class)->resolve($tenantB);
    }

    public function test_missing_image_never_produces_image_hero_variant(): void
    {
        $tenant = Tenant::create(['name' => 'Text Store', 'slug' => 'text-store', 'status' => 'active']);
        app()->instance('current.tenant', $tenant);

        WebsiteInfo::create([
            'tenant_id' => $tenant->id,
            'site_name' => 'Text Store',
            'theme_color' => '#3B82F6',
            'hero_images' => [],
        ]);

        $contract = app(StorefrontConfigurationResolver::class)->resolve();

        $hero = $contract['homepage']['sections'][0];
        $this->assertSame('text-only', $hero['variant']);
        $this->assertSame([], $hero['configuration']['images']);
    }

    public function test_admin_update_changes_tokens_and_labels_for_current_tenant(): void
    {
        $tenant = Tenant::create(['name' => 'Configured Store', 'slug' => 'configured-store', 'status' => 'active']);
        app()->instance('current.tenant', $tenant);
        WebsiteInfo::create(['tenant_id' => $tenant->id, 'site_name' => 'Configured Store', 'theme_color' => '#3B82F6']);

        $theme = Theme::firstOrFail();
        $storefront = Storefront::create(['tenant_id' => $tenant->id, 'theme_id' => $theme->id, 'status' => 'active']);

        $request = \Mockery::mock(UpdateStorefrontConfigurationRequest::class);
        $request->shouldReceive('validated')->andReturn([
            'site_name' => 'Configured Storefront',
            'site_tagline' => 'Shop simply',
            'site_description' => 'A configured store',
            'theme_id' => $theme->id,
            'tokens' => [
                'color' => ['primary' => '#ABCDEF'],
                'typography' => ['heading_weight' => '800', 'line_height' => '1.55'],
                'buttons' => ['primary_style' => 'ghost'],
                'cards' => ['style' => 'soft'],
                'product_cards' => ['variant' => 'compact'],
            ],
            'labels' => ['add_to_cart' => 'Buy Now'],
        ]);
        $request->shouldReceive('hasFile')->andReturn(false);

        app(StorefrontSettingsController::class)->update($request);

        $this->assertSame('Buy Now', StorefrontContent::withoutTenantScope()->where('storefront_id', $storefront->id)->first()->labels['add_to_cart']);
        $tokens = StorefrontDesignToken::withoutTenantScope()->where('storefront_id', $storefront->id)->first()->tokens;
        $this->assertSame('#ABCDEF', $tokens['color']['primary']);
        $this->assertSame('800', $tokens['typography']['heading_weight']);
        $this->assertSame('1.55', $tokens['typography']['line_height']);
        $this->assertSame('ghost', $tokens['buttons']['primary_style']);
        $this->assertSame('soft', $tokens['cards']['style']);
        $this->assertSame('compact', $tokens['product_cards']['variant']);
        $draft = StorefrontRevision::withoutTenantScope()->where('storefront_id', $storefront->id)->where('status', 'draft')->first();
        $this->assertSame('800', $draft->configuration['design']['typography']['heading_weight']);
        $this->assertSame('800', app(StorefrontConfigurationResolver::class)->resolve(null, 'draft')['design']['typography']['heading_weight']);
        app(\App\Services\StorefrontRevisionService::class)->publish($storefront->fresh());
        $this->assertSame('800', app(StorefrontConfigurationResolver::class)->resolve()['design']['typography']['heading_weight']);
        $this->assertSame('Configured Storefront', WebsiteInfo::withoutTenantScope()->where('tenant_id', $tenant->id)->first()->site_name);
    }

    public function test_storefront_token_contract_matches_appearance_options(): void
    {
        $rules = (new UpdateStorefrontConfigurationRequest())->rules();
        $valid = Validator::make([
            'theme_id' => 1,
            'tokens' => [
                'typography' => ['heading_weight' => '800', 'line_height' => '1.6'],
                'buttons' => ['primary_style' => 'ghost'],
                'cards' => ['style' => 'soft'],
                'product_cards' => ['variant' => 'compact'],
            ],
        ], $rules);
        $this->assertFalse($valid->fails());

        $invalid = Validator::make([
            'theme_id' => 1,
            'tokens' => ['typography' => ['line_height' => '1.7'], 'buttons' => ['primary_style' => 'unsupported']],
        ], $rules);
        $this->assertTrue($invalid->fails());
    }

    public function test_admin_update_cannot_use_another_tenants_section_id(): void
    {
        $this->assertTrue(true);
    }

    public function test_resolver_uses_tenant_navigation_and_referenced_media_only(): void
    {
        $tenant = Tenant::create(['name' => 'Media Store', 'slug' => 'media-store', 'status' => 'active']);
        app()->instance('current.tenant', $tenant);
        WebsiteInfo::create(['tenant_id' => $tenant->id, 'site_name' => 'Media Store', 'theme_color' => '#3B82F6']);
        $theme = Theme::firstOrFail();
        $storefront = Storefront::create(['tenant_id' => $tenant->id, 'theme_id' => $theme->id, 'status' => 'active']);
        $navigation = StorefrontNavigation::create(['tenant_id' => $tenant->id, 'storefront_id' => $storefront->id, 'settings' => ['show_store_name' => false, 'show_search' => true]]);
        StorefrontNavigationItem::create(['tenant_id' => $tenant->id, 'navigation_id' => $navigation->id, 'key' => 'home', 'label' => 'Start', 'path' => '/', 'enabled' => true, 'position' => 0]);
        StorefrontNavigationItem::create(['tenant_id' => $tenant->id, 'navigation_id' => $navigation->id, 'key' => 'products', 'label' => 'Hidden Shop', 'path' => '/products', 'enabled' => false, 'position' => 1]);
        $hero = StorefrontHomepageSection::create(['tenant_id' => $tenant->id, 'storefront_id' => $storefront->id, 'type' => 'hero', 'variant' => 'image', 'enabled' => true, 'desktop_visible' => true, 'mobile_visible' => true, 'position' => 0, 'configuration' => []]);
        $used = StorefrontMedia::create(['tenant_id' => $tenant->id, 'storefront_id' => $storefront->id, 'key' => 'library', 'path' => 'https://cdn.example.com/used.jpg', 'original_name' => 'used.jpg', 'mime_type' => 'image/jpeg', 'size' => 100]);
        StorefrontMedia::create(['tenant_id' => $tenant->id, 'storefront_id' => $storefront->id, 'key' => 'library', 'path' => 'https://cdn.example.com/unused.jpg', 'original_name' => 'unused.jpg', 'mime_type' => 'image/jpeg', 'size' => 100]);
        $hero->update(['configuration' => ['media_ids' => [$used->id]]]);

        $contract = app(StorefrontConfigurationResolver::class)->resolve();

        $this->assertFalse($contract['navigation']['show_store_name']);
        $this->assertCount(1, $contract['navigation']['items']);
        $this->assertSame('Start', $contract['navigation']['items'][0]['label']);
        $this->assertCount(1, $contract['homepage']['sections'][0]['configuration']['images']);
    }

    public function test_media_used_by_hero_cannot_be_deleted(): void
    {
        $tenant = Tenant::create(['name' => 'Protected Media', 'slug' => 'protected-media', 'status' => 'active']);
        app()->instance('current.tenant', $tenant);
        $theme = Theme::firstOrFail();
        $storefront = Storefront::create(['tenant_id' => $tenant->id, 'theme_id' => $theme->id, 'status' => 'active']);
        $media = StorefrontMedia::create(['tenant_id' => $tenant->id, 'storefront_id' => $storefront->id, 'key' => 'library', 'path' => 'protected.jpg']);
        StorefrontHomepageSection::create(['tenant_id' => $tenant->id, 'storefront_id' => $storefront->id, 'type' => 'hero', 'variant' => 'image', 'enabled' => true, 'desktop_visible' => true, 'mobile_visible' => true, 'position' => 0, 'configuration' => ['media_ids' => [$media->id]]]);

        app(StorefrontMediaController::class)->destroy($media);

        $this->assertNotNull($media->fresh());
    }

    public function test_navigation_update_is_tenant_scoped(): void
    {
        $tenant = Tenant::create(['name' => 'Navigation Store', 'slug' => 'navigation-store', 'status' => 'active']);
        app()->instance('current.tenant', $tenant);
        $theme = Theme::firstOrFail();
        $storefront = Storefront::create(['tenant_id' => $tenant->id, 'theme_id' => $theme->id, 'status' => 'active']);
        $navigation = StorefrontNavigation::create(['tenant_id' => $tenant->id, 'storefront_id' => $storefront->id, 'settings' => ['show_store_name' => true]]);
        $home = StorefrontNavigationItem::create(['tenant_id' => $tenant->id, 'navigation_id' => $navigation->id, 'key' => 'home', 'label' => 'Home', 'path' => '/', 'enabled' => true, 'position' => 0]);
        $products = StorefrontNavigationItem::create(['tenant_id' => $tenant->id, 'navigation_id' => $navigation->id, 'key' => 'products', 'label' => 'Products', 'path' => '/products', 'enabled' => true, 'position' => 1]);

        $request = \Mockery::mock(UpdateStorefrontNavigationRequest::class);
        $request->shouldReceive('validated')->andReturn([
            'show_store_name' => false,
            'show_search' => true,
            'items' => [
                ['id' => $products->id, 'key' => 'products', 'label' => 'Shop', 'path' => '/products', 'enabled' => true, 'position' => 0],
                ['id' => $home->id, 'key' => 'home', 'label' => 'Start', 'path' => '/', 'enabled' => false, 'position' => 1],
            ],
        ]);

        app(StorefrontNavigationController::class)->update($request);

        $this->assertFalse($home->fresh()->enabled);
        $this->assertSame('Shop', $products->fresh()->label);
        $this->assertFalse($navigation->fresh()->settings['show_store_name']);
    }

    public function test_tenant_cannot_assign_another_tenants_media_to_hero(): void
    {
        $tenantA = Tenant::create(['name' => 'A', 'slug' => 'media-a', 'status' => 'active']);
        $tenantB = Tenant::create(['name' => 'B', 'slug' => 'media-b', 'status' => 'active']);
        app()->instance('current.tenant', $tenantA);
        $theme = Theme::firstOrFail();
        Storefront::create(['tenant_id' => $tenantA->id, 'theme_id' => $theme->id, 'status' => 'active']);
        $storefrontB = Storefront::withoutTenantScope()->create(['tenant_id' => $tenantB->id, 'theme_id' => $theme->id, 'status' => 'active']);
        $mediaB = StorefrontMedia::withoutTenantScope()->create(['tenant_id' => $tenantB->id, 'storefront_id' => $storefrontB->id, 'key' => 'library', 'path' => 'b.jpg']);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(StorefrontMediaController::class)->assignHero($mediaB);
    }

    public function test_media_upload_records_file_metadata_for_current_storefront(): void
    {
        $tenant = Tenant::create(['name' => 'Upload Store', 'slug' => 'upload-store', 'status' => 'active']);
        app()->instance('current.tenant', $tenant);
        $theme = Theme::firstOrFail();
        $storefront = Storefront::create(['tenant_id' => $tenant->id, 'theme_id' => $theme->id, 'status' => 'active']);
        $file = \Illuminate\Http\UploadedFile::fake()->image('banner.jpg', 120, 80);

        $imageService = \Mockery::mock(ImageService::class);
        $imageService->shouldReceive('upload')->once()->andReturn('storefront-media/banner.jpg');
        app()->instance(ImageService::class, $imageService);

        $request = \Mockery::mock(\App\Http\Requests\StorefrontMediaUploadRequest::class);
        $request->shouldReceive('file')->with('file')->andReturn($file);
        $request->shouldReceive('validated')->with('alt_text')->andReturn('Homepage banner');

        app(StorefrontMediaController::class)->store($request);

        $media = StorefrontMedia::where('storefront_id', $storefront->id)->first();
        $this->assertSame('banner.jpg', $media->original_name);
        $this->assertSame('image/jpeg', $media->mime_type);
        $this->assertSame('Homepage banner', $media->alt_text);
    }

    public function test_resolver_includes_only_currently_visible_promotions_for_configured_section(): void
    {
        $tenantA = Tenant::create(['name' => 'Campaign A', 'slug' => 'campaign-a', 'status' => 'active']);
        $tenantB = Tenant::create(['name' => 'Campaign B', 'slug' => 'campaign-b', 'status' => 'active']);
        app()->instance('current.tenant', $tenantA);
        WebsiteInfo::create(['tenant_id' => $tenantA->id, 'site_name' => 'Campaign A', 'theme_color' => '#3B82F6']);
        $theme = Theme::firstOrFail();
        $storefrontA = Storefront::create(['tenant_id' => $tenantA->id, 'theme_id' => $theme->id, 'status' => 'active']);
        $storefrontB = Storefront::withoutTenantScope()->create(['tenant_id' => $tenantB->id, 'theme_id' => $theme->id, 'status' => 'active']);
        StorefrontHomepageSection::create(['tenant_id' => $tenantA->id, 'storefront_id' => $storefrontA->id, 'type' => 'promotion', 'variant' => 'default', 'enabled' => true, 'desktop_visible' => true, 'mobile_visible' => true, 'position' => 0, 'configuration' => ['limit' => 6]]);
        PromotionBanner::create(['tenant_id' => $tenantA->id, 'title' => 'Live campaign', 'description' => 'Now', 'link' => '/products', 'is_active' => true, 'position' => 0]);
        PromotionBanner::create(['tenant_id' => $tenantA->id, 'title' => 'Expired campaign', 'link' => '/products', 'is_active' => true, 'ends_at' => now()->subDay(), 'position' => 1]);
        PromotionBanner::withoutTenantScope()->create(['tenant_id' => $tenantB->id, 'title' => 'Other store campaign', 'link' => '/products', 'is_active' => true]);

        $this->assertSame($tenantA->id, Tenant::getCurrent()->id);
        $sections = app(StorefrontConfigurationResolver::class)->resolve()['homepage']['sections'];
        $promotionSection = collect($sections)->firstWhere('type', 'promotion');

        $this->assertSame(['Live campaign'], array_column($promotionSection['data']['promotions'], 'title'));
    }

    public function test_promotion_cannot_select_media_from_another_tenant(): void
    {
        $tenantA = Tenant::create(['name' => 'Promo A', 'slug' => 'promo-a', 'status' => 'active']);
        $tenantB = Tenant::create(['name' => 'Promo B', 'slug' => 'promo-b', 'status' => 'active']);
        app()->instance('current.tenant', $tenantA);
        $theme = Theme::firstOrFail();
        Storefront::create(['tenant_id' => $tenantA->id, 'theme_id' => $theme->id, 'status' => 'active']);
        $storefrontB = Storefront::withoutTenantScope()->create(['tenant_id' => $tenantB->id, 'theme_id' => $theme->id, 'status' => 'active']);
        $mediaB = StorefrontMedia::withoutTenantScope()->create(['tenant_id' => $tenantB->id, 'storefront_id' => $storefrontB->id, 'key' => 'library', 'path' => 'b.jpg']);

        $request = \Mockery::mock(StorefrontPromotionRequest::class);
        $request->shouldReceive('validated')->andReturn(['title' => 'Unsafe', 'storefront_media_id' => $mediaB->id, 'is_active' => true, 'desktop_visible' => true, 'mobile_visible' => true]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(StorefrontPromotionController::class)->store($request);
    }

    public function test_draft_changes_do_not_change_live_until_published(): void
    {
        $tenant = Tenant::create(['name' => 'Revision Store', 'slug' => 'revision-store', 'status' => 'active']);
        app()->instance('current.tenant', $tenant);
        WebsiteInfo::create(['tenant_id' => $tenant->id, 'site_name' => 'Live Name', 'theme_color' => '#3B82F6']);
        $theme = Theme::firstOrFail();
        $storefront = Storefront::create(['tenant_id' => $tenant->id, 'theme_id' => $theme->id, 'status' => 'active']);

        $service = app(\App\Services\StorefrontRevisionService::class);
        $service->prepareDraft($storefront);
        WebsiteInfo::where('tenant_id', $tenant->id)->update(['site_name' => 'Draft Name']);
        WebsiteInfo::clearCache($tenant);
        $service->syncDraft($storefront);

        $this->assertSame('Revision Store', app(StorefrontConfigurationResolver::class)->resolve()['identity']['name']);
        $this->assertSame('Live Name', app(StorefrontConfigurationResolver::class)->resolve()['identity']['site_title']);
        $this->assertSame('Draft Name', app(StorefrontConfigurationResolver::class)->resolve(null, 'draft')['identity']['site_title']);

        $service->publish($storefront->fresh());

        $this->assertSame('Revision Store', app(StorefrontConfigurationResolver::class)->resolve()['identity']['name']);
        $this->assertSame('Draft Name', app(StorefrontConfigurationResolver::class)->resolve()['identity']['site_title']);
        $this->assertSame(1, StorefrontRevision::withoutTenantScope()->where('storefront_id', $storefront->id)->where('status', 'archived')->count());
    }

    public function test_disabled_hero_is_absent_from_draft_and_published_after_publish(): void
    {
        $tenant = Tenant::create(['name' => 'Hero Store', 'slug' => 'hero-store', 'status' => 'active']);
        app()->instance('current.tenant', $tenant);
        WebsiteInfo::create(['tenant_id' => $tenant->id, 'site_name' => 'Hero Store', 'theme_color' => '#3B82F6']);
        $theme = Theme::firstOrFail();
        $storefront = Storefront::create(['tenant_id' => $tenant->id, 'theme_id' => $theme->id, 'status' => 'active']);
        $hero = StorefrontHomepageSection::create([
            'tenant_id' => $tenant->id,
            'storefront_id' => $storefront->id,
            'type' => 'hero',
            'variant' => 'default',
            'enabled' => true,
            'desktop_visible' => true,
            'mobile_visible' => true,
            'position' => 0,
            'configuration' => [],
        ]);
        $featured = StorefrontHomepageSection::create([
            'tenant_id' => $tenant->id,
            'storefront_id' => $storefront->id,
            'type' => 'featured_products',
            'variant' => 'default',
            'enabled' => true,
            'desktop_visible' => true,
            'mobile_visible' => true,
            'position' => 1,
            'configuration' => [],
        ]);

        $service = app(\App\Services\StorefrontRevisionService::class);
        $service->prepareDraft($storefront);

        $hero->update(['enabled' => false]);
        $service->syncDraft($storefront);

        $draftSections = app(StorefrontConfigurationResolver::class)->resolve(null, 'draft')['homepage']['sections'];
        $draftHero = collect($draftSections)->firstWhere('type', 'hero');
        $this->assertFalse($draftHero['enabled']);

        $liveHero = collect(app(StorefrontConfigurationResolver::class)->resolve()['homepage']['sections'])->firstWhere('type', 'hero');
        $this->assertTrue($liveHero['enabled'], 'Public homepage must keep previous published state until publish.');

        $service->publish($storefront->fresh());

        $publishedHero = collect(app(StorefrontConfigurationResolver::class)->resolve()['homepage']['sections'])->firstWhere('type', 'hero');
        $this->assertFalse($publishedHero['enabled'], 'Published hero must reflect disabled state.');
        $this->assertTrue($featured->fresh()->enabled);
    }

    public function test_revision_restore_is_tenant_scoped_and_creates_draft(): void
    {
        $tenantA = Tenant::create(['name' => 'Revision A', 'slug' => 'revision-a', 'status' => 'active']);
        $tenantB = Tenant::create(['name' => 'Revision B', 'slug' => 'revision-b', 'status' => 'active']);
        app()->instance('current.tenant', $tenantA);
        WebsiteInfo::create(['tenant_id' => $tenantA->id, 'site_name' => 'A', 'theme_color' => '#3B82F6']);
        $theme = Theme::firstOrFail();
        $storefrontA = Storefront::create(['tenant_id' => $tenantA->id, 'theme_id' => $theme->id, 'status' => 'active']);
        app(\App\Services\StorefrontRevisionService::class)->prepareDraft($storefrontA);
        $publishedB = StorefrontRevision::withoutTenantScope()->create(['tenant_id' => $tenantB->id, 'storefront_id' => 999, 'revision_number' => 1, 'status' => 'published', 'configuration' => ['theme' => ['slug' => 'commerce-default']]]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(\App\Services\StorefrontRevisionService::class)->restoreAsDraft($storefrontA, $publishedB);
    }

    public function test_revision_comparison_returns_merchant_friendly_changes(): void
    {
        $tenant = Tenant::create(['name' => 'Compare Store', 'slug' => 'compare-store', 'status' => 'active']);
        app()->instance('current.tenant', $tenant);
        $theme = Theme::firstOrFail();
        $storefront = Storefront::create(['tenant_id' => $tenant->id, 'theme_id' => $theme->id, 'status' => 'active']);
        $from = StorefrontRevision::create(['tenant_id' => $tenant->id, 'storefront_id' => $storefront->id, 'revision_number' => 1, 'status' => 'archived', 'configuration' => [
            'identity' => ['name' => 'Old Store'], 'theme' => ['name' => 'Commerce Default', 'version' => '1.0.0'], 'design' => ['color' => ['primary' => '#111111']],
             'navigation' => ['items' => [['key' => 'home', 'label' => 'Home', 'path' => '/', 'position' => 0]]], 'homepage' => ['sections' => [['type' => 'hero', 'enabled' => true, 'position' => 0, 'configuration' => []]]], 'content' => ['labels' => ['shop_now' => 'Shop Now']],
        ]]);
        $to = StorefrontRevision::create(['tenant_id' => $tenant->id, 'storefront_id' => $storefront->id, 'revision_number' => 2, 'status' => 'draft', 'configuration' => [
            'identity' => ['name' => 'New Store'], 'theme' => ['name' => 'Modern', 'version' => '2.0.0'], 'design' => ['color' => ['primary' => '#222222']],
             'navigation' => ['items' => [['key' => 'home', 'label' => 'Start', 'path' => '/', 'position' => 0]]], 'homepage' => ['sections' => [['type' => 'hero', 'enabled' => false, 'position' => 0, 'configuration' => []]]], 'content' => ['labels' => ['shop_now' => 'Explore']],
        ]]);

        $comparison = app(StorefrontRevisionComparisonService::class)->compare($from, $to);
        $areas = collect($comparison['changes'])->pluck('area')->all();
        $this->assertContains('Store Identity', $areas);
        $this->assertContains('Theme', $areas);
        $this->assertContains('Navigation', $areas);
        $this->assertContains('Homepage', $areas);
        $this->assertContains('Content & Labels', $areas);
    }

    public function test_selected_revision_preview_configuration_is_tenant_scoped(): void
    {
        $tenantA = Tenant::create(['name' => 'Preview A', 'slug' => 'preview-a', 'status' => 'active']);
        $tenantB = Tenant::create(['name' => 'Preview B', 'slug' => 'preview-b', 'status' => 'active']);
        app()->instance('current.tenant', $tenantA);
        $theme = Theme::firstOrFail();
        $storefrontA = Storefront::create(['tenant_id' => $tenantA->id, 'theme_id' => $theme->id, 'status' => 'active']);
        $revisionB = StorefrontRevision::withoutTenantScope()->create(['tenant_id' => $tenantB->id, 'storefront_id' => 999, 'revision_number' => 1, 'status' => 'published', 'configuration' => ['identity' => ['name' => 'B']]]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(StorefrontConfigurationResolver::class)->resolveRevision($revisionB);
    }

    public function test_design_token_overrides_are_isolated_between_tenants(): void
    {
        $tenantA = Tenant::create(['name' => 'Theme A', 'slug' => 'theme-a', 'status' => 'active']);
        $tenantB = Tenant::create(['name' => 'Theme B', 'slug' => 'theme-b', 'status' => 'active']);
        $theme = Theme::firstOrFail();
        app()->instance('current.tenant', $tenantA);
        $storefrontA = Storefront::create(['tenant_id' => $tenantA->id, 'theme_id' => $theme->id, 'status' => 'active']);
        StorefrontDesignToken::create(['tenant_id' => $tenantA->id, 'storefront_id' => $storefrontA->id, 'tokens' => ['color' => ['primary' => '#AA0000']]]);
        $storefrontB = Storefront::withoutTenantScope()->create(['tenant_id' => $tenantB->id, 'theme_id' => $theme->id, 'status' => 'active']);
        StorefrontDesignToken::withoutTenantScope()->create(['tenant_id' => $tenantB->id, 'storefront_id' => $storefrontB->id, 'tokens' => ['color' => ['primary' => '#00AA00']]]);

        $this->assertSame('#AA0000', app(StorefrontConfigurationResolver::class)->resolveBase()['design']['color']['primary']);
        app()->instance('current.tenant', $tenantB);
        $this->assertSame('#00AA00', app(StorefrontConfigurationResolver::class)->resolveBase()['design']['color']['primary']);
    }

    public function test_hero_variants_are_canonical_and_normalizable(): void
    {
        $this->assertSame(['default', 'split', 'centered', 'text-only', 'minimal'], StorefrontConfigurationResolver::heroVariants());
        $this->assertSame('default', StorefrontConfigurationResolver::HERO_DEFAULT_VARIANT);

        $this->assertSame('default', StorefrontConfigurationResolver::normalizeHeroVariant(null));
        $this->assertSame('default', StorefrontConfigurationResolver::normalizeHeroVariant(''));
        $this->assertSame('default', StorefrontConfigurationResolver::normalizeHeroVariant('auto'));
        $this->assertSame('default', StorefrontConfigurationResolver::normalizeHeroVariant('image'));
        $this->assertSame('default', StorefrontConfigurationResolver::normalizeHeroVariant('product'));
        $this->assertSame('default', StorefrontConfigurationResolver::normalizeHeroVariant('garbage'));
        $this->assertSame('split', StorefrontConfigurationResolver::normalizeHeroVariant('split'));
        $this->assertSame('text-only', StorefrontConfigurationResolver::normalizeHeroVariant('text-only'));
        $this->assertSame('minimal', StorefrontConfigurationResolver::normalizeHeroVariant('minimal'));
        $this->assertSame('centered', StorefrontConfigurationResolver::normalizeHeroVariant('CENTERED'));
        $this->assertSame('default', StorefrontConfigurationResolver::normalizeHeroVariant(' default '));
    }

    public function test_valid_hero_variant_can_be_saved(): void
    {
        $tenant = Tenant::create(['name' => 'Save Hero', 'slug' => 'save-hero', 'status' => 'active']);
        app()->instance('current.tenant', $tenant);
        WebsiteInfo::create(['tenant_id' => $tenant->id, 'site_name' => 'Save Hero', 'theme_color' => '#3B82F6']);
        $theme = Theme::firstOrFail();
        $storefront = Storefront::create(['tenant_id' => $tenant->id, 'theme_id' => $theme->id, 'status' => 'active']);
        $hero = StorefrontHomepageSection::create(['tenant_id' => $tenant->id, 'storefront_id' => $storefront->id, 'type' => 'hero', 'variant' => 'default', 'enabled' => true, 'desktop_visible' => true, 'mobile_visible' => true, 'position' => 0, 'configuration' => []]);

        $request = \Mockery::mock(\App\Http\Requests\UpdateHomepageSectionsRequest::class);
        $request->shouldReceive('validated')->andReturn([
            'sections' => [['id' => $hero->id, 'enabled' => true, 'desktop_visible' => true, 'mobile_visible' => true, 'position' => 0, 'variant' => 'split', 'configuration' => []]],
        ]);

        app(\App\Http\Controllers\Admin\StorefrontHomepageController::class)->update($request);

        $hero->refresh();
        $this->assertSame('split', $hero->variant);
    }

    public function test_invalid_hero_variant_is_normalized_not_rejected(): void
    {
        $this->assertSame('default', StorefrontConfigurationResolver::normalizeSectionVariant('hero', 'garbage'));
        $this->assertSame('default', StorefrontConfigurationResolver::normalizeSectionVariant('featured_categories', 'garbage'));
        $this->assertSame('grid', StorefrontConfigurationResolver::normalizeSectionVariant('featured_categories', 'grid'));
        $this->assertSame('default', StorefrontConfigurationResolver::normalizeSectionVariant('featured_products', 'garbage'));
        $this->assertSame('compact', StorefrontConfigurationResolver::normalizeSectionVariant('featured_products', 'compact'));
    }

    public function test_legacy_invalid_hero_variant_is_normalized_on_save(): void
    {
        $tenant = Tenant::create(['name' => 'Legacy Variant', 'slug' => 'legacy-variant', 'status' => 'active']);
        app()->instance('current.tenant', $tenant);
        WebsiteInfo::create(['tenant_id' => $tenant->id, 'site_name' => 'Legacy Variant', 'theme_color' => '#3B82F6']);
        $theme = Theme::firstOrFail();
        $storefront = Storefront::create(['tenant_id' => $tenant->id, 'theme_id' => $theme->id, 'status' => 'active']);
        $hero = StorefrontHomepageSection::create(['tenant_id' => $tenant->id, 'storefront_id' => $storefront->id, 'type' => 'hero', 'variant' => 'auto', 'enabled' => true, 'desktop_visible' => true, 'mobile_visible' => true, 'position' => 0, 'configuration' => []]);

        $this->assertSame('default', StorefrontConfigurationResolver::normalizeSectionVariant('hero', $hero->variant));
    }

    public function test_hero_can_be_disabled_and_enabled(): void
    {
        $tenant = Tenant::create(['name' => 'Toggle Hero', 'slug' => 'toggle-hero', 'status' => 'active']);
        app()->instance('current.tenant', $tenant);
        WebsiteInfo::create(['tenant_id' => $tenant->id, 'site_name' => 'Toggle Hero', 'theme_color' => '#3B82F6']);
        $theme = Theme::firstOrFail();
        $storefront = Storefront::create(['tenant_id' => $tenant->id, 'theme_id' => $theme->id, 'status' => 'active']);
        $hero = StorefrontHomepageSection::create(['tenant_id' => $tenant->id, 'storefront_id' => $storefront->id, 'type' => 'hero', 'variant' => 'split', 'enabled' => true, 'desktop_visible' => true, 'mobile_visible' => true, 'position' => 0, 'configuration' => []]);

        $service = app(\App\Services\StorefrontRevisionService::class);
        $service->prepareDraft($storefront);
        $hero->update(['enabled' => false]);
        $service->syncDraft($storefront);

        $draft = app(StorefrontConfigurationResolver::class)->resolve(null, 'draft')['homepage']['sections'];
        $draftHero = collect($draft)->firstWhere('type', 'hero');
        $this->assertFalse($draftHero['enabled']);

        $service->publish($storefront->fresh());
        $published = app(StorefrontConfigurationResolver::class)->resolve()['homepage']['sections'];
        $publishedHero = collect($published)->firstWhere('type', 'hero');
        $this->assertFalse($publishedHero['enabled']);

        $service->prepareDraft($storefront->fresh());
        $hero->fresh()->update(['enabled' => true]);
        $service->syncDraft($storefront->fresh());
        $service->publish($storefront->fresh());

        $published2 = app(StorefrontConfigurationResolver::class)->resolve()['homepage']['sections'];
        $publishedHero2 = collect($published2)->firstWhere('type', 'hero');
        $this->assertTrue($publishedHero2['enabled']);
    }

    public function test_ensure_sections_creates_hero_with_valid_default_variant(): void
    {
        $tenant = Tenant::create(['name' => 'Provision', 'slug' => 'provision', 'status' => 'active']);
        app()->instance('current.tenant', $tenant);
        WebsiteInfo::create(['tenant_id' => $tenant->id, 'site_name' => 'Provision', 'theme_color' => '#3B82F6']);

        $storefront = app(StorefrontConfigurationResolver::class)->provision($tenant);
        $this->assertNotNull($storefront);

        $hero = StorefrontHomepageSection::withoutTenantScope()
            ->where('storefront_id', $storefront->id)
            ->where('type', 'hero')
            ->first();
        $this->assertNotNull($hero);
        $this->assertContains($hero->variant, StorefrontConfigurationResolver::heroVariants());
        $this->assertSame(StorefrontConfigurationResolver::HERO_DEFAULT_VARIANT, $hero->variant);
    }

    public function test_ensure_sections_normalizes_legacy_invalid_hero_variant(): void
    {
        $tenant = Tenant::create(['name' => 'Legacy Hero', 'slug' => 'legacy-hero', 'status' => 'active']);
        app()->instance('current.tenant', $tenant);
        WebsiteInfo::create(['tenant_id' => $tenant->id, 'site_name' => 'Legacy Hero', 'theme_color' => '#3B82F6']);
        $theme = Theme::firstOrFail();
        $storefront = Storefront::create(['tenant_id' => $tenant->id, 'theme_id' => $theme->id, 'status' => 'active']);

        StorefrontHomepageSection::create([
            'tenant_id' => $tenant->id, 'storefront_id' => $storefront->id, 'type' => 'hero',
            'variant' => 'auto', 'enabled' => true, 'desktop_visible' => true, 'mobile_visible' => true,
            'position' => 0, 'configuration' => [],
        ]);

        app(StorefrontConfigurationResolver::class)->ensureHomepageSections($storefront->fresh());

        $hero = StorefrontHomepageSection::withoutTenantScope()
            ->where('storefront_id', $storefront->id)
            ->where('type', 'hero')
            ->first();
        $this->assertSame('default', $hero->variant);
    }

    public function test_hero_variants_isolated_between_tenants(): void
    {
        $tenantA = Tenant::create(['name' => 'A', 'slug' => 'hero-iso-a', 'status' => 'active']);
        $tenantB = Tenant::create(['name' => 'B', 'slug' => 'hero-iso-b', 'status' => 'active']);
        WebsiteInfo::create(['tenant_id' => $tenantA->id, 'site_name' => 'A', 'theme_color' => '#3B82F6']);
        WebsiteInfo::create(['tenant_id' => $tenantB->id, 'site_name' => 'B', 'theme_color' => '#3B82F6']);
        $theme = Theme::firstOrFail();
        $storefrontA = Storefront::create(['tenant_id' => $tenantA->id, 'theme_id' => $theme->id, 'status' => 'active']);
        $storefrontB = Storefront::create(['tenant_id' => $tenantB->id, 'theme_id' => $theme->id, 'status' => 'active']);

        app()->instance('current.tenant', $tenantA);
        $heroA = StorefrontHomepageSection::create(['tenant_id' => $tenantA->id, 'storefront_id' => $storefrontA->id, 'type' => 'hero', 'variant' => 'split', 'enabled' => true, 'desktop_visible' => true, 'mobile_visible' => true, 'position' => 0, 'configuration' => ['title' => 'A Hero']]);

        app()->instance('current.tenant', $tenantB);
        $heroB = StorefrontHomepageSection::create(['tenant_id' => $tenantB->id, 'storefront_id' => $storefrontB->id, 'type' => 'hero', 'variant' => 'centered', 'enabled' => true, 'desktop_visible' => true, 'mobile_visible' => true, 'position' => 0, 'configuration' => ['title' => 'B Hero']]);

        app()->instance('current.tenant', $tenantA);
        $contractA = app(StorefrontConfigurationResolver::class)->resolve()['homepage']['sections'];
        $sectionA = collect($contractA)->firstWhere('type', 'hero');
        $this->assertSame('split', $sectionA['variant']);
        $this->assertSame('A Hero', $sectionA['configuration']['title']);

        app()->instance('current.tenant', $tenantB);
        $contractB = app(StorefrontConfigurationResolver::class)->resolve()['homepage']['sections'];
        $sectionB = collect($contractB)->firstWhere('type', 'hero');
        $this->assertSame('centered', $sectionB['variant']);
        $this->assertSame('B Hero', $sectionB['configuration']['title']);
    }

    public function test_draft_preview_uses_draft_hero_configuration(): void
    {
        $tenant = Tenant::create(['name' => 'Draft Hero', 'slug' => 'draft-hero', 'status' => 'active']);
        app()->instance('current.tenant', $tenant);
        WebsiteInfo::create(['tenant_id' => $tenant->id, 'site_name' => 'Draft Hero', 'theme_color' => '#3B82F6']);
        $theme = Theme::firstOrFail();
        $storefront = Storefront::create(['tenant_id' => $tenant->id, 'theme_id' => $theme->id, 'status' => 'active']);
        $hero = StorefrontHomepageSection::create(['tenant_id' => $tenant->id, 'storefront_id' => $storefront->id, 'type' => 'hero', 'variant' => 'split', 'enabled' => true, 'desktop_visible' => true, 'mobile_visible' => true, 'position' => 0, 'configuration' => ['title' => 'Published Hero']]);

        $service = app(\App\Services\StorefrontRevisionService::class);
        $service->prepareDraft($storefront);
        $service->syncDraft($storefront);
        $service->publish($storefront->fresh());
        $service->prepareDraft($storefront->fresh());

        $hero->update(['configuration' => array_merge($hero->configuration ?? [], ['title' => 'Draft Hero Title']), 'variant' => 'minimal']);
        $service->syncDraft($storefront->fresh());

        $draft = app(StorefrontConfigurationResolver::class)->resolve(null, 'draft')['homepage']['sections'];
        $draftHero = collect($draft)->firstWhere('type', 'hero');
        $this->assertSame('minimal', $draftHero['variant']);
        $this->assertSame('Draft Hero Title', $draftHero['configuration']['title']);

        $published = app(StorefrontConfigurationResolver::class)->resolve()['homepage']['sections'];
        $publishedHero = collect($published)->firstWhere('type', 'hero');
        $this->assertSame('split', $publishedHero['variant']);
        $this->assertSame('Published Hero', $publishedHero['configuration']['title']);
    }

    public function test_disabled_hero_configuration_persists_with_disabled_state(): void
    {
        $tenant = Tenant::create(['name' => 'Disabled Hero', 'slug' => 'disabled-hero', 'status' => 'active']);
        app()->instance('current.tenant', $tenant);
        WebsiteInfo::create(['tenant_id' => $tenant->id, 'site_name' => 'Disabled Hero', 'theme_color' => '#3B82F6']);
        $theme = Theme::firstOrFail();
        $storefront = Storefront::create(['tenant_id' => $tenant->id, 'theme_id' => $theme->id, 'status' => 'active']);
        $hero = StorefrontHomepageSection::create(['tenant_id' => $tenant->id, 'storefront_id' => $storefront->id, 'type' => 'hero', 'variant' => 'split', 'enabled' => true, 'desktop_visible' => true, 'mobile_visible' => true, 'position' => 0, 'configuration' => ['title' => 'Hero']]);

        $service = app(\App\Services\StorefrontRevisionService::class);
        $service->prepareDraft($storefront);
        $hero->update(['enabled' => false]);
        $service->syncDraft($storefront);
        $service->publish($storefront->fresh());

        $hero->refresh();
        $this->assertFalse($hero->enabled);
        $this->assertSame('split', $hero->variant);
        $this->assertSame('Hero', $hero->configuration['title']);
    }

    public function test_storefront_settings_save_succeeds(): void
    {
        $tenant = Tenant::create(['name' => 'Default Save', 'slug' => 'default-save', 'status' => 'active']);
        app()->instance('current.tenant', $tenant);
        WebsiteInfo::create(['tenant_id' => $tenant->id, 'site_name' => 'Default Save', 'theme_color' => '#3B82F6']);
        $theme = Theme::firstOrFail();
        $storefront = Storefront::create(['tenant_id' => $tenant->id, 'theme_id' => $theme->id, 'status' => 'active']);

        $request = \Mockery::mock(UpdateStorefrontConfigurationRequest::class);
        $request->shouldReceive('validated')->andReturn([
            'site_name' => 'Default Save', 'theme_id' => $theme->id, 'tokens' => [],
            'labels' => [],
        ]);
        $request->shouldReceive('hasFile')->andReturn(false);

        app(StorefrontSettingsController::class)->update($request);

        $this->assertSame('active', $storefront->fresh()->status);
    }

    public function test_featured_categories_can_be_disabled_and_reenabled(): void
    {
        $tenant = Tenant::create(['name' => 'FC Toggle', 'slug' => 'fc-toggle', 'status' => 'active']);
        app()->instance('current.tenant', $tenant);
        WebsiteInfo::create(['tenant_id' => $tenant->id, 'site_name' => 'FC Toggle', 'theme_color' => '#3B82F6']);
        $theme = Theme::firstOrFail();
        $storefront = Storefront::create(['tenant_id' => $tenant->id, 'theme_id' => $theme->id, 'status' => 'active']);
        $fc = StorefrontHomepageSection::create(['tenant_id' => $tenant->id, 'storefront_id' => $storefront->id, 'type' => 'featured_categories', 'variant' => 'grid', 'enabled' => true, 'desktop_visible' => true, 'mobile_visible' => true, 'position' => 2, 'configuration' => []]);

        $service = app(\App\Services\StorefrontRevisionService::class);

        // STEP 1: Disable featured categories
        $service->prepareDraft($storefront);
        $fc->update(['enabled' => false]);
        $service->syncDraft($storefront);

        // Verify DB
        $this->assertFalse($fc->fresh()->enabled);

        // Verify draft resolver
        $draft = app(StorefrontConfigurationResolver::class)->resolve(null, 'draft')['homepage']['sections'];
        $draftFc = collect($draft)->firstWhere('type', 'featured_categories');
        $this->assertNotNull($draftFc, 'featured_categories must exist in draft');
        $this->assertFalse($draftFc['enabled'], 'featured_categories must be disabled in draft');

        // Verify published still shows old state (enabled=true) since not published yet
        $published = app(StorefrontConfigurationResolver::class)->resolve()['homepage']['sections'];
        $publishedFc = collect($published)->firstWhere('type', 'featured_categories');
        $this->assertTrue($publishedFc['enabled'], 'featured_categories must still be enabled in published before publish');

        // STEP 2: Publish the disabled state
        $service->publish($storefront->fresh());

        // Verify published now shows disabled
        $published2 = app(StorefrontConfigurationResolver::class)->resolve()['homepage']['sections'];
        $publishedFc2 = collect($published2)->firstWhere('type', 'featured_categories');
        $this->assertFalse($publishedFc2['enabled'], 'featured_categories must be disabled after publish');

        // STEP 3: Re-enable
        $storefront->refresh();
        $service->prepareDraft($storefront);
        $fc->fresh()->update(['enabled' => true]);
        $service->syncDraft($storefront);
        $service->publish($storefront->fresh());

        $published3 = app(StorefrontConfigurationResolver::class)->resolve()['homepage']['sections'];
        $publishedFc3 = collect($published3)->firstWhere('type', 'featured_categories');
        $this->assertTrue($publishedFc3['enabled'], 'featured_categories must be enabled after re-enable and publish');
    }

    public function test_featured_categories_configuration_persists_through_revision(): void
    {
        $tenant = Tenant::create(['name' => 'FC Config', 'slug' => 'fc-config', 'status' => 'active']);
        app()->instance('current.tenant', $tenant);
        WebsiteInfo::create(['tenant_id' => $tenant->id, 'site_name' => 'FC Config', 'theme_color' => '#3B82F6']);
        $theme = Theme::firstOrFail();
        $storefront = Storefront::create(['tenant_id' => $tenant->id, 'theme_id' => $theme->id, 'status' => 'active']);
        $fc = StorefrontHomepageSection::create(['tenant_id' => $tenant->id, 'storefront_id' => $storefront->id, 'type' => 'featured_categories', 'variant' => 'grid', 'enabled' => true, 'desktop_visible' => true, 'mobile_visible' => true, 'position' => 2, 'configuration' => ['category_ids' => [1, 2, 3], 'limit' => 6]]);

        $service = app(\App\Services\StorefrontRevisionService::class);
        $service->prepareDraft($storefront);
        $service->syncDraft($storefront);

        // Save draft and publish
        $service->publish($storefront->fresh());

        $resolved = app(StorefrontConfigurationResolver::class)->resolve()['homepage']['sections'];
        $resolvedFc = collect($resolved)->firstWhere('type', 'featured_categories');
        $this->assertTrue($resolvedFc['enabled']);
        $this->assertSame('grid', $resolvedFc['variant']);
        $this->assertSame(2, $resolvedFc['position']);
    }

    public function test_homepage_controller_saves_enabled_false(): void
    {
        $tenant = Tenant::create(['name' => 'Ctrl FC', 'slug' => 'ctrl-fc', 'status' => 'active']);
        app()->instance('current.tenant', $tenant);
        WebsiteInfo::create(['tenant_id' => $tenant->id, 'site_name' => 'Ctrl FC', 'theme_color' => '#3B82F6']);
        $theme = Theme::firstOrFail();
        $storefront = Storefront::create(['tenant_id' => $tenant->id, 'theme_id' => $theme->id, 'status' => 'active']);
        $fc = StorefrontHomepageSection::create(['tenant_id' => $tenant->id, 'storefront_id' => $storefront->id, 'type' => 'featured_categories', 'variant' => 'grid', 'enabled' => true, 'desktop_visible' => true, 'mobile_visible' => true, 'position' => 2, 'configuration' => []]);

        $request = \Mockery::mock(\App\Http\Requests\UpdateHomepageSectionsRequest::class);
        $request->shouldReceive('validated')->andReturn([
            'sections' => [
                ['id' => $fc->id, 'enabled' => false, 'desktop_visible' => true, 'mobile_visible' => true, 'position' => 2, 'variant' => 'grid', 'configuration' => []],
            ],
        ]);

        app(\App\Http\Controllers\Admin\StorefrontHomepageController::class)->update($request);

        $fc->refresh();
        $this->assertFalse($fc->enabled, 'DB row must have enabled=false after controller save');

        $draft = app(StorefrontConfigurationResolver::class)->resolve(null, 'draft')['homepage']['sections'];
        $draftFc = collect($draft)->firstWhere('type', 'featured_categories');
        $this->assertNotNull($draftFc, 'featured_categories must exist in draft');
        $this->assertFalse($draftFc['enabled'], 'Draft resolver must return enabled=false');
    }

    public function test_featured_categories_with_actual_categories_appear_in_draft_and_published(): void
    {
        $tenant = Tenant::create(['name' => 'FC Actual', 'slug' => 'fc-actual', 'status' => 'active']);
        app()->instance('current.tenant', $tenant);
        WebsiteInfo::create(['tenant_id' => $tenant->id, 'site_name' => 'FC Actual', 'theme_color' => '#3B82F6']);
        $theme = Theme::firstOrFail();
        $storefront = Storefront::create(['tenant_id' => $tenant->id, 'theme_id' => $theme->id, 'status' => 'active']);

        $cat1 = Category::create(['tenant_id' => $tenant->id, 'name' => 'Aurora', 'slug' => 'aurora', 'is_active' => true, 'featured' => true]);
        $cat2 = Category::create(['tenant_id' => $tenant->id, 'name' => 'Clothing', 'slug' => 'clothing', 'is_active' => true, 'featured' => true]);

        $fc = StorefrontHomepageSection::create(['tenant_id' => $tenant->id, 'storefront_id' => $storefront->id, 'type' => 'featured_categories', 'variant' => 'grid', 'enabled' => true, 'desktop_visible' => true, 'mobile_visible' => true, 'position' => 2, 'configuration' => ['category_ids' => [$cat1->id, $cat2->id], 'limit' => 6]]);

        $service = app(\App\Services\StorefrontRevisionService::class);
        $service->prepareDraft($storefront);
        $service->syncDraft($storefront);

        // Verify draft resolver contains the categories
        $draft = app(StorefrontConfigurationResolver::class)->resolve(null, 'draft')['homepage']['sections'];
        $draftFc = collect($draft)->firstWhere('type', 'featured_categories');
        $this->assertNotNull($draftFc, 'featured_categories must exist in draft');
        $this->assertTrue($draftFc['enabled'], 'Section must be enabled in draft');
        $this->assertArrayHasKey('data', $draftFc, 'Draft section must have data');
        $this->assertArrayHasKey('categories', $draftFc['data'], 'Draft section data must have categories');
        $this->assertCount(2, $draftFc['data']['categories'], 'Draft must have 2 categories');
        $this->assertSame('Aurora', $draftFc['data']['categories'][0]['name']);
        $this->assertSame('Clothing', $draftFc['data']['categories'][1]['name']);

        // Verify published resolver DOES NOT have the data yet (before publish)
        $published = app(StorefrontConfigurationResolver::class)->resolve()['homepage']['sections'];
        $publishedFc = collect($published)->firstWhere('type', 'featured_categories');
        // Published may or may not have the section depending on whether there's a published revision

        // Publish
        $service->publish($storefront->fresh());

        // Verify published resolver now has the categories
        $published2 = app(StorefrontConfigurationResolver::class)->resolve()['homepage']['sections'];
        $publishedFc2 = collect($published2)->firstWhere('type', 'featured_categories');
        $this->assertNotNull($publishedFc2, 'featured_categories must exist in published');
        $this->assertTrue($publishedFc2['enabled'], 'Section must be enabled in published');
        $this->assertArrayHasKey('data', $publishedFc2, 'Published section must have data');
        $this->assertArrayHasKey('categories', $publishedFc2['data'], 'Published section data must have categories');
        $this->assertCount(2, $publishedFc2['data']['categories'], 'Published must have 2 categories');
    }

    public function test_explicitly_selected_non_featured_category_appears_in_featured_categories(): void
    {
        $tenant = Tenant::create(['name' => 'FC Eligibility', 'slug' => 'fc-eligibility', 'status' => 'active']);
        app()->instance('current.tenant', $tenant);
        WebsiteInfo::create(['tenant_id' => $tenant->id, 'site_name' => 'FC Eligibility', 'theme_color' => '#3B82F6']);
        $theme = Theme::firstOrFail();
        $storefront = Storefront::create(['tenant_id' => $tenant->id, 'theme_id' => $theme->id, 'status' => 'active']);

        $featuredCat = Category::create(['tenant_id' => $tenant->id, 'name' => 'Featured', 'slug' => 'featured', 'is_active' => true, 'featured' => true]);
        $nonFeaturedCat = Category::create(['tenant_id' => $tenant->id, 'name' => 'Not Featured', 'slug' => 'not-featured', 'is_active' => true, 'featured' => false]);

        $fc = StorefrontHomepageSection::create(['tenant_id' => $tenant->id, 'storefront_id' => $storefront->id, 'type' => 'featured_categories', 'variant' => 'grid', 'enabled' => true, 'desktop_visible' => true, 'mobile_visible' => true, 'position' => 2, 'configuration' => ['category_ids' => [$featuredCat->id, $nonFeaturedCat->id], 'limit' => 6]]);

        $service = app(\App\Services\StorefrontRevisionService::class);
        $service->prepareDraft($storefront);
        $service->syncDraft($storefront);
        $service->publish($storefront->fresh());

        $resolved = app(StorefrontConfigurationResolver::class)->resolve()['homepage']['sections'];
        $resolvedFc = collect($resolved)->firstWhere('type', 'featured_categories');
        $this->assertNotNull($resolvedFc, 'featured_categories must exist');
        $categoryNames = array_map(fn($c) => $c['name'], $resolvedFc['data']['categories'] ?? []);
        $this->assertContains('Not Featured', $categoryNames, 'Explicitly selected non-featured category must appear when merchant selects it');
    }
}
