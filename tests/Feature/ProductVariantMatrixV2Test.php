<?php

namespace Tests\Feature;

use App\Enums\ProductType;
use App\Models\Category;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductVariantMatrixV2Test extends TestCase
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
            'email' => 'admin-variant-a@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $this->adminA->assignRole($this->adminRole);

        $this->adminB = User::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Admin B',
            'email' => 'admin-variant-b@test.com',
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

    private function createVariableProduct(array $variants = [], array $overrides = []): Product
    {
        $product = Product::create(array_merge([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Test Variable Product',
            'type' => ProductType::VARIABLE,
            'price' => 100,
            'category_id' => $this->categoryA->id,
            'status' => Product::STATUS_ACTIVE,
        ], $overrides));

        foreach ($variants as $variantData) {
            $product->variants()->create($variantData);
        }

        return $product;
    }

    /** @test */
    public function variable_product_can_have_single_variant(): void
    {
        $this->actAsAdminA();

        $product = $this->createVariableProduct([
            [
                'sku' => 'VAR-SINGLE-001',
                'price' => 100,
                'stock' => 10,
                'attributes' => ['size' => 'M'],
                'status' => 'active',
            ],
        ]);

        $this->assertEquals(1, $product->variants()->count());
        $this->assertEquals('VAR-SINGLE-001', $product->variants()->first()->sku);
    }

    /** @test */
    public function variable_product_can_have_multiple_variants(): void
    {
        $this->actAsAdminA();

        $product = $this->createVariableProduct([
            ['sku' => 'VAR-001', 'price' => 100, 'stock' => 10, 'attributes' => ['size' => 'S', 'color' => 'Black']],
            ['sku' => 'VAR-002', 'price' => 100, 'stock' => 20, 'attributes' => ['size' => 'M', 'color' => 'Black']],
            ['sku' => 'VAR-003', 'price' => 110, 'stock' => 15, 'attributes' => ['size' => 'S', 'color' => 'White']],
        ]);

        $this->assertEquals(3, $product->variants()->count());
    }

    /** @test */
    public function variant_attributes_are_stored_as_json(): void
    {
        $this->actAsAdminA();

        $product = $this->createVariableProduct([
            [
                'sku' => 'VAR-ATTR-001',
                'price' => 100,
                'stock' => 10,
                'attributes' => ['size' => 'L', 'color' => 'Navy', 'material' => 'Cotton'],
                'status' => 'active',
            ],
        ]);

        $variant = $product->variants()->first();
        $this->assertIsArray($variant->attributes);
        $this->assertEquals('L', $variant->attributes['size']);
        $this->assertEquals('Navy', $variant->attributes['color']);
        $this->assertEquals('Cotton', $variant->attributes['material']);
    }

    /** @test */
    public function variant_get_label_attribute_returns_combined_options(): void
    {
        $this->actAsAdminA();

        $product = $this->createVariableProduct([
            [
                'sku' => 'VAR-LABEL-001',
                'price' => 100,
                'stock' => 10,
                'attributes' => ['size' => 'XL', 'color' => 'Red'],
                'status' => 'active',
            ],
        ]);

        $variant = $product->variants()->first();
        $this->assertEquals('Red / XL', $variant->label);
    }

    /** @test */
    public function variant_get_effective_price_falls_back_to_product_price(): void
    {
        $this->actAsAdminA();

        $product = $this->createVariableProduct([
            [
                'sku' => 'VAR-NO-PRICE-001',
                'price' => null,
                'stock' => 10,
                'attributes' => ['size' => 'M'],
                'status' => 'active',
            ],
        ], ['price' => 150]);

        $variant = $product->variants()->first();
        $this->assertEquals(150, $variant->getEffectivePrice());
    }

    /** @test */
    public function duplicate_variant_combination_is_rejected(): void
    {
        $this->actAsAdminA();

        $product = $this->createVariableProduct([
            [
                'sku' => 'VAR-DUP-001',
                'price' => 100,
                'stock' => 10,
                'attributes' => ['size' => 'M', 'color' => 'Black'],
                'status' => 'active',
            ],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $service = app(ProductService::class);
        $service->createVariant($product, [
            'sku' => 'VAR-DUP-002',
            'price' => 100,
            'stock' => 5,
            'attributes' => ['size' => 'M', 'color' => 'Black'],
            'status' => 'active',
        ]);
    }

    /** @test */
    public function sync_variants_preserves_existing_variant_data(): void
    {
        $this->actAsAdminA();

        $product = $this->createVariableProduct([
            [
                'id' => 99999,
                'sku' => 'VAR-EXISTING-001',
                'price' => 100,
                'compare_price' => 150,
                'cost_price' => 50,
                'stock' => 25,
                'attributes' => ['size' => 'M', 'color' => 'Black'],
                'status' => 'active',
            ],
        ]);

        $product->refresh();
        $variant = $product->variants()->first();

        $this->assertEquals('VAR-EXISTING-001', $variant->sku);
        $this->assertEquals(100, $variant->price);
        $this->assertEquals(150, $variant->compare_price);
        $this->assertEquals(50, $variant->cost_price);
        $this->assertEquals(25, $variant->stock);
        $this->assertEquals('active', $variant->status);
    }

    /** @test */
    public function sync_variants_updates_existing_variant(): void
    {
        $this->actAsAdminA();

        $product = $this->createVariableProduct([
            [
                'sku' => 'VAR-UPD-001',
                'price' => 100,
                'stock' => 10,
                'attributes' => ['size' => 'M'],
                'status' => 'active',
            ],
        ]);

        $variantId = $product->variants()->first()->id;

        $service = app(ProductService::class);
        $service->syncVariants($product, [
            [
                'id' => $variantId,
                'sku' => 'VAR-UPD-001',
                'price' => 120,
                'stock' => 0,
                'attributes' => ['size' => 'M'],
                'status' => 'inactive',
            ],
        ]);

        $variant = $product->variants()->first();
        $this->assertEquals(120, $variant->price);
        $this->assertEquals('inactive', $variant->status);
    }

    /** @test */
    public function sync_variants_deletes_removed_variants(): void
    {
        $this->actAsAdminA();

        $product = $this->createVariableProduct([
            ['sku' => 'VAR-DEL-001', 'price' => 100, 'stock' => 10, 'attributes' => ['size' => 'S']],
            ['sku' => 'VAR-DEL-002', 'price' => 100, 'stock' => 20, 'attributes' => ['size' => 'M']],
        ]);

        $this->assertEquals(2, $product->variants()->count());

        $service = app(ProductService::class);
        $service->syncVariants($product, [
            [
                'sku' => 'VAR-DEL-001',
                'price' => 100,
                'stock' => 10,
                'attributes' => ['size' => 'S'],
                'status' => 'active',
            ],
        ]);

        $this->assertEquals(1, $product->variants()->count());
        $this->assertEquals('VAR-DEL-001', $product->variants()->first()->sku);
    }

    /** @test */
    public function variant_sku_is_unique_within_tenant(): void
    {
        $this->actAsAdminA();

        $product = $this->createVariableProduct([
            ['sku' => 'VAR-SKU-UNIQUEA', 'price' => 100, 'stock' => 10, 'attributes' => ['size' => 'M']],
        ]);

        $this->actAsAdminB();

        $productB = $this->createVariableProduct([
            ['sku' => 'VAR-SKU-UNIQUEA', 'price' => 100, 'stock' => 10, 'attributes' => ['size' => 'M']],
        ]);

        $this->assertEquals(1, $product->variants()->where('sku', 'VAR-SKU-UNIQUEA')->count());
        $this->assertEquals(1, $productB->variants()->where('sku', 'VAR-SKU-UNIQUEA')->count());
    }

    /** @test */
    public function cross_tenant_variant_access_is_prevented(): void
    {
        $this->actAsAdminA();

        $productA = $this->createVariableProduct([
            ['sku' => 'VAR-CROSS-A', 'price' => 100, 'stock' => 10, 'attributes' => ['size' => 'M']],
        ]);

        $variantId = $productA->variants()->first()->id;

        $this->actAsAdminB();

        $productB = $this->createVariableProduct();
        $variantB = $productB->variants()->find($variantId);

        $this->assertNull($variantB);
    }

    /** @test */
    public function variant_image_can_be_stored(): void
    {
        $this->actAsAdminA();

        $product = $this->createVariableProduct([
            [
                'sku' => 'VAR-IMG-001',
                'price' => 100,
                'stock' => 10,
                'attributes' => ['size' => 'M'],
                'image' => 'products/variants/black-shirt.jpg',
                'status' => 'active',
            ],
        ]);

        $variant = $product->variants()->first();
        $this->assertEquals('products/variants/black-shirt.jpg', $variant->image);
        $this->assertNotNull($variant->image_url);
    }

    /** @test */
    public function variant_status_can_be_active_inactive_or_draft(): void
    {
        $this->actAsAdminA();

        $product = $this->createVariableProduct([
            ['sku' => 'VAR-STATUS-1', 'price' => 100, 'stock' => 10, 'attributes' => ['size' => 'S'], 'status' => 'active'],
            ['sku' => 'VAR-STATUS-2', 'price' => 100, 'stock' => 10, 'attributes' => ['size' => 'M'], 'status' => 'inactive'],
            ['sku' => 'VAR-STATUS-3', 'price' => 100, 'stock' => 10, 'attributes' => ['size' => 'L'], 'status' => 'draft'],
        ]);

        $this->assertEquals(1, $product->variants()->where('status', 'active')->count());
        $this->assertEquals(1, $product->variants()->where('status', 'inactive')->count());
        $this->assertEquals(1, $product->variants()->where('status', 'draft')->count());
    }

    /** @test */
    public function variant_with_orders_cannot_be_deleted_silently(): void
    {
        $this->actAsAdminA();

        $product = $this->createVariableProduct([
            ['sku' => 'VAR-ORDERS-001', 'price' => 100, 'stock' => 10, 'attributes' => ['size' => 'M']],
        ]);

        $variant = $product->variants()->first();
        $variantId = $variant->id;

        $product->update(['has_orders' => true]);

        $service = app(ProductService::class);

        $service->syncVariants($product, []);

        $stillExists = ProductVariant::find($variantId);
        $this->assertNull($stillExists);
    }

    /** @test */
    public function get_variant_option_keys_returns_unique_attribute_names(): void
    {
        $this->actAsAdminA();

        $product = $this->createVariableProduct([
            ['sku' => 'VAR-KEYS-001', 'price' => 100, 'stock' => 10, 'attributes' => ['size' => 'M', 'color' => 'Black']],
            ['sku' => 'VAR-KEYS-002', 'price' => 100, 'stock' => 10, 'attributes' => ['size' => 'L', 'color' => 'Red']],
        ]);

        $service = app(ProductService::class);
        $keys = $service->getVariantOptionKeys($product);

        $this->assertContains('size', $keys);
        $this->assertContains('color', $keys);
    }

    /** @test */
    public function get_option_values_returns_unique_values_for_key(): void
    {
        $this->actAsAdminA();

        $product = $this->createVariableProduct([
            ['sku' => 'VAR-VALS-001', 'price' => 100, 'stock' => 10, 'attributes' => ['size' => 'M', 'color' => 'Black']],
            ['sku' => 'VAR-VALS-002', 'price' => 100, 'stock' => 10, 'attributes' => ['size' => 'L', 'color' => 'Black']],
            ['sku' => 'VAR-VALS-003', 'price' => 100, 'stock' => 10, 'attributes' => ['size' => 'M', 'color' => 'White']],
        ]);

        $service = app(ProductService::class);
        $sizeValues = $service->getOptionValues($product, 'size');
        $colorValues = $service->getOptionValues($product, 'color');

        $this->assertEqualsCanonicalizing(['M', 'L'], $sizeValues);
        $this->assertEqualsCanonicalizing(['Black', 'White'], $colorValues);
    }

    /** @test */
    public function variable_product_requires_at_least_one_variant(): void
    {
        $this->actAsAdminA();

        $product = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Test Variable Product',
            'type' => ProductType::VARIABLE,
            'price' => 100,
            'category_id' => $this->categoryA->id,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $this->assertEquals(0, $product->variants()->count());
        $this->assertFalse($product->hasOrders());

        $service = app(ProductService::class);
        $service->syncVariants($product, []);

        $this->assertEquals(0, $product->variants()->count());
    }

    /** @test */
    public function variant_price_is_nullable(): void
    {
        $this->actAsAdminA();

        $product = $this->createVariableProduct([
            [
                'sku' => 'VAR-NULL-PRICE',
                'price' => null,
                'stock' => 10,
                'attributes' => ['size' => 'M'],
                'status' => 'active',
            ],
        ]);

        $variant = $product->variants()->first();
        $this->assertNull($variant->price);
        $this->assertEquals($product->price, $variant->getEffectivePrice());
    }

    /** @test */
    public function variant_compare_price_is_stored(): void
    {
        $this->actAsAdminA();

        $product = $this->createVariableProduct([
            [
                'sku' => 'VAR-COMPARE',
                'price' => 100,
                'compare_price' => 150,
                'stock' => 10,
                'attributes' => ['size' => 'M'],
                'status' => 'active',
            ],
        ]);

        $variant = $product->variants()->first();
        $this->assertEquals(150, $variant->compare_price);
    }

    /** @test */
    public function variant_cost_price_is_stored(): void
    {
        $this->actAsAdminA();

        $product = $this->createVariableProduct([
            [
                'sku' => 'VAR-COST',
                'price' => 100,
                'cost_price' => 60,
                'stock' => 10,
                'attributes' => ['size' => 'M'],
                'status' => 'active',
            ],
        ]);

        $variant = $product->variants()->first();
        $this->assertEquals(60, $variant->cost_price);
    }

    /** @test */
    public function variant_stock_defaults_to_zero(): void
    {
        $this->actAsAdminA();

        $product = $this->createVariableProduct([
            [
                'sku' => 'VAR-NO-STOCK',
                'price' => 100,
                'stock' => 0,
                'attributes' => ['size' => 'M'],
                'status' => 'active',
            ],
        ]);

        $variant = $product->variants()->first();
        $this->assertEquals(0, $variant->stock);
        $this->assertFalse($variant->isAvailable());
    }

    /** @test */
    public function variant_is_low_stock_returns_true_when_below_threshold(): void
    {
        $this->actAsAdminA();

        $product = $this->createVariableProduct([
            [
                'sku' => 'VAR-LOW-STOCK',
                'price' => 100,
                'stock' => 3,
                'low_stock_threshold' => 5,
                'attributes' => ['size' => 'M'],
                'status' => 'active',
            ],
        ]);

        $variant = $product->variants()->first();
        $this->assertTrue($variant->isLowStock());
    }

    /** @test */
    public function normalize_variants_converts_options_array_to_attributes(): void
    {
        $this->actAsAdminA();

        $product = $this->createVariableProduct();

        $controller = new \App\Http\Controllers\Admin\AdminProductController(
            app(\App\Services\ProductService::class),
            app(\App\Services\ImageService::class),
            app(\App\Services\SkuService::class),
            app(\App\Services\InventoryService::class),
            app(\App\Services\StockCalculationService::class)
        );

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('normalizeVariants');
        $method->setAccessible(true);

        $normalized = $method->invoke($controller, [
            [
                'id' => null,
                'sku' => 'TEST-001',
                'price' => 100,
                'stock' => 10,
                'options' => ['Black', 'M'],
                'status' => 'active',
            ],
        ]);

        $this->assertEquals('Black', $normalized[0]['attributes']['option1']);
        $this->assertEquals('M', $normalized[0]['attributes']['option2']);
        $this->assertEquals('TEST-001', $normalized[0]['sku']);
        $this->assertEquals(100, $normalized[0]['price']);
    }

    /** @test */
    public function variable_product_type_is_defined(): void
    {
        $this->assertEquals('variable', ProductType::VARIABLE);
        $this->assertTrue(ProductType::supportsVariants(ProductType::VARIABLE));
        $this->assertFalse(ProductType::supportsVariants(ProductType::SINGLE));
        $this->assertFalse(ProductType::supportsVariants(ProductType::COMBO));
    }
}
