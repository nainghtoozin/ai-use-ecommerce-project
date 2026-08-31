<?php

namespace Tests\Feature;

use App\Enums\ProductType;
use App\Models\Category;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Product;
use App\Models\ProductCombo;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductComboV2Test extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $adminA;
    private User $adminB;
    private Category $categoryA;
    private Plan $freePlan;
    private Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupMinimalSchema();
        $this->seedPlansAndRoles();
        $this->setupTenantsAndUsers();
    }

    private function setupMinimalSchema(): void
    {
        $tables = [
            'permissions', 'roles', 'model_has_roles', 'role_has_permissions',
            'model_has_permissions', 'users', 'tenants', 'plans', 'plan_features',
            'categories', 'brands', 'units', 'products', 'product_variants',
            'product_combos', 'warehouses', 'stock_movements',
        ];

        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) {
                $this->markTestSkipped("Table {$table} not found. Run migrations first.");
            }
        }
    }

    private function seedPlansAndRoles(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'products.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'products.create', 'guard_name' => 'web']);
        Permission::create(['name' => 'products.edit', 'guard_name' => 'web']);
        Permission::create(['name' => 'products.delete', 'guard_name' => 'web']);

        $this->adminRole = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $this->adminRole->syncPermissions(Permission::whereIn('name', [
            'products.view', 'products.create', 'products.edit', 'products.delete'
        ])->get());

        $this->freePlan = Plan::create([
            'name' => 'Free Plan',
            'slug' => 'free-plan',
            'monthly_price' => 0,
            'yearly_price' => 0,
            'status' => 'active',
        ]);

        PlanFeature::create(['plan_id' => $this->freePlan->id, 'feature_key' => 'single_products', 'is_enabled' => true]);
        PlanFeature::create(['plan_id' => $this->freePlan->id, 'feature_key' => 'variable_products', 'is_enabled' => true]);
        PlanFeature::create(['plan_id' => $this->freePlan->id, 'feature_key' => 'combo_products', 'is_enabled' => true]);
    }

    private function setupTenantsAndUsers(): void
    {
        $this->tenantA = Tenant::create(['name' => 'Store A', 'slug' => 'store-a']);
        $this->tenantB = Tenant::create(['name' => 'Store B', 'slug' => 'store-b']);

        $this->tenantA->subscription_plan_id = $this->freePlan->id;
        $this->tenantA->save();
        $this->tenantB->subscription_plan_id = $this->freePlan->id;
        $this->tenantB->save();

        $this->categoryA = Category::create(['tenant_id' => $this->tenantA->id, 'name' => 'Category A', 'slug' => 'category-a', 'is_active' => true]);

        $this->adminA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Admin A',
            'email' => 'admin-combo-a@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $this->adminA->assignRole($this->adminRole);

        $this->adminB = User::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Admin B',
            'email' => 'admin-combo-b@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $this->adminB->assignRole($this->adminRole);
    }

    private function actAsAdminA(): void
    {
        $this->actingAs($this->adminA);
        \App\Models\Tenant::setCurrent($this->tenantA);
    }

    private function actAsAdminB(): void
    {
        $this->actingAs($this->adminB);
        \App\Models\Tenant::setCurrent($this->tenantB);
    }

    private function createProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Test Product',
            'type' => ProductType::SINGLE,
            'price' => 100,
            'stock' => 50,
            'category_id' => $this->categoryA->id,
            'status' => Product::STATUS_ACTIVE,
        ], $overrides));
    }

    private function createComboProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Test Combo',
            'type' => ProductType::COMBO,
            'price' => 80,
            'category_id' => $this->categoryA->id,
            'status' => Product::STATUS_ACTIVE,
        ], $overrides));
    }

    /** @test */
    public function combo_product_can_have_components(): void
    {
        $this->actAsAdminA();

        $combo = $this->createComboProduct();
        $productA = $this->createProduct(['name' => 'Product A', 'price' => 50]);
        $productB = $this->createProduct(['name' => 'Product B', 'price' => 30]);

        $combo->comboItems()->create([
            'combo_product_id' => $productA->id,
            'quantity' => 1,
        ]);
        $combo->comboItems()->create([
            'combo_product_id' => $productB->id,
            'quantity' => 2,
        ]);

        $this->assertEquals(2, $combo->comboItems()->count());
    }

    /** @test */
    public function component_quantity_is_stored_correctly(): void
    {
        $this->actAsAdminA();

        $combo = $this->createComboProduct();
        $product = $this->createProduct();

        $item = $combo->comboItems()->create([
            'combo_product_id' => $product->id,
            'quantity' => 3,
        ]);

        $this->assertEquals(3, $item->quantity);
    }

    /** @test */
    public function invalid_quantity_is_rejected(): void
    {
        $this->actAsAdminA();

        $combo = $this->createComboProduct();
        $product = $this->createProduct();

        $item = $combo->comboItems()->create([
            'combo_product_id' => $product->id,
            'quantity' => 0,
        ]);

        $this->assertEquals(1, $item->quantity);
    }

    /** @test */
    public function combo_cannot_contain_itself(): void
    {
        $this->actAsAdminA();

        $combo = $this->createComboProduct();
        $service = app(ProductService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A combo cannot include itself.');
        $service->addToCombo($combo, $combo, 1);
    }

    /** @test */
    public function combo_cannot_contain_other_combo(): void
    {
        $this->actAsAdminA();

        $comboA = $this->createComboProduct(['name' => 'Combo A']);
        $comboB = $this->createComboProduct(['name' => 'Combo B']);

        $this->expectException(\InvalidArgumentException::class);
        $service = app(ProductService::class);
        $service->addToCombo($comboA, $comboB, 1);
    }

    /** @test */
    public function valid_tenant_component_can_be_attached(): void
    {
        $this->actAsAdminA();

        $combo = $this->createComboProduct();
        $product = $this->createProduct();

        $item = $combo->comboItems()->create([
            'combo_product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->assertEquals($product->id, $item->combo_product_id);
        $this->assertEquals(1, $item->quantity);
    }

    /** @test */
    public function cross_tenant_component_is_rejected(): void
    {
        $this->actAsAdminA();

        $combo = $this->createComboProduct();

        $this->actAsAdminB();
        $productB = $this->createProduct(['tenant_id' => $this->tenantB->id]);

        $this->actAsAdminA();

        $item = $combo->comboItems()->create([
            'combo_product_id' => $productB->id,
            'quantity' => 1,
        ]);

        $this->assertNull($item->combo_product_id);
    }

    /** @test */
    public function combo_base_price_equals_sum_of_components(): void
    {
        $this->actAsAdminA();

        $combo = $this->createComboProduct(['price' => 50]);
        $productA = $this->createProduct(['price' => 30]);
        $productB = $this->createProduct(['price' => 20]);

        $combo->comboItems()->create(['combo_product_id' => $productA->id, 'quantity' => 1]);
        $combo->comboItems()->create(['combo_product_id' => $productB->id, 'quantity' => 2]);

        $this->assertEquals(70, $combo->getComboBasePrice());
    }

    /** @test */
    public function combo_stock_equals_min_of_component_ratios(): void
    {
        $this->actAsAdminA();

        $combo = $this->createComboProduct();
        $productA = $this->createProduct(['stock' => 10]);
        $productB = $this->createProduct(['stock' => 6]);

        $combo->comboItems()->create(['combo_product_id' => $productA->id, 'quantity' => 1]);
        $combo->comboItems()->create(['combo_product_id' => $productB->id, 'quantity' => 2]);

        $this->assertEquals(3, $combo->comboStock());
    }

    /** @test */
    public function zero_stock_component_reports_correctly(): void
    {
        $this->actAsAdminA();

        $combo = $this->createComboProduct();
        $productA = $this->createProduct(['stock' => 0]);
        $productB = $this->createProduct(['stock' => 10]);

        $combo->comboItems()->create(['combo_product_id' => $productA->id, 'quantity' => 1]);
        $combo->comboItems()->create(['combo_product_id' => $productB->id, 'quantity' => 1]);

        $this->assertEquals(0, $combo->comboStock());
    }

    /** @test */
    public function combo_with_variant_component_uses_variant_price_and_stock(): void
    {
        $this->actAsAdminA();

        $combo = $this->createComboProduct();
        $product = $this->createProduct(['price' => 100, 'stock' => 50]);
        $variant = $product->variants()->create([
            'sku' => 'VAR-TEST-001',
            'price' => 80,
            'stock' => 20,
            'attributes' => ['size' => 'L'],
            'status' => 'active',
        ]);

        $combo->comboItems()->create([
            'combo_product_id' => $product->id,
            'linked_variant_id' => $variant->id,
            'quantity' => 1,
        ]);

        $item = $combo->comboItems()->first();
        $this->assertEquals(80, $item->getEffectivePrice());
        $this->assertEquals(20, $item->getEffectiveStock());
    }

    /** @test */
    public function combo_availability_identifies_bottleneck(): void
    {
        $this->actAsAdminA();

        $combo = $this->createComboProduct();
        $productA = $this->createProduct(['stock' => 10]);
        $productB = $this->createProduct(['stock' => 3]);

        $combo->comboItems()->create(['combo_product_id' => $productA->id, 'quantity' => 1]);
        $combo->comboItems()->create(['combo_product_id' => $productB->id, 'quantity' => 2]);

        $availability = $combo->calculateComboAvailability();

        $this->assertEquals(1, $availability['available_stock']);
        $this->assertEquals($productB->id, $availability['bottleneck']['product_id']);
    }

    /** @test */
    public function sync_combo_items_updates_quantities(): void
    {
        $this->actAsAdminA();

        $combo = $this->createComboProduct();
        $product = $this->createProduct();

        $combo->comboItems()->create(['combo_product_id' => $product->id, 'quantity' => 1]);

        $service = app(ProductService::class);
        $service->syncComboItems($combo, [
            ['combo_product_id' => $product->id, 'quantity' => 5],
        ]);

        $item = $combo->comboItems()->first();
        $this->assertEquals(5, $item->quantity);
    }

    /** @test */
    public function sync_combo_items_removes_missing_items(): void
    {
        $this->actAsAdminA();

        $combo = $this->createComboProduct();
        $productA = $this->createProduct(['name' => 'Product A']);
        $productB = $this->createProduct(['name' => 'Product B']);

        $combo->comboItems()->create(['combo_product_id' => $productA->id, 'quantity' => 1]);
        $combo->comboItems()->create(['combo_product_id' => $productB->id, 'quantity' => 1]);

        $service = app(ProductService::class);
        $service->syncComboItems($combo, [
            ['combo_product_id' => $productA->id, 'quantity' => 2],
        ]);

        $this->assertEquals(1, $combo->comboItems()->count());
        $this->assertEquals($productA->id, $combo->comboItems()->first()->combo_product_id);
        $this->assertEquals(2, $combo->comboItems()->first()->quantity);
    }

    /** @test */
    public function product_scope_excludes_combos_from_selection(): void
    {
        $this->actAsAdminA();

        $combo = $this->createComboProduct();
        $singleProduct = $this->createProduct(['type' => ProductType::SINGLE]);

        $selectable = Product::comboSelectable()->get();

        $this->assertTrue($selectable->contains('id', $singleProduct->id));
        $this->assertFalse($selectable->contains('id', $combo->id));
    }

    /** @test */
    public function duplicate_component_is_rejected(): void
    {
        $this->actAsAdminA();

        $combo = $this->createComboProduct();
        $product = $this->createProduct();

        $combo->comboItems()->create(['combo_product_id' => $product->id, 'quantity' => 1]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $controller = new \App\Http\Controllers\Admin\AdminProductController(
            app(\App\Services\ProductService::class),
            app(\App\Services\ImageService::class),
            app(\App\Services\SkuService::class),
            app(\App\Services\InventoryService::class),
            app(\App\Services\StockCalculationService::class)
        );
        $controller->validateComboItems([
            ['combo_product_id' => $product->id, 'quantity' => 1],
            ['combo_product_id' => $product->id, 'quantity' => 2],
        ]);
    }

    /** @test */
    public function variant_must_belong_to_product(): void
    {
        $this->actAsAdminA();

        $combo = $this->createComboProduct();
        $product = $this->createProduct();
        $variant = $product->variants()->create([
            'sku' => 'VAR-TEST-002',
            'price' => 50,
            'stock' => 10,
            'attributes' => ['size' => 'M'],
            'status' => 'active',
        ]);

        $otherProduct = $this->createProduct(['name' => 'Other Product']);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $controller = new \App\Http\Controllers\Admin\AdminProductController(
            app(\App\Services\ProductService::class),
            app(\App\Services\ImageService::class),
            app(\App\Services\SkuService::class),
            app(\App\Services\InventoryService::class),
            app(\App\Services\StockCalculationService::class)
        );
        $controller->validateComboItems([
            ['combo_product_id' => $otherProduct->id, 'linked_variant_id' => $variant->id, 'quantity' => 1],
        ]);
    }

    /** @test */
    public function combo_items_deleted_when_product_type_changes(): void
    {
        $this->actAsAdminA();

        $product = $this->createProduct(['type' => ProductType::COMBO, 'price' => 50]);
        $component = $this->createProduct();
        $product->comboItems()->create(['combo_product_id' => $component->id, 'quantity' => 1]);

        $this->assertEquals(1, $product->comboItems()->count());

        $product->update(['type' => ProductType::SINGLE]);

        $product->refresh();
        $this->assertEquals(0, $product->comboItems()->count());
    }

    /** @test */
    public function get_combo_summary_returns_correct_structure(): void
    {
        $this->actAsAdminA();

        $combo = $this->createComboProduct(['price' => 80]);
        $productA = $this->createProduct(['name' => 'Product A', 'price' => 30]);
        $productB = $this->createProduct(['name' => 'Product B', 'price' => 20]);

        $combo->comboItems()->create(['combo_product_id' => $productA->id, 'quantity' => 1]);
        $combo->comboItems()->create(['combo_product_id' => $productB->id, 'quantity' => 2]);

        $summary = $combo->getComboSummary();

        $this->assertEquals(2, $summary['item_count']);
        $this->assertEquals(70, $summary['base_price']);
        $this->assertEquals(80, $summary['combo_price']);
        $this->assertEquals(0, $summary['savings']);
    }
}
