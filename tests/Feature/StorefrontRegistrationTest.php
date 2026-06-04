<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\WebsiteInfo;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StorefrontRegistrationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createMinimalSchema();

        $this->withoutMiddleware(
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Inertia\Middleware::class,
        );

        // Create default website settings with registration enabled
        WebsiteInfo::create([
            'allow_registration' => true,
            'maintenance_mode' => false,
        ]);
        Cache::forget('website_settings_default');
    }

    private function createTenantWithSettings(string $name, string $slug): Tenant
    {
        $tenant = Tenant::create([
            'name' => $name,
            'slug' => $slug,
            'store_url' => "/store/{$slug}",
            'status' => 'active',
        ]);

        // Create tenant-specific website settings
        WebsiteInfo::create([
            'tenant_id' => $tenant->id,
            'allow_registration' => true,
            'maintenance_mode' => false,
        ]);
        Cache::forget("website_settings_{$tenant->id}");

        return $tenant;
    }

    private function createMinimalSchema(): void
    {
        $tables = [
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
            'users' => function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->string('status')->default('active');
                $table->boolean('is_owner')->default(false);
                $table->boolean('allow_cod')->default(true);
                $table->text('profile_image')->nullable();
                $table->json('notification_preferences')->nullable();
                $table->rememberToken();
                $table->timestamps();
            },
            'personal_access_tokens' => function ($table) {
                $table->id();
                $table->morphs('tokenable');
                $table->string('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            },
            'website_infos' => function ($table) {
                $table->id();
                $table->string('site_name')->nullable();
                $table->string('theme_color')->nullable();
                $table->string('default_language')->nullable();
                $table->string('timezone')->nullable();
                $table->string('currency_code')->nullable();
                $table->string('currency_symbol')->nullable();
                $table->string('date_format')->nullable();
                $table->boolean('maintenance_mode')->default(false);
                $table->boolean('enable_wishlist')->default(true);
                $table->boolean('allow_registration')->default(true);
                $table->unsignedBigInteger('tenant_id')->nullable()->unique();
                $table->timestamps();
            },
        ];

        foreach ($tables as $name => $callback) {
            if (!Schema::hasTable($name)) {
                Schema::create($name, $callback);
            }
        }

        // Create permissions tables if not exist
        $tableNames = config('permission.table_names');
        if (!Schema::hasTable($tableNames['roles'])) {
            Schema::create($tableNames['roles'], function ($table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
                $table->string('name');
                $table->string('guard_name');
                $table->timestamps();
                $table->unique(['tenant_id', 'name', 'guard_name'], 'roles_tenant_id_name_guard_name_unique');
            });
        }

        if (!Schema::hasTable($tableNames['model_has_roles'])) {
            Schema::create($tableNames['model_has_roles'], function ($table) use ($tableNames) {
                $table->unsignedBigInteger('role_id');
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
                $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');
                $table->foreign('role_id')
                    ->references('id')
                    ->on($tableNames['roles'])
                    ->onDelete('cascade');
                $table->primary(['role_id', 'model_id', 'model_type']);
            });
        }

        if (!Schema::hasTable($tableNames['permissions'])) {
            Schema::create($tableNames['permissions'], function ($table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->string('guard_name');
                $table->timestamps();
                $table->unique(['name', 'guard_name']);
            });
        }

        if (!Schema::hasTable('activity_logs')) {
            Schema::create('activity_logs', function ($table) {
                $table->id();
                $table->string('log_name')->nullable();
                $table->text('description');
                $table->string('subject_type')->nullable();
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->string('causer_type')->nullable();
                $table->unsignedBigInteger('causer_id')->nullable();
                $table->unsignedBigInteger('impersonator_id')->nullable();
                $table->unsignedBigInteger('impersonated_user_id')->nullable();
                $table->json('properties')->nullable();
                $table->string('event')->nullable();
                $table->string('batch_uuid')->nullable();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->timestamps();
                $table->index(['subject_type', 'subject_id']);
            });
        }
    }

    public function test_global_register_get_redirects_with_message(): void
    {
        $response = $this->get('/register');

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error', 'Please register from a specific store.');
    }

    public function test_global_register_post_redirects_with_message(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error', 'Please register from a specific store.');
    }

    public function test_storefront_register_get_returns_ok(): void
    {
        $tenant = $this->createTenantWithSettings('Coca Cola', 'coca-cola');

        $response = $this->get("/store/{$tenant->slug}/register");

        $response->assertOk();
    }

    public function test_storefront_register_creates_user_with_tenant_and_customer_role(): void
    {
        $tenant = $this->createTenantWithSettings('May Fashion', 'may-fashion');

        $response = $this->post("/store/{$tenant->slug}/register", [
            'name' => 'Store Customer',
            'email' => 'customer@mayfashion.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('client.dashboard', absolute: false));
        $this->assertAuthenticated();

        $user = User::where('email', 'customer@mayfashion.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals($tenant->id, $user->tenant_id);
        $this->assertTrue(Hash::check('password', $user->password));

        $this->assertTrue($user->hasRole('customer'));
    }

    public function test_storefront_register_creates_tenant_scoped_customer_role(): void
    {
        $tenantA = $this->createTenantWithSettings('Store A', 'store-a');
        $tenantB = $this->createTenantWithSettings('Store B', 'store-b');

        // Register at Store A
        $responseA = $this->post("/store/{$tenantA->slug}/register", [
            'name' => 'User A',
            'email' => 'usera@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $responseA->assertRedirect(route('client.dashboard', absolute: false));

        $this->post('/logout');
        $this->assertGuest();

        // Register at Store B
        $responseB = $this->post("/store/{$tenantB->slug}/register", [
            'name' => 'User B',
            'email' => 'userb@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();

        $userA = User::where('email', 'usera@example.com')->first();
        $userB = User::where('email', 'userb@example.com')->first();

        $this->assertNotNull($userA, 'User A not found');
        $this->assertNotNull($userB, 'User B not found');

        $this->assertEquals($tenantA->id, $userA->tenant_id);
        $this->assertEquals($tenantB->id, $userB->tenant_id);

        // Each user should have a tenant-scoped customer role
        $rolesA = $userA->getRoleNames();
        $rolesB = $userB->getRoleNames();
        $this->assertCount(1, $rolesA);
        $this->assertEquals('customer', $rolesA->first());
        $this->assertCount(1, $rolesB);
        $this->assertEquals('customer', $rolesB->first());
    }
}
