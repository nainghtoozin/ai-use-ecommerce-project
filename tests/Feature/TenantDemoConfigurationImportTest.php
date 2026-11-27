<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\City;
use App\Models\CodRule;
use App\Models\DeliveryService;
use App\Models\PackagingOption;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\Township;
use App\Models\User;
use App\Services\DeliveryFeeService;
use App\Services\TenantBootstrapService;
use App\Services\TenantDemoConfigurationImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TenantDemoConfigurationImportTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private TenantDemoConfigurationImportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name' => 'Store A', 'slug' => 'demo-a',
            'store_url' => '/store/demo-a', 'status' => 'active',
        ]);
        $this->tenantB = Tenant::create([
            'name' => 'Store B', 'slug' => 'demo-b',
            'store_url' => '/store/demo-b', 'status' => 'active',
        ]);

        $this->service = app(TenantDemoConfigurationImportService::class);
    }

    private function counts(Tenant $tenant): array
    {
        return [
            'payment_methods' => PaymentMethod::withoutTenantScope()->where('tenant_id', $tenant->id)->count(),
            'delivery_services' => DeliveryService::withoutTenantScope()->where('tenant_id', $tenant->id)->count(),
            'packaging_options' => PackagingOption::withoutTenantScope()->where('tenant_id', $tenant->id)->count(),
            'cod_rules' => CodRule::withoutTenantScope()->where('tenant_id', $tenant->id)->count(),
        ];
    }

    private function makeAdmin(Tenant $tenant, array $permissions = ['settings.view', 'settings.edit']): User
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $role = Role::withoutTenantScope()->firstOrCreate([
            'name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id,
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin ' . $tenant->slug,
            'email' => 'admin-' . $tenant->slug . '@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $user->assignRole($role);
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function makeSuperAdmin(): User
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::withoutTenantScope()->firstOrCreate([
            'name' => 'superadmin', 'guard_name' => 'web', 'tenant_id' => null,
        ]);

        $user = User::create([
            'tenant_id' => null,
            'name' => 'Platform Admin',
            'email' => 'superadmin-demo@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $user->assignRole($role);

        return $user;
    }

    /** @test */
    public function import_all_creates_every_demo_category_for_one_tenant(): void
    {
        $summary = $this->service->importAll($this->tenantA);

        $this->assertSame(
            ['payment_methods' => 4, 'delivery_services' => 3, 'packaging_options' => 3, 'cod_rules' => 1],
            $this->counts($this->tenantA)
        );
        $this->assertSame(11, $summary['totals']['created']);
        $this->assertSame(0, $summary['totals']['skipped']);
        $this->assertSame(0, $summary['totals']['failed']);

        $this->assertSame(0, PaymentMethod::withoutTenantScope()->where('tenant_id', $this->tenantB->id)->count());
    }

    /** @test */
    public function each_category_can_be_imported_independently(): void
    {
        $this->service->import($this->tenantA, [TenantDemoConfigurationImportService::CATEGORY_PAYMENT_METHODS]);
        $this->assertSame(
            ['payment_methods' => 4, 'delivery_services' => 0, 'packaging_options' => 0, 'cod_rules' => 0],
            $this->counts($this->tenantA)
        );

        $this->service->import($this->tenantA, [TenantDemoConfigurationImportService::CATEGORY_DELIVERY_SERVICES]);
        $this->assertSame(
            ['payment_methods' => 4, 'delivery_services' => 3, 'packaging_options' => 0, 'cod_rules' => 0],
            $this->counts($this->tenantA)
        );

        $this->service->import($this->tenantA, [TenantDemoConfigurationImportService::CATEGORY_PACKAGING_OPTIONS]);
        $this->assertSame(
            ['payment_methods' => 4, 'delivery_services' => 3, 'packaging_options' => 3, 'cod_rules' => 0],
            $this->counts($this->tenantA)
        );

        $this->service->import($this->tenantA, [TenantDemoConfigurationImportService::CATEGORY_COD_RULES]);
        $this->assertSame(
            ['payment_methods' => 4, 'delivery_services' => 3, 'packaging_options' => 3, 'cod_rules' => 1],
            $this->counts($this->tenantA)
        );
    }

    /** @test */
    public function running_the_import_twice_does_not_create_duplicates(): void
    {
        $first = $this->service->importAll($this->tenantA);
        $before = $this->counts($this->tenantA);

        $second = $this->service->importAll($this->tenantA);

        $this->assertSame($before, $this->counts($this->tenantA));
        $this->assertSame(11, $first['totals']['created']);
        $this->assertSame(0, $second['totals']['created']);
        $this->assertSame(11, $second['totals']['skipped']);
        $this->assertSame(0, $second['totals']['failed']);
    }

    /** @test */
    public function existing_custom_settings_are_preserved(): void
    {
        $customDelivery = DeliveryService::withoutTenantScope()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'My Own Express',
            'code' => 'express-delivery',
            'description' => 'Custom merchant description',
            'base_fee' => 999,
            'min_days' => 2,
            'max_days' => 4,
            'is_active' => false,
        ]);

        $customPackaging = PackagingOption::withoutTenantScope()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'My Gift Wrap',
            'code' => 'gift-packaging',
            'fee' => 777,
            'is_active' => false,
        ]);

        $this->service->importAll($this->tenantA);

        $customDelivery->refresh();
        $customPackaging->refresh();

        $this->assertSame('My Own Express', $customDelivery->name);
        $this->assertSame(999, (int) $customDelivery->base_fee);
        $this->assertFalse((bool) $customDelivery->is_active);
        $this->assertSame(777, (int) $customPackaging->fee);
        $this->assertFalse((bool) $customPackaging->is_active);

        $this->assertSame(3, DeliveryService::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->count());
        $this->assertSame(3, PackagingOption::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->count());
    }

    /** @test */
    public function existing_payment_credentials_are_preserved(): void
    {
        $custom = PaymentMethod::withoutTenantScope()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'KBZPay',
            'type' => 'manual',
            'account_name' => 'Real Merchant',
            'account_number' => 'REAL-12345',
            'is_active' => true,
        ]);

        $this->service->importAll($this->tenantA);

        $custom->refresh();

        $this->assertSame('Real Merchant', $custom->account_name);
        $this->assertSame('REAL-12345', $custom->account_number);
        $this->assertSame(4, PaymentMethod::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->count());
    }

    /** @test */
    public function existing_active_status_is_preserved(): void
    {
        $custom = PaymentMethod::withoutTenantScope()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Wave Pay',
            'type' => 'manual',
            'account_number' => '09-111-222-333',
            'is_active' => false,
        ]);

        $this->service->importAll($this->tenantA);

        $custom->refresh();

        $this->assertFalse((bool) $custom->is_active);
    }

    /** @test */
    public function imported_records_are_assigned_to_the_target_tenant(): void
    {
        $this->service->importAll($this->tenantA);

        foreach ([PaymentMethod::class, DeliveryService::class, PackagingOption::class, CodRule::class] as $model) {
            $rows = $model::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->get();
            $this->assertNotEmpty($rows, $model . ' should have demo rows');

            foreach ($rows as $row) {
                $this->assertSame($this->tenantA->id, (int) $row->tenant_id);
            }

            $this->assertSame(
                0,
                $model::withoutTenantScope()->where('tenant_id', $this->tenantB->id)->count()
            );
        }
    }

    /** @test */
    public function tenant_scoped_queries_never_expose_another_tenants_demo_settings(): void
    {
        $this->service->importAll($this->tenantA);
        $this->service->importAll($this->tenantB);

        Tenant::setCurrent($this->tenantA);

        $this->assertSame(4, PaymentMethod::query()->count());
        $this->assertSame(3, DeliveryService::query()->count());
        $this->assertSame(3, PackagingOption::query()->count());
        $this->assertSame(1, CodRule::query()->count());

        $this->assertSame(
            0,
            PaymentMethod::query()->where('tenant_id', $this->tenantB->id)->count()
        );
    }

    /** @test */
    public function merchant_cannot_import_demo_configuration_into_another_tenant(): void
    {
        $admin = $this->makeAdmin($this->tenantA);

        $this->actingAs($admin)
            ->post('/store/demo-b/admin/settings/demo-configuration/import', [
                'categories' => TenantDemoConfigurationImportService::ALL_CATEGORIES,
            ])
            ->assertForbidden();

        $this->assertSame(0, PaymentMethod::withoutTenantScope()->where('tenant_id', $this->tenantB->id)->count());
        $this->assertSame(0, DeliveryService::withoutTenantScope()->where('tenant_id', $this->tenantB->id)->count());
        $this->assertSame(0, PackagingOption::withoutTenantScope()->where('tenant_id', $this->tenantB->id)->count());
        $this->assertSame(0, CodRule::withoutTenantScope()->where('tenant_id', $this->tenantB->id)->count());
    }

    /** @test */
    public function merchant_import_always_targets_its_own_tenant_even_with_a_foreign_tenant_id(): void
    {
        $admin = $this->makeAdmin($this->tenantA);

        $response = $this->actingAs($admin)->post('/store/demo-a/admin/settings/demo-configuration/import', [
            'tenant_id' => $this->tenantB->id,
            'categories' => [TenantDemoConfigurationImportService::CATEGORY_PAYMENT_METHODS],
        ]);

        $response->assertRedirect();
        $this->assertSame(4, PaymentMethod::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->count());
        $this->assertSame(0, PaymentMethod::withoutTenantScope()->where('tenant_id', $this->tenantB->id)->count());
    }

    /** @test */
    public function merchant_without_settings_permission_cannot_import(): void
    {
        $admin = $this->makeAdmin($this->tenantA, ['settings.view']);

        $this->actingAs($admin)
            ->post('/store/demo-a/admin/settings/demo-configuration/import', [])
            ->assertForbidden();
    }

    /** @test */
    public function superadmin_bulk_import_is_authorized_and_merchants_are_rejected(): void
    {
        $merchant = $this->makeAdmin($this->tenantA);
        $this->actingAs($merchant)
            ->post('/superadmin/demo-configuration/import', [
                'tenant_ids' => [$this->tenantB->id],
                'categories' => TenantDemoConfigurationImportService::ALL_CATEGORIES,
            ])
            ->assertForbidden();

        $superadmin = $this->makeSuperAdmin();
        $this->actingAs($superadmin)
            ->post('/superadmin/demo-configuration/import', [
                'tenant_ids' => [$this->tenantA->id, $this->tenantB->id],
                'categories' => TenantDemoConfigurationImportService::ALL_CATEGORIES,
            ])
            ->assertRedirect('/superadmin/demo-configuration');

        $this->assertSame(4, PaymentMethod::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->count());
        $this->assertSame(4, PaymentMethod::withoutTenantScope()->where('tenant_id', $this->tenantB->id)->count());
        $this->assertSame(3, DeliveryService::withoutTenantScope()->where('tenant_id', $this->tenantB->id)->count());
    }

    /** @test */
    public function one_tenant_failure_does_not_corrupt_other_tenants(): void
    {
        $brokenTenant = new Tenant(['name' => 'Broken Store', 'slug' => 'demo-broken']);
        $brokenTenant->id = 999999;

        $service = new class extends TenantDemoConfigurationImportService {
            public int $brokenTenantId = 999999;

            public function import(Tenant $tenant, array $categories = self::ALL_CATEGORIES): array
            {
                if ((int) $tenant->id === $this->brokenTenantId) {
                    throw new \RuntimeException('Simulated tenant failure');
                }

                return parent::import($tenant, $categories);
            }
        };

        $results = $service->importForTenants([$this->tenantA, $brokenTenant], TenantDemoConfigurationImportService::ALL_CATEGORIES);

        $this->assertCount(2, $results);

        $healthy = collect($results)->firstWhere('tenant_id', $this->tenantA->id);
        $broken = collect($results)->firstWhere('tenant_id', 999999);

        $this->assertSame(11, $healthy['totals']['created']);
        $this->assertArrayNotHasKey('error', $healthy);
        $this->assertArrayHasKey('error', $broken);
        $this->assertSame('Simulated tenant failure', $broken['error']);

        $this->assertSame(
            ['payment_methods' => 4, 'delivery_services' => 3, 'packaging_options' => 3, 'cod_rules' => 1],
            $this->counts($this->tenantA)
        );
        $this->assertSame(
            ['payment_methods' => 0, 'delivery_services' => 0, 'packaging_options' => 0, 'cod_rules' => 0],
            $this->counts($this->tenantB)
        );
    }

    /** @test */
    public function newly_created_tenants_receive_demo_configuration_during_bootstrap(): void
    {
        $tenant = Tenant::create([
            'name' => 'Fresh Demo Store', 'slug' => 'fresh-demo',
            'store_url' => '/store/fresh-demo', 'status' => 'pending',
        ]);

        app(TenantBootstrapService::class)->bootstrap($tenant, [
            'owner_name' => 'Fresh Owner',
            'owner_email' => 'fresh-demo@test.com',
            'owner_password' => 'password',
            'status' => 'active',
            'email_verified' => true,
        ]);

        $this->assertSame(
            ['payment_methods' => 5, 'delivery_services' => 3, 'packaging_options' => 3, 'cod_rules' => 1],
            $this->counts($tenant)
        );

        $this->assertSame(4, PaymentMethod::withoutTenantScope()
            ->where('tenant_id', $tenant->id)->where('is_demo', true)->count());
    }

    /** @test */
    public function checkout_only_lists_enabled_payment_methods_for_the_current_tenant(): void
    {
        $this->grantStorefrontEntitlement($this->tenantA);
        Tenant::setCurrent($this->tenantA);

        $city = City::withoutTenantScope()->create([
            'tenant_id' => $this->tenantA->id, 'name' => 'Yangon', 'is_active' => true,
        ]);
        Township::withoutTenantScope()->create([
            'tenant_id' => $this->tenantA->id, 'city_id' => $city->id,
            'name' => 'Bahan', 'delivery_fee' => 1000, 'is_active' => true,
        ]);

        $category = Category::withoutTenantScope()->create([
            'tenant_id' => $this->tenantA->id, 'name' => 'Demo Cat', 'slug' => 'demo-cat',
        ]);
        $product = Product::create([
            'name' => 'Demo Widget', 'type' => 'single',
            'price' => 5000, 'stock' => 20, 'status' => 'active', 'category_id' => $category->id,
        ]);
        StockMovement::create([
            'product_id' => $product->id, 'product_variant_id' => null,
            'type' => StockMovement::TYPE_OPENING_STOCK, 'quantity' => 20,
        ]);

        $this->service->importAll($this->tenantA);
        $this->service->importAll($this->tenantB);

        PaymentMethod::withoutTenantScope()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Tenant B Only Method',
            'type' => 'manual',
            'is_active' => true,
        ]);

        $buyer = User::create([
            'tenant_id' => $this->tenantA->id, 'name' => 'Demo Buyer',
            'email' => 'demo-buyer@test.com', 'password' => bcrypt('password'),
            'status' => 'active', 'allow_cod' => true,
        ]);

        $this->actingAs($buyer, 'accounts');
        app()->instance('current.tenant', $this->tenantA);
        $this->post('/store/demo-a/cart/add', ['product_id' => $product->id, 'quantity' => 1]);

        $response = $this->get('/store/demo-a/checkout');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('paymentMethods', function ($methods) {
                $names = collect($methods)->pluck('name')->all();

                $this->assertContains('KBZPay', $names);
                $this->assertContains('Wave Pay', $names);
                $this->assertContains('CB Pay', $names);
                $this->assertNotContains('Visa Card', $names);
                $this->assertNotContains('Tenant B Only Method', $names);

                return true;
            }));
    }

    /** @test */
    public function demo_delivery_services_remain_tenant_scoped(): void
    {
        $this->service->importAll($this->tenantA);
        $this->service->importAll($this->tenantB);

        Tenant::setCurrent($this->tenantA);

        $codes = app(DeliveryFeeService::class)->getAvailableServices()->pluck('code')->sort()->values()->all();

        $this->assertSame(['express-delivery', 'same-day-delivery', 'standard-delivery'], $codes);
    }

    /** @test */
    public function demo_cod_import_respects_the_single_rule_architecture(): void
    {
        $custom = CodRule::withoutTenantScope()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Merchant COD Rule',
            'is_active' => false,
            'min_order_amount' => 5000,
        ]);

        $this->service->import($this->tenantA, [TenantDemoConfigurationImportService::CATEGORY_COD_RULES]);
        $this->service->import($this->tenantA, [TenantDemoConfigurationImportService::CATEGORY_COD_RULES]);

        $this->assertSame(1, CodRule::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->count());

        $custom->refresh();
        $this->assertSame('Merchant COD Rule', $custom->name);
        $this->assertFalse((bool) $custom->is_active);
        $this->assertEquals(5000, (float) $custom->min_order_amount);

        $this->service->import($this->tenantB, [TenantDemoConfigurationImportService::CATEGORY_COD_RULES]);

        $demoRule = CodRule::withoutTenantScope()->where('tenant_id', $this->tenantB->id)->first();
        $this->assertNotNull($demoRule);
        $this->assertTrue((bool) $demoRule->is_active);
        $this->assertEquals(0, (float) $demoRule->min_order_amount);
    }

    /** @test */
    public function status_reports_missing_demo_records_without_changing_data(): void
    {
        $this->service->import($this->tenantA, [TenantDemoConfigurationImportService::CATEGORY_PAYMENT_METHODS]);

        $status = $this->service->status($this->tenantA);

        $this->assertSame(4, $status['categories']['payment_methods']['demo_total']);
        $this->assertSame(4, $status['categories']['payment_methods']['demo_present']);
        $this->assertSame(0, $status['categories']['payment_methods']['demo_missing']);
        $this->assertSame(3, $status['categories']['delivery_services']['demo_missing']);
        $this->assertSame(0, $status['categories']['delivery_services']['existing_count']);

        $this->assertSame(0, DeliveryService::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->count());
    }
}
