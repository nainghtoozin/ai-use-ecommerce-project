<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\PromotionBanner;
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

class PromotionMediaLibraryTest extends TestCase
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

    public function test_library_image_can_be_selected_for_promotion(): void
    {
        [$tenant, $owner] = $this->seedStore('promo-lib-shop');
        $media = $this->makeMedia($tenant, 'banner.png', 'storefront-media/banner.png');

        $this->actingAs($owner, 'accounts')
            ->post("/store/{$tenant->slug}/admin/storefront/promotions", $this->payload([
                'storefront_media_id' => $media->id,
            ]))
            ->assertSessionHasNoErrors();

        $banner = PromotionBanner::withoutTenantScope()->where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($banner);
        $this->assertEquals($media->id, (int) $banner->storefront_media_id);
        $this->assertNotNull(StorefrontMedia::withoutTenantScope()->find($media->id));
    }

    public function test_inline_upload_creates_library_row(): void
    {
        [$tenant, $owner] = $this->seedStore('promo-upload-shop');

        $uploader = \Mockery::mock(ImageService::class);
        $uploader->shouldReceive('upload')->once()->andReturn('storefront-media/promo-new.png');
        $this->app->instance(ImageService::class, $uploader);

        $response = $this->actingAs($owner, 'accounts')
            ->post("/store/{$tenant->slug}/admin/storefront/media/promotion/upload", [
                'file' => UploadedFile::fake()->image('promo-new.png'),
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('url', fn ($url) => is_string($url) && $url !== '');
        $id = $response->json('id');
        $this->assertNotNull($id);

        $row = StorefrontMedia::withoutTenantScope()->find($id);
        $this->assertNotNull($row);
        $this->assertEquals($tenant->id, (int) $row->tenant_id);
        $this->assertEquals('storefront-media/promo-new.png', $row->path);
    }

    public function test_cross_tenant_media_is_rejected(): void
    {
        [$tenantA, $ownerA] = $this->seedStore('promo-shop-a');
        [$tenantB] = $this->seedStore('promo-shop-b');
        $foreign = $this->makeMedia($tenantB, 'foreign.png', 'storefront-media/foreign-promo.png');

        $this->actingAs($ownerA, 'accounts')
            ->post("/store/{$tenantA->slug}/admin/storefront/promotions", $this->payload([
                'storefront_media_id' => $foreign->id,
            ]))
            ->assertStatus(422);

        $this->assertEquals(0, PromotionBanner::withoutTenantScope()->where('tenant_id', $tenantA->id)->count());
    }

    public function test_promotion_flow_still_works(): void
    {
        [$tenant, $owner] = $this->seedStore('promo-flow-shop');
        $media = $this->makeMedia($tenant, 'flow.png', 'storefront-media/flow.png');

        $this->actingAs($owner, 'accounts')
            ->post("/store/{$tenant->slug}/admin/storefront/promotions", $this->payload([
                'title' => 'Winter Sale',
                'storefront_media_id' => $media->id,
            ]))
            ->assertSessionHasNoErrors();

        $banner = PromotionBanner::withoutTenantScope()->where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($banner);

        $this->actingAs($owner, 'accounts')
            ->post("/store/{$tenant->slug}/admin/storefront/promotions/{$banner->id}", $this->payload([
                'title' => 'Winter Sale Updated',
                'storefront_media_id' => $media->id,
            ]))
            ->assertSessionHasNoErrors();
        $this->assertEquals('Winter Sale Updated', $banner->fresh()->title);
        $this->assertEquals($media->id, (int) $banner->fresh()->storefront_media_id);

        $this->actingAs($owner, 'accounts')
            ->post("/store/{$tenant->slug}/admin/storefront/promotions/{$banner->id}/toggle")
            ->assertSessionHasNoErrors();

        $this->actingAs($owner, 'accounts')
            ->delete("/store/{$tenant->slug}/admin/storefront/promotions/{$banner->id}")
            ->assertSessionHasNoErrors();
        $this->assertNull(PromotionBanner::withoutTenantScope()->find($banner->id));
        $this->assertNotNull(StorefrontMedia::withoutTenantScope()->find($media->id));
    }

    public function test_library_image_used_by_promotion_cannot_be_deleted(): void
    {
        [$tenant, $owner] = $this->seedStore('promo-guard-shop');
        $media = $this->makeMedia($tenant, 'guard.png', 'storefront-media/guard.png');

        $this->actingAs($owner, 'accounts')
            ->post("/store/{$tenant->slug}/admin/storefront/promotions", $this->payload([
                'storefront_media_id' => $media->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->actingAs($owner, 'accounts')
            ->delete("/store/{$tenant->slug}/admin/storefront/media/{$media->id}")
            ->assertSessionHas('error');

        $this->assertNotNull(StorefrontMedia::withoutTenantScope()->find($media->id));
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

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Promo ' . uniqid(),
            'is_active' => true,
            'desktop_visible' => true,
            'mobile_visible' => true,
        ], $overrides);
    }
}
