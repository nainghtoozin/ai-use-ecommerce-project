<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SubscriptionLimitService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SubscriptionLimitServiceTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;
    private Plan $freePlan;
    private Plan $starterPlan;
    private Plan $businessPlan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createMinimalSchema();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'dashboard.view', 'guard_name' => 'web']);
        $superadminRole = Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        $superadminRole->syncPermissions(Permission::all());
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
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

        $this->tenant = Tenant::create([
            'name' => 'Test Store',
            'slug' => 'test-store',
            'store_url' => '/store/test-store',
            'status' => 'active',
            'used_storage_bytes' => 0,
        ]);

        \Illuminate\Support\Facades\App::instance('current.tenant', $this->tenant);
    }

    /* ── Product Limit Tests ── */

    public function test_free_plan_blocks_product_creation_at_limit(): void
    {
        $this->createSubscription($this->freePlan);
        $this->createProducts(10);

        $service = SubscriptionLimitService::for($this->tenant, $this->freePlan);

        $this->assertFalse($service->canCreateProduct());
        $this->assertEquals(0, $service->productRemaining());
    }

    public function test_free_plan_allows_product_creation_under_limit(): void
    {
        $this->createSubscription($this->freePlan);
        $this->createProducts(5);

        $service = SubscriptionLimitService::for($this->tenant, $this->freePlan);

        $this->assertTrue($service->canCreateProduct());
        $this->assertEquals(5, $service->productRemaining());
    }

    public function test_free_plan_allows_product_creation_when_empty(): void
    {
        $this->createSubscription($this->freePlan);

        $service = SubscriptionLimitService::for($this->tenant, $this->freePlan);

        $this->assertTrue($service->canCreateProduct());
        $this->assertEquals(10, $service->productRemaining());
    }

    public function test_business_plan_allows_unlimited_products(): void
    {
        $this->createSubscription($this->businessPlan);
        $this->createProducts(999);

        $service = SubscriptionLimitService::for($this->tenant, $this->businessPlan);

        $this->assertTrue($service->canCreateProduct());
        $this->assertEquals(PHP_INT_MAX, $service->productRemaining());
    }

    public function test_starter_plan_allows_creation_under_limit(): void
    {
        $this->createSubscription($this->starterPlan);
        $this->createProducts(50);

        $service = SubscriptionLimitService::for($this->tenant, $this->starterPlan);

        $this->assertTrue($service->canCreateProduct());
        $this->assertEquals(50, $service->productRemaining());
    }

    public function test_starter_plan_blocks_at_limit(): void
    {
        $this->createSubscription($this->starterPlan);
        $this->createProducts(100);

        $service = SubscriptionLimitService::for($this->tenant, $this->starterPlan);

        $this->assertFalse($service->canCreateProduct());
        $this->assertEquals(0, $service->productRemaining());
    }

    /* ── Staff Limit Tests ── */

    public function test_free_plan_blocks_staff_creation_at_limit(): void
    {
        $this->createSubscription($this->freePlan);
        $this->createStaff(2);

        $service = SubscriptionLimitService::for($this->tenant, $this->freePlan);

        $this->assertFalse($service->canCreateStaff());
        $this->assertEquals(0, $service->staffRemaining());
    }

    public function test_free_plan_allows_staff_creation_under_limit(): void
    {
        $this->createSubscription($this->freePlan);
        $this->createStaff(1);

        $service = SubscriptionLimitService::for($this->tenant, $this->freePlan);

        $this->assertTrue($service->canCreateStaff());
        $this->assertEquals(1, $service->staffRemaining());
    }

    public function test_business_plan_allows_unlimited_staff(): void
    {
        $this->createSubscription($this->businessPlan);

        $service = SubscriptionLimitService::for($this->tenant, $this->businessPlan);

        $this->assertTrue($service->canCreateStaff());
        $this->assertEquals(PHP_INT_MAX, $service->staffRemaining());
    }

    /* ── Storage Limit Tests ── */

    public function test_free_plan_blocks_upload_when_full(): void
    {
        $this->createSubscription($this->freePlan);
        $this->tenant->update(['used_storage_bytes' => 100 * 1024 * 1024]);

        $service = SubscriptionLimitService::for($this->tenant, $this->freePlan);

        $this->assertFalse($service->canUpload(1));
        $this->assertEquals(0, $service->storageRemainingBytes());
    }

    public function test_free_plan_allows_upload_when_under_limit(): void
    {
        $this->createSubscription($this->freePlan);
        $this->tenant->update(['used_storage_bytes' => 50 * 1024 * 1024]);

        $service = SubscriptionLimitService::for($this->tenant, $this->freePlan);

        $this->assertTrue($service->canUpload(10 * 1024 * 1024));
        $this->assertEquals(50 * 1024 * 1024, $service->storageRemainingBytes());
    }

    public function test_free_plan_blocks_upload_exceeding_remaining(): void
    {
        $this->createSubscription($this->freePlan);
        $this->tenant->update(['used_storage_bytes' => 95 * 1024 * 1024]);

        $service = SubscriptionLimitService::for($this->tenant, $this->freePlan);

        $this->assertFalse($service->canUpload(10 * 1024 * 1024));
    }

    public function test_business_plan_allows_unlimited_storage(): void
    {
        $this->createSubscription($this->businessPlan);
        $this->tenant->update(['used_storage_bytes' => 999 * 1024 * 1024 * 1024]);

        $service = SubscriptionLimitService::for($this->tenant, $this->businessPlan);

        $this->assertTrue($service->canUpload(PHP_INT_MAX));
        $this->assertEquals(PHP_INT_MAX, $service->storageRemainingBytes());
    }

    /* ── Assertion Tests ── */

    public function test_assert_can_create_product_throws_at_limit(): void
    {
        $this->createSubscription($this->freePlan);
        $this->createProducts(10);

        $service = SubscriptionLimitService::for($this->tenant, $this->freePlan);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Product limit reached');
        $service->assertCanCreateProduct();
    }

    public function test_assert_can_create_staff_throws_at_limit(): void
    {
        $this->createSubscription($this->freePlan);
        $this->createStaff(2);

        $service = SubscriptionLimitService::for($this->tenant, $this->freePlan);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Staff limit reached');
        $service->assertCanCreateStaff();
    }

    public function test_assert_can_upload_throws_when_full(): void
    {
        $this->createSubscription($this->freePlan);
        $this->tenant->update(['used_storage_bytes' => 100 * 1024 * 1024]);

        $service = SubscriptionLimitService::for($this->tenant, $this->freePlan);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Storage limit reached');
        $service->assertCanUpload(1);
    }

    /* ── Usage Report Tests ── */

    public function test_get_all_usage_returns_expected_structure(): void
    {
        $this->createSubscription($this->freePlan);
        $this->createProducts(3);
        $this->createStaff(1);
        $this->tenant->update(['used_storage_bytes' => 25 * 1024 * 1024]);

        $service = SubscriptionLimitService::for($this->tenant, $this->freePlan);
        $usage = $service->getAllUsage();

        $this->assertArrayHasKey('products', $usage);
        $this->assertArrayHasKey('staff', $usage);
        $this->assertArrayHasKey('storage', $usage);

        $this->assertEquals(3, $usage['products']['current']);
        $this->assertEquals(10, $usage['products']['limit']);
        $this->assertEquals(7, $usage['products']['remaining']);
        $this->assertFalse($usage['products']['is_unlimited']);

        $this->assertEquals(1, $usage['staff']['current']);
        $this->assertEquals(2, $usage['staff']['limit']);
        $this->assertEquals(1, $usage['staff']['remaining']);

        $this->assertEquals('25 MB', $usage['storage']['current']);
        $this->assertEquals(25 * 1024 * 1024, $usage['storage']['current_bytes']);
        $this->assertEquals('100 MB', $usage['storage']['limit']);
        $this->assertEquals(100, $usage['storage']['limit_mb']);
    }

    /* ── Helpers ── */

    private function createSubscription(Plan $plan): Subscription
    {
        return Subscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_interval' => 'monthly',
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
        ]);
    }

    private function createProducts(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $product = new Product();
            $product->tenant_id = $this->tenant->id;
            $product->name = "Product {$i}";
            $product->price = 9.99;
            $product->category_id = 1;
            $product->save();
        }
    }

    private function createStaff(int $count): void
    {
        $adminRole = Role::where('name', 'admin')->whereNull('tenant_id')->first();

        for ($i = 0; $i < $count; $i++) {
            $user = User::create([
                'name' => "Staff {$i}",
                'email' => "staff{$i}@example.com",
                'password' => Hash::make('password'),
                'tenant_id' => $this->tenant->id,
                'status' => 'active',
            ]);
            $user->assignRole($adminRole);
        }
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
            'products' => function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('name');
                $table->decimal('price', 10, 2);
                $table->unsignedBigInteger('category_id')->nullable();
                $table->text('description')->nullable();
                $table->string('status', 20)->default('active');
                $table->string('type', 20)->default('single');
                $table->integer('stock')->default(0);
                $table->timestamps();
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
}
