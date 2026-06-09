<?php

namespace Tests\Feature;

use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StorefrontCustomerTest extends TestCase
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

        \App\Models\WebsiteInfo::create([
            'allow_registration' => true,
            'maintenance_mode' => false,
        ]);
        \Illuminate\Support\Facades\Cache::forget('website_settings_default');
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
            'orders' => function ($table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('first_name');
                $table->string('last_name');
                $table->string('phone', 20);
                $table->string('email')->nullable();
                $table->text('address');
                $table->string('city')->nullable();
                $table->string('postal_code')->nullable();
                $table->text('notes')->nullable();
                $table->string('payment_method')->default('cash');
                $table->decimal('total_amount', 10, 2);
                $table->string('payment_status')->default('pending');
                $table->string('order_status')->default('pending');
                $table->timestamps();
            },
            'order_items' => function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('product_id')->nullable();
                $table->unsignedBigInteger('variant_id')->nullable();
                $table->integer('quantity')->default(1);
                $table->decimal('price', 10, 2)->default(0);
                $table->timestamps();
            },
            'customer_addresses' => function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('user_id');
                $table->string('label')->default('Home');
                $table->string('first_name');
                $table->string('last_name');
                $table->string('phone', 20);
                $table->text('address_line');
                $table->unsignedBigInteger('city_id')->nullable();
                $table->unsignedBigInteger('township_id')->nullable();
                $table->string('postal_code', 20)->nullable();
                $table->boolean('is_default')->default(false);
                $table->text('notes')->nullable();
                $table->timestamps();
            },
            'cities' => function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('name');
                $table->decimal('delivery_fee', 10, 2)->default(0);
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
            'activity_logs' => function ($table) {
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
    }

    private function createTenant(string $name, string $slug): Tenant
    {
        $tenant = Tenant::create([
            'name' => $name,
            'slug' => $slug,
            'store_url' => "/store/{$slug}",
            'status' => 'active',
        ]);

        \App\Models\WebsiteInfo::create([
            'tenant_id' => $tenant->id,
            'allow_registration' => true,
            'maintenance_mode' => false,
        ]);
        \Illuminate\Support\Facades\Cache::forget("website_settings_{$tenant->id}");

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

    private function loginAsCustomer(Tenant $tenant, User $user): void
    {
        app()->instance('current.tenant', $tenant);
        $this->actingAs($user);
    }

    public function test_guest_is_redirected_to_login_when_accessing_account(): void
    {
        $tenant = $this->createTenant('Test Store', 'test-store');

        $response = $this->get("/store/{$tenant->slug}/customer/account");

        $response->assertRedirect(route('login'));
    }

    public function test_customer_can_view_account_page(): void
    {
        $tenant = $this->createTenant('Test Store', 'test-store');
        $user = $this->createCustomerUser($tenant, 'customer@test.com');
        $this->loginAsCustomer($tenant, $user);

        $response = $this->get("/store/{$tenant->slug}/customer/account");

        $response->assertOk();
    }

    public function test_customer_is_blocked_from_other_tenant_account(): void
    {
        $tenantA = $this->createTenant('Store A', 'store-a');
        $tenantB = $this->createTenant('Store B', 'store-b');

        $user = $this->createCustomerUser($tenantA, 'alice@store-a.com');

        app()->instance('current.tenant', $tenantB);
        $this->actingAs($user);

        $response = $this->get("/store/{$tenantB->slug}/customer/account");

        $response->assertRedirect(route('storefront.login', ['store_slug' => $tenantB->slug], absolute: false));
        $this->assertGuest();
    }

    public function test_customer_can_view_orders_page(): void
    {
        $tenant = $this->createTenant('Test Store', 'test-store');
        $user = $this->createCustomerUser($tenant, 'customer@test.com');
        $this->loginAsCustomer($tenant, $user);

        $response = $this->get("/store/{$tenant->slug}/customer/orders");

        $response->assertOk();
    }

    public function test_customer_can_view_own_order_detail(): void
    {
        $tenant = $this->createTenant('Test Store', 'test-store');
        $user = $this->createCustomerUser($tenant, 'alice@test.com');
        $order = $this->createOrder($user, $tenant);

        $this->loginAsCustomer($tenant, $user);

        $response = $this->get("/store/{$tenant->slug}/customer/orders/{$order->id}");

        $response->assertOk();
    }

    public function test_customer_cannot_view_another_customers_order(): void
    {
        $tenant = $this->createTenant('Test Store', 'test-store');

        $userA = $this->createCustomerUser($tenant, 'alice@test.com');
        $userB = $this->createCustomerUser($tenant, 'bob@test.com', 'password2');

        $order = $this->createOrder($userB, $tenant);

        $this->loginAsCustomer($tenant, $userA);

        $response = $this->get("/store/{$tenant->slug}/customer/orders/{$order->id}");

        $response->assertNotFound();
    }

    public function test_customer_can_view_addresses_page(): void
    {
        $tenant = $this->createTenant('Test Store', 'test-store');
        $user = $this->createCustomerUser($tenant, 'customer@test.com');
        $this->loginAsCustomer($tenant, $user);

        $response = $this->get("/store/{$tenant->slug}/customer/addresses");

        $response->assertOk();
    }

    public function test_customer_can_create_address(): void
    {
        $tenant = $this->createTenant('Test Store', 'test-store');
        $user = $this->createCustomerUser($tenant, 'customer@test.com');
        $this->loginAsCustomer($tenant, $user);

        $response = $this->post("/store/{$tenant->slug}/customer/addresses", [
            'label' => 'Home',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '09123456789',
            'address_line' => '123 Main Street',
            'postal_code' => '11001',
            'is_default' => true,
        ]);

        $response->assertRedirect(route('storefront.customer.addresses', ['store_slug' => $tenant->slug], absolute: false));

        $this->assertDatabaseHas('customer_addresses', [
            'user_id' => $user->id,
            'label' => 'Home',
            'address_line' => '123 Main Street',
            'is_default' => true,
        ]);
    }

    public function test_customer_can_update_address(): void
    {
        $tenant = $this->createTenant('Test Store', 'test-store');
        $user = $this->createCustomerUser($tenant, 'customer@test.com');
        $this->loginAsCustomer($tenant, $user);

        $address = CustomerAddress::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'label' => 'Home',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '09123456789',
            'address_line' => '123 Main Street',
        ]);

        $response = $this->put("/store/{$tenant->slug}/customer/addresses/{$address->id}", [
            'label' => 'Office',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '09123456789',
            'address_line' => '456 Office Road',
            'is_default' => true,
        ]);

        $response->assertRedirect(route('storefront.customer.addresses', ['store_slug' => $tenant->slug], absolute: false));

        $this->assertDatabaseHas('customer_addresses', [
            'id' => $address->id,
            'label' => 'Office',
            'address_line' => '456 Office Road',
        ]);
    }

    public function test_customer_can_delete_address(): void
    {
        $tenant = $this->createTenant('Test Store', 'test-store');
        $user = $this->createCustomerUser($tenant, 'customer@test.com');
        $this->loginAsCustomer($tenant, $user);

        $address = CustomerAddress::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'label' => 'Home',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '09123456789',
            'address_line' => '123 Main Street',
        ]);

        $response = $this->delete("/store/{$tenant->slug}/customer/addresses/{$address->id}");

        $response->assertRedirect(route('storefront.customer.addresses', ['store_slug' => $tenant->slug], absolute: false));

        $this->assertDatabaseMissing('customer_addresses', ['id' => $address->id]);
    }

    public function test_cross_tenant_address_access_is_blocked(): void
    {
        $tenantA = $this->createTenant('Store A', 'store-a');
        $tenantB = $this->createTenant('Store B', 'store-b');

        $user = $this->createCustomerUser($tenantA, 'alice@store-a.com');

        app()->instance('current.tenant', $tenantB);
        $this->actingAs($user);

        $response = $this->get("/store/{$tenantB->slug}/customer/addresses");

        $response->assertRedirect(route('storefront.login', ['store_slug' => $tenantB->slug], absolute: false));
        $this->assertGuest();
    }

    private function createOrder(User $user, Tenant $tenant): Order
    {
        $order = new Order();
        $order->user_id = $user->id;
        $order->tenant_id = $tenant->id;
        $order->first_name = 'John';
        $order->last_name = 'Doe';
        $order->phone = '09123456789';
        $order->email = $user->email;
        $order->address = '123 Test Street';
        $order->city = 'Yangon';
        $order->total_amount = 100.00;
        $order->payment_status = Order::PAYMENT_STATUS_PENDING;
        $order->order_status = Order::ORDER_STATUS_PENDING;
        $order->save();

        return $order;
    }
}
