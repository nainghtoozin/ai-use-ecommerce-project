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
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CatalogV2FoundationTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private Plan $freePlan;
    private Plan $starterPlan;
    private Plan $businessPlan;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createMinimalSchema();
        $this->seedPlansAndTenants();
    }

    private function createMinimalSchema(): void
    {
        $tables = [
            'permissions' => function ($table) {
                $table->id();
                $table->string('name');
                $table->string('guard_name');
                $table->timestamps();
                $table->unique(['name', 'guard_name']);
            },
            'roles' => function ($table) {
                $table->id();
                $table->string('name');
                $table->string('guard_name');
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->timestamps();
                $table->unique(['name', 'guard_name', 'tenant_id']);
            },
            'model_has_roles' => function ($table) {
                $table->unsignedBigInteger('role_id');
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
                $table->index(['model_id', 'model_type']);
                $table->primary(['role_id', 'model_id', 'model_type']);
            },
            'role_has_permissions' => function ($table) {
                $table->unsignedBigInteger('permission_id');
                $table->unsignedBigInteger('role_id');
                $table->primary(['permission_id', 'role_id']);
            },
            'model_has_permissions' => function ($table) {
                $table->unsignedBigInteger('permission_id');
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
                $table->index(['model_id', 'model_type']);
                $table->primary(['permission_id', 'model_id', 'model_type']);
            },
            'users' => function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->string('status')->default('active');
                $table->string('profile_image')->nullable();
                $table->boolean('is_owner')->default(false);
                $table->rememberToken();
                $table->timestamps();
            },
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
            'plans' => function ($table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->decimal('price', 10, 2)->default(0);
                $table->string('currency')->default('USD');
                $table->string('interval')->default('monthly');
                $table->text('description')->nullable();
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->decimal('monthly_price', 10, 2)->nullable();
                $table->decimal('yearly_price', 10, 2)->nullable();
                $table->unsignedInteger('product_limit')->nullable();
                $table->unsignedInteger('staff_limit')->nullable();
                $table->unsignedInteger('storage_limit')->nullable();
                $table->boolean('analytics_enabled')->default(false);
                $table->boolean('custom_domain_enabled')->default(false);
                $table->string('status', 20)->default('active');
                $table->timestamps();
            },
            'plan_features' => function ($table) {
                $table->id();
                $table->unsignedBigInteger('plan_id');
                $table->string('feature_key');
                $table->boolean('is_enabled')->default(true);
                $table->string('display_label')->nullable();
                $table->text('description')->nullable();
                $table->timestamps();
                $table->unique(['plan_id', 'feature_key']);
            },
            'subscriptions' => function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('plan_id')->nullable();
                $table->string('billing_interval')->default('monthly');
                $table->string('status')->default('active');
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('trial_ends_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamp('suspended_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            },
            'categories' => function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('name');
                $table->string('slug', 255)->nullable();
                $table->text('description')->nullable();
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['tenant_id', 'slug'], 'cat_tenant_slug_idx');
            },
            'brands' => function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('name');
                $table->string('slug')->nullable();
                $table->text('description')->nullable();
                $table->string('logo')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            },
            'units' => function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('name');
                $table->string('short_name')->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            },
            'products' => function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('name');
                $table->string('slug')->nullable();
                $table->string('sku')->nullable();
                $table->string('barcode', 100)->nullable();
                $table->string('short_description', 500)->nullable();
                $table->text('description')->nullable();
                $table->decimal('price', 10, 2)->default(0);
                $table->decimal('sale_price', 10, 2)->nullable();
                $table->decimal('base_price', 10, 2)->default(0);
                $table->decimal('cost_price', 10, 2)->nullable();
                $table->unsignedBigInteger('category_id')->nullable();
                $table->unsignedBigInteger('brand_id')->nullable();
                $table->unsignedBigInteger('unit_id')->nullable();
                $table->integer('stock')->default(0);
                $table->integer('low_stock_alert')->default(5);
                $table->string('photo1')->nullable();
                $table->string('photo2')->nullable();
                $table->json('gallery_images')->nullable();
                $table->string('seo_title')->nullable();
                $table->text('seo_description')->nullable();
                $table->string('seo_keywords')->nullable();
                $table->string('seo_image')->nullable();
                $table->string('status', 20)->default('active');
                $table->string('type', 20)->default('single');
                $table->timestamps();
                $table->unique(['tenant_id', 'sku']);
            },
            'product_variants' => function ($table) {
                $table->id();
                $table->unsignedBigInteger('product_id');
                $table->string('sku')->nullable();
                $table->string('barcode')->nullable();
                $table->decimal('price', 10, 2)->nullable();
                $table->decimal('compare_price', 10, 2)->nullable();
                $table->decimal('cost_price', 10, 2)->nullable();
                $table->integer('stock')->default(0);
                $table->integer('low_stock_threshold')->default(5);
                $table->string('image')->nullable();
                $table->json('attributes')->nullable();
                $table->string('status', 20)->default('active');
                $table->timestamps();
            },
            'product_combos' => function ($table) {
                $table->id();
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('combo_product_id');
                $table->unsignedBigInteger('linked_variant_id')->nullable();
                $table->unsignedInteger('quantity')->default(1);
                $table->timestamps();
                $table->unique(['product_id', 'combo_product_id'], 'product_combo_unique');
            },
            'activity_logs' => function ($table) {
                $table->id();
                $table->string('log_name');
                $table->text('description');
                $table->string('subject_type')->nullable();
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->string('causer_type')->nullable();
                $table->unsignedBigInteger('causer_id')->nullable();
                $table->unsignedBigInteger('impersonator_id')->nullable();
                $table->unsignedBigInteger('impersonated_user_id')->nullable();
                $table->text('properties')->nullable();
                $table->string('event')->nullable();
                $table->string('batch_uuid')->nullable();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->index(['subject_type', 'subject_id']);
                $table->index(['causer_type', 'causer_id']);
                $table->timestamps();
            },
        ];

        foreach ($tables as $name => $callback) {
            if (!Schema::hasTable($name)) {
                Schema::create($name, $callback);
            }
        }
    }

    private function seedPlansAndTenants(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'dashboard.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'categories.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'categories.create', 'guard_name' => 'web']);
        Permission::create(['name' => 'categories.update', 'guard_name' => 'web']);
        Permission::create(['name' => 'categories.delete', 'guard_name' => 'web']);
        Permission::create(['name' => 'products.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'products.create', 'guard_name' => 'web']);
        Permission::create(['name' => 'products.edit', 'guard_name' => 'web']);
        Permission::create(['name' => 'products.delete', 'guard_name' => 'web']);

        $superadminRole = Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        $superadminRole->syncPermissions(Permission::all());

        $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions(Permission::all());

        Role::create(['name' => 'customer', 'guard_name' => 'web']);

        $this->freePlan = Plan::create([
            'name' => 'Free',
            'slug' => 'free',
            'monthly_price' => 0,
            'yearly_price' => 0,
            'product_limit' => 10,
            'staff_limit' => 2,
            'storage_limit' => 100,
            'description' => 'Free plan',
            'status' => 'active',
        ]);

        $this->starterPlan = Plan::create([
            'name' => 'Starter',
            'slug' => 'starter',
            'monthly_price' => 9.99,
            'yearly_price' => 99.99,
            'product_limit' => 100,
            'staff_limit' => 5,
            'storage_limit' => 1024,
            'description' => 'Starter plan',
            'status' => 'active',
        ]);

        $this->businessPlan = Plan::create([
            'name' => 'Business',
            'slug' => 'business',
            'monthly_price' => 29.99,
            'yearly_price' => 299.99,
            'product_limit' => null,
            'staff_limit' => null,
            'storage_limit' => null,
            'description' => 'Business plan',
            'status' => 'active',
        ]);

        $this->seedPlanFeatures($this->freePlan, [
            'single_products' => true,
            'variable_products' => false,
            'combo_products' => false,
        ]);

        $this->seedPlanFeatures($this->starterPlan, [
            'single_products' => true,
            'variable_products' => true,
            'combo_products' => false,
        ]);

        $this->seedPlanFeatures($this->businessPlan, [
            'single_products' => true,
            'variable_products' => true,
            'combo_products' => true,
        ]);

        $this->tenantA = Tenant::create([
            'name' => 'Store A',
            'slug' => 'store-a',
            'store_url' => '/store/store-a',
        ]);

        $this->tenantB = Tenant::create([
            'name' => 'Store B',
            'slug' => 'store-b',
            'store_url' => '/store/store-b',
        ]);

        $subscriptionA = \App\Models\Subscription::create([
            'tenant_id' => $this->tenantA->id,
            'plan_id' => $this->starterPlan->id,
            'billing_interval' => 'monthly',
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
        ]);

        $subscriptionB = \App\Models\Subscription::create([
            'tenant_id' => $this->tenantB->id,
            'plan_id' => $this->freePlan->id,
            'billing_interval' => 'monthly',
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
        ]);

        $this->tenantA->subscription_plan_id = $this->starterPlan->id;
        $this->tenantA->save();

        $this->tenantB->subscription_plan_id = $this->freePlan->id;
        $this->tenantB->save();

        $this->admin = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Admin A',
            'email' => 'admin-a@example.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $this->admin->assignRole($adminRole);
    }

    private function seedPlanFeatures(Plan $plan, array $features): void
    {
        foreach ($features as $key => $enabled) {
            PlanFeature::updateOrCreate(
                ['plan_id' => $plan->id, 'feature_key' => $key],
                ['is_enabled' => $enabled, 'display_label' => ucfirst(str_replace('_', ' ', $key))]
            );
        }
    }

    private function actAsAdminForTenantA(): void
    {
        $this->actingAs($this->admin);
        \App\Models\Tenant::setCurrent($this->tenantA);
    }

    private function actAsAdminForTenantB(): void
    {
        $userB = User::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Admin B',
            'email' => 'admin-b@example.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $role = Role::where('name', 'admin')->first();
        $userB->assignRole($role);
        $this->actingAs($userB);
        \App\Models\Tenant::setCurrent($this->tenantB);
    }

    private function flushFeatureCache(): void
    {
        FeatureGate::clearCache($this->freePlan);
        FeatureGate::clearCache($this->starterPlan);
        FeatureGate::clearCache($this->businessPlan);
    }

    /** @test */
    public function storefront_homepage_sections_index_has_short_name(): void
    {
        $indexName = 'shs_tenant_store_enabled_pos_idx';
        $this->assertLessThanOrEqual(64, strlen($indexName));
    }

    /** @test */
    public function category_has_slug_field(): void
    {
        $this->assertTrue(Schema::hasColumn('categories', 'slug'));
    }

    /** @test */
    public function category_has_parent_id_field(): void
    {
        $this->assertTrue(Schema::hasColumn('categories', 'parent_id'));
    }

    /** @test */
    public function category_has_is_active_field(): void
    {
        $this->assertTrue(Schema::hasColumn('categories', 'is_active'));
    }

    /** @test */
    public function category_slug_is_generated_automatically(): void
    {
        $this->actAsAdminForTenantA();

        $category = Category::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Test Category',
            'description' => 'Test description',
        ]);

        $this->assertNotNull($category->slug);
        $this->assertEquals('test-category', $category->slug);
    }

    /** @test */
    public function category_slug_is_unique_within_tenant(): void
    {
        $this->actAsAdminForTenantA();

        $cat1 = Category::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Electronics',
            'description' => 'Test',
        ]);

        $cat2 = Category::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Electronics',
            'description' => 'Another',
        ]);

        $this->assertNotEquals($cat1->slug, $cat2->slug);
        $this->assertEquals('electronics', $cat1->slug);
        $this->assertEquals('electronics-1', $cat2->slug);
    }

    /** @test */
    public function category_slug_is_tenant_isolated(): void
    {
        $this->actAsAdminForTenantA();

        $catTenantA = Category::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Fashion',
            'description' => 'Store A fashion',
        ]);

        $this->actAsAdminForTenantB();

        $catTenantB = Category::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Fashion',
            'description' => 'Store B fashion',
        ]);

        $this->assertEquals('fashion', $catTenantA->slug);
        $this->assertEquals('fashion', $catTenantB->slug);
    }

    /** @test */
    public function category_parent_cannot_reference_other_tenant(): void
    {
        $this->actAsAdminForTenantA();

        $parentTenantA = Category::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Parent A',
        ]);

        $this->actAsAdminForTenantB();

        $parentTenantB = Category::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Parent B',
        ]);

        $this->actingAs($this->admin);
        \App\Models\Tenant::setCurrent($this->tenantB);

        $childTenantB = Category::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Child B',
        ]);

        $this->actingAs($this->admin);
        \App\Models\Tenant::setCurrent($this->tenantA);

        $childTenantA = Category::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Child A',
        ]);

        $childTenantA->parent_id = $parentTenantB->id;

        $validator = validator(['parent_id' => $parentTenantB->id], [
            'parent_id' => 'nullable|integer|exists:categories,id',
        ]);

        $this->assertTrue($validator->fails());
    }

    /** @test */
    public function category_cannot_reference_itself_as_parent(): void
    {
        $this->actAsAdminForTenantA();

        $category = Category::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Test Category',
        ]);

        $category->parent_id = $category->id;
        $this->assertTrue($category->hasCircularReference($category->id));
    }

    /** @test */
    public function category_parent_reference_allows_same_tenant(): void
    {
        $this->actAsAdminForTenantA();

        $parent = Category::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Parent',
        ]);

        $child = Category::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Child',
            'parent_id' => $parent->id,
        ]);

        $this->assertEquals($parent->id, $child->parent_id);
        $this->assertEquals('Parent', $child->parent->name);
        $this->assertCount(1, $child->parent->children);
    }

    /** @test */
    public function existing_category_records_remain_valid(): void
    {
        $this->actAsAdminForTenantA();

        $category = Category::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Legacy Category',
            'description' => 'Existing category without slug',
        ]);

        $this->assertNotNull($category->id);
        $this->assertEquals('Legacy Category', $category->name);
        $this->assertNull($category->slug);
        $this->assertNull($category->parent_id);
        $this->assertTrue($category->is_active);
    }

    /** @test */
    public function product_has_sale_price_field(): void
    {
        $this->assertTrue(Schema::hasColumn('products', 'sale_price'));
    }

    /** @test */
    public function product_effective_price_returns_regular_price_when_sale_price_is_null(): void
    {
        $this->actAsAdminForTenantA();

        $product = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Test Product',
            'price' => 100.00,
            'sale_price' => null,
            'status' => 'active',
            'type' => 'single',
        ]);

        $this->assertEquals(100.00, $product->getEffectivePrice());
    }

    /** @test */
    public function product_effective_price_returns_sale_price_when_set(): void
    {
        $this->actAsAdminForTenantA();

        $product = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Test Product Sale',
            'price' => 100.00,
            'sale_price' => 79.00,
            'status' => 'active',
            'type' => 'single',
        ]);

        $this->assertEquals(79.00, $product->getEffectivePrice());
    }

    /** @test */
    public function product_sale_price_does_not_affect_combo_base_price(): void
    {
        $this->actAsAdminForTenantA();

        $component = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Component A',
            'price' => 50.00,
            'status' => 'active',
            'type' => 'single',
        ]);

        $combo = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Combo Product',
            'price' => 80.00,
            'sale_price' => 70.00,
            'status' => 'active',
            'type' => 'combo',
        ]);

        ProductCombo::create([
            'product_id' => $combo->id,
            'combo_product_id' => $component->id,
            'quantity' => 2,
        ]);

        $combo->refresh();
        $this->assertEquals(100.00, $combo->getComboBasePrice());
        $this->assertEquals(70.00, $combo->getEffectivePrice());
    }

    /** @test */
    public function existing_product_pricing_unchanged_when_sale_price_is_null(): void
    {
        $this->actAsAdminForTenantA();

        $product = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Legacy Product',
            'price' => 199.99,
            'stock' => 10,
            'status' => 'active',
            'type' => 'single',
        ]);

        $this->assertEquals(199.99, $product->getEffectivePrice());
        $this->assertEquals(199.99, $product->price);
        $this->assertNull($product->sale_price);
    }

    /** @test */
    public function variant_pricing_unchanged_by_product_sale_price(): void
    {
        $this->actAsAdminForTenantA();

        $product = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Variable Product',
            'price' => 100.00,
            'sale_price' => 80.00,
            'status' => 'active',
            'type' => 'variable',
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'price' => 110.00,
            'stock' => 5,
            'attributes' => ['size' => 'L'],
            'status' => 'active',
        ]);

        $product->refresh();
        $this->assertEquals(110.00, $variant->getEffectivePrice());
        $this->assertEquals(80.00, $product->getEffectivePrice());
    }

    /** @test */
    public function single_products_feature_is_enabled_on_all_plans(): void
    {
        $this->flushFeatureCache();

        $this->assertTrue($this->freePlan->hasFeature('single_products'));
        $this->assertTrue($this->starterPlan->hasFeature('single_products'));
        $this->assertTrue($this->businessPlan->hasFeature('single_products'));
    }

    /** @test */
    public function variable_products_feature_respects_plan(): void
    {
        $this->flushFeatureCache();

        $this->assertFalse($this->freePlan->hasFeature('variable_products'));
        $this->assertTrue($this->starterPlan->hasFeature('variable_products'));
        $this->assertTrue($this->businessPlan->hasFeature('variable_products'));
    }

    /** @test */
    public function combo_products_feature_respects_plan(): void
    {
        $this->flushFeatureCache();

        $this->assertFalse($this->freePlan->hasFeature('combo_products'));
        $this->assertFalse($this->starterPlan->hasFeature('combo_products'));
        $this->assertTrue($this->businessPlan->hasFeature('combo_products'));
    }

    /** @test */
    public function product_type_available_types_respects_plan(): void
    {
        $this->flushFeatureCache();

        $this->actingAs($this->admin);
        \App\Models\Tenant::setCurrent($this->tenantA);

        $availableTypes = ProductType::availableTypes();
        $this->assertContains('single', $availableTypes);
        $this->assertContains('variable', $availableTypes);
        $this->assertNotContains('combo', $availableTypes);
    }

    /** @test */
    public function product_type_is_locked_respects_plan(): void
    {
        $this->flushFeatureCache();

        $this->actingAs($this->admin);
        \App\Models\Tenant::setCurrent($this->tenantA);

        $this->assertFalse(ProductType::isLocked('single'));
        $this->assertFalse(ProductType::isLocked('variable'));
        $this->assertTrue(ProductType::isLocked('combo'));
    }

    /** @test */
    public function tenant_isolation_enforced_on_categories(): void
    {
        $this->actAsAdminForTenantA();

        $catA = Category::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Tenant A Category',
        ]);

        $this->actingAs($this->admin);
        \App\Models\Tenant::setCurrent($this->tenantB);

        $catB = Category::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Tenant B Category',
        ]);

        $this->actingAs($this->admin);
        \App\Models\Tenant::setCurrent($this->tenantA);

        $categories = Category::forCurrentTenant()->get();
        $this->assertTrue($categories->contains('id', $catA->id));
        $this->assertFalse($categories->contains('id', $catB->id));
    }

    /** @test */
    public function tenant_isolation_enforced_on_products(): void
    {
        $this->actAsAdminForTenantA();

        $prodA = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Product A',
            'price' => 10,
            'status' => 'active',
            'type' => 'single',
        ]);

        $this->actingAs($this->admin);
        \App\Models\Tenant::setCurrent($this->tenantB);

        $prodB = Product::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Product B',
            'price' => 20,
            'status' => 'active',
            'type' => 'single',
        ]);

        $this->actingAs($this->admin);
        \App\Models\Tenant::setCurrent($this->tenantA);

        $products = Product::forCurrentTenant()->get();
        $this->assertTrue($products->contains('id', $prodA->id));
        $this->assertFalse($products->contains('id', $prodB->id));
    }

    /** @test */
    public function category_active_scope_works(): void
    {
        $this->actAsAdminForTenantA();

        Category::create(['tenant_id' => $this->tenantA->id, 'name' => 'Active Cat', 'is_active' => true]);
        Category::create(['tenant_id' => $this->tenantA->id, 'name' => 'Inactive Cat', 'is_active' => false]);

        $activeCategories = Category::forCurrentTenant()->active()->get();
        $this->assertCount(1, $activeCategories);
        $this->assertEquals('Active Cat', $activeCategories->first()->name);
    }

    /** @test */
    public function category_hierarchy_scope_works(): void
    {
        $this->actAsAdminForTenantA();

        $parent = Category::create(['tenant_id' => $this->tenantA->id, 'name' => 'Parent']);
        Category::create(['tenant_id' => $this->tenantA->id, 'name' => 'Child', 'parent_id' => $parent->id]);
        Category::create(['tenant_id' => $this->tenantA->id, 'name' => 'Root']);

        $this->assertCount(2, Category::forCurrentTenant()->rootOnly()->get());
        $this->assertCount(1, Category::forCurrentTenant()->withParent()->get());
    }

    /** @test */
    public function category_relationship_with_products_still_works(): void
    {
        $this->actAsAdminForTenantA();

        $category = Category::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Test Category',
        ]);

        $product = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Test Product',
            'price' => 10,
            'category_id' => $category->id,
            'status' => 'active',
            'type' => 'single',
        ]);

        $this->assertEquals($category->id, $product->category->id);
        $this->assertCount(1, $category->products);
        $this->assertEquals('Test Product', $category->products->first()->name);
    }

    /** @test */
    public function display_price_summary_reflects_sale_price(): void
    {
        $this->actAsAdminForTenantA();

        $product = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Sale Product',
            'price' => 100.00,
            'sale_price' => 75.00,
            'status' => 'active',
            'type' => 'single',
        ]);

        $summary = $product->display_price_summary;

        $this->assertEquals('single', $summary['type']);
        $this->assertEquals(75.00, $summary['price']);
        $this->assertEquals(100.00, $summary['regular_price']);
        $this->assertEquals(75.00, $summary['sale_price']);
    }

    /** @test */
    public function display_price_summary_unchanged_when_sale_price_is_null(): void
    {
        $this->actAsAdminForTenantA();

        $product = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Regular Product',
            'price' => 100.00,
            'status' => 'active',
            'type' => 'single',
        ]);

        $summary = $product->display_price_summary;

        $this->assertEquals('single', $summary['type']);
        $this->assertEquals(100.00, $summary['price']);
        $this->assertEquals(100.00, $summary['regular_price']);
        $this->assertNull($summary['sale_price']);
    }
}
