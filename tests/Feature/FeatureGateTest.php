<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Tenant;
use App\Models\User;
use App\Services\FeatureGate;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class FeatureGateTest extends TestCase
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

        $this->seedPlanFeatures();

        $this->tenant = Tenant::create([
            'name' => 'Test Store',
            'slug' => 'test-store',
            'store_url' => '/store/test-store',
            'status' => 'active',
        ]);

        App::instance('current.tenant', $this->tenant);
    }

    /* ── DEV_MODE is disabled ── */

    public function test_dev_mode_is_false(): void
    {
        $reflection = new \ReflectionClass(FeatureGate::class);
        $devMode = $reflection->getConstant('DEV_MODE');
        $this->assertFalse($devMode);
    }

    /* ── Free Plan Feature Checks ── */

    public function test_free_plan_has_single_products(): void
    {
        $gate = FeatureGate::forPlan($this->freePlan);
        $this->assertTrue($gate->isEnabled('single_products'));
    }

    public function test_free_plan_lacks_variable_products(): void
    {
        $gate = FeatureGate::forPlan($this->freePlan);
        $this->assertFalse($gate->isEnabled('variable_products'));
    }

    public function test_free_plan_lacks_combo_products(): void
    {
        $gate = FeatureGate::forPlan($this->freePlan);
        $this->assertFalse($gate->isEnabled('combo_products'));
    }

    public function test_free_plan_type_enabled(): void
    {
        $gate = FeatureGate::forPlan($this->freePlan);
        $this->assertTrue($gate->typeEnabled('single'));
        $this->assertFalse($gate->typeEnabled('variable'));
        $this->assertFalse($gate->typeEnabled('combo'));
    }

    /* ── Starter Plan Feature Checks ── */

    public function test_starter_plan_has_single_products(): void
    {
        $gate = FeatureGate::forPlan($this->starterPlan);
        $this->assertTrue($gate->isEnabled('single_products'));
    }

    public function test_starter_plan_has_variable_products(): void
    {
        $gate = FeatureGate::forPlan($this->starterPlan);
        $this->assertTrue($gate->isEnabled('variable_products'));
    }

    public function test_starter_plan_lacks_combo_products(): void
    {
        $gate = FeatureGate::forPlan($this->starterPlan);
        $this->assertFalse($gate->isEnabled('combo_products'));
    }

    /* ── Business Plan Feature Checks ── */

    public function test_business_plan_has_all_features(): void
    {
        $gate = FeatureGate::forPlan($this->businessPlan);
        $this->assertTrue($gate->isEnabled('single_products'));
        $this->assertTrue($gate->isEnabled('variable_products'));
        $this->assertTrue($gate->isEnabled('combo_products'));
    }

    /* ── Static enabled() via user ── */

    public function test_enabled_static_with_free_plan_user(): void
    {
        $user = $this->createUserWithSubscription($this->freePlan);
        $this->actingAs($user);

        $this->assertTrue(FeatureGate::enabled('single_products'));
        $this->assertFalse(FeatureGate::enabled('variable_products'));
        $this->assertFalse(FeatureGate::enabled('combo_products'));
    }

    public function test_enabled_static_with_starter_plan_user(): void
    {
        $user = $this->createUserWithSubscription($this->starterPlan);
        $this->actingAs($user);

        $this->assertTrue(FeatureGate::enabled('variable_products'));
        $this->assertFalse(FeatureGate::enabled('combo_products'));
    }

    public function test_enabled_static_with_business_plan_user(): void
    {
        $user = $this->createUserWithSubscription($this->businessPlan);
        $this->actingAs($user);

        $this->assertTrue(FeatureGate::enabled('combo_products'));
    }

    /* ── require() throws ── */

    public function test_require_throws_on_disabled_feature(): void
    {
        $gate = FeatureGate::forPlan($this->freePlan);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Variable Products (Size, Color, etc.) is not available on your current plan. Upgrade to Starter plan to unlock this feature.');
        $gate->require('variable_products');
    }

    public function test_require_does_not_throw_on_enabled_feature(): void
    {
        $gate = FeatureGate::forPlan($this->freePlan);

        $gate->require('single_products');
        $this->expectNotToPerformAssertions();
    }

    /* ── getEnabledFeatures() ── */

    public function test_free_plan_enabled_features(): void
    {
        $gate = FeatureGate::forPlan($this->freePlan);
        $features = $gate->getEnabledFeatures();
        $this->assertEquals(['single_products'], $features);
    }

    public function test_business_plan_enabled_features(): void
    {
        $gate = FeatureGate::forPlan($this->businessPlan);
        $features = $gate->getEnabledFeatures();
        $this->assertEqualsCanonicalizing(
            ['single_products', 'variable_products', 'combo_products'],
            $features
        );
    }

    /* ── getAllFeaturesStatus() ── */

    public function test_free_plan_all_features_status(): void
    {
        $gate = FeatureGate::forPlan($this->freePlan);
        $status = $gate->getAllFeaturesStatus();

        $this->assertTrue($status['single_products']['enabled']);
        $this->assertFalse($status['single_products']['locked']);

        $this->assertFalse($status['variable_products']['enabled']);
        $this->assertTrue($status['variable_products']['locked']);
        $this->assertEquals('Starter', $status['variable_products']['upgrade_hint']);

        $this->assertFalse($status['combo_products']['enabled']);
        $this->assertTrue($status['combo_products']['locked']);
        $this->assertEquals('Business', $status['combo_products']['upgrade_hint']);
    }

    /* ── Cache invalidation ── */

    public function test_cache_invalidation_clears_feature_data(): void
    {
        FeatureGate::forPlan($this->freePlan)->isEnabled('single_products');

        $feature = PlanFeature::where('plan_id', $this->freePlan->id)
            ->where('feature_key', 'single_products')
            ->first();
        $feature->update(['is_enabled' => false]);

        FeatureGate::clearCache($this->freePlan);

        $gate = FeatureGate::forPlan($this->freePlan);
        $this->assertFalse($gate->isEnabled('single_products'));
    }

    public function test_cache_ttl_serves_stale_data_without_invalidation(): void
    {
        FeatureGate::forPlan($this->freePlan)->isEnabled('single_products');

        $feature = PlanFeature::where('plan_id', $this->freePlan->id)
            ->where('feature_key', 'single_products')
            ->first();
        $feature->update(['is_enabled' => false]);

        $gate = FeatureGate::forPlan($this->freePlan);
        $this->assertTrue($gate->isEnabled('single_products'));
    }

    /* ── Helpers ── */

    private function seedPlanFeatures(): void
    {
        $features = [
            $this->freePlan->id => [
                'single_products' => true,
                'variable_products' => false,
                'combo_products' => false,
            ],
            $this->starterPlan->id => [
                'single_products' => true,
                'variable_products' => true,
                'combo_products' => false,
            ],
            $this->businessPlan->id => [
                'single_products' => true,
                'variable_products' => true,
                'combo_products' => true,
            ],
        ];

        foreach ($features as $planId => $featureKeys) {
            foreach ($featureKeys as $key => $enabled) {
                PlanFeature::create([
                    'plan_id' => $planId,
                    'feature_key' => $key,
                    'is_enabled' => $enabled,
                    'display_label' => ucfirst(str_replace('_', ' ', $key)),
                ]);
            }
        }
    }

    private function createUserWithSubscription(Plan $plan): User
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
        ]);

        $this->tenant->subscription()->create([
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_interval' => 'monthly',
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
        ]);

        return $user;
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
