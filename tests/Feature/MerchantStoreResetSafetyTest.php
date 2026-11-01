<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebsiteInfo;
use Spatie\Permission\Models\Permission;
use Tests\Support\MerchantRestoreDrill\DrillFixture;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class MerchantStoreResetSafetyTest extends TestCase
{
    use DatabaseTransactions;

    private DrillFixture $fixture;
    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $ownerA;
    private User $ownerB;
    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        config(['identity.use_accounts' => false]);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['billing.view', 'store.reset'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions(['billing.view', 'store.reset']);

        $this->plan = Plan::create([
            'name' => 'Reset Safety Plan ' . uniqid(),
            'slug' => 'reset-safety-plan-' . uniqid(),
            'monthly_price' => 0,
            'yearly_price' => 0,
            'product_limit' => 50,
            'status' => 'active',
        ]);

        $this->fixture = new DrillFixture();
        $this->fixture->create();

        $this->tenantA = Tenant::findOrFail($this->fixture->tenantA);
        $this->tenantB = Tenant::findOrFail($this->fixture->tenantB);

        $this->tenantA->subscription_plan_id = $this->plan->id;
        $this->tenantA->save();

        Subscription::forceCreate([
            'tenant_id' => $this->tenantA->id,
            'plan_id' => $this->plan->id,
            'billing_interval' => 'monthly',
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
        ]);

        $this->ownerA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Reset Owner A',
            'email' => 'reset-owner-a-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
            'status' => 'active',
            'is_owner' => true,
        ]);
        $this->ownerA->assignRole($adminRole);

        $this->ownerB = User::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Reset Owner B',
            'email' => 'reset-owner-b-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
            'status' => 'active',
            'is_owner' => true,
        ]);
        $this->ownerB->assignRole($adminRole);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        $this->fixture->purge();
        $this->fixture->cleanupAsset();
        parent::tearDown();
    }

    private function actAsOwnerA(): void
    {
        $this->actingAs($this->ownerA);
        Tenant::setCurrent($this->tenantA);
    }

    public function test_owner_can_reset_own_store(): void
    {
        $this->actAsOwnerA();
        $siteNameBefore = WebsiteInfo::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->value('site_name');

        $response = $this->post('/admin/store-reset', [
            'confirmation' => $this->tenantA->name,
            'accept' => true,
        ]);
        $response->assertRedirect();

        $this->assertSame(0, \App\Models\Product::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->count());
        $this->assertSame(0, \App\Models\Order::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->count());
        $this->assertSame(0, \App\Models\Category::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->count());
        $this->assertSame(0, \App\Models\OrderItem::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->count());
        $this->assertSame(0, \App\Models\StockMovement::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->count());
        $this->assertSame(0, \App\Models\Message::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->count());

        $this->tenantA->refresh();
        $this->assertSame($this->fixture->tenantA, $this->tenantA->id);
    }

    public function test_staff_cannot_reset(): void
    {
        $staff = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Staff Reset',
            'email' => 'staff-reset-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
            'status' => 'active',
            'is_owner' => false,
        ]);
        $staff->givePermissionTo(['billing.view', 'store.reset']);

        $this->actingAs($staff);
        Tenant::setCurrent($this->tenantA);

        $productsBefore = \App\Models\Product::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->count();

        $this->post('/admin/store-reset', [
            'confirmation' => $this->tenantA->name,
            'accept' => true,
        ])->assertForbidden();

        $this->assertSame($productsBefore, \App\Models\Product::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->count());
    }

    public function test_cross_tenant_cannot_reset(): void
    {
        $this->actingAs($this->ownerB);
        Tenant::setCurrent($this->tenantA);

        $productsBefore = \App\Models\Product::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->count();

        $this->post('/admin/store-reset', [
            'confirmation' => $this->tenantA->name,
            'accept' => true,
        ])->assertRedirect();

        $this->assertSame($productsBefore, \App\Models\Product::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->count());
    }

    public function test_superadmin_cannot_reset_through_merchant_routes(): void
    {
        $superadminRole = Role::create([
            'name' => 'superadmin',
            'guard_name' => 'web',
            'tenant_id' => $this->tenantA->id,
        ]);
        $superadminRole->syncPermissions(Permission::all());

        $superadmin = User::create([
            'name' => 'Reset SuperAdmin',
            'email' => 'reset-superadmin-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
            'status' => 'active',
            'tenant_id' => $this->tenantA->id,
            'is_owner' => false,
        ]);
        $superadmin->assignRole($superadminRole);

        $this->actingAs($superadmin);
        Tenant::setCurrent($this->tenantA);

        $this->post('/admin/store-reset', [
            'confirmation' => $this->tenantA->name,
            'accept' => true,
        ])->assertForbidden();
    }

    public function test_subscription_and_billing_preserved(): void
    {
        $this->actAsOwnerA();

        $subsBefore = Subscription::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->count();
        $invBefore = Invoice::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->count();

        // create an invoice to verify preservation
        Invoice::forceCreate([
            'tenant_id' => $this->tenantA->id,
            'subscription_id' => Subscription::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->value('id'),
            'plan_id' => $this->plan->id,
            'invoice_number' => 'INV-TEST-' . uniqid(),
            'status' => 'unpaid',
            'amount' => 100,
            'billing_period_start' => now()->toDateString(),
            'billing_period_end' => now()->addMonth()->toDateString(),
        ]);

        $this->post('/admin/store-reset', [
            'confirmation' => $this->tenantA->name,
            'accept' => true,
        ])->assertRedirect();

        $this->assertSame($subsBefore, Subscription::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->count());
        $this->assertSame($invBefore + 1, Invoice::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->count());
    }

    public function test_store_configuration_preserved(): void
    {
        $this->actAsOwnerA();

        $siteNameBefore = WebsiteInfo::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->value('site_name');
        $settingsBefore = \App\Models\Setting::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->count();
        $storefrontMediaBefore = \App\Models\StorefrontMedia::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->count();

        $this->post('/admin/store-reset', [
            'confirmation' => $this->tenantA->name,
            'accept' => true,
        ])->assertRedirect();

        $this->assertSame($siteNameBefore, WebsiteInfo::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->value('site_name'));
        $this->assertSame($settingsBefore, \App\Models\Setting::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->count());
        $this->assertSame($storefrontMediaBefore, \App\Models\StorefrontMedia::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->count());
    }

    public function test_customer_identity_preserved(): void
    {
        $this->actAsOwnerA();
        $customerCountBefore = User::where('tenant_id', $this->tenantA->id)->count();

        $this->post('/admin/store-reset', [
            'confirmation' => $this->tenantA->name,
            'accept' => true,
        ])->assertRedirect();

        $this->assertSame($customerCountBefore, User::where('tenant_id', $this->tenantA->id)->count());
    }


    public function test_foreign_key_integrity_after_reset(): void
    {
        $this->actAsOwnerA();
        $this->post('/admin/store-reset', [
            'confirmation' => $this->tenantA->name,
            'accept' => true,
        ])->assertRedirect();

        $this->assertSame(0, \App\Models\OrderItem::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->count());
        $this->assertSame(0, \App\Models\ProductVariant::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->count());
        $this->assertSame(0, \App\Models\StockMovement::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->count());
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('promotion_product')->where('tenant_id', $this->tenantA->id)->count());
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('coupon_product')->where('tenant_id', $this->tenantA->id)->count());
    }

    public function test_confirmation_mismatch_does_not_delete(): void
    {
        $this->actAsOwnerA();
        $productsBefore = \App\Models\Product::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->count();

        $this->post('/admin/store-reset', [
            'confirmation' => 'wrong-name',
            'accept' => true,
        ])->assertSessionHasErrors();

        $this->assertSame($productsBefore, \App\Models\Product::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->count());
    }

    public function test_final_confirmation_required(): void
    {
        $this->actAsOwnerA();
        $productsBefore = \App\Models\Product::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->count();

        $this->post('/admin/store-reset', [
            'confirmation' => $this->tenantA->name,
        ])->assertSessionHasErrors();

        $this->assertSame($productsBefore, \App\Models\Product::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->count());
    }

    public function test_other_tenant_data_unchanged(): void
    {
        $this->actAsOwnerA();
        $productsBBefore = \App\Models\Product::withoutTenantScope()->where('tenant_id', $this->tenantB->id)->count();

        $this->post('/admin/store-reset', [
            'confirmation' => $this->tenantA->name,
            'accept' => true,
        ])->assertRedirect();

        $this->assertSame($productsBBefore, \App\Models\Product::withoutTenantScope()->where('tenant_id', $this->tenantB->id)->count());
    }

    public function test_media_preserved(): void
    {
        $this->actAsOwnerA();
        $mediaBefore = \App\Models\StorefrontMedia::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->count();

        $this->post('/admin/store-reset', [
            'confirmation' => $this->tenantA->name,
            'accept' => true,
        ])->assertRedirect();

        $this->assertSame($mediaBefore, \App\Models\StorefrontMedia::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->count());
    }
}
