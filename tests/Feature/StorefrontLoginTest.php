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

class StorefrontLoginTest extends TestCase
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

        WebsiteInfo::create([
            'allow_registration' => true,
            'maintenance_mode' => false,
        ]);
        Cache::forget('website_settings_default');
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
                $table->boolean('allow_registration')->default(true);
                $table->boolean('enable_wishlist')->default(true);
                $table->unsignedBigInteger('tenant_id')->nullable()->unique();
                $table->timestamps();
            },
        ];

        foreach ($tables as $name => $callback) {
            if (!Schema::hasTable($name)) {
                Schema::create($name, $callback);
            }
        }

        $tableNames = config('permission.table_names');
        if (!Schema::hasTable($tableNames['roles'])) {
            Schema::create($tableNames['roles'], function ($table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('tenant_id')->nullable();
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

    private function createTenantWithSettings(string $name, string $slug): Tenant
    {
        $tenant = Tenant::create([
            'name' => $name,
            'slug' => $slug,
            'store_url' => "/store/{$slug}",
            'status' => 'active',
        ]);

        WebsiteInfo::create([
            'tenant_id' => $tenant->id,
            'allow_registration' => true,
            'maintenance_mode' => false,
        ]);
        Cache::forget("website_settings_{$tenant->id}");

        return $tenant;
    }

    private function createCustomerUser(Tenant $tenant, string $email = 'customer@test.com', string $password = 'password'): User
    {
        $user = new User();
        $user->name = 'Test Customer';
        $user->email = $email;
        $user->password = Hash::make($password);
        $user->tenant_id = $tenant->id;
        $user->save();

        $role = \App\Models\Role::firstOrCreate([
            'name' => 'customer',
            'guard_name' => 'web',
            'tenant_id' => $tenant->id,
        ]);

        $user->assignRole($role);

        return $user;
    }

    public function test_storefront_login_get_returns_ok(): void
    {
        $tenant = $this->createTenantWithSettings('Coca Cola', 'coca-cola');

        $response = $this->get("/store/{$tenant->slug}/login");

        $response->assertOk();
    }

    public function test_storefront_login_without_tenant_redirects_to_global_login(): void
    {
        $response = $this->get('/store/no-such-store/login');

        $response->assertNotFound();
    }

    public function test_customer_can_login_at_their_store(): void
    {
        $tenant = $this->createTenantWithSettings('May Fashion', 'may-fashion');
        $this->createCustomerUser($tenant, 'customer@mayfashion.com');

        $response = $this->post("/store/{$tenant->slug}/login", [
            'email' => 'customer@mayfashion.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('storefront.index', ['store_slug' => $tenant->slug], absolute: false));
        $this->assertAuthenticated();
    }

    public function test_customer_cannot_login_at_different_store(): void
    {
        $tenantA = $this->createTenantWithSettings('Store A', 'store-a');
        $tenantB = $this->createTenantWithSettings('Store B', 'store-b');

        $this->createCustomerUser($tenantA, 'customer@store-a.com');

        // Try to log in at Store B with Store A's credentials
        $response = $this->post("/store/{$tenantB->slug}/login", [
            'email' => 'customer@store-a.com',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_storefront_login_rejects_invalid_credentials(): void
    {
        $tenant = $this->createTenantWithSettings('Test Store', 'test-store');
        $this->createCustomerUser($tenant, 'customer@test.com');

        $response = $this->post("/store/{$tenant->slug}/login", [
            'email' => 'customer@test.com',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_global_login_still_works_for_admin(): void
    {
        // Create a superadmin user (tenant_id = null)
        $user = new User();
        $user->name = 'Super Admin';
        $user->email = 'admin@test.com';
        $user->password = Hash::make('password');
        $user->save();

        $role = \App\Models\Role::firstOrCreate([
            'name' => 'superadmin',
            'guard_name' => 'web',
            'tenant_id' => null,
        ]);

        $user->assignRole($role);

        $response = $this->post('/login', [
            'email' => 'admin@test.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.dashboard', absolute: false));
        $this->assertAuthenticated();
    }

    public function test_storefront_login_redirects_admin_to_admin_dashboard(): void
    {
        $tenant = $this->createTenantWithSettings('Store X', 'store-x');

        $adminUser = new User();
        $adminUser->name = 'Store Admin';
        $adminUser->email = 'admin@store-x.com';
        $adminUser->password = Hash::make('password');
        $adminUser->tenant_id = $tenant->id;
        $adminUser->save();

        $adminRole = \App\Models\Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
            'tenant_id' => $tenant->id,
        ]);

        $adminUser->assignRole($adminRole);

        $response = $this->post("/store/{$tenant->slug}/login", [
            'email' => 'admin@store-x.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.dashboard', absolute: false));
        $this->assertAuthenticated();
    }
}
