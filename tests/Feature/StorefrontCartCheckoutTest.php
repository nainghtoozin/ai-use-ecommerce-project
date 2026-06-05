<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StorefrontCartCheckoutTest extends TestCase
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
            'products' => function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('name');
                $table->text('description')->nullable();
                $table->decimal('price', 10, 2);
                $table->integer('stock')->default(0);
                $table->string('type')->default('single');
                $table->string('status')->default('active');
                $table->string('photo1')->nullable();
                $table->unsignedBigInteger('category_id')->nullable();
                $table->timestamps();
            },
            'product_variants' => function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->unsignedBigInteger('product_id');
                $table->string('sku')->nullable();
                $table->string('label')->nullable();
                $table->decimal('price', 10, 2)->nullable();
                $table->integer('stock')->default(0);
                $table->json('attributes')->nullable();
                $table->string('status')->default('active');
                $table->timestamps();
            },
            'payment_methods' => function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('name');
                $table->string('type')->default('banking');
                $table->string('account_name')->nullable();
                $table->string('account_number')->nullable();
                $table->string('bank_name')->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            },
            'promotions' => function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('name');
                $table->string('code')->nullable();
                $table->string('type')->nullable();
                $table->string('promotion_type')->default('fixed_amount');
                $table->decimal('discount_value', 10, 2)->default(0);
                $table->string('applies_to')->nullable();
                $table->integer('usage_limit')->nullable();
                $table->integer('usage_count')->default(0);
                $table->boolean('is_automatic')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->integer('priority')->default(0);
                $table->text('description')->nullable();
                $table->timestamps();
            },
            'orders' => function ($table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('first_name');
                $table->string('last_name');
                $table->string('phone', 20);
                $table->string('email')->nullable();
                $table->text('address');
                $table->unsignedBigInteger('city_id')->nullable();
                $table->unsignedBigInteger('township_id')->nullable();
                $table->string('postal_code', 20)->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('payment_method_id')->nullable();
                $table->string('payer_name')->nullable();
                $table->string('payment_screenshot')->nullable();
                $table->string('transaction_id')->nullable();
                $table->decimal('subtotal', 10, 2)->default(0);
                $table->decimal('delivery_fee', 10, 2)->default(0);
                $table->decimal('discount_amount', 10, 2)->default(0);
                $table->decimal('total_amount', 10, 2)->default(0);
                $table->unsignedBigInteger('promotion_id')->nullable();
                $table->string('promotion_code')->nullable();
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
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            },
            'townships' => function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->unsignedBigInteger('city_id');
                $table->string('name');
                $table->boolean('is_active')->default(true);
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
            'settings' => function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('key');
                $table->text('value')->nullable();
                $table->timestamps();
                $table->unique(['tenant_id', 'key']);
            },
            'subscriptions' => function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->unsignedBigInteger('plan_id')->nullable();
                $table->string('status')->default('active');
                $table->timestamp('trial_ends_at')->nullable();
                $table->timestamp('ends_at')->nullable();
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

    private function createProduct(Tenant $tenant, string $name = 'Test Product', float $price = 100.00, int $stock = 10): Product
    {
        $product = new Product();
        $product->tenant_id = $tenant->id;
        $product->name = $name;
        $product->price = $price;
        $product->stock = $stock;
        $product->type = 'single';
        $product->status = 'active';
        $product->save();

        return $product;
    }

    private function createPaymentMethod(Tenant $tenant, string $name = 'KBZ Pay', string $type = 'banking'): PaymentMethod
    {
        $pm = new PaymentMethod();
        $pm->tenant_id = $tenant->id;
        $pm->name = $name;
        $pm->type = $type;
        $pm->account_name = 'Test Account';
        $pm->account_number = '123456789';
        $pm->bank_name = 'KBZ Bank';
        $pm->is_active = true;
        $pm->save();

        return $pm;
    }

    private function createCity(Tenant $tenant, string $name = 'Yangon', float $deliveryFee = 2000): City
    {
        $city = new City();
        $city->tenant_id = $tenant->id;
        $city->name = $name;
        $city->delivery_fee = $deliveryFee;
        $city->save();

        return $city;
    }

    private function addItemToCart(Product $product, int $quantity = 1): void
    {
        $cartKey = 'p' . $product->id . '_v0';
        $cart = session()->get('cart', []);
        $cart[$cartKey] = [
            'id' => $product->id,
            'product_id' => $product->id,
            'variant_id' => null,
            'name' => $product->name,
            'price' => (float) $product->price,
            'photo1' => $product->photo1,
            'quantity' => $quantity,
        ];
        session()->put('cart', $cart);
    }

    private function setCurrentTenant(Tenant $tenant): void
    {
        app()->instance('current.tenant', $tenant);
    }

    // ─── Cart Page Tests ───────────────────────────────────────

    public function test_guest_can_view_cart_page(): void
    {
        $tenant = $this->createTenant('Test Store', 'test-store');
        $product = $this->createProduct($tenant);
        $this->addItemToCart($product);

        $response = $this->get("/store/{$tenant->slug}/cart");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Storefront/Cart')
            ->has('tenant')
            ->has('cartItems', 1)
        );
    }

    public function test_cart_only_shows_items_belonging_to_current_tenant(): void
    {
        $tenant1 = $this->createTenant('Store One', 'store-one');
        $tenant2 = $this->createTenant('Store Two', 'store-two');

        $product1 = $this->createProduct($tenant1, 'Product from Store 1');
        $product2 = $this->createProduct($tenant2, 'Product from Store 2');

        $this->addItemToCart($product1);
        $this->addItemToCart($product2);

        $response = $this->get("/store/{$tenant1->slug}/cart");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Storefront/Cart')
            ->has('cartItems', 1)
            ->where('cartItems.0.name', 'Product from Store 1')
        );
    }

    public function test_cart_shows_empty_state(): void
    {
        $tenant = $this->createTenant('Test Store', 'test-store');

        $response = $this->get("/store/{$tenant->slug}/cart");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Storefront/Cart')
            ->has('cartItems', 0)
        );
    }

    public function test_cart_shows_empty_when_no_items_belong_to_tenant(): void
    {
        $tenant1 = $this->createTenant('Store One', 'store-one');
        $tenant2 = $this->createTenant('Store Two', 'store-two');

        $product = $this->createProduct($tenant2, 'Other Store Product');
        $this->addItemToCart($product);

        $response = $this->get("/store/{$tenant1->slug}/cart");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Storefront/Cart')
            ->has('cartItems', 0)
        );
    }

    // ─── Checkout Page Tests ───────────────────────────────────

    public function test_guest_can_view_checkout_page(): void
    {
        $tenant = $this->createTenant('Test Store', 'test-store');
        $product = $this->createProduct($tenant);
        $this->createPaymentMethod($tenant);
        $this->createCity($tenant);
        $this->addItemToCart($product);

        $response = $this->get("/store/{$tenant->slug}/checkout");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Storefront/Checkout')
            ->has('tenant')
            ->has('cartItems', 1)
            ->has('paymentMethods')
            ->has('cities')
        );
    }

    public function test_checkout_redirects_to_cart_when_no_tenant_items(): void
    {
        $tenant = $this->createTenant('Test Store', 'test-store');

        $response = $this->get("/store/{$tenant->slug}/checkout");

        $response->assertRedirect("/store/{$tenant->slug}/cart");
    }

    public function test_checkout_redirects_to_cart_when_all_items_are_from_other_tenant(): void
    {
        $tenant1 = $this->createTenant('Store One', 'store-one');
        $tenant2 = $this->createTenant('Store Two', 'store-two');

        $product = $this->createProduct($tenant2, 'Other Store Product');
        $this->addItemToCart($product);

        $response = $this->get("/store/{$tenant1->slug}/checkout");

        $response->assertRedirect("/store/{$tenant1->slug}/cart");
    }

    // ─── Order Creation Tests ──────────────────────────────────

    public function test_guest_can_place_order(): void
    {
        $tenant = $this->createTenant('Test Store', 'test-store');
        $product = $this->createProduct($tenant, 'Test Product', 100.00);
        $pm = $this->createPaymentMethod($tenant);
        $city = $this->createCity($tenant, 'Yangon', 2000);
        $this->addItemToCart($product, 2);

        $response = $this->post("/store/{$tenant->slug}/checkout", [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '09123456789',
            'email' => 'john@example.com',
            'address' => '123 Main St',
            'city_id' => $city->id,
            'payment_method_id' => $pm->id,
        ]);

        $order = Order::first();
        $this->assertNotNull($order);

        $response->assertRedirect(route('storefront.customer.orders.show', [
            'store_slug' => $tenant->slug,
            'order' => $order->id,
        ]));
        $response->assertSessionHas('success');

        $this->assertEquals('John', $order->first_name);
        $this->assertEquals('Doe', $order->last_name);
        $this->assertEquals('09123456789', $order->phone);
        $this->assertEquals('123 Main St', $order->address);
        $this->assertEquals($city->id, $order->city_id);
        $this->assertEquals($pm->id, $order->payment_method_id);
        $this->assertEquals(200.00, (float) $order->subtotal);
        $this->assertEquals(2000, (float) $order->delivery_fee);
        $this->assertEquals(2200, (float) $order->total_amount);
        $this->assertEquals(Order::PAYMENT_STATUS_PENDING, $order->payment_status);
        $this->assertEquals(Order::ORDER_STATUS_PENDING, $order->order_status);
        $this->assertCount(1, $order->items);
        $this->assertEquals($product->id, $order->items->first()->product_id);
        $this->assertEquals(2, $order->items->first()->quantity);

        $this->assertEmpty(session()->get('cart'));
    }

    public function test_order_belongs_to_current_tenant(): void
    {
        $tenant = $this->createTenant('Test Store', 'test-store');
        $product = $this->createProduct($tenant);
        $pm = $this->createPaymentMethod($tenant);
        $this->addItemToCart($product);

        $response = $this->post("/store/{$tenant->slug}/checkout", [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '09123456789',
            'address' => '123 Main St',
            'payment_method_id' => $pm->id,
        ]);

        $response->assertSessionHasNoErrors();

        $order = Order::withoutTenantScope()->first();
        $this->assertNotNull($order);
        $this->assertEquals($tenant->id, $order->tenant_id);
    }

    public function test_guest_checkout_requires_valid_payment_method(): void
    {
        $tenant = $this->createTenant('Test Store', 'test-store');
        $product = $this->createProduct($tenant);
        $this->addItemToCart($product);

        $response = $this->from("/store/{$tenant->slug}/checkout")->post("/store/{$tenant->slug}/checkout", [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '09123456789',
            'address' => '123 Main St',
            'payment_method_id' => 999,
        ]);

        $response->assertSessionHasErrors('payment_method_id');
    }

    public function test_guest_checkout_requires_required_fields(): void
    {
        $tenant = $this->createTenant('Test Store', 'test-store');
        $product = $this->createProduct($tenant);
        $pm = $this->createPaymentMethod($tenant);
        $this->addItemToCart($product);

        $response = $this->from("/store/{$tenant->slug}/checkout")->post("/store/{$tenant->slug}/checkout", [
            'first_name' => '',
            'last_name' => '',
            'phone' => '',
            'address' => '',
            'payment_method_id' => $pm->id,
        ]);

        $response->assertSessionHasErrors(['first_name', 'last_name', 'phone', 'address']);
    }

    public function test_cart_is_cleared_after_order(): void
    {
        $tenant = $this->createTenant('Test Store', 'test-store');
        $product = $this->createProduct($tenant);
        $pm = $this->createPaymentMethod($tenant);
        $this->addItemToCart($product);

        $this->assertNotEmpty(session()->get('cart'));

        $response = $this->post("/store/{$tenant->slug}/checkout", [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '09123456789',
            'address' => '123 Main St',
            'payment_method_id' => $pm->id,
        ]);

        $response->assertSessionHasNoErrors();

        $order = Order::withoutTenantScope()->first();
        $this->assertNotNull($order);

        $this->assertEmpty(session()->get('cart'));
    }

    public function test_authenticated_user_can_place_order(): void
    {
        $tenant = $this->createTenant('Test Store', 'test-store');
        $user = $this->createCustomerUser($tenant);
        $product = $this->createProduct($tenant);
        $pm = $this->createPaymentMethod($tenant);
        $this->addItemToCart($product);

        $this->loginAsCustomer($tenant, $user);

        $response = $this->post("/store/{$tenant->slug}/checkout", [
            'first_name' => 'Auth',
            'last_name' => 'User',
            'phone' => '09123456789',
            'address' => '456 Oak St',
            'payment_method_id' => $pm->id,
        ]);

        $response->assertSessionHasNoErrors();

        $order = Order::withoutTenantScope()->first();
        $this->assertNotNull($order);
        $this->assertEquals($user->id, $order->user_id);
        $this->assertEquals('Auth', $order->first_name);

        $response->assertSessionHas('success');
    }

    public function test_non_existent_store_returns_404(): void
    {
        $response = $this->get('/store/non-existent/cart');

        $response->assertNotFound();
    }

    public function test_order_uses_tenant_scoped_payment_methods(): void
    {
        $tenant1 = $this->createTenant('Store One', 'store-one');
        $tenant2 = $this->createTenant('Store Two', 'store-two');

        $pm1 = $this->createPaymentMethod($tenant1, 'KBZ Pay');
        $pm2 = $this->createPaymentMethod($tenant2, 'Wave Pay');

        $product1 = $this->createProduct($tenant1);
        $this->addItemToCart($product1);

        $response = $this->get("/store/{$tenant1->slug}/checkout");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('paymentMethods', 1)
            ->where('paymentMethods.0.name', 'KBZ Pay')
        );
    }
}
