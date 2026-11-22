<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OrderReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AfterSalesSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const PERMISSIONS = [
        'returns.view',
        'returns.view-own',
        'returns.create',
        'returns.approve',
        'returns.reject',
        'returns.receive',
        'returns.inspect',
        'returns.settings',
        'cancellations.view',
        'cancellations.manage',
        'refunds.view',
        'refunds.manage',
        'refunds.process',
    ];

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $adminA;
    private User $adminB;
    private User $adminNoPerm;
    private User $customerA;
    private User $otherCustomerA;
    private OrderReturn $returnA;

    protected function setUp(): void
    {
        parent::setUp();

        config(['identity.use_accounts' => false]);

        Queue::fake();
        Notification::fake();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->tenantA = Tenant::create([
            'name' => 'Security Store A',
            'slug' => 'security-store-a',
            'store_url' => '/store/security-store-a',
            'status' => 'active',
        ]);
        $this->tenantB = Tenant::create([
            'name' => 'Security Store B',
            'slug' => 'security-store-b',
            'store_url' => '/store/security-store-b',
            'status' => 'active',
        ]);

        $this->grantStorefrontEntitlement($this->tenantA);
        $this->grantStorefrontEntitlement($this->tenantB);

        $this->adminA = $this->makeUser($this->tenantA, 'admin-a@test.com');
        $this->adminA->assignRole('admin');
        $this->adminA->givePermissionTo(self::PERMISSIONS);

        $this->adminB = $this->makeUser($this->tenantB, 'admin-b@test.com');
        $this->adminB->assignRole('admin');
        $this->adminB->givePermissionTo(self::PERMISSIONS);

        $this->adminNoPerm = $this->makeUser($this->tenantA, 'admin-noperm@test.com');
        $this->adminNoPerm->assignRole('admin');

        $this->customerA = $this->makeUser($this->tenantA, 'customer-a@test.com');
        $this->otherCustomerA = $this->makeUser($this->tenantA, 'other-customer-a@test.com');

        $this->returnA = $this->seedReturnForTenantA();
    }

    private function makeUser(Tenant $tenant, string $email): User
    {
        return User::create([
            'tenant_id' => $tenant->id,
            'name' => $email,
            'email' => $email,
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
    }

    private function actAs(User $user, Tenant $tenant): void
    {
        $this->actingAs($user);
        Tenant::setCurrent($tenant);
    }

    private function seedReturnForTenantA(): OrderReturn
    {
        Tenant::setCurrent($this->tenantA);

        $category = Category::create(['name' => 'Security Cat', 'slug' => 'security-cat']);
        $product = Product::create([
            'name' => 'Security Widget',
            'type' => 'single',
            'price' => 1000,
            'stock' => 20,
            'status' => 'active',
            'category_id' => $category->id,
        ]);

        $order = Order::create([
            'user_id' => $this->customerA->id,
            'user_type' => User::class,
            'first_name' => 'Security',
            'last_name' => 'Customer',
            'phone' => '09911122233',
            'address' => 'No. 3, Security Road',
            'subtotal' => 1000,
            'total_amount' => 1000,
            'delivery_fee' => 0,
            'paid_amount' => 1000,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'order_status' => Order::ORDER_STATUS_DELIVERED,
            'delivered_at' => now()->subDay(),
            'stock_reduced' => true,
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 1000,
        ]);

        return app(OrderReturnService::class)->request($order, $this->customerA, [
            'items' => [['order_item_id' => $item->id, 'quantity' => 1]],
            'resolution' => OrderReturn::RESOLUTION_REFUND,
            'reason_code' => 'damaged',
        ]);
    }

    public function test_admin_with_permission_can_view_returns_list(): void
    {
        $this->actAs($this->adminA, $this->tenantA);

        $this->get('/admin/returns')->assertStatus(200);
    }

    public function test_admin_without_permission_is_forbidden(): void
    {
        $this->actAs($this->adminNoPerm, $this->tenantA);

        $this->get('/admin/returns')->assertStatus(403);
    }

    public function test_cross_tenant_return_is_not_found(): void
    {
        $this->actAs($this->adminB, $this->tenantB);

        $this->get('/admin/returns/' . $this->returnA->id)->assertStatus(404);
    }

    public function test_admin_without_approve_permission_cannot_approve_return(): void
    {
        $this->adminNoPerm->givePermissionTo('returns.view');

        $this->actAs($this->adminNoPerm, $this->tenantA);

        $this->post('/admin/returns/' . $this->returnA->id . '/approve')->assertStatus(403);
    }

    public function test_admin_with_approve_permission_can_approve_return(): void
    {
        $this->actAs($this->adminA, $this->tenantA);

        $this->post('/admin/returns/' . $this->returnA->id . '/approve')
            ->assertRedirect();

        $this->assertSame(OrderReturn::STATUS_AWAITING_SHIPMENT, $this->returnA->fresh()->status);
    }

    public function test_other_customer_cannot_view_someone_elses_return(): void
    {
        $this->actAs($this->otherCustomerA, $this->tenantA);

        $this->get('/store/security-store-a/customer/returns/' . $this->returnA->id)
            ->assertStatus(404);
    }

    public function test_owner_can_view_own_return(): void
    {
        $this->actAs($this->customerA, $this->tenantA);

        $this->get('/store/security-store-a/customer/returns/' . $this->returnA->id)
            ->assertStatus(200);
    }

    public function test_customer_role_without_view_own_permission_is_forbidden_from_returns(): void
    {
        $this->actAs($this->customerA, $this->tenantA);
        $this->assignCustomerRole($this->customerA);

        $this->get('/store/security-store-a/customer/returns')->assertStatus(403);
        $this->get('/store/security-store-a/customer/returns/' . $this->returnA->id)->assertStatus(403);
    }

    public function test_customer_role_with_view_own_permission_can_list_returns(): void
    {
        $this->actAs($this->customerA, $this->tenantA);
        $this->assignCustomerRole($this->customerA, ['returns.view-own']);

        $this->get('/store/security-store-a/customer/returns')->assertStatus(200);
        $this->get('/store/security-store-a/customer/returns/' . $this->returnA->id)->assertStatus(200);
    }

    public function test_customer_cannot_access_another_tenants_return(): void
    {
        $returnB = $this->seedReturnForTenantB();

        $this->actAs($this->customerA, $this->tenantA);

        $this->get('/store/security-store-a/customer/returns/' . $returnB->id)
            ->assertStatus(404);
    }

    public function test_admin_without_receive_permission_cannot_mark_return_in_transit(): void
    {
        $this->actAs($this->adminA, $this->tenantA);
        $this->post('/admin/returns/' . $this->returnA->id . '/approve')->assertRedirect();

        $this->adminNoPerm->givePermissionTo('returns.view');

        $this->actAs($this->adminNoPerm, $this->tenantA);

        $this->post('/admin/returns/' . $this->returnA->id . '/in-transit')->assertStatus(403);
        $this->assertSame(OrderReturn::STATUS_AWAITING_SHIPMENT, $this->returnA->fresh()->status);
    }

    public function test_admin_with_receive_permission_can_mark_return_in_transit(): void
    {
        $this->actAs($this->adminA, $this->tenantA);
        $this->post('/admin/returns/' . $this->returnA->id . '/approve')->assertRedirect();

        $this->post('/admin/returns/' . $this->returnA->id . '/in-transit', [
            'carrier_name' => 'DHL',
            'tracking_number' => 'TRK-123',
            'tracking_url' => 'https://dhl.test/TRK-123',
        ])->assertRedirect();

        $fresh = $this->returnA->fresh();
        $this->assertSame(OrderReturn::STATUS_IN_TRANSIT, $fresh->status);
        $this->assertSame('DHL', $fresh->carrier_name);
        $this->assertSame('TRK-123', $fresh->tracking_number);
        $this->assertSame('https://dhl.test/TRK-123', $fresh->safe_tracking_url);
        $this->assertTrue($fresh->histories()->where('action', 'return_in_transit')->exists());
    }

    public function test_admin_can_update_tracking_without_changing_status(): void
    {
        $this->actAs($this->adminA, $this->tenantA);
        $this->post('/admin/returns/' . $this->returnA->id . '/approve')->assertRedirect();
        $this->post('/admin/returns/' . $this->returnA->id . '/in-transit', ['tracking_number' => 'TRK-1'])->assertRedirect();

        $this->post('/admin/returns/' . $this->returnA->id . '/tracking', [
            'carrier_name' => 'FedEx',
            'tracking_number' => 'FX-2',
            'tracking_note' => 'Updated details',
        ])->assertRedirect();

        $fresh = $this->returnA->fresh();
        $this->assertSame(OrderReturn::STATUS_IN_TRANSIT, $fresh->status);
        $this->assertSame('FedEx', $fresh->carrier_name);
        $this->assertSame('FX-2', $fresh->tracking_number);
        $this->assertTrue($fresh->histories()->where('action', 'return_tracking_updated')->exists());
    }

    public function test_admin_cannot_store_unsafe_tracking_url(): void
    {
        $this->actAs($this->adminA, $this->tenantA);
        $this->post('/admin/returns/' . $this->returnA->id . '/approve')->assertRedirect();

        $this->from('/admin/returns/' . $this->returnA->id)
            ->post('/admin/returns/' . $this->returnA->id . '/in-transit', ['tracking_url' => 'javascript:alert(1)'])
            ->assertSessionHasErrors('tracking_url');

        $this->assertSame(OrderReturn::STATUS_AWAITING_SHIPMENT, $this->returnA->fresh()->status);
    }

    public function test_cross_tenant_tracking_update_is_not_found(): void
    {
        $this->actAs($this->adminA, $this->tenantA);
        $this->post('/admin/returns/' . $this->returnA->id . '/approve')->assertRedirect();
        $this->post('/admin/returns/' . $this->returnA->id . '/in-transit', ['tracking_number' => 'TRK-1'])->assertRedirect();

        $this->actAs($this->adminB, $this->tenantB);
        $this->post('/admin/returns/' . $this->returnA->id . '/tracking', ['tracking_number' => 'HACK'])
            ->assertStatus(404);

        $this->assertSame('TRK-1', $this->returnA->fresh()->tracking_number);
    }

    public function test_admin_without_receive_permission_cannot_update_tracking(): void
    {
        $this->actAs($this->adminA, $this->tenantA);
        $this->post('/admin/returns/' . $this->returnA->id . '/approve')->assertRedirect();
        $this->post('/admin/returns/' . $this->returnA->id . '/in-transit', ['tracking_number' => 'TRK-1'])->assertRedirect();

        $this->adminNoPerm->givePermissionTo('returns.view');
        $this->actAs($this->adminNoPerm, $this->tenantA);

        $this->post('/admin/returns/' . $this->returnA->id . '/tracking', ['tracking_number' => 'X'])
            ->assertStatus(403);
    }

    public function test_customer_sees_only_own_return_tracking(): void
    {
        $this->actAs($this->adminA, $this->tenantA);
        $this->post('/admin/returns/' . $this->returnA->id . '/approve')->assertRedirect();
        $this->post('/admin/returns/' . $this->returnA->id . '/in-transit', [
            'carrier_name' => 'DHL',
            'tracking_number' => 'TRK-VISIBLE',
            'tracking_url' => 'https://dhl.test/TRK-VISIBLE',
            'tracking_note' => 'On the way',
        ])->assertRedirect();

        $this->actAs($this->customerA, $this->tenantA);

        $this->get('/store/security-store-a/customer/returns/' . $this->returnA->id)
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Storefront/ReturnShow')
                ->where('returnRequest.tracking.carrier_name', 'DHL')
                ->where('returnRequest.tracking.tracking_number', 'TRK-VISIBLE')
                ->where('returnRequest.tracking.tracking_url', 'https://dhl.test/TRK-VISIBLE')
            );

        $this->actAs($this->otherCustomerA, $this->tenantA);

        $this->get('/store/security-store-a/customer/returns/' . $this->returnA->id)
            ->assertStatus(404);
    }

    public function test_cross_tenant_return_in_transit_is_not_found(): void
    {
        $this->actAs($this->adminA, $this->tenantA);
        $this->post('/admin/returns/' . $this->returnA->id . '/approve')->assertRedirect();

        $this->actAs($this->adminB, $this->tenantB);

        $this->post('/admin/returns/' . $this->returnA->id . '/in-transit')->assertStatus(404);
    }

    private function assignCustomerRole(User $user, array $permissions = []): \App\Models\Role
    {
        $role = \App\Models\Role::withoutTenantScope()->firstOrCreate([
            'name' => 'customer',
            'guard_name' => 'web',
            'tenant_id' => $this->tenantA->id,
        ]);

        if ($permissions) {
            $role->givePermissionTo($permissions);
        }

        $user->assignRole($role);
        $user->refresh();

        return $role;
    }

    private function seedReturnForTenantB(): OrderReturn
    {
        Tenant::setCurrent($this->tenantB);

        $customerB = $this->makeUser($this->tenantB, 'customer-b@test.com');

        $category = Category::create(['name' => 'Security Cat B', 'slug' => 'security-cat-b']);
        $product = Product::create([
            'name' => 'Security Widget B',
            'type' => 'single',
            'price' => 500,
            'stock' => 10,
            'status' => 'active',
            'category_id' => $category->id,
        ]);

        $order = Order::create([
            'user_id' => $customerB->id,
            'user_type' => User::class,
            'first_name' => 'Other',
            'last_name' => 'Customer',
            'phone' => '09944455566',
            'address' => 'No. 9, Security Road',
            'subtotal' => 500,
            'total_amount' => 500,
            'delivery_fee' => 0,
            'paid_amount' => 500,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'order_status' => Order::ORDER_STATUS_DELIVERED,
            'delivered_at' => now()->subDay(),
            'stock_reduced' => true,
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 500,
        ]);

        $return = app(OrderReturnService::class)->request($order, $customerB, [
            'items' => [['order_item_id' => $item->id, 'quantity' => 1]],
            'resolution' => OrderReturn::RESOLUTION_REFUND,
            'reason_code' => 'damaged',
        ]);

        Tenant::setCurrent($this->tenantA);

        return $return;
    }
}
