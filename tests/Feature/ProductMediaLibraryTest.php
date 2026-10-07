<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Storefront;
use App\Models\StorefrontMedia;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Services\ImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProductMediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['products.view', 'products.create', 'products.edit', 'products.delete'] as $perm) {
            if (!Permission::where('name', $perm)->exists()) {
                Permission::create(['name' => $perm, 'guard_name' => 'web']);
            }
        }

        config()->set('identity.use_accounts', true);
        config()->set('app.dev_mode', true);
    }

    public function test_store_with_library_primary_and_gallery(): void
    {
        [$tenant, $owner, $category] = $this->seedStore('prod-lib-shop');
        $primary = $this->makeMedia($tenant, 'primary.png', 'storefront-media/primary.png');
        $gallery = $this->makeMedia($tenant, 'gallery.png', 'storefront-media/gallery.png');

        $this->actingAs($owner, 'accounts')
            ->post("/store/{$tenant->slug}/admin/products", $this->payload($category, [
                'photo1_media_id' => $primary->id,
                'existing_gallery_images' => json_encode(['storefront-media/gallery.png']),
            ]))
            ->assertSessionHasNoErrors();

        $product = Product::withoutTenantScope()->where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($product);
        $this->assertEquals('storefront-media/primary.png', $product->photo1);
        $this->assertEquals(['storefront-media/gallery.png'], $product->gallery_images);
        $this->assertNotNull(StorefrontMedia::withoutTenantScope()->find($primary->id));
        $this->assertNotNull(StorefrontMedia::withoutTenantScope()->find($gallery->id));
    }

    public function test_store_rejects_foreign_media_id(): void
    {
        [$tenantA, $ownerA, $categoryA] = $this->seedStore('prod-shop-a');
        [$tenantB] = $this->seedStore('prod-shop-b');
        $foreign = $this->makeMedia($tenantB, 'foreign.png', 'storefront-media/foreign.png');

        $this->actingAs($ownerA, 'accounts')
            ->post("/store/{$tenantA->slug}/admin/products", $this->payload($categoryA, [
                'photo1_media_id' => $foreign->id,
            ]))
            ->assertStatus(422);

        $this->assertEquals(0, Product::withoutTenantScope()->where('tenant_id', $tenantA->id)->count());
    }

    public function test_store_rejects_foreign_gallery_path(): void
    {
        [$tenantA, $ownerA, $categoryA] = $this->seedStore('prod-gal-shop-a');
        [$tenantB] = $this->seedStore('prod-gal-shop-b');
        $own = $this->makeMedia($tenantA, 'own.png', 'storefront-media/own.png');
        $this->makeMedia($tenantB, 'evil.png', 'storefront-media/evil.png');
        $media = $this->mockUploads('products/direct.png');

        $this->actingAs($ownerA, 'accounts')
            ->post("/store/{$tenantA->slug}/admin/products", $this->payload($categoryA, [
                'photo1' => UploadedFile::fake()->image('direct.png'),
                'existing_gallery_images' => json_encode(['storefront-media/own.png', 'storefront-media/evil.png', 'storefront-media/own.png']),
            ]))
            ->assertSessionHasNoErrors();

        $product = Product::withoutTenantScope()->where('tenant_id', $tenantA->id)->first();
        $this->assertEquals(['storefront-media/own.png'], array_values($product->gallery_images));
    }

    public function test_update_can_switch_primary_to_library_image(): void
    {
        [$tenant, $owner, $category] = $this->seedStore('prod-switch-shop');
        $library = $this->makeMedia($tenant, 'lib.png', 'storefront-media/lib.png');
        $product = $this->makeProduct($tenant, $category, ['photo1' => 'products/old.png']);

        $this->actingAs($owner, 'accounts')
            ->put("/store/{$tenant->slug}/admin/products/{$product->id}", $this->updatePayload($category, [
                'photo1_media_id' => $library->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertEquals('storefront-media/lib.png', $product->fresh()->photo1);
    }

    public function test_update_rejects_foreign_primary_media_id(): void
    {
        [$tenantA, $ownerA, $categoryA] = $this->seedStore('prod-upd-shop-a');
        [$tenantB] = $this->seedStore('prod-upd-shop-b');
        $product = $this->makeProduct($tenantA, $categoryA, ['photo1' => 'products/keep.png']);
        $foreign = $this->makeMedia($tenantB, 'foreign.png', 'storefront-media/foreign2.png');

        $this->actingAs($ownerA, 'accounts')
            ->put("/store/{$tenantA->slug}/admin/products/{$product->id}", $this->updatePayload($categoryA, [
                'photo1_media_id' => $foreign->id,
            ]))
            ->assertStatus(422);

        $this->assertEquals('products/keep.png', $product->fresh()->photo1);
    }

    public function test_gallery_order_and_duplicates_preserved(): void
    {
        [$tenant, $owner, $category] = $this->seedStore('prod-order-shop');
        $product = $this->makeProduct($tenant, $category, [
            'gallery_images' => ['products/gallery/old1.png', 'products/gallery/old2.png'],
        ]);
        $library = $this->makeMedia($tenant, 'new.png', 'storefront-media/new.png');

        $this->actingAs($owner, 'accounts')
            ->put("/store/{$tenant->slug}/admin/products/{$product->id}", $this->updatePayload($category, [
                'existing_gallery_images' => json_encode(['products/gallery/old2.png', 'storefront-media/new.png', 'products/gallery/old1.png', 'storefront-media/new.png']),
            ]))
            ->assertSessionHasNoErrors();

        $this->assertEquals(
            ['products/gallery/old2.png', 'storefront-media/new.png', 'products/gallery/old1.png'],
            array_values($product->fresh()->gallery_images)
        );
    }

    public function test_library_image_used_by_product_cannot_be_deleted(): void
    {
        [$tenant, $owner] = $this->seedStore('prod-guard-shop');
        $media = $this->makeMedia($tenant, 'used.png', 'storefront-media/used.png');
        $this->makeProduct($tenant, null, ['photo1' => 'storefront-media/used.png']);

        $response = $this->actingAs($owner, 'accounts')
            ->delete("/store/{$tenant->slug}/admin/storefront/media/{$media->id}");
        $response->assertSessionHas('error');

        $this->assertNotNull(StorefrontMedia::withoutTenantScope()->find($media->id));
    }

    public function test_product_delete_preserves_library_file(): void
    {
        [$tenant, $owner] = $this->seedStore('prod-del-shop');
        $media = $this->makeMedia($tenant, 'keep.png', 'storefront-media/keep.png');
        $product = $this->makeProduct($tenant, null, ['photo1' => 'storefront-media/keep.png']);

        $deleter = \Mockery::mock(ImageService::class);
        $deleter->shouldNotReceive('delete', ['storefront-media/keep.png']);
        $deleter->shouldReceive('delete')->zeroOrMoreTimes()->andReturnFalse();
        $this->app->instance(ImageService::class, $deleter);

        $this->actingAs($owner, 'accounts')
            ->delete("/store/{$tenant->slug}/admin/products/{$product->id}")
            ->assertSessionHasNoErrors();

        $this->assertNull(Product::withoutTenantScope()->find($product->id));
        $this->assertNotNull(StorefrontMedia::withoutTenantScope()->find($media->id));
    }

    public function test_direct_upload_still_works(): void
    {
        [$tenant, $owner, $category] = $this->seedStore('prod-direct-shop');
        $this->mockUploads('products/uploaded.png', 'products/gallery/g1.png');

        $response = $this->actingAs($owner, 'accounts')
            ->post("/store/{$tenant->slug}/admin/products", array_merge(
                $this->payload($category),
                ['photo1' => UploadedFile::fake()->image('up.png'), 'gallery_images' => [UploadedFile::fake()->image('g1.png')]]
            ));

        $response->assertSessionHasNoErrors();
        $product = Product::withoutTenantScope()->where('tenant_id', $tenant->id)->first();
        $this->assertEquals('products/uploaded.png', $product->photo1);
        $this->assertEquals(['products/gallery/g1.png'], array_values($product->gallery_images));
    }

    public function test_product_pages_expose_tenant_media_only(): void
    {
        [$tenantA] = $this->seedStore('prod-page-a');
        [$tenantB, $ownerB] = $this->seedStore('prod-page-b');
        $mediaA = $this->makeMedia($tenantA, 'a.png', 'storefront-media/a.png');
        $mediaB = $this->makeMedia($tenantB, 'b.png', 'storefront-media/b.png');

        $this->actingAs($ownerB, 'accounts')
            ->get("/store/{$tenantB->slug}/admin/products/create")
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->where('mediaLibrary', fn ($data) => collect($data)->pluck('id')->contains($mediaB->id))
                ->where('mediaLibrary', fn ($data) => collect($data)->pluck('id')->doesntContain($mediaA->id))
            );
    }

    private function freePlan()
    {
        $plan = \App\Models\Plan::firstOrCreate(
            ['slug' => 'media-lib-free'],
            ['name' => 'Media Free', 'monthly_price' => 0, 'yearly_price' => 0, 'status' => 'active']
        );
        foreach (['single_products', 'variable_products', 'combo_products'] as $key) {
            \App\Models\PlanFeature::firstOrCreate(
                ['plan_id' => $plan->id, 'feature_key' => $key],
                ['is_enabled' => true]
            );
        }

        return $plan;
    }

    private function seedStore(string $slug): array
    {
        $tenant = Tenant::create(['slug' => $slug, 'name' => $slug, 'status' => 'active']);
        $tenant->subscription_plan_id = $this->freePlan()->id;
        $tenant->save();
        $storefront = Storefront::withoutTenantScope()->create(['tenant_id' => $tenant->id, 'status' => 'active']);

        $adminRole = Role::withoutTenantScope()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $adminRole->syncPermissions(Permission::all());
        $owner = Account::create(['name' => "Owner {$slug}", 'email' => "owner@{$slug}.test", 'password' => bcrypt('password'), 'status' => 'active']);
        TenantMembership::create(['account_id' => $owner->id, 'tenant_id' => $tenant->id, 'role_id' => $adminRole->id, 'is_owner' => true, 'status' => 'active', 'joined_at' => now()]);

        $category = Category::withoutTenantScope()->where('tenant_id', $tenant->id)->where('slug', "cat-{$slug}")->first();
        if (!$category) {
            $category = new Category(['name' => "Cat {$slug}", 'slug' => "cat-{$slug}"]);
            $category->tenant_id = $tenant->id;
            $category->save();
        }

        return [$tenant, $owner, $category, $storefront];
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

    private function makeProduct(Tenant $tenant, $category, array $overrides = []): Product
    {
        $category ??= Category::withoutTenantScope()->where('tenant_id', $tenant->id)->first();
        if (!$category) {
            $category = new Category(['name' => 'Auto Cat ' . uniqid(), 'slug' => 'auto-cat-' . uniqid()]);
            $category->tenant_id = $tenant->id;
            $category->save();
        }

        $product = new Product(array_merge([
            'name' => 'Test Product ' . uniqid(),
            'type' => 'single',
            'price' => 100,
            'base_price' => 100,
            'category_id' => $category?->id,
            'status' => Product::STATUS_ACTIVE,
        ], $overrides));
        $product->tenant_id = $tenant->id;
        $product->save();

        return $product;
    }

    private function mockUploads(string ...$paths)
    {
        $mock = \Mockery::mock(ImageService::class);
        foreach ($paths as $path) {
            $mock->shouldReceive('upload')->once()->andReturn($path);
        }
        $mock->shouldReceive('delete')->zeroOrMoreTimes()->andReturnTrue();
        $this->app->instance(ImageService::class, $mock);

        return $mock;
    }

    private function payload(Category $category, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Library Product ' . uniqid(),
            'price' => 100,
            'base_price' => 100,
            'category_id' => $category->id,
            'status' => 'active',
            'type' => 'single',
        ], $overrides);
    }

    private function updatePayload(Category $category, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Updated Product',
            'price' => 120,
            'base_price' => 120,
            'status' => 'active',
            'category_id' => $category->id,
        ], $overrides);
    }
}
