<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MerchantManagementTest extends TestCase
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

        Permission::create(['name' => 'dashboard.view', 'guard_name' => 'web']);

        $superadminRole = Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        $superadminRole->syncPermissions(Permission::all());

        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        Role::create(['name' => 'customer', 'guard_name' => 'web']);

        $this->superadmin = User::create([
            'name' => 'Super Admin',
            'email' => 'super@admin.com',
            'password' => Hash::make('password'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $this->superadmin->assignRole('superadmin');
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
                $table->string('profile_image')->nullable();
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
            'tenants' => function ($table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('domain')->nullable()->unique();
                $table->string('store_url')->nullable();
                $table->string('email')->nullable();
                $table->string('logo')->nullable();
                $table->string('status')->default('active');
                $table->json('settings')->nullable();
                $table->unsignedBigInteger('subscription_plan_id')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->bigInteger('used_storage_bytes')->default(0);
                $table->timestamps();
            },
            'plans' => function ($table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->decimal('monthly_price', 10, 2)->nullable();
                $table->decimal('yearly_price', 10, 2)->nullable();
                $table->integer('product_limit')->nullable();
                $table->integer('staff_limit')->nullable();
                $table->bigInteger('storage_limit')->nullable();
                $table->boolean('analytics_enabled')->default(false);
                $table->boolean('custom_domain_enabled')->default(false);
                $table->string('status')->default('active');
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
            'notifications' => function ($table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->timestamps();
            },
            'subscriptions' => function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('plan_id')->nullable();
                $table->string('billing_interval')->default('monthly');
                $table->string('status')->default('active');
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('trial_ends_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamp('suspended_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            },

        ];

        foreach ($tables as $name => $callback) {
            if (!Schema::hasTable($name)) {
                Schema::create($name, $callback);
            }
        }

    }

    public function test_merchant_creation_generates_store_url(): void
    {
        $response = $this->actingAs($this->superadmin)->post('/superadmin/tenants', [
            'name' => 'May Fashion',
            'slug' => 'may-fashion',
            'email' => 'may@example.com',
            'status' => 'active',
        ]);

        $response->assertRedirect();

        $tenant = Tenant::where('slug', 'may-fashion')->first();
        $this->assertNotNull($tenant);
        $this->assertEquals('/store/may-fashion', $tenant->store_url);
    }

    public function test_merchant_creation_with_admin_generates_store_url(): void
    {
        $response = $this->actingAs($this->superadmin)->post('/superadmin/tenants', [
            'name' => 'Coca Cola',
            'slug' => 'coca-cola',
            'email' => 'admin@cocacola.com',
            'status' => 'active',
            'create_admin' => true,
            'admin_name' => 'Coca Admin',
            'admin_email' => 'admin@cocacola.com',
            'admin_password' => 'password123',
        ]);

        $response->assertRedirect();

        $tenant = Tenant::where('slug', 'coca-cola')->first();
        $this->assertNotNull($tenant);
        $this->assertEquals('/store/coca-cola', $tenant->store_url);
    }

    public function test_store_slug_reuses_tenant_slug(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Store',
            'slug' => 'test-store',
            'store_url' => '/store/test-store',
            'status' => 'active',
        ]);

        $this->assertEquals('test-store', $tenant->store_slug);
    }

    public function test_updating_slug_updates_store_url(): void
    {
        $tenant = Tenant::create([
            'name' => 'Old Name',
            'slug' => 'old-store',
            'store_url' => '/store/old-store',
            'status' => 'active',
        ]);

        $this->actingAs($this->superadmin)->put("/superadmin/tenants/{$tenant->id}", [
            'name' => 'New Name',
            'slug' => 'new-store',
            'domain' => null,
            'email' => null,
            'status' => 'active',
        ]);

        $tenant->refresh();
        $this->assertEquals('/store/new-store', $tenant->store_url);
    }
}
