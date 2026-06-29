<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Tenant;
use App\Services\SubscriptionLimitService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SubscriptionLimitTest extends TestCase
{
    use DatabaseTransactions;

    private Plan $freePlan;
    private Plan $starterPlan;
    private Plan $businessPlan;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createMinimalSchema();

        $this->freePlan = Plan::create([
            'name' => 'Free', 'slug' => 'free', 'description' => 'Free plan',
            'monthly_price' => 0, 'yearly_price' => 0, 'status' => 'active',
            'analytics_enabled' => false, 'custom_domain_enabled' => false,
            'product_limit' => 10, 'staff_limit' => 2, 'storage_limit' => 100,
            'orders_monthly_limit' => 50, 'coupon_limit' => 5,
            'promotion_limit' => 3, 'flash_sale_limit' => 1,
            'api_request_limit' => 1000, 'image_limit' => 5,
            'image_max_size_kb' => 2048, 'branch_limit' => 1,
            'warehouse_limit' => 1, 'pos_device_limit' => 1,
        ]);

        $this->starterPlan = Plan::create([
            'name' => 'Starter', 'slug' => 'starter', 'description' => 'Starter plan',
            'monthly_price' => 29, 'yearly_price' => 290, 'status' => 'active',
            'analytics_enabled' => true, 'custom_domain_enabled' => true,
            'product_limit' => 100, 'staff_limit' => 5, 'storage_limit' => 1024,
            'orders_monthly_limit' => 500, 'coupon_limit' => 20,
            'promotion_limit' => 10, 'flash_sale_limit' => 5,
            'api_request_limit' => 10000, 'image_limit' => 10,
            'image_max_size_kb' => 5120, 'branch_limit' => 3,
            'warehouse_limit' => 2, 'pos_device_limit' => 3,
        ]);

        $this->businessPlan = Plan::create([
            'name' => 'Business', 'slug' => 'business', 'description' => 'Business plan',
            'monthly_price' => 99, 'yearly_price' => 990, 'status' => 'active',
            'analytics_enabled' => true, 'custom_domain_enabled' => true,
            'product_limit' => null, 'staff_limit' => null, 'storage_limit' => null,
            'orders_monthly_limit' => null, 'coupon_limit' => null,
            'promotion_limit' => null, 'flash_sale_limit' => null,
            'api_request_limit' => null, 'image_limit' => null,
            'image_max_size_kb' => 10240, 'branch_limit' => null,
            'warehouse_limit' => null, 'pos_device_limit' => null,
        ]);

        $this->tenant = Tenant::create([
            'slug' => 'test-tenant',
            'name' => 'Test Tenant',
            'status' => 'active',
            'plan_id' => $this->freePlan->id,
        ]);
    }

    private function createMinimalSchema(): void
    {
        Schema::create('tenants', function ($table) {
            $table->id(); $table->string('slug')->unique();
            $table->string('name'); $table->string('email')->nullable();
            $table->string('status')->default('active');
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->json('settings')->nullable();
            $table->string('logo')->nullable(); $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
        });
        Schema::create('plans', function ($table) {
            $table->id(); $table->string('name'); $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('monthly_price', 10, 2)->nullable();
            $table->decimal('yearly_price', 10, 2)->nullable();
            $table->integer('product_limit')->nullable();
            $table->integer('staff_limit')->nullable();
            $table->bigInteger('storage_limit')->nullable();
            $table->integer('orders_monthly_limit')->nullable();
            $table->integer('coupon_limit')->nullable();
            $table->integer('promotion_limit')->nullable();
            $table->integer('flash_sale_limit')->nullable();
            $table->integer('api_request_limit')->nullable();
            $table->integer('image_limit')->nullable();
            $table->integer('image_max_size_kb')->nullable();
            $table->integer('branch_limit')->nullable();
            $table->integer('warehouse_limit')->nullable();
            $table->integer('pos_device_limit')->nullable();
            $table->boolean('analytics_enabled')->default(false);
            $table->boolean('custom_domain_enabled')->default(false);
            $table->string('price')->nullable();
            $table->string('currency')->nullable();
            $table->string('interval')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });
    }

    private function service(?Plan $plan = null): SubscriptionLimitService
    {
        return SubscriptionLimitService::for(null, $plan ?? $this->freePlan);
    }

    // --- Maximum ---

    public function test_free_plan_finite_limits(): void
    {
        $svc = $this->service($this->freePlan);
        $this->assertSame(10, $svc->maximum('product_limit'));
        $this->assertSame(2, $svc->maximum('staff_limit'));
        $this->assertSame(100, $svc->maximum('storage_limit'));
        $this->assertSame(50, $svc->maximum('orders_monthly_limit'));
        $this->assertSame(5, $svc->maximum('coupon_limit'));
        $this->assertSame(3, $svc->maximum('promotion_limit'));
        $this->assertSame(1, $svc->maximum('flash_sale_limit'));
        $this->assertSame(1000, $svc->maximum('api_request_limit'));
        $this->assertSame(5, $svc->maximum('image_limit'));
        $this->assertSame(2048, $svc->maximum('image_max_size_kb'));
        $this->assertSame(1, $svc->maximum('branch_limit'));
        $this->assertSame(1, $svc->maximum('warehouse_limit'));
        $this->assertSame(1, $svc->maximum('pos_device_limit'));
    }

    public function test_starter_plan_finite_limits(): void
    {
        $svc = $this->service($this->starterPlan);
        $this->assertSame(100, $svc->maximum('product_limit'));
        $this->assertSame(500, $svc->maximum('orders_monthly_limit'));
        $this->assertSame(20, $svc->maximum('coupon_limit'));
    }

    public function test_business_plan_unlimited_limits(): void
    {
        $svc = $this->service($this->businessPlan);
        $this->assertNull($svc->maximum('product_limit'));
        $this->assertNull($svc->maximum('staff_limit'));
        $this->assertNull($svc->maximum('storage_limit'));
        $this->assertNull($svc->maximum('orders_monthly_limit'));
        $this->assertNull($svc->maximum('coupon_limit'));
        $this->assertNull($svc->maximum('promotion_limit'));
        $this->assertNull($svc->maximum('flash_sale_limit'));
        $this->assertNull($svc->maximum('api_request_limit'));
        $this->assertNull($svc->maximum('image_limit'));
        $this->assertSame(10240, $svc->maximum('image_max_size_kb'));
        $this->assertNull($svc->maximum('branch_limit'));
        $this->assertNull($svc->maximum('warehouse_limit'));
        $this->assertNull($svc->maximum('pos_device_limit'));
    }

    // --- checkLimit ---

    public function test_check_limit_returns_true_for_unlimited(): void
    {
        $svc = $this->service($this->businessPlan);
        $this->assertTrue($svc->checkLimit('product_limit'));
        $this->assertTrue($svc->checkLimit('coupon_limit'));
        $this->assertTrue($svc->checkLimit('promotion_limit'));
    }

    public function test_check_limit_returns_true_when_under_limit(): void
    {
        $svc = $this->service($this->freePlan);
        $this->assertTrue($svc->checkLimit('coupon_limit'));
        $this->assertTrue($svc->checkLimit('promotion_limit'));
        $this->assertTrue($svc->checkLimit('flash_sale_limit'));
    }

    // --- remaining ---

    public function test_remaining_returns_max_for_unlimited(): void
    {
        $svc = $this->service($this->businessPlan);
        $this->assertSame(PHP_INT_MAX, $svc->remaining('product_limit'));
        $this->assertSame(PHP_INT_MAX, $svc->remaining('coupon_limit'));
    }

    public function test_remaining_returns_finite_value(): void
    {
        $svc = $this->service($this->freePlan);
        $this->assertSame(10, $svc->remaining('product_limit'));
        $this->assertSame(5, $svc->remaining('coupon_limit'));
    }

    // --- currentUsage ---

    public function test_current_usage_returns_zero_when_no_records(): void
    {
        $svc = $this->service($this->freePlan);
        $this->assertSame(0, $svc->currentUsage('coupon_limit'));
        $this->assertSame(0, $svc->currentUsage('promotion_limit'));
        $this->assertSame(0, $svc->currentUsage('flash_sale_limit'));
        $this->assertSame(0, $svc->currentUsage('orders_monthly_limit'));
        $this->assertSame(0, $svc->currentUsage('branch_limit'));
        $this->assertSame(0, $svc->currentUsage('warehouse_limit'));
        $this->assertSame(0, $svc->currentUsage('pos_device_limit'));
    }

    // --- getUsage ---

    public function test_get_usage_structure_for_finite_limit(): void
    {
        $svc = $this->service($this->freePlan);
        $usage = $svc->getUsage('coupon_limit');

        $this->assertIsArray($usage);
        $this->assertArrayHasKey('current', $usage);
        $this->assertArrayHasKey('limit', $usage);
        $this->assertArrayHasKey('remaining', $usage);
        $this->assertArrayHasKey('is_unlimited', $usage);
        $this->assertArrayHasKey('percent', $usage);

        $this->assertSame(0, $usage['current']);
        $this->assertSame(5, $usage['limit']);
        $this->assertSame(5, $usage['remaining']);
        $this->assertFalse($usage['is_unlimited']);
        $this->assertEquals(0, $usage['percent']);
    }

    public function test_get_usage_structure_for_unlimited(): void
    {
        $svc = $this->service($this->businessPlan);
        $usage = $svc->getUsage('coupon_limit');

        $this->assertNull($usage['limit']);
        $this->assertTrue($usage['is_unlimited']);
        $this->assertSame(0, $usage['percent']);
    }

    // --- getAllLimits ---

    public function test_get_all_limits_returns_all_keys(): void
    {
        $svc = $this->service($this->freePlan);
        $all = $svc->getAllLimits();

        $expectedKeys = [
            'product_limit', 'staff_limit', 'storage_limit',
            'orders_monthly_limit', 'coupon_limit', 'promotion_limit',
            'flash_sale_limit', 'branch_limit', 'warehouse_limit',
            'pos_device_limit',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $all);
            $this->assertArrayHasKey('current', $all[$key]);
            $this->assertArrayHasKey('limit', $all[$key]);
        }
    }

    // --- assertCanCreate ---

    public function test_assert_can_create_throws_when_limit_reached(): void
    {
        $plan = Plan::create([
            'name' => 'Tiny', 'slug' => 'tiny', 'description' => 'Tiny plan',
            'monthly_price' => 0, 'yearly_price' => 0, 'status' => 'active',
            'analytics_enabled' => false, 'custom_domain_enabled' => false,
            'coupon_limit' => 0,
        ]);

        $svc = $this->service($plan);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Coupons limit reached');

        $svc->assertCanCreate('coupon_limit');
    }

    public function test_assert_can_create_does_not_throw_when_under_limit(): void
    {
        $svc = $this->service($this->freePlan);

        // Should not throw
        $svc->assertCanCreate('coupon_limit');
        $this->assertTrue(true);
    }

    // --- LIMIT_LABELS constant ---

    public function test_limit_labels_are_complete(): void
    {
        $expectedKeys = [
            'product_limit', 'staff_limit', 'storage_limit',
            'orders_monthly_limit', 'coupon_limit', 'promotion_limit',
            'flash_sale_limit', 'api_request_limit', 'image_limit',
            'image_max_size_kb', 'branch_limit', 'warehouse_limit',
            'pos_device_limit',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, SubscriptionLimitService::LIMIT_LABELS);
        }
    }
}
