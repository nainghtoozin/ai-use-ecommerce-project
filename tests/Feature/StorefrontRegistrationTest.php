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
                $table->timestamp('activated_at')->nullable();
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

    public function test_global_register_get_renders_registration_screen(): void
    {
        $response = $this->get('/register');

        $response->assertOk();
    }

    public function test_global_register_creates_account_and_redirects_to_verification(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $this->assertAuthenticated('accounts');
        $this->assertDatabaseHas('accounts', ['email' => 'test@example.com']);
    }

    public function test_storefront_register_get_returns_ok(): void
    {
        $tenant = $this->createTenantWithSettings('Coca Cola', 'coca-cola');

        $response = $this->get("/store/{$tenant->slug}/register");

        $response->assertOk();
    }

    public function test_storefront_register_creates_account_with_membership_and_customer_role(): void
    {
        $tenant = $this->createTenantWithSettings('May Fashion', 'may-fashion');

        $response = $this->post("/store/{$tenant->slug}/register", [
            'name' => 'Store Customer',
            'email' => 'customer@mayfashion.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'phone' => '09123456789',
        ]);

        $response->assertRedirect(route('storefront.index', ['store_slug' => $tenant->slug], absolute: false));
        $this->assertAuthenticated('accounts');

        $account = \App\Models\Account::where('email', 'customer@mayfashion.com')->first();
        $this->assertNotNull($account);
        $this->assertTrue(Hash::check('Password123', $account->password));

        $membership = \App\Models\TenantMembership::where('account_id', $account->id)
            ->where('tenant_id', $tenant->id)
            ->first();
        $this->assertNotNull($membership);
        $role = \App\Models\Role::withoutTenantScope()->find($membership->role_id);
        $this->assertNotNull($role);
        $this->assertEquals('customer', $role->name);
    }

    public function test_storefront_register_creates_tenant_scoped_customer_role(): void
    {
        $tenantA = $this->createTenantWithSettings('Store A', 'store-a');
        $tenantB = $this->createTenantWithSettings('Store B', 'store-b');

        // Register at Store A
        $responseA = $this->post("/store/{$tenantA->slug}/register", [
            'name' => 'User A',
            'email' => 'usera@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'phone' => '09111111111',
        ]);

        $this->assertAuthenticated('accounts');
        $responseA->assertRedirect(route('storefront.index', ['store_slug' => $tenantA->slug], absolute: false));

        $this->post('/logout');
        $this->assertGuest('accounts');

        // Register at Store B
        $responseB = $this->post("/store/{$tenantB->slug}/register", [
            'name' => 'User B',
            'email' => 'userb@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'phone' => '09222222222',
        ]);

        $this->assertAuthenticated('accounts');

        $accountA = \App\Models\Account::where('email', 'usera@example.com')->first();
        $accountB = \App\Models\Account::where('email', 'userb@example.com')->first();

        $this->assertNotNull($accountA, 'Account A not found');
        $this->assertNotNull($accountB, 'Account B not found');

        $membershipA = \App\Models\TenantMembership::where('account_id', $accountA->id)->first();
        $membershipB = \App\Models\TenantMembership::where('account_id', $accountB->id)->first();

        $this->assertEquals($tenantA->id, $membershipA->tenant_id);
        $this->assertEquals($tenantB->id, $membershipB->tenant_id);

        // Each membership should reference a customer role scoped to its own tenant
        $roleA = \App\Models\Role::withoutTenantScope()->find($membershipA->role_id);
        $roleB = \App\Models\Role::withoutTenantScope()->find($membershipB->role_id);
        $this->assertNotNull($roleA);
        $this->assertNotNull($roleB);
        $this->assertEquals('customer', $roleA->name);
        $this->assertEquals($tenantA->id, $roleA->tenant_id);
        $this->assertEquals('customer', $roleB->name);
        $this->assertEquals($tenantB->id, $roleB->tenant_id);
    }
}
