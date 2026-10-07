<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Role;
use App\Models\Storefront;
use App\Models\StorefrontMedia;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\WebsiteFaq;
use App\Services\ImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class FaqMediaLibraryTest extends TestCase
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

    public function test_editor_upload_creates_tenant_library_row(): void
    {
        [$tenant, $owner] = $this->seedStore('faq-upload-shop');

        $uploader = \Mockery::mock(ImageService::class);
        $uploader->shouldReceive('upload')->once()->andReturn('storefront-media/faq-pic.png');
        $this->app->instance(ImageService::class, $uploader);

        $response = $this->actingAs($owner, 'accounts')
            ->post("/store/{$tenant->slug}/admin/storefront/media/editor/upload", [
                'file' => UploadedFile::fake()->image('faq-pic.png'),
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('url', fn ($url) => is_string($url) && $url !== '');

        $row = StorefrontMedia::withoutTenantScope()->find($response->json('id'));
        $this->assertNotNull($row);
        $this->assertEquals($tenant->id, (int) $row->tenant_id);
        $this->assertEquals('storefront-media/faq-pic.png', $row->path);
    }

    public function test_faq_pages_expose_only_current_tenant_media(): void
    {
        [$tenantA] = $this->seedStore('faq-shop-a');
        [$tenantB, $ownerB] = $this->seedStore('faq-shop-b');
        $mediaA = $this->makeMedia($tenantA, 'a.png', 'storefront-media/faq-a.png');
        $mediaB = $this->makeMedia($tenantB, 'b.png', 'storefront-media/faq-b.png');

        $this->actingAs($ownerB, 'accounts')
            ->get("/store/{$tenantB->slug}/admin/faqs/create")
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Faqs/Create')
                ->where('mediaLibrary', fn ($data) => collect($data)->pluck('id')->contains($mediaB->id))
                ->where('mediaLibrary', fn ($data) => collect($data)->pluck('id')->doesntContain($mediaA->id))
            );
    }

    public function test_faq_answer_with_library_image_saves_and_updates(): void
    {
        [$tenant, $owner] = $this->seedStore('faq-save-shop');
        $media = $this->makeMedia($tenant, 'answer.png', 'storefront-media/answer.png');

        $answer = '<p>See this:</p><p><img src="storefront-media/answer.png" alt="answer"></p>';

        $this->actingAs($owner, 'accounts')
            ->post("/store/{$tenant->slug}/admin/faqs", [
                'category' => 'general',
                'question_en' => 'How to track?',
                'answer_en' => $answer,
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors();

        $faq = WebsiteFaq::withoutTenantScope()->where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($faq);
        $this->assertStringContainsString('storefront-media/answer.png', $faq->answer_en);

        $this->actingAs($owner, 'accounts')
            ->put("/store/{$tenant->slug}/admin/faqs/{$faq->id}", [
                'category' => 'general',
                'question_en' => 'How to track?',
                'answer_en' => '<p>Updated answer without image.</p>',
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertStringNotContainsString('storefront-media/answer.png', $faq->fresh()->answer_en);
    }

    public function test_library_image_used_in_faq_answer_cannot_be_deleted(): void
    {
        [$tenant, $owner] = $this->seedStore('faq-guard-shop');
        $media = $this->makeMedia($tenant, 'guard.png', 'storefront-media/guard.png');

        $this->actingAs($owner, 'accounts')
            ->post("/store/{$tenant->slug}/admin/faqs", [
                'category' => 'general',
                'question_en' => 'Guarded?',
                'answer_en' => '<p><img src="storefront-media/guard.png"></p>',
                'is_active' => true,
            ])
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
}
