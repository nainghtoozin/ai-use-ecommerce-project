<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PlatformSettingsTest extends TestCase
{
    use DatabaseTransactions;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createMinimalSchema();

        $this->withoutMiddleware(
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Inertia\Middleware::class,
        );

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'platform.settings.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'platform.settings.update', 'guard_name' => 'web']);

        $superadminRole = Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        $superadminRole->syncPermissions(Permission::all());

        $this->superadmin = User::create([
            'name' => 'Super Admin',
            'email' => 'super@admin.com',
            'password' => Hash::make('password'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $this->superadmin->assignRole('superadmin');

        // Seed a default platform_settings row
        PlatformSetting::create([
            'site_name' => 'My Application',
            'site_logo' => null,
            'favicon' => null,
            'support_email' => null,
            'maintenance_mode' => false,
            'registration_enabled' => true,
        ]);

        // Clear the cache so ::current() fetches from DB
        PlatformSetting::clearCache();
    }

    private function createMinimalSchema(): void
    {
        $tables = [
            'permissions' => function ($table) {
                $table->id();
                $table->string('name');
                $table->string('guard_name');
                $table->timestamps();
                $table->unique(['name', 'guard_name']);
            },
            'roles' => function ($table) {
                $table->id();
                $table->string('name');
                $table->string('guard_name');
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->timestamps();
                $table->unique(['name', 'guard_name', 'tenant_id']);
            },
            'model_has_roles' => function ($table) {
                $table->unsignedBigInteger('role_id');
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
                $table->index(['model_id', 'model_type']);
                $table->primary(['role_id', 'model_id', 'model_type']);
            },
            'role_has_permissions' => function ($table) {
                $table->unsignedBigInteger('permission_id');
                $table->unsignedBigInteger('role_id');
                $table->primary(['permission_id', 'role_id']);
            },
            'model_has_permissions' => function ($table) {
                $table->unsignedBigInteger('permission_id');
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
                $table->index(['model_id', 'model_type']);
                $table->primary(['permission_id', 'model_id', 'model_type']);
            },
            'users' => function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->string('status')->default('active');
                $table->boolean('is_owner')->default(false);
                $table->rememberToken();
                $table->timestamps();
            },
            'activity_logs' => function ($table) {
                $table->id();
                $table->string('log_name');
                $table->text('description');
                $table->string('subject_type')->nullable();
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->string('causer_type')->nullable();
                $table->unsignedBigInteger('causer_id')->nullable();
                $table->unsignedBigInteger('impersonator_id')->nullable();
                $table->unsignedBigInteger('impersonated_user_id')->nullable();
                $table->text('properties')->nullable();
                $table->string('event')->nullable();
                $table->string('batch_uuid')->nullable();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->index(['subject_type', 'subject_id']);
                $table->index(['causer_type', 'causer_id']);
                $table->timestamps();
            },
            'website_infos' => function ($table) {
                $table->id();
                $table->string('site_name')->nullable();
                $table->string('site_tagline')->nullable();
                $table->text('site_description')->nullable();
                $table->string('site_keywords')->nullable();
                $table->string('theme_color')->nullable();
                $table->string('default_language')->nullable();
                $table->string('timezone')->nullable();
                $table->string('currency_code')->nullable();
                $table->string('currency_symbol')->nullable();
                $table->string('date_format')->nullable();
                $table->string('logo')->nullable();
                $table->string('favicon')->nullable();
                $table->string('footer_logo')->nullable();
                $table->string('contact_email')->nullable();
                $table->string('support_email')->nullable();
                $table->string('phone')->nullable();
                $table->string('whatsapp_number')->nullable();
                $table->text('address')->nullable();
                $table->string('country')->nullable();
                $table->text('google_maps_embed_url')->nullable();
                $table->string('about_title')->nullable();
                $table->text('about_description')->nullable();
                $table->string('mission_title')->nullable();
                $table->text('mission_description')->nullable();
                $table->string('vision_title')->nullable();
                $table->text('vision_description')->nullable();
                $table->string('facebook_url')->nullable();
                $table->string('instagram_url')->nullable();
                $table->string('twitter_url')->nullable();
                $table->string('linkedin_url')->nullable();
                $table->string('youtube_url')->nullable();
                $table->string('meta_title')->nullable();
                $table->text('meta_description')->nullable();
                $table->string('meta_keywords')->nullable();
                $table->string('canonical_url')->nullable();
                $table->string('robots_meta')->nullable();
                $table->string('og_image')->nullable();
                $table->string('hero_title')->nullable();
                $table->string('hero_subtitle')->nullable();
                $table->string('hero_button_text')->nullable();
                $table->string('hero_button_link')->nullable();
                $table->string('hero_image')->nullable();
                $table->text('hero_images')->nullable();
                $table->text('footer_description')->nullable();
                $table->string('footer_copyright')->nullable();
                $table->text('footer_settings')->nullable();
                $table->text('contact_info')->nullable();
                $table->text('address_info')->nullable();
                $table->boolean('maintenance_mode')->default(false);
                $table->text('maintenance_message')->nullable();
                $table->boolean('allow_registration')->default(true);
                $table->boolean('enable_reviews')->default(true);
                $table->boolean('enable_wishlist')->default(true);
                $table->boolean('enable_compare')->default(false);
                $table->boolean('guest_checkout_enabled')->default(true);
                $table->boolean('cod_enabled')->default(true);
                $table->decimal('free_shipping_threshold', 10, 2)->nullable();
                $table->decimal('default_shipping_fee', 10, 2)->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('tenant_id')->nullable()->unique();
                $table->timestamps();
            },
            'platform_settings' => function ($table) {
                $table->id();
                $table->string('site_name')->default('My Application');
                $table->string('site_logo')->nullable();
                $table->string('favicon')->nullable();
                $table->string('support_email')->nullable();
                $table->boolean('maintenance_mode')->default(false);
                $table->boolean('registration_enabled')->default(true);
                $table->boolean('trial_enabled')->default(true);
                $table->integer('trial_days')->default(14);
                $table->boolean('allow_trial_renewal')->default(false);
                $table->unsignedTinyInteger('max_trial_renewals')->default(0);
                $table->timestamps();
            },
        ];

        foreach ($tables as $name => $callback) {
            if (!Schema::hasTable($name)) {
                Schema::create($name, $callback);
            }
        }
    }

    public function test_superadmin_can_view_platform_settings_page(): void
    {
        $this->actingAs($this->superadmin);

        $response = $this->get('/superadmin/platform-settings');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('SuperAdmin/PlatformSettings/Index')
            ->has('settings')
        );
    }

    public function test_superadmin_can_update_platform_name(): void
    {
        $this->actingAs($this->superadmin);

        $response = $this->post('/superadmin/platform-settings', [
            'site_name' => 'Updated Platform',
            'support_email' => '',
            'maintenance_mode' => '0',
            'registration_enabled' => '1',
        ]);

        $response->assertSessionHas('success');

        $this->assertEquals('Updated Platform', PlatformSetting::current()->site_name);
    }

    public function test_superadmin_can_update_support_email(): void
    {
        $this->actingAs($this->superadmin);

        $response = $this->post('/superadmin/platform-settings', [
            'site_name' => 'My Application',
            'support_email' => 'support@example.com',
            'maintenance_mode' => '0',
            'registration_enabled' => '1',
        ]);

        $response->assertSessionHas('success');

        $this->assertEquals('support@example.com', PlatformSetting::current()->support_email);
    }

    public function test_superadmin_can_upload_logo(): void
    {
        Storage::fake('public');

        $this->actingAs($this->superadmin);

        $file = UploadedFile::fake()->image('logo.png', 200, 200);

        $response = $this->post('/superadmin/platform-settings', [
            'site_name' => 'My Application',
            'support_email' => '',
            'maintenance_mode' => '0',
            'registration_enabled' => '1',
            'logo' => $file,
        ]);

        $response->assertSessionHas('success');

        $settings = PlatformSetting::current();
        $this->assertNotNull($settings->site_logo);
        $this->assertStringContainsString('platform-settings/', $settings->site_logo);

        Storage::disk('public')->assertExists($settings->site_logo);
    }

    public function test_superadmin_can_upload_favicon(): void
    {
        Storage::fake('public');

        $this->actingAs($this->superadmin);

        $file = UploadedFile::fake()->image('favicon.png', 64, 64);

        $response = $this->post('/superadmin/platform-settings', [
            'site_name' => 'My Application',
            'support_email' => '',
            'maintenance_mode' => '0',
            'registration_enabled' => '1',
            'favicon' => $file,
        ]);

        $response->assertSessionHas('success');

        $settings = PlatformSetting::current();
        $this->assertNotNull($settings->favicon);
        $this->assertStringContainsString('platform-settings/', $settings->favicon);

        Storage::disk('public')->assertExists($settings->favicon);
    }

    public function test_superadmin_can_toggle_maintenance_mode(): void
    {
        $this->actingAs($this->superadmin);

        $this->post('/superadmin/platform-settings', [
            'site_name' => 'My Application',
            'support_email' => '',
            'maintenance_mode' => '1',
            'registration_enabled' => '1',
        ]);

        $this->assertTrue(PlatformSetting::current()->maintenance_mode);

        $this->post('/superadmin/platform-settings', [
            'site_name' => 'My Application',
            'support_email' => '',
            'maintenance_mode' => '0',
            'registration_enabled' => '1',
        ]);

        $this->assertFalse(PlatformSetting::current()->maintenance_mode);
    }

    public function test_superadmin_can_toggle_registration_enabled(): void
    {
        $this->actingAs($this->superadmin);

        $this->post('/superadmin/platform-settings', [
            'site_name' => 'My Application',
            'support_email' => '',
            'maintenance_mode' => '0',
            'registration_enabled' => '0',
        ]);

        $this->assertFalse(PlatformSetting::current()->registration_enabled);

        $this->post('/superadmin/platform-settings', [
            'site_name' => 'My Application',
            'support_email' => '',
            'maintenance_mode' => '0',
            'registration_enabled' => '1',
        ]);

        $this->assertTrue(PlatformSetting::current()->registration_enabled);
    }

    public function test_persistence_after_refresh(): void
    {
        $this->actingAs($this->superadmin);

        $this->post('/superadmin/platform-settings', [
            'site_name' => 'Persistent Platform',
            'support_email' => 'persist@test.com',
            'maintenance_mode' => '1',
            'registration_enabled' => '0',
        ]);

        PlatformSetting::clearCache();

        $settings = PlatformSetting::current();
        $this->assertEquals('Persistent Platform', $settings->site_name);
        $this->assertEquals('persist@test.com', $settings->support_email);
        $this->assertTrue($settings->maintenance_mode);
        $this->assertFalse($settings->registration_enabled);
    }

    public function test_guest_cannot_access_platform_settings(): void
    {
        $response = $this->get('/superadmin/platform-settings');
        $response->assertRedirect('/login');
    }
}
