<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Product;
use App\Models\Role;
use App\Models\Storefront;
use App\Models\StorefrontMedia;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Services\ImageService;
use App\Services\MediaCleanupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MediaHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        if (!Permission::where('name', 'settings.website')->exists()) {
            Permission::create(['name' => 'settings.website', 'guard_name' => 'web']);
        }

        config()->set('identity.use_accounts', true);
    }

    public function test_search_filters_by_filename_and_paginates(): void
    {
        [$tenant, $owner] = $this->seedStore('search-shop');

        for ($i = 1; $i <= 30; $i++) {
            $this->makeMedia($tenant, "banner-{$i}.png", "storefront-media/banner-{$i}.png");
        }
        $this->makeMedia($tenant, 'logo-special.png', 'storefront-media/logo-special.png');

        $response = $this->actingAs($owner, 'accounts')
            ->getJson("/store/{$tenant->slug}/admin/storefront/media/search?q=banner&per_page=10");

        $response->assertStatus(200);
        $response->assertJsonPath('total', 30);
        $response->assertJsonPath('per_page', 10);
        $response->assertJsonPath('last_page', 3);
        $this->assertCount(10, $response->json('data'));

        $page2 = $this->actingAs($owner, 'accounts')
            ->getJson("/store/{$tenant->slug}/admin/storefront/media/search?q=banner&per_page=10&page=2");
        $this->assertCount(10, $page2->json('data'));
        $this->assertEmpty(array_intersect(
            collect($response->json('data'))->pluck('id')->all(),
            collect($page2->json('data'))->pluck('id')->all()
        ));

        $exact = $this->actingAs($owner, 'accounts')
            ->getJson("/store/{$tenant->slug}/admin/storefront/media/search?q=logo-special");
        $exact->assertJsonPath('total', 1);
    }

    public function test_search_is_tenant_isolated_and_caps_page_size(): void
    {
        [$tenantA] = $this->seedStore('search-shop-a');
        [$tenantB, $ownerB] = $this->seedStore('search-shop-b');
        $this->makeMedia($tenantA, 'shared-name.png', 'storefront-media/a.png');
        $mediaB = $this->makeMedia($tenantB, 'shared-name.png', 'storefront-media/b.png');

        $response = $this->actingAs($ownerB, 'accounts')
            ->getJson("/store/{$tenantB->slug}/admin/storefront/media/search?q=shared-name&per_page=5000");

        $response->assertJsonPath('total', 1);
        $response->assertJsonPath('data.0.id', $mediaB->id);
        $this->assertLessThanOrEqual(50, $response->json('per_page'));
    }

    public function test_variant_and_legacy_promo_and_description_usage_blocks_delete(): void
    {
        [$tenant, $owner] = $this->seedStore('usage-shop');
        $variantMedia = $this->makeMedia($tenant, 'v.png', 'storefront-media/v.png');
        $promoMedia = $this->makeMedia($tenant, 'p.png', 'storefront-media/p.png');
        $descMedia = $this->makeMedia($tenant, 'd.png', 'storefront-media/d.png');

        $product = $this->makeProduct($tenant, [
            'description' => '<p>Look <img src="storefront-media/d.png"></p>',
        ]);
        \App\Models\ProductVariant::withoutTenantScope()->create([
            'product_id' => $product->id,
            'sku' => 'V-1', 'price' => 10, 'stock' => 1,
            'image' => 'storefront-media/v.png',
        ]);
        \App\Models\PromotionBanner::withoutTenantScope()->create([
            'tenant_id' => $tenant->id,
            'title' => 'Promo', 'image' => 'storefront-media/p.png',
            'link' => '/products',
            'is_active' => true,
        ]);

        foreach ([$variantMedia, $promoMedia, $descMedia] as $media) {
            $this->actingAs($owner, 'accounts')
                ->delete("/store/{$tenant->slug}/admin/storefront/media/{$media->id}")
                ->assertSessionHas('error');
            $this->assertNotNull(StorefrontMedia::withoutTenantScope()->find($media->id));
        }
    }

    public function test_cleanup_dry_run_lists_only_true_orphans(): void
    {
        [$tenant] = $this->seedStore('cleanup-shop');
        $used = $this->makeMedia($tenant, 'used.png', 'storefront-media/used.png');
        $orphan = $this->makeMedia($tenant, 'orphan.png', 'storefront-media/orphan.png');

        $product = $this->makeProduct($tenant, ['photo1' => 'storefront-media/used.png']);

        $cleanup = app(MediaCleanupService::class);
        $candidates = collect($cleanup->orphanCandidates($tenant->id))->pluck('id')->all();

        $this->assertContains($orphan->id, $candidates);
        $this->assertNotContains($used->id, $candidates);
    }

    public function test_cleanup_delete_removes_orphan_and_keeps_used(): void
    {
        [$tenant] = $this->seedStore('cleanup-del-shop');
        $used = $this->makeMedia($tenant, 'used.png', 'storefront-media/used2.png');
        $orphan = $this->makeMedia($tenant, 'orphan.png', 'storefront-media/orphan2.png');

        $product = $this->makeProduct($tenant, ['photo1' => 'storefront-media/used2.png']);

        $this->artisan('media:cleanup', ['--tenant' => $tenant->id, '--delete' => true])
            ->assertSuccessful();

        $this->assertNull(StorefrontMedia::withoutTenantScope()->find($orphan->id));
        $this->assertNotNull(StorefrontMedia::withoutTenantScope()->find($used->id));
    }

    public function test_cleanup_respects_tenant_boundaries(): void
    {
        [$tenantA] = $this->seedStore('cleanup-iso-a');
        [$tenantB] = $this->seedStore('cleanup-iso-b');
        $orphanA = $this->makeMedia($tenantA, 'a.png', 'storefront-media/iso-a.png');

        $cleanup = app(MediaCleanupService::class);

        $this->assertFalse($cleanup->isOrphan($orphanA, $tenantB->id));
        $this->assertTrue($cleanup->isOrphan($orphanA, $tenantA->id));
    }

    public function test_optimization_whitelist_covers_storefront_media(): void
    {
        $ref = new \ReflectionClass(\App\Services\ImageOptimizationService::class);
        $whitelist = $ref->getConstant('FOLDER_WHITELIST');

        $this->assertContains('storefront-media', $whitelist);
    }

    public function test_filesystem_url_abstraction(): void
    {
        $this->assertEquals('https://cdn.example.com/x.png', ImageService::url('https://cdn.example.com/x.png'));
        $this->assertStringContainsString('storefront-media/a.png', ImageService::url('storefront-media/a.png'));
    }

    private function makeProduct(Tenant $tenant, array $overrides = []): Product
    {
        $category = \App\Models\Category::withoutTenantScope()->where('tenant_id', $tenant->id)->first();
        if (!$category) {
            $category = new \App\Models\Category(['name' => 'Cat ' . uniqid(), 'slug' => 'cat-' . uniqid()]);
            $category->tenant_id = $tenant->id;
            $category->save();
        }

        $product = new Product(array_merge([
            'name' => 'P ' . uniqid(),
            'type' => 'single',
            'price' => 1,
            'base_price' => 1,
            'category_id' => $category->id,
            'status' => Product::STATUS_ACTIVE,
        ], $overrides));
        $product->tenant_id = $tenant->id;
        $product->save();

        return $product;
    }

    private function seedStore(string $slug): array
    {
        $tenant = Tenant::create(['slug' => $slug, 'name' => $slug, 'status' => 'active']);
        Storefront::withoutTenantScope()->create(['tenant_id' => $tenant->id, 'status' => 'active']);

        $adminRole = Role::withoutTenantScope()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $adminRole->syncPermissions(Permission::all());
        $owner = Account::create(['name' => "Owner {$slug}", 'email' => "owner@{$slug}.test", 'password' => bcrypt('password'), 'status' => 'active']);
        TenantMembership::create(['account_id' => $owner->id, 'tenant_id' => $tenant->id, 'role_id' => $adminRole->id, 'is_owner' => true, 'status' => 'active', 'joined_at' => now()]);

        return [$tenant, $owner];
    }

    private function makeMedia(Tenant $tenant, string $filename, string $path): StorefrontMedia
    {
        $storefront = Storefront::withoutTenantScope()->where('tenant_id', $tenant->id)->first();

        return StorefrontMedia::withoutTenantScope()->create([
            'tenant_id' => $tenant->id,
            'storefront_id' => $storefront->id,
            'key' => 'library',
            'path' => $path,
            'original_name' => $filename,
            'mime_type' => 'image/png',
            'size' => 1024,
            'alt_text' => $filename,
            'is_visible' => true,
        ]);
    }
}
