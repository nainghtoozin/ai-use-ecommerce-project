<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Role;
use App\Models\Storefront;
use App\Models\StorefrontMedia;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\WebsiteInfo;
use App\Services\ImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class StorefrontMediaLogoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        if (!Permission::where('name', 'settings.website')->exists()) {
            Permission::create(['name' => 'settings.website', 'guard_name' => 'web']);
        }
        foreach (['products.view'] as $perm) {
            if (!Permission::where('name', $perm)->exists()) {
                Permission::create(['name' => $perm, 'guard_name' => 'web']);
            }
        }

        config()->set('identity.use_accounts', true);
    }

    public function test_select_existing_media_as_logo(): void
    {
        [$tenant, $owner] = $this->seedStore('logo-lib-shop');
        $media = $this->makeMedia($tenant, 'logo-lib-shop', 'logo.png', 'storefront-media/logo.png');

        $this->actingAs($owner, 'accounts')
            ->put("/store/{$tenant->slug}/admin/settings", ['logo_media_id' => $media->id])
            ->assertSessionHasNoErrors();

        $this->assertEquals('storefront-media/logo.png', $this->websiteInfo($tenant)->logo);
        $this->assertNotNull(StorefrontMedia::withoutTenantScope()->find($media->id));
    }

    public function test_cannot_select_another_tenants_media(): void
    {
        [$tenantA] = $this->seedStore('logo-shop-a');
        [$tenantB, $ownerB] = $this->seedStore('logo-shop-b');

        $this->actingAs($ownerB, 'accounts')
            ->put("/store/{$tenantB->slug}/admin/settings", ['logo_media_id' => 999999999])
            ->assertSessionHasErrors('logo_media_id');

        $otherTenantMedia = $this->makeMedia($tenantA, 'logo-shop-a', 'a-logo.png', 'storefront-media/a-logo.png');

        $this->actingAs($ownerB, 'accounts')
            ->put("/store/{$tenantB->slug}/admin/settings", ['logo_media_id' => $otherTenantMedia->id])
            ->assertSessionHasErrors('logo_media_id');

        $this->assertNull($this->websiteInfo($tenantB)->logo);
    }

    public function test_upload_new_logo_still_works(): void
    {
        [$tenant, $owner] = $this->seedStore('logo-upload-shop');

        $imageService = \Mockery::mock(ImageService::class);
        $imageService->shouldReceive('upload')->once()->andReturn('website-settings/new-logo.png');
        $this->app->instance(ImageService::class, $imageService);

        $this->actingAs($owner, 'accounts')
            ->put("/store/{$tenant->slug}/admin/settings", [
                'logo' => UploadedFile::fake()->image('new-logo.png'),
            ])
            ->assertSessionHasNoErrors();

        $this->assertEquals('website-settings/new-logo.png', $this->websiteInfo($tenant)->logo);
    }

    public function test_library_owned_logo_file_is_not_deleted_on_reupload(): void
    {
        [$tenant, $owner] = $this->seedStore('logo-protect-shop');
        $media = $this->makeMedia($tenant, 'logo-protect-shop', 'shared.png', 'storefront-media/shared.png');
        $info = $this->websiteInfo($tenant);
        $info->logo = 'storefront-media/shared.png';
        $info->save();

        $imageService = \Mockery::mock(ImageService::class);
        $imageService->shouldReceive('delete')->never();
        $imageService->shouldReceive('upload')->once()->andReturn('website-settings/replacement.png');
        $this->app->instance(ImageService::class, $imageService);

        $this->actingAs($owner, 'accounts')
            ->put("/store/{$tenant->slug}/admin/settings", [
                'logo' => UploadedFile::fake()->image('replacement.png'),
            ])
            ->assertSessionHasNoErrors();

        $this->assertEquals('website-settings/replacement.png', $this->websiteInfo($tenant)->logo);
        $this->assertNotNull(StorefrontMedia::withoutTenantScope()->find($media->id));
    }

    public function test_settings_page_lists_only_current_tenant_media(): void
    {
        [$tenantA] = $this->seedStore('logo-list-a');
        [$tenantB, $ownerB] = $this->seedStore('logo-list-b');
        $mediaA = $this->makeMedia($tenantA, 'logo-list-a', 'a.png', 'storefront-media/a.png');
        $mediaB = $this->makeMedia($tenantB, 'logo-list-b', 'b.png', 'storefront-media/b.png');

        $this->actingAs($ownerB, 'accounts')
            ->get("/store/{$tenantB->slug}/admin/settings")
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Settings/Edit')
                ->where('mediaLibrary', fn ($data) => collect($data)->pluck('id')->contains($mediaB->id))
                ->where('mediaLibrary', fn ($data) => collect($data)->pluck('id')->doesntContain($mediaA->id))
            );
    }

    public function test_media_library_assign_logo_still_works(): void
    {
        [$tenant, $owner] = $this->seedStore('logo-assign-shop');
        $media = $this->makeMedia($tenant, 'logo-assign-shop', 'pick.png', 'storefront-media/pick.png');

        $this->actingAs($owner, 'accounts')
            ->post("/store/{$tenant->slug}/admin/storefront/media/{$media->id}/assign-logo")
            ->assertSessionHasNoErrors();

        $this->assertEquals('storefront-media/pick.png', $this->websiteInfo($tenant)->logo);
    }

    public function test_select_existing_media_as_about_image(): void
    {
        [$tenant, $owner] = $this->seedStore('about-lib-shop');
        $media = $this->makeMedia($tenant, 'about-lib-shop', 'about.png', 'storefront-media/about.png');

        $this->actingAs($owner, 'accounts')
            ->put("/store/{$tenant->slug}/admin/settings", ['about_media_id' => $media->id])
            ->assertSessionHasNoErrors();

        $this->assertEquals('storefront-media/about.png', $this->websiteInfo($tenant)->about_image);
        $this->assertNotNull(StorefrontMedia::withoutTenantScope()->find($media->id));
    }

    public function test_cannot_select_another_tenants_media_as_about_image(): void
    {
        [$tenantA] = $this->seedStore('about-shop-a');
        [$tenantB, $ownerB] = $this->seedStore('about-shop-b');
        $foreign = $this->makeMedia($tenantA, 'about-shop-a', 'foreign-about.png', 'storefront-media/foreign-about.png');

        $this->actingAs($ownerB, 'accounts')
            ->put("/store/{$tenantB->slug}/admin/settings", ['about_media_id' => $foreign->id])
            ->assertSessionHasErrors('about_media_id');

        $this->assertNull($this->websiteInfo($tenantB)->about_image);
    }

    public function test_upload_new_about_image_still_works(): void
    {
        [$tenant, $owner] = $this->seedStore('about-upload-shop');

        $imageService = \Mockery::mock(ImageService::class);
        $imageService->shouldReceive('upload')->once()->andReturn('website-settings/about-new.png');
        $this->app->instance(ImageService::class, $imageService);

        $this->actingAs($owner, 'accounts')
            ->put("/store/{$tenant->slug}/admin/settings", [
                'about_image' => UploadedFile::fake()->image('about-new.png'),
            ])
            ->assertSessionHasNoErrors();

        $this->assertEquals('website-settings/about-new.png', $this->websiteInfo($tenant)->about_image);
    }

    public function test_about_image_used_by_store_blocks_media_deletion(): void
    {
        [$tenant, $owner] = $this->seedStore('about-guard-shop');
        $media = $this->makeMedia($tenant, 'about-guard-shop', 'guard-about.png', 'storefront-media/guard-about.png');

        $this->actingAs($owner, 'accounts')
            ->put("/store/{$tenant->slug}/admin/settings", ['about_media_id' => $media->id])
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

        $info = WebsiteInfo::withoutTenantScope()->where('tenant_id', $tenant->id)->first() ?? new WebsiteInfo();
        $info->tenant_id = $tenant->id;
        $info->site_name = $slug;
        $info->save();

        app()->instance('current.tenant', $tenant);

        return [$tenant, $owner];
    }

    private function makeMedia(Tenant $tenant, string $slug, string $filename, string $path): StorefrontMedia
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

    private function websiteInfo(Tenant $tenant): WebsiteInfo
    {
        return WebsiteInfo::withoutTenantScope()->where('tenant_id', $tenant->id)->first();
    }
}
