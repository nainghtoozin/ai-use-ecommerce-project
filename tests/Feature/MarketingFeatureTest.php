<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Tenant;
use App\Services\FeatureGate;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MarketingFeatureTest extends TestCase
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

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'dashboard.view', 'guard_name' => 'web']);
        $permissions = [
            'coupons.view', 'coupons.create', 'coupons.update', 'coupons.delete',
            'promotions.view', 'promotions.create', 'promotions.update', 'promotions.delete',
        ];
        foreach ($permissions as $perm) {
            Permission::create(['name' => $perm, 'guard_name' => 'web']);
        }
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        Role::create(['name' => 'customer', 'guard_name' => 'web']);

        $this->freePlan = Plan::create([
            'name' => 'Free', 'slug' => 'free', 'description' => 'Free plan',
            'monthly_price' => 0, 'yearly_price' => 0, 'status' => 'active',
            'analytics_enabled' => false, 'custom_domain_enabled' => false,
        ]);
        $this->starterPlan = Plan::create([
            'name' => 'Starter', 'slug' => 'starter', 'description' => 'Starter plan',
            'monthly_price' => 29, 'yearly_price' => 290, 'status' => 'active',
            'analytics_enabled' => true, 'custom_domain_enabled' => true,
        ]);
        $this->businessPlan = Plan::create([
            'name' => 'Business', 'slug' => 'business', 'description' => 'Business plan',
            'monthly_price' => 99, 'yearly_price' => 990, 'status' => 'active',
            'analytics_enabled' => true, 'custom_domain_enabled' => true,
        ]);

        $this->seedPlanFeatures($this->freePlan, [
            'single_products', 'payment_gateways_cod', 'payment_gateways_manual',
            'reviews', 'wishlist',
        ]);
        $this->seedPlanFeatures($this->starterPlan, [
            'single_products', 'variable_products', 'digital_products',
            'reports', 'coupons',
            'telegram_integration', 'whatsapp_integration', 'social_media_integration',
            'google_analytics', 'meta_pixel',
            'payment_gateways_cod', 'payment_gateways_kbzpay', 'payment_gateways_wavepay',
            'payment_gateways_stripe', 'payment_gateways_paypal', 'payment_gateways_manual',
            'advanced_seo', 'maintenance_mode', 'reviews', 'wishlist',
        ]);
        $allKeys = array_column(FeatureGate::getAllFeatureDefinitions(), 'key');
        $this->seedPlanFeatures($this->businessPlan, $allKeys);

        $this->tenant = Tenant::create([
            'slug' => 'test-tenant',
            'name' => 'Test Tenant',
            'status' => 'active',
            'plan_id' => $this->freePlan->id,
        ]);
    }

    private function createMinimalSchema(): void
    {
        Schema::create('permissions', function ($table) {
            $table->id(); $table->string('name'); $table->string('guard_name');
            $table->timestamps(); $table->unique(['name', 'guard_name']);
        });
        Schema::create('roles', function ($table) {
            $table->id(); $table->string('name'); $table->string('guard_name');
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps(); $table->unique(['name', 'guard_name', 'tenant_id']);
        });
        Schema::create('model_has_roles', function ($table) {
            $table->unsignedBigInteger('role_id'); $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type']);
            $table->primary(['role_id', 'model_id', 'model_type']);
        });
        Schema::create('role_has_permissions', function ($table) {
            $table->unsignedBigInteger('permission_id'); $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
        });
        Schema::create('model_has_permissions', function ($table) {
            $table->unsignedBigInteger('permission_id'); $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type']);
            $table->primary(['permission_id', 'model_id', 'model_type']);
        });
        Schema::create('users', function ($table) {
            $table->id(); $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('name'); $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password'); $table->string('status')->default('active');
            $table->string('profile_image')->nullable();
            $table->boolean('is_owner')->default(false); $table->boolean('is_admin')->default(false);
            $table->rememberToken(); $table->timestamps();
        });
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
            $table->boolean('analytics_enabled')->default(false);
            $table->boolean('custom_domain_enabled')->default(false);
            $table->string('price')->nullable();
            $table->string('currency')->nullable();
            $table->string('interval')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->enum('status', ['active', 'inactive', 'deprecated'])->default('active');
            $table->timestamps();
        });
        Schema::create('plan_features', function ($table) {
            $table->id(); $table->unsignedBigInteger('plan_id');
            $table->string('feature_key'); $table->boolean('is_enabled')->default(false);
            $table->string('display_label')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['plan_id', 'feature_key']);
        });
        Schema::create('subscriptions', function ($table) {
            $table->id(); $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('plan_id');
            $table->string('status')->default('active');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->integer('trial_renewal_count')->default(0);
            $table->timestamps();
        });
    }

    private function seedPlanFeatures(Plan $plan, array $enabledFeatures): void
    {
        $definitions = FeatureGate::getAllFeatureDefinitions();
        foreach ($definitions as $def) {
            PlanFeature::create([
                'plan_id' => $plan->id,
                'feature_key' => $def['key'],
                'is_enabled' => in_array($def['key'], $enabledFeatures),
                'display_label' => $def['label'],
                'description' => in_array($def['key'], $enabledFeatures) ? $def['label'] : null,
            ]);
        }
        FeatureGate::clearCache($plan);
    }

    // --- Static method tests (no DB needed) ---

    public function test_upgrade_hints(): void
    {
        $this->assertEquals('Starter', FeatureGate::getUpgradeHintStatic('coupons'));
        $this->assertEquals('Business', FeatureGate::getUpgradeHintStatic('promotions'));
        $this->assertEquals('Business', FeatureGate::getUpgradeHintStatic('flash_sales'));
        $this->assertEquals('Business', FeatureGate::getUpgradeHintStatic('gift_cards'));
        $this->assertEquals('Business', FeatureGate::getUpgradeHintStatic('loyalty_points'));
        $this->assertEquals('Business', FeatureGate::getUpgradeHintStatic('referral_system'));
    }

    public function test_future_feature_keys_exist(): void
    {
        $definitions = FeatureGate::getAllFeatureDefinitions();
        $keys = array_column($definitions, 'key');
        $this->assertContains('gift_cards', $keys);
        $this->assertContains('loyalty_points', $keys);
        $this->assertContains('referral_system', $keys);
    }

    public function test_feature_labels_exist(): void
    {
        $this->assertEquals('Coupons', FeatureGate::getLabelStatic('coupons'));
        $this->assertEquals('Promotions & Discounts', FeatureGate::getLabelStatic('promotions'));
        $this->assertEquals('Flash Sales', FeatureGate::getLabelStatic('flash_sales'));
        $this->assertEquals('Gift Cards', FeatureGate::getLabelStatic('gift_cards'));
        $this->assertEquals('Loyalty Points Program', FeatureGate::getLabelStatic('loyalty_points'));
        $this->assertEquals('Referral System', FeatureGate::getLabelStatic('referral_system'));
    }

    // --- FeatureGate::enabled() tests using FeatureGate::forPlan() ---

    public function test_free_plan_coupon_disabled(): void
    {
        $this->assertFalse(FeatureGate::forPlan($this->freePlan)->isEnabled('coupons'));
    }

    public function test_free_plan_promotion_disabled(): void
    {
        $this->assertFalse(FeatureGate::forPlan($this->freePlan)->isEnabled('promotions'));
    }

    public function test_free_plan_flash_sales_disabled(): void
    {
        $this->assertFalse(FeatureGate::forPlan($this->freePlan)->isEnabled('flash_sales'));
    }

    public function test_starter_plan_coupon_enabled(): void
    {
        $this->assertTrue(FeatureGate::forPlan($this->starterPlan)->isEnabled('coupons'));
    }

    public function test_starter_plan_promotion_disabled(): void
    {
        $this->assertFalse(FeatureGate::forPlan($this->starterPlan)->isEnabled('promotions'));
    }

    public function test_business_plan_coupon_enabled(): void
    {
        $this->assertTrue(FeatureGate::forPlan($this->businessPlan)->isEnabled('coupons'));
    }

    public function test_business_plan_promotion_enabled(): void
    {
        $this->assertTrue(FeatureGate::forPlan($this->businessPlan)->isEnabled('promotions'));
    }

    public function test_business_plan_flash_sales_enabled(): void
    {
        $this->assertTrue(FeatureGate::forPlan($this->businessPlan)->isEnabled('flash_sales'));
    }
}
