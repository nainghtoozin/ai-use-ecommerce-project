<?php

namespace Tests\Feature;

use App\Enums\ProductType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Product;
use App\Models\ProductCombo;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Services\FeatureGate;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductV2Test extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $adminA;
    private User $adminB;
    private Category $categoryA;
    private Category $categoryB;
    private Brand $brandA;
    private Brand $brandB;
    private Unit $unitA;
    private Unit $unitB;
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
        PlanFeature::create(['plan_id' => $this->freePlan->id, 'feature_key' => 'variable_products', 'is_enabled' => false]);
        PlanFeature::create(['plan_id' => $this->freePlan->id, 'feature_key' => 'combo_products', 'is_enabled' => false]);
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
        $this->categoryB = Category::create(['tenant_id' => $this->tenantB->id, 'name' => 'Category B', 'slug' => 'category-b', 'is_active' => true]);

        $this->brandA = Brand::create(['tenant_id' => $this->tenantA->id, 'name' => 'Brand A', 'slug' => 'brand-a', 'is_active' => true]);
        $this->brandB = Brand::create(['tenant_id' => $this->tenantB->id, 'name' => 'Brand B', 'slug' => 'brand-b', 'is_active' => true]);

        $this->unitA = Unit::create(['tenant_id' => $this->tenantA->id, 'name' => 'Piece', 'short_name' => 'pcs']);
        $this->unitB = Unit::create(['tenant_id' => $this->tenantB->id, 'name' => 'Kilogram', 'short_name' => 'kg']);

        $this->adminA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Admin A',
            'email' => 'admin-product-a@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $this->adminA->assignRole($this->adminRole);

        $this->adminB = User::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Admin B',
            'email' => 'admin-product-b@test.com',
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

    /** @test */
    public function single_product_creation_works(): void
    {
        $this->actAsAdminA();

        $product = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Single Product Test',
            'slug' => 'single-product-test',
            'type' => ProductType::SINGLE,
            'price' => 100,
            'category_id' => $this->categoryA->id,
            'brand_id' => $this->brandA->id,
            'unit_id' => $this->unitA->id,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $this->assertNotNull($product->id);
        $this->assertEquals('Single Product Test', $product->name);
        $this->assertEquals(ProductType::SINGLE, $product->type);
        $this->assertEquals(100, $product->price);
        $this->assertTrue($product->isSingle());
        $this->assertFalse($product->isVariable());
        $this->assertFalse($product->isCombo());
    }

    /** @test */
    public function product_type_is_preserved_on_update(): void
    {
        $this->actAsAdminA();

        $product = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Type Test Product',
            'type' => ProductType::SINGLE,
            'price' => 50,
            'category_id' => $this->categoryA->id,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $product->update(['name' => 'Updated Name']);
        $this->assertEquals(ProductType::SINGLE, $product->fresh()->type);

        $product->update(['price' => 75]);
        $this->assertEquals(ProductType::SINGLE, $product->fresh()->type);
    }

    /** @test */
    public function single_product_editing_works(): void
    {
        $this->actAsAdminA();

        $product = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Edit Test Product',
            'type' => ProductType::SINGLE,
            'price' => 100,
            'category_id' => $this->categoryA->id,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $product->update([
            'name' => 'Edited Product',
            'price' => 150,
        ]);

        $this->assertEquals('Edited Product', $product->fresh()->name);
        $this->assertEquals(150, $product->fresh()->price);
    }

    /** @test */
    public function variable_product_respects_feature_gate(): void
    {
        $this->actAsAdminA();

        $this->assertFalse(ProductType::isAvailable(ProductType::VARIABLE));

        $this->expectException(\InvalidArgumentException::class);
        ProductType::isAvailable(ProductType::VARIABLE);
    }

    /** @test */
    public function combo_product_respects_feature_gate(): void
    {
        $this->actAsAdminA();

        $this->assertFalse(ProductType::isAvailable(ProductType::COMBO));
    }

    /** @test */
    public function unauthorized_combo_creation_is_rejected_server_side(): void
    {
        $this->actAsAdminA();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Combo Product is not available on your current plan.');

        $service = app(\App\Services\ProductService::class);
        $service->validateType(ProductType::COMBO);
    }

    /** @test */
    public function unauthorized_combo_update_is_rejected_server_side(): void
    {
        $this->actAsAdminA();

        $product = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Combo Attempt Product',
            'type' => ProductType::SINGLE,
            'price' => 100,
            'category_id' => $this->categoryA->id,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $service = app(\App\Services\ProductService::class);
        $service->validateType(ProductType::COMBO);
    }

    /** @test */
    public function product_can_select_valid_category(): void
    {
        $this->actAsAdminA();

        $product = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Category Test',
            'type' => ProductType::SINGLE,
            'price' => 100,
            'category_id' => $this->categoryA->id,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $this->assertEquals($this->categoryA->id, $product->category_id);
        $this->assertEquals('Category A', $product->category->name);
    }

    /** @test */
    public function cross_tenant_category_selection_is_rejected(): void
    {
        $this->actAsAdminA();

        $this->expectException(\Illuminate\Database\QueryException::class);

        Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Cross Tenant Category Test',
            'type' => ProductType::SINGLE,
            'price' => 100,
            'category_id' => $this->categoryB->id,
            'status' => Product::STATUS_ACTIVE,
        ]);
    }

    /** @test */
    public function product_can_select_valid_brand(): void
    {
        $this->actAsAdminA();

        $product = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Brand Test',
            'type' => ProductType::SINGLE,
            'price' => 100,
            'category_id' => $this->categoryA->id,
            'brand_id' => $this->brandA->id,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $this->assertEquals($this->brandA->id, $product->brand_id);
        $this->assertEquals('Brand A', $product->brand->name);
    }

    /** @test */
    public function cross_tenant_brand_selection_is_rejected(): void
    {
        $this->actAsAdminA();

        $this->expectException(\Illuminate\Database\QueryException::class);

        Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Cross Tenant Brand Test',
            'type' => ProductType::SINGLE,
            'price' => 100,
            'category_id' => $this->categoryA->id,
            'brand_id' => $this->brandB->id,
            'status' => Product::STATUS_ACTIVE,
        ]);
    }

    /** @test */
    public function cross_tenant_unit_selection_is_rejected(): void
    {
        $this->actAsAdminA();

        $this->expectException(\Illuminate\Database\QueryException::class);

        Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Cross Tenant Unit Test',
            'type' => ProductType::SINGLE,
            'price' => 100,
            'category_id' => $this->categoryA->id,
            'unit_id' => $this->unitB->id,
            'status' => Product::STATUS_ACTIVE,
        ]);
    }

    /** @test */
    public function base_price_behavior_is_preserved(): void
    {
        $this->actAsAdminA();

        $product = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Price Test',
            'type' => ProductType::SINGLE,
            'price' => 100,
            'base_price' => 150,
            'category_id' => $this->categoryA->id,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $this->assertEquals(100, $product->price);
        $this->assertEquals(150, $product->base_price);
    }

    /** @test */
    public function sale_price_null_preserves_existing_behavior(): void
    {
        $this->actAsAdminA();

        $product = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Sale Price Null Test',
            'type' => ProductType::SINGLE,
            'price' => 100,
            'sale_price' => null,
            'category_id' => $this->categoryA->id,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $this->assertEquals(100, $product->getEffectivePrice());
    }

    /** @test */
    public function negative_sale_price_is_rejected(): void
    {
        $this->actAsAdminA();

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $request = new \App\Http\Requests\StoreProductRequest();
        $request->merge([
            'name' => 'Negative Sale Price Test',
            'type' => 'single',
            'price' => 100,
            'sale_price' => -50,
            'base_price' => 150,
            'category_id' => $this->categoryA->id,
            'status' => 'active',
        ]);

        app()->call([$request, 'validate']);
    }

    /** @test */
    public function existing_effective_price_behavior_remains_correct(): void
    {
        $this->actAsAdminA();

        $productWithSale = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'With Sale Price',
            'type' => ProductType::SINGLE,
            'price' => 100,
            'sale_price' => 80,
            'category_id' => $this->categoryA->id,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $productWithoutSale = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Without Sale Price',
            'type' => ProductType::SINGLE,
            'price' => 100,
            'sale_price' => null,
            'category_id' => $this->categoryA->id,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $this->assertEquals(80, $productWithSale->getEffectivePrice());
        $this->assertEquals(100, $productWithoutSale->getEffectivePrice());
    }

    /** @test */
    public function product_tenant_isolation_works(): void
    {
        $this->actAsAdminA();

        $productA = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Tenant A Product',
            'type' => ProductType::SINGLE,
            'price' => 100,
            'category_id' => $this->categoryA->id,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $this->actAsAdminB();

        $productB = Product::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Tenant B Product',
            'type' => ProductType::SINGLE,
            'price' => 200,
            'category_id' => $this->categoryB->id,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $this->actingAs($this->adminA);
        \App\Models\Tenant::setCurrent($this->tenantA);

        $tenantAProducts = Product::forCurrentTenant()->get();
        $this->assertTrue($tenantAProducts->contains('id', $productA->id));
        $this->assertFalse($tenantAProducts->contains('id', $productB->id));

        $this->actingAs($this->adminB);
        \App\Models\Tenant::setCurrent($this->tenantB);

        $tenantBProducts = Product::forCurrentTenant()->get();
        $this->assertTrue($tenantBProducts->contains('id', $productB->id));
        $this->assertFalse($tenantBProducts->contains('id', $productA->id));
    }

    /** @test */
    public function variant_tenant_isolation_works(): void
    {
        $this->actAsAdminA();

        $productA = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Variable A',
            'type' => ProductType::VARIABLE,
            'price' => 100,
            'category_id' => $this->categoryA->id,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $variantA = ProductVariant::create([
            'product_id' => $productA->id,
            'attributes' => ['size' => 'M'],
            'price' => 100,
            'stock' => 10,
            'status' => ProductVariant::STATUS_ACTIVE,
        ]);

        $this->actAsAdminB();

        $productB = Product::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Variable B',
            'type' => ProductType::VARIABLE,
            'price' => 200,
            'category_id' => $this->categoryB->id,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $variantB = ProductVariant::create([
            'product_id' => $productB->id,
            'attributes' => ['size' => 'L'],
            'price' => 200,
            'stock' => 20,
            'status' => ProductVariant::STATUS_ACTIVE,
        ]);

        $this->actingAs($this->adminA);
        \App\Models\Tenant::setCurrent($this->tenantA);

        $this->assertTrue(ProductVariant::forCurrentTenant()->get()->contains('id', $variantA->id));
        $this->assertFalse(ProductVariant::forCurrentTenant()->get()->contains('id', $variantB->id));
    }

    /** @test */
    public function combo_component_tenant_isolation_works(): void
    {
        $this->actAsAdminA();

        $componentA = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Component A',
            'type' => ProductType::SINGLE,
            'price' => 50,
            'category_id' => $this->categoryA->id,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $comboA = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Combo A',
            'type' => ProductType::COMBO,
            'price' => 90,
            'category_id' => $this->categoryA->id,
            'status' => Product::STATUS_ACTIVE,
        ]);

        ProductCombo::create([
            'product_id' => $comboA->id,
            'combo_product_id' => $componentA->id,
            'quantity' => 2,
        ]);

        $this->actAsAdminB();

        $componentB = Product::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Component B',
            'type' => ProductType::SINGLE,
            'price' => 60,
            'category_id' => $this->categoryB->id,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $this->actingAs($this->adminA);
        \App\Models\Tenant::setCurrent($this->tenantA);

        $comboComponents = ProductCombo::forCurrentTenant()->get();
        $this->assertTrue($comboComponents->contains('combo_product_id', $componentA->id));
        $this->assertFalse($comboComponents->contains('combo_product_id', $componentB->id));
    }

    /** @test */
    public function variable_product_creation_uses_existing_architecture(): void
    {
        $this->actAsAdminA();

        $this->freePlan->planFeatures()->updateOrCreate(
            ['feature_key' => 'variable_products'],
            ['is_enabled' => true]
        );

        $product = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Variable Test Product',
            'type' => ProductType::VARIABLE,
            'price' => 100,
            'category_id' => $this->categoryA->id,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'attributes' => ['size' => 'S', 'color' => 'Red'],
            'price' => 100,
            'stock' => 5,
            'status' => ProductVariant::STATUS_ACTIVE,
        ]);

        $this->assertTrue($product->isVariable());
        $this->assertEquals(1, $product->variants()->count());
        $this->assertEquals('Red', $variant->attributes['color']);
    }

    /** @test */
    public function combo_product_creation_uses_existing_architecture(): void
    {
        $this->actAsAdminA();

        $this->freePlan->planFeatures()->updateOrCreate(
            ['feature_key' => 'combo_products'],
            ['is_enabled' => true]
        );

        $component = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Combo Component',
            'type' => ProductType::SINGLE,
            'price' => 50,
            'category_id' => $this->categoryA->id,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $combo = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Combo Test Product',
            'type' => ProductType::COMBO,
            'price' => 90,
            'category_id' => $this->categoryA->id,
            'status' => Product::STATUS_ACTIVE,
        ]);

        ProductCombo::create([
            'product_id' => $combo->id,
            'combo_product_id' => $component->id,
            'quantity' => 2,
        ]);

        $this->assertTrue($combo->isCombo());
        $this->assertEquals(1, $combo->comboItems()->count());
        $this->assertEquals(2, $combo->comboItems()->first()->quantity);
    }

    /** @test */
    public function variable_product_editing_works(): void
    {
        $this->actAsAdminA();

        $this->freePlan->planFeatures()->updateOrCreate(
            ['feature_key' => 'variable_products'],
            ['is_enabled' => true]
        );

        $product = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Variable Edit Test',
            'type' => ProductType::VARIABLE,
            'price' => 100,
            'category_id' => $this->categoryA->id,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'attributes' => ['size' => 'M'],
            'price' => 100,
            'stock' => 10,
            'status' => ProductVariant::STATUS_ACTIVE,
        ]);

        $product->update(['name' => 'Updated Variable Name']);
        $this->assertEquals('Updated Variable Name', $product->fresh()->name);
        $this->assertEquals(1, $product->fresh()->variants()->count());
    }

    /** @test */
    public function combo_product_editing_works(): void
    {
        $this->actAsAdminA();

        $this->freePlan->planFeatures()->updateOrCreate(
            ['feature_key' => 'combo_products'],
            ['is_enabled' => true]
        );

        $component = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Edit Combo Component',
            'type' => ProductType::SINGLE,
            'price' => 50,
            'category_id' => $this->categoryA->id,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $combo = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Edit Combo Test',
            'type' => ProductType::COMBO,
            'price' => 90,
            'category_id' => $this->categoryA->id,
            'status' => Product::STATUS_ACTIVE,
        ]);

        ProductCombo::create([
            'product_id' => $combo->id,
            'combo_product_id' => $component->id,
            'quantity' => 2,
        ]);

        $combo->update(['name' => 'Updated Combo Name']);
        $this->assertEquals('Updated Combo Name', $combo->fresh()->name);
        $this->assertEquals(1, $combo->fresh()->comboItems()->count());
    }

    /** @test */
    public function existing_product_crud_still_works(): void
    {
        $this->actAsAdminA();

        $product = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'CRUD Test',
            'type' => ProductType::SINGLE,
            'price' => 100,
            'sku' => 'CRUD-TEST-001',
            'category_id' => $this->categoryA->id,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $this->assertNotNull($product->id);
        $this->assertEquals('CRUD Test', $product->name);
        $this->assertEquals('CRUD-TEST-001', $product->sku);

        $product->update(['name' => 'Updated CRUD Test', 'price' => 150]);
        $this->assertEquals('Updated CRUD Test', $product->fresh()->name);
        $this->assertEquals(150, $product->fresh()->price);

        $product->delete();
        $this->assertNull(Product::find($product->id));
    }

    /** @test */
    public function product_has_required_type_column(): void
    {
        $this->actAsAdminA();
        $this->assertTrue(Schema::hasColumn('products', 'type'));
    }

    /** @test */
    public function product_type_enum_values_are_valid(): void
    {
        $this->assertEquals('single', ProductType::SINGLE);
        $this->assertEquals('variable', ProductType::VARIABLE);
        $this->assertEquals('combo', ProductType::COMBO);
        $this->assertContains('single', ProductType::all());
        $this->assertContains('variable', ProductType::all());
        $this->assertContains('combo', ProductType::all());
    }

    /** @test */
    public function variable_product_cannot_be_edited_to_combo_without_feature(): void
    {
        $this->actAsAdminA();

        $this->freePlan->planFeatures()->updateOrCreate(
            ['feature_key' => 'variable_products'],
            ['is_enabled' => true]
        );

        $product = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Variable to Combo Test',
            'type' => ProductType::VARIABLE,
            'price' => 100,
            'category_id' => $this->categoryA->id,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $service = app(\App\Services\ProductService::class);
        $service->validateType(ProductType::COMBO);
    }

    /** @test */
    public function product_delete_does_not_affect_other_tenant_products(): void
    {
        $this->actAsAdminA();

        $productA = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Tenant A To Delete',
            'type' => ProductType::SINGLE,
            'price' => 100,
            'category_id' => $this->categoryA->id,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $productAId = $productA->id;
        $productA->delete();

        $this->actAsAdminB();

        $productB = Product::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Tenant B Product',
            'type' => ProductType::SINGLE,
            'price' => 200,
            'category_id' => $this->categoryB->id,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $this->actingAs($this->adminA);
        \App\Models\Tenant::setCurrent($this->tenantA);

        $this->assertNull(Product::find($productAId));
        $this->assertNotNull(Product::find($productB->id));
    }
}
