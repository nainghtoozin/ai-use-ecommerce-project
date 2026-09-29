<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\City;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\Township;
use App\Models\User;
use App\Models\WebsiteInfo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CodFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Tenant $otherTenant;
    private City $city;
    private Township $township;
    private User $user;
    private User $admin;
    private \App\Models\Account $account;
    private Product $product;
    private PaymentMethod $bankMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Fulfill Shop', 'slug' => 'fulfill-shop',
            'store_url' => '/store/fulfill-shop', 'status' => 'active',
        ]);
        $this->otherTenant = Tenant::create([
            'name' => 'Foreign Shop', 'slug' => 'fulfill-foreign',
            'store_url' => '/store/fulfill-foreign', 'status' => 'active',
        ]);
        Tenant::setCurrent($this->tenant);

        $this->city = City::create(['name' => 'Yangon', 'is_active' => true]);
        $this->township = Township::create([
            'city_id' => $this->city->id, 'name' => 'Bahan',
            'postal_code' => '11201', 'delivery_fee' => 1000, 'is_active' => true,
        ]);

        $category = Category::create(['name' => 'Fulfill Cat', 'slug' => 'fulfill-cat']);
        $this->product = Product::create([
            'name' => 'Fulfill Widget', 'type' => 'single', 'price' => 5000,
            'stock' => 50, 'status' => 'active', 'category_id' => $category->id,
        ]);
        StockMovement::create([
            'product_id' => $this->product->id, 'product_variant_id' => null,
            'type' => StockMovement::TYPE_OPENING_STOCK, 'quantity' => 50,
        ]);

        $this->bankMethod = PaymentMethod::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Bank Transfer',
            'type' => 'bank_transfer', 'account_name' => 'A', 'account_number' => '1',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Fulfill Buyer',
            'email' => 'fulfill-buyer@test.com', 'password' => bcrypt('password'),
            'status' => 'active', 'allow_cod' => true,
        ]);
        $this->admin = $this->makeAdmin($this->tenant);

        $this->account = \App\Models\Account::create([
            'name' => 'Fulfill Acct', 'email' => 'fulfill-acct@test.com',
            'password' => bcrypt('password'), 'status' => 'active',
        ]);
        $customerRole = \App\Models\Role::firstOrCreate([
            'name' => 'customer', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id,
        ]);
        \App\Models\TenantMembership::firstOrCreate(
            ['account_id' => $this->account->id, 'tenant_id' => $this->tenant->id],
            ['role_id' => $customerRole->id, 'status' => 'active', 'joined_at' => now()]
        );

        WebsiteInfo::updateOrCreate(
            ['tenant_id' => $this->tenant->id],
            ['site_name' => $this->tenant->name, 'cod_enabled' => true]
        );
        Setting::set('cod_availability_mode', 'all');
        Tenant::setCurrent($this->tenant);
    }

    private function makeAdmin(Tenant $tenant): User
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['orders.view', 'orders.update-status'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        $role = \App\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);

        $user = User::create([
            'tenant_id' => $tenant->id, 'name' => 'Fulfill Admin',
            'email' => 'fulfill-admin-' . $tenant->slug . '@test.com', 'password' => bcrypt('password'),
            'status' => 'active', 'email_verified_at' => now(),
        ]);
        $user->assignRole($role);
        $user->givePermissionTo(['orders.view', 'orders.update-status']);

        return $user;
    }

    private function placeCodOrder(): Order
    {
        $this->actingAs($this->user, 'accounts');
        app()->instance('current.tenant', $this->tenant);
        $this->post('/store/fulfill-shop/cart/add', ['product_id' => $this->product->id, 'quantity' => 1]);
        $this->post('/store/fulfill-shop/checkout', [
            'first_name' => 'Fulfill', 'last_name' => 'Buyer', 'phone' => '09911122233',
            'address' => 'No. 1, Fulfill Road',
            'city_id' => $this->city->id, 'township_id' => $this->township->id,
            'postal_code' => '11201',
            'payment_method_id' => 'cod',
        ]);

        return Order::where('tenant_id', $this->tenant->id)->latest()->firstOrFail();
    }

    private function placeBankOrder(): Order
    {
        $this->actingAs($this->user, 'accounts');
        app()->instance('current.tenant', $this->tenant);
        $this->post('/store/fulfill-shop/cart/add', ['product_id' => $this->product->id, 'quantity' => 1]);
        $this->post('/store/fulfill-shop/checkout', [
            'first_name' => 'Fulfill', 'last_name' => 'Buyer', 'phone' => '09911122233',
            'address' => 'No. 1, Fulfill Road',
            'city_id' => $this->city->id, 'township_id' => $this->township->id,
            'postal_code' => '11201',
            'payment_method_id' => $this->bankMethod->id,
        ]);

        return Order::where('tenant_id', $this->tenant->id)->latest()->firstOrFail();
    }

    private function placeCodOrderAsAccount(): Order
    {
        $this->actingAs($this->account, 'accounts');
        app()->instance('current.tenant', $this->tenant);
        $this->post('/store/fulfill-shop/cart/add', ['product_id' => $this->product->id, 'quantity' => 1]);
        $this->post('/store/fulfill-shop/checkout', [
            'first_name' => 'Fulfill', 'last_name' => 'Acct', 'phone' => '09911122233',
            'address' => 'No. 1, Fulfill Road',
            'city_id' => $this->city->id, 'township_id' => $this->township->id,
            'postal_code' => '11201',
            'payment_method_id' => 'cod',
        ]);

        return Order::where('tenant_id', $this->tenant->id)->latest()->firstOrFail();
    }

    private function placeBankOrderAsAccount(): Order
    {
        $this->actingAs($this->account, 'accounts');
        app()->instance('current.tenant', $this->tenant);
        $this->post('/store/fulfill-shop/cart/add', ['product_id' => $this->product->id, 'quantity' => 1]);
        $this->post('/store/fulfill-shop/checkout', [
            'first_name' => 'Fulfill', 'last_name' => 'Acct', 'phone' => '09911122233',
            'address' => 'No. 1, Fulfill Road',
            'city_id' => $this->city->id, 'township_id' => $this->township->id,
            'postal_code' => '11201',
            'payment_method_id' => $this->bankMethod->id,
        ]);

        return Order::where('tenant_id', $this->tenant->id)->latest()->firstOrFail();
    }

    private function inertiaOrderIds($response): array
    {
        $page = $response->viewData('page');
        $props = is_array($page) ? ($page['props'] ?? []) : (is_object($page) ? ($page['props'] ?? []) : []);

        return collect($props['orders']['data'] ?? [])->pluck('id')->all();
    }

    private function fulfill(Order $order): void
    {
        $this->actingAs($this->admin);
        foreach (['confirm', 'process', 'ship', 'deliver'] as $action) {
            $this->post("/admin/orders/{$order->id}/{$action}")->assertRedirect();
        }
    }

    /** @test */
    public function cod_order_created_pending_with_no_fee(): void
    {
        $order = $this->placeCodOrder();

        $this->assertNull($order->payment_method_id);
        $this->assertEquals(Order::PAYMENT_STATUS_PENDING, $order->payment_status);
        $this->assertEquals(Order::ORDER_STATUS_PENDING, $order->order_status);
        $this->assertNull($order->cod_fee);
        $this->assertEquals(6000, (float) $order->total_amount);
    }

    /** @test */
    public function customer_detail_flags_cod_order(): void
    {
        $order = $this->placeCodOrder();

        $this->actingAs($this->user)->get("/client/orders/{$order->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('isCodOrder', true));
    }

    /** @test */
    public function admin_detail_flags_cod_order(): void
    {
        $order = $this->placeCodOrder();

        $this->actingAs($this->admin)->get("/admin/orders/{$order->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('isCodOrder', true));
    }

    /** @test */
    public function unpaid_cod_detail_carries_display_data(): void
    {
        $order = $this->placeCodOrder();

        $this->actingAs($this->user)->get("/client/orders/{$order->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('isCodOrder', true)
                ->where('order.payment_status', Order::PAYMENT_STATUS_PENDING)
                ->where('order.total_amount', '6000.00')
                ->where('order.payment_method_id', null));
    }

    /** @test */
    public function paid_cod_detail_carries_display_data(): void
    {
        $order = $this->placeCodOrder();
        $this->actingAs($this->admin)
            ->post("/admin/orders/{$order->id}/collect-cod-payment")
            ->assertRedirect();

        $this->actingAs($this->user)->get("/client/orders/{$order->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('isCodOrder', true)
                ->where('order.payment_status', Order::PAYMENT_STATUS_PAID)
                ->where('order.total_amount', '6000.00'));
    }

    /** @test */
    public function storefront_detail_flags_unpaid_cod(): void
    {
        $order = $this->placeCodOrderAsAccount();

        $this->actingAs($this->account)->get("/store/fulfill-shop/customer/orders/{$order->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('isCodOrder', true)
                ->where('order.payment_status', Order::PAYMENT_STATUS_PENDING)
                ->where('order.total_amount', '6000.00')
                ->where('order.payment_method_id', null));
    }

    /** @test */
    public function storefront_detail_flags_paid_cod(): void
    {
        $order = $this->placeCodOrderAsAccount();
        $this->actingAs($this->admin)
            ->post("/admin/orders/{$order->id}/collect-cod-payment")
            ->assertRedirect();

        $this->actingAs($this->account)->get("/store/fulfill-shop/customer/orders/{$order->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('isCodOrder', true)
                ->where('order.payment_status', Order::PAYMENT_STATUS_PAID));
    }

    /** @test */
    public function storefront_detail_non_cod_unchanged(): void
    {
        $order = $this->placeBankOrderAsAccount();

        $this->actingAs($this->account)->get("/store/fulfill-shop/customer/orders/{$order->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('isCodOrder', false));
    }

    /** @test */
    public function storefront_proof_upload_rejected_for_cod(): void
    {
        $order = $this->placeCodOrderAsAccount();

        $this->actingAs($this->account)
            ->post("/store/fulfill-shop/customer/orders/{$order->id}/upload-payment", [
                'transaction_id' => 'X',
                'payment_proof' => \Illuminate\Http\UploadedFile::fake()->image('proof.jpg'),
            ])
            ->assertRedirect();

        $this->assertEquals(Order::PAYMENT_STATUS_PENDING, $order->fresh()->payment_status);
        $this->assertNull($order->fresh()->payment_proof);
    }

    /** @test */
    public function customer_lists_expose_cod_display_data(): void
    {
        $order = $this->placeCodOrderAsAccount();

        $this->actingAs($this->account)
            ->get('/store/fulfill-shop/customer/orders')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('orders.data.0.payment_status', Order::PAYMENT_STATUS_PENDING)
                ->where('orders.data.0.payment_method_id', null)
                ->where('orders.data.0.total_amount', '6000.00'));

        $this->actingAs($this->user)->get('/client/orders')->assertOk()
            ->assertInertia(fn ($page) => $page->has('orders.data', 0));

        $this->actingAs($this->user)
            ->withSession(['cart' => ['k1' => ['product_id' => $this->product->id, 'quantity' => 1]]])
            ->post('/checkout', [
                'first_name' => 'Fulfill', 'last_name' => 'Buyer', 'phone' => '09911122233',
                'address' => 'No. 1, Fulfill Road',
                'city_id' => $this->city->id, 'township_id' => $this->township->id,
                'postal_code' => '11201',
                'payment_method_id' => $this->bankMethod->id,
            ]);

        $this->actingAs($this->user)->get('/client/orders')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('orders.data.0.payment_status', Order::PAYMENT_STATUS_PENDING)
                ->where('orders.data.0.payment_method_id', $this->bankMethod->id));
    }

    /** @test */
    public function admin_list_exposes_cod_display_data(): void
    {
        $order = $this->placeCodOrder();

        $this->actingAs($this->admin)->get('/admin/orders')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('orders.data.0.payment_status', Order::PAYMENT_STATUS_PENDING)
                ->where('orders.data.0.payment_method_id', null)
                ->where('orders.data.0.total_amount', '6000.00'));
    }

    /** @test */
    public function due_on_delivery_filter_returns_only_unpaid_cod(): void
    {
        $cod = $this->placeCodOrderAsAccount();
        $this->placeBankOrderAsAccount();

        $response = $this->actingAs($this->account)
            ->get('/store/fulfill-shop/customer/orders?payment_status=due_on_delivery');
        $response->assertOk();
        $ids = $this->inertiaOrderIds($response);
        $this->assertContains($cod->id, $ids);
        $this->assertCount(1, $ids);

        $adminResponse = $this->actingAs($this->admin)
            ->get('/admin/orders?payment_status=due_on_delivery');
        $adminResponse->assertOk();
        $adminIds = $this->inertiaOrderIds($adminResponse);
        $this->assertContains($cod->id, $adminIds);
    }

    /** @test */
    public function paid_filter_includes_collected_cod_and_pending_excludes_it(): void
    {
        $cod = $this->placeCodOrderAsAccount();
        $this->actingAs($this->admin)->post("/admin/orders/{$cod->id}/collect-cod-payment");

        $paid = $this->actingAs($this->account)
            ->get('/store/fulfill-shop/customer/orders?payment_status=paid');
        $paidIds = $this->inertiaOrderIds($paid);
        $this->assertContains($cod->id, $paidIds);

        $pending = $this->actingAs($this->account)
            ->get('/store/fulfill-shop/customer/orders?payment_status=pending');
        $pendingIds = $this->inertiaOrderIds($pending);
        $this->assertNotContains($cod->id, $pendingIds);
    }

    /** @test */
    public function canonical_order_number_is_shared_everywhere(): void
    {
        $order = $this->placeCodOrderAsAccount();
        $order->refresh();

        $this->assertMatchesRegularExpression('/^ORD-\d{8}-\d{5}$/', $order->invoice_number);
        $this->assertEquals($order->id, $order->getAttributes()['id']);

        $invoice = $this->actingAs($this->account)
            ->get("/store/fulfill-shop/customer/orders/{$order->invoice_number}/invoice");
        $invoice->assertOk();
        $invoice->assertSee($order->invoice_number, false);
        $invoice->assertSee('Cash on Delivery', false);
        $invoice->assertSee('Due on Delivery', false);
    }

    /** @test */
    public function historical_null_invoice_number_falls_back_to_generated(): void
    {
        $order = $this->placeCodOrderAsAccount();
        Order::withoutTenantScope()->where('id', $order->id)->update(['invoice_number' => null]);

        $fresh = $order->fresh();
        $expected = 'ORD-' . $fresh->created_at->format('Ymd') . '-' . str_pad($fresh->id, 5, '0', STR_PAD_LEFT);
        $this->assertEquals($expected, $fresh->invoice_number);
    }

    /** @test */
    public function non_cod_detail_is_not_flagged(): void
    {
        $order = $this->placeBankOrder();

        $this->actingAs($this->admin)->get("/admin/orders/{$order->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('isCodOrder', false));
    }

    /** @test */
    public function cod_moves_through_fulfillment_while_pending(): void
    {
        $order = $this->placeCodOrder();
        $this->fulfill($order);

        $fresh = $order->fresh();
        $this->assertEquals(Order::ORDER_STATUS_DELIVERED, $fresh->order_status);
        $this->assertEquals(Order::PAYMENT_STATUS_PENDING, $fresh->payment_status);
    }

    /** @test */
    public function collect_marks_delivered_cod_paid(): void
    {
        $order = $this->placeCodOrder();
        $this->fulfill($order);

        $this->actingAs($this->admin)
            ->post("/admin/orders/{$order->id}/collect-cod-payment")
            ->assertRedirect();

        $fresh = $order->fresh();
        $this->assertEquals(Order::PAYMENT_STATUS_PAID, $fresh->payment_status);
        $this->assertEquals(6000, (float) $fresh->paid_amount);
        $this->assertNotNull($fresh->payment_verified_at);
        $this->assertEquals(Order::ORDER_STATUS_DELIVERED, $fresh->order_status);
        $this->assertEquals(6000, (float) $fresh->total_amount);
    }

    /** @test */
    public function collect_rejected_for_non_cod_order(): void
    {
        $order = $this->placeBankOrder();

        $this->actingAs($this->admin)
            ->post("/admin/orders/{$order->id}/collect-cod-payment")
            ->assertRedirect();

        $this->assertEquals(Order::PAYMENT_STATUS_PENDING, $order->fresh()->payment_status);
    }

    /** @test */
    public function collect_rejected_when_already_paid(): void
    {
        $order = $this->placeCodOrder();
        $this->actingAs($this->admin)
            ->post("/admin/orders/{$order->id}/collect-cod-payment")
            ->assertRedirect();
        $this->assertEquals(Order::PAYMENT_STATUS_PAID, $order->fresh()->payment_status);

        $this->actingAs($this->admin)
            ->post("/admin/orders/{$order->id}/collect-cod-payment")
            ->assertRedirect();

        $this->assertEquals(Order::PAYMENT_STATUS_PAID, $order->fresh()->payment_status);
    }

    /** @test */
    public function verify_payment_rejected_for_cod(): void
    {
        $order = $this->placeCodOrder();

        $this->actingAs($this->admin)
            ->post("/admin/orders/{$order->id}/verify-payment")
            ->assertRedirect();

        $this->assertEquals(Order::PAYMENT_STATUS_PENDING, $order->fresh()->payment_status);
    }

    /** @test */
    public function verify_payment_unchanged_for_bank_order(): void
    {
        $order = $this->placeBankOrder();

        $this->actingAs($this->admin)
            ->post("/admin/orders/{$order->id}/verify-payment")
            ->assertRedirect();

        $this->assertEquals(Order::PAYMENT_STATUS_PAID, $order->fresh()->payment_status);
    }

    /** @test */
    public function collect_is_tenant_isolated(): void
    {
        $order = $this->placeCodOrder();
        Tenant::setCurrent($this->otherTenant);
        $foreignAdmin = $this->makeAdmin($this->otherTenant);

        $this->actingAs($foreignAdmin)
            ->post("/admin/orders/{$order->id}/collect-cod-payment")
            ->assertRedirect();

        $fresh = $order->fresh();
        $this->assertEquals(Order::PAYMENT_STATUS_PENDING, $fresh->payment_status);
        $this->assertNull($fresh->paid_amount);
        $this->assertNull($fresh->payment_verified_at);
    }

    /** @test */
    public function legacy_row_cod_order_is_collectable(): void
    {
        $legacy = PaymentMethod::withoutTenantScope()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Cash On Delivery',
            'type' => 'cod', 'is_active' => true,
        ]);

        $this->actingAs($this->user, 'accounts');
        app()->instance('current.tenant', $this->tenant);
        $this->post('/store/fulfill-shop/cart/add', ['product_id' => $this->product->id, 'quantity' => 1]);
        $this->post('/store/fulfill-shop/checkout', [
            'first_name' => 'Fulfill', 'last_name' => 'Buyer', 'phone' => '09911122233',
            'address' => 'No. 1, Fulfill Road',
            'city_id' => $this->city->id, 'township_id' => $this->township->id,
            'postal_code' => '11201',
            'payment_method_id' => $legacy->id,
        ]);

        $order = Order::where('tenant_id', $this->tenant->id)->latest()->firstOrFail();

        $this->actingAs($this->admin)
            ->post("/admin/orders/{$order->id}/collect-cod-payment")
            ->assertRedirect();

        $this->assertEquals(Order::PAYMENT_STATUS_PAID, $order->fresh()->payment_status);
    }

    /** @test */
    public function cod_proof_upload_rejected(): void
    {
        $order = $this->placeCodOrder();

        $this->actingAs($this->user)
            ->post("/orders/{$order->id}/upload-payment", [
                'transaction_id' => 'X',
                'payment_proof' => \Illuminate\Http\UploadedFile::fake()->image('proof.jpg'),
            ])
            ->assertRedirect();

        $this->assertEquals(Order::PAYMENT_STATUS_PENDING, $order->fresh()->payment_status);
        $this->assertNull($order->fresh()->payment_proof);
    }

    private function inertiaOrderNumbers($response): array
    {
        $page = $response->viewData('page');
        $props = is_array($page) ? ($page['props'] ?? []) : (is_object($page) ? ($page['props'] ?? []) : []);

        return collect($props['orders']['data'] ?? [])->pluck('invoice_number')->filter()->all();
    }

    private function paymentFilterOptions(string $file, string $marker): array
    {
        $source = file_get_contents($file);
        $start = strpos($source, $marker);
        $this->assertNotFalse($start, "marker missing in {$file}");
        $block = substr($source, $start, strpos($source, '</select>', $start) - $start);
        preg_match_all('/<option value="([^"]*)"/', $block, $matches);

        return $matches[1];
    }

    /** @test */
    public function customer_and_admin_due_on_delivery_filters_match(): void
    {
        $cod = $this->placeCodOrderAsAccount();
        $bank = $this->placeBankOrderAsAccount();

        $customerIds = $this->inertiaOrderIds(
            $this->actingAs($this->account)->get('/store/fulfill-shop/customer/orders?payment_status=due_on_delivery')
        );
        $adminIds = $this->inertiaOrderIds(
            $this->actingAs($this->admin)->get('/admin/orders?payment_status=due_on_delivery')
        );

        $this->assertEquals([$cod->id], $customerIds);
        $this->assertEquals($customerIds, array_values(array_intersect($adminIds, $customerIds)));
        $this->assertNotContains($bank->id, $adminIds);
    }

    /** @test */
    public function pending_filter_keeps_non_cod_pending_orders(): void
    {
        $cod = $this->placeCodOrderAsAccount();
        $bank = $this->placeBankOrderAsAccount();

        $customerPending = $this->inertiaOrderIds(
            $this->actingAs($this->account)->get('/store/fulfill-shop/customer/orders?payment_status=pending')
        );
        $adminPending = $this->inertiaOrderIds(
            $this->actingAs($this->admin)->get('/admin/orders?payment_status=pending')
        );

        $this->assertContains($cod->id, $customerPending);
        $this->assertContains($bank->id, $customerPending);
        $this->assertContains($cod->id, $adminPending);
        $this->assertContains($bank->id, $adminPending);
    }

    /** @test */
    public function admin_list_search_matches_invoice_number(): void
    {
        $order = $this->placeCodOrder();
        $order->refresh();

        $ids = $this->inertiaOrderIds(
            $this->actingAs($this->admin)->get('/admin/orders?search=' . urlencode($order->invoice_number))
        );

        $this->assertEquals([$order->id], $ids);
    }

    /** @test */
    public function customer_and_admin_payment_filter_options_are_identical(): void
    {
        $adminOptions = $this->paymentFilterOptions(
            resource_path('js/Pages/Admin/Orders/Index.jsx'),
            'aria-label="Payment status filter"'
        );
        $customerOptions = $this->paymentFilterOptions(
            resource_path('js/Pages/Storefront/Orders.jsx'),
            'Payment Status</label>'
        );

        $this->assertEquals($adminOptions, $customerOptions);
        $this->assertContains('due_on_delivery', $customerOptions);
        $this->assertNotContains('unpaid', $customerOptions);
        $this->assertNotContains('verified', $customerOptions);
        $this->assertNotContains('rejected', $customerOptions);
    }

    /** @test */
    public function customer_orders_filters_start_collapsed_with_search_and_chips(): void
    {
        $source = file_get_contents(resource_path('js/Pages/Storefront/Orders.jsx'));

        $this->assertStringContainsString('const [filtersOpen, setFiltersOpen] = useState(false);', $source);
        $this->assertStringContainsString('placeholder="Search orders..."', $source);
        $this->assertStringContainsString('filterChips', $source);
        $this->assertStringContainsString('Clear', $source);
        $this->assertStringContainsString('Apply Filters', $source);
        $this->assertStringContainsString('View Order', $source);
        $this->assertStringContainsString('Invoice', $source);
    }

    /** @test */
    public function cod_card_labels_come_from_cod_display_helpers(): void
    {
        $source = file_get_contents(resource_path('js/Pages/Storefront/Orders.jsx'));
        $helpers = file_get_contents(resource_path('js/Utils/codDisplay.js'));

        $this->assertStringContainsString('codPaymentStatusLabel(order)', $source);
        $this->assertStringContainsString('codPaymentMethodLabel(order)', $source);
        $this->assertStringContainsString("'Due on Delivery'", $helpers);
        $this->assertStringContainsString("'Cash on Delivery'", $helpers);
    }

    /** @test */
    public function stored_invoice_number_is_never_rewritten(): void
    {
        $order = $this->placeCodOrder();
        Order::withoutTenantScope()->where('id', $order->id)->update(['invoice_number' => 'LEGACY-ORD-001']);

        $fresh = $order->fresh();
        $this->assertEquals('LEGACY-ORD-001', $fresh->invoice_number);

        $this->actingAs($this->user)
            ->get("/client/orders/{$order->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('order.invoice_number', 'LEGACY-ORD-001'));

        $this->actingAs($this->admin)
            ->get("/admin/orders/{$order->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('order.invoice_number', 'LEGACY-ORD-001'));
    }

    /** @test */
    public function order_export_honours_due_on_delivery_filter(): void
    {
        $cod = $this->placeCodOrder();
        $cod->refresh();
        $bank = $this->placeBankOrder();
        $bank->refresh();

        $csv = $this->actingAs($this->admin)
            ->get('/admin/orders/export?format=csv&payment_status=due_on_delivery')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString($cod->invoice_number, $csv);
        $this->assertStringContainsString('Cash on Delivery', $csv);
        $this->assertStringContainsString('Due on Delivery', $csv);
        $this->assertStringNotContainsString($bank->invoice_number, $csv);
    }
}
