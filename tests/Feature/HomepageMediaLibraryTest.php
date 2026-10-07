<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Role;
use App\Models\Storefront;
use App\Models\StorefrontHomepageSection;
use App\Models\StorefrontMedia;
use App\Models\Tenant;
use App\Models\TenantMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class HomepageMediaLibraryTest extends TestCase
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

    public function test_library_images_can_be_selected_for_hero(): void
    {
        [$tenant, $owner, $storefront, $hero] = $this->seedHomepage('hero-lib-shop');
        $mediaA = $this->makeMedia($tenant, $storefront, 'a.png', 'storefront-media/a.png');
        $mediaB = $this->makeMedia($tenant, $storefront, 'b.png', 'storefront-media/b.png');

        $this->actingAs($owner, 'accounts')
            ->put("/store/{$tenant->slug}/admin/storefront/homepage", [
                'sections' => [$this->sectionPayload($hero, [
                    'title' => 'Welcome',
                    'media_ids' => [$mediaA->id, $mediaB->id],
                ])],
            ])
            ->assertSessionHasNoErrors();

        $config = StorefrontHomepageSection::withoutTenantScope()->find($hero->id)->configuration;
        $this->assertEqualsCanonicalizing([$mediaA->id, $mediaB->id], $config['media_ids']);
    }

    public function test_cross_tenant_media_is_dropped_from_hero(): void
    {
        [$tenantA, $ownerA, $storefrontA, $heroA] = $this->seedHomepage('hero-shop-a');
        [$tenantB] = $this->seedHomepage('hero-shop-b');
        $own = $this->makeMedia($tenantA, $storefrontA, 'own.png', 'storefront-media/own.png');
        $foreign = StorefrontMedia::withoutTenantScope()->create([
            'tenant_id' => $tenantB->id,
            'storefront_id' => Storefront::withoutTenantScope()->where('tenant_id', $tenantB->id)->first()->id,
            'key' => 'library',
            'path' => 'storefront-media/foreign.png',
            'original_name' => 'foreign.png',
            'is_visible' => true,
        ]);

        $this->actingAs($ownerA, 'accounts')
            ->put("/store/{$tenantA->slug}/admin/storefront/homepage", [
                'sections' => [$this->sectionPayload($heroA, ['media_ids' => [$own->id, $foreign->id]])],
            ])
            ->assertSessionHasNoErrors();

        $config = StorefrontHomepageSection::withoutTenantScope()->find($heroA->id)->configuration;
        $this->assertEquals([$own->id], array_values($config['media_ids']));
    }

    public function test_library_image_can_be_selected_for_brand_story_and_cta(): void
    {
        [$tenant, $owner, $storefront, $hero] = $this->seedHomepage('section-lib-shop');
        $story = $this->makeSection($tenant, $storefront, 'brand_story', 1);
        $cta = $this->makeSection($tenant, $storefront, 'cta', 2);
        $media = $this->makeMedia($tenant, $storefront, 'story.png', 'storefront-media/story.png');

        $this->actingAs($owner, 'accounts')
            ->put("/store/{$tenant->slug}/admin/storefront/homepage", [
                'sections' => [
                    $this->sectionPayload($hero, []),
                    $this->sectionPayload($story, ['button_text' => 'More', 'media_id' => $media->id]),
                    $this->sectionPayload($cta, ['title' => 'Sale', 'media_id' => $media->id]),
                ],
            ])
            ->assertSessionHasNoErrors();

        $storyConfig = StorefrontHomepageSection::withoutTenantScope()->find($story->id)->configuration;
        $ctaConfig = StorefrontHomepageSection::withoutTenantScope()->find($cta->id)->configuration;
        $this->assertEquals($media->id, $storyConfig['media_id']);
        $this->assertEquals($media->id, $ctaConfig['media_id']);
    }

    public function test_foreign_media_id_is_cleared_for_brand_story(): void
    {
        [$tenantA, $ownerA, $storefrontA, $heroA] = $this->seedHomepage('story-shop-a');
        [$tenantB] = $this->seedHomepage('story-shop-b');
        $story = $this->makeSection($tenantA, $storefrontA, 'brand_story', 1);
        $foreign = StorefrontMedia::withoutTenantScope()->create([
            'tenant_id' => $tenantB->id,
            'storefront_id' => Storefront::withoutTenantScope()->where('tenant_id', $tenantB->id)->first()->id,
            'key' => 'library',
            'path' => 'storefront-media/foreign-story.png',
            'original_name' => 'foreign-story.png',
            'is_visible' => true,
        ]);

        $this->actingAs($ownerA, 'accounts')
            ->put("/store/{$tenantA->slug}/admin/storefront/homepage", [
                'sections' => [
                    $this->sectionPayload($heroA, []),
                    $this->sectionPayload($story, ['button_text' => 'More', 'media_id' => $foreign->id]),
                ],
            ])
            ->assertSessionHasNoErrors();

        $storyConfig = StorefrontHomepageSection::withoutTenantScope()->find($story->id)->configuration;
        $this->assertNull($storyConfig['media_id']);
    }

    public function test_homepage_page_loads_with_media_and_publish_still_works(): void
    {
        [$tenant, $owner, $storefront, $hero] = $this->seedHomepage('flow-shop');
        $media = $this->makeMedia($tenant, $storefront, 'flow.png', 'storefront-media/flow.png');

        $this->actingAs($owner, 'accounts')
            ->get("/store/{$tenant->slug}/admin/storefront/homepage")
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Storefront/Homepage')
                ->where('media', fn ($data) => collect($data)->pluck('id')->contains($media->id))
            );

        $this->actingAs($owner, 'accounts')
            ->put("/store/{$tenant->slug}/admin/storefront/homepage", [
                'sections' => [$this->sectionPayload($hero, ['media_ids' => [$media->id]])],
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($owner, 'accounts')
            ->post("/store/{$tenant->slug}/admin/storefront/publish")
            ->assertSessionHas('success');
    }

    private function seedHomepage(string $slug): array
    {
        $tenant = Tenant::create(['slug' => $slug, 'name' => $slug, 'status' => 'active']);
        $storefront = Storefront::withoutTenantScope()->create(['tenant_id' => $tenant->id, 'status' => 'active']);

        $adminRole = Role::withoutTenantScope()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $adminRole->syncPermissions(Permission::all());
        $owner = Account::create(['name' => "Owner {$slug}", 'email' => "owner@{$slug}.test", 'password' => bcrypt('password'), 'status' => 'active']);
        TenantMembership::create(['account_id' => $owner->id, 'tenant_id' => $tenant->id, 'role_id' => $adminRole->id, 'is_owner' => true, 'status' => 'active', 'joined_at' => now()]);

        app()->instance('current.tenant', $tenant);
        $hero = $this->makeSection($tenant, $storefront, 'hero', 0);

        return [$tenant, $owner, $storefront, $hero];
    }

    private function makeSection(Tenant $tenant, Storefront $storefront, string $type, int $position): StorefrontHomepageSection
    {
        return StorefrontHomepageSection::withoutTenantScope()->create([
            'tenant_id' => $tenant->id,
            'storefront_id' => $storefront->id,
            'type' => $type,
            'variant' => 'auto',
            'enabled' => true,
            'desktop_visible' => true,
            'mobile_visible' => true,
            'position' => $position,
            'configuration' => [],
        ]);
    }

    private function makeMedia(Tenant $tenant, Storefront $storefront, string $filename, string $path): StorefrontMedia
    {
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

    private function sectionPayload(StorefrontHomepageSection $section, array $configuration): array
    {
        return [
            'id' => $section->id,
            'enabled' => true,
            'desktop_visible' => true,
            'mobile_visible' => true,
            'position' => $section->position,
            'variant' => $section->variant,
            'configuration' => $configuration,
        ];
    }
}
