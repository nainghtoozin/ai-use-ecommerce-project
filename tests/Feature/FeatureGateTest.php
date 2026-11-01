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

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::findOrCreate('dashboard.view', 'web');
        $superadminRole = Role::findOrCreate('superadmin', 'web');
        $superadminRole->syncPermissions(Permission::all());
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('customer', 'web');

        $this->freePlan = Plan::firstOrCreate(['slug' => 'free'], [
            'name' => 'Free',
            'monthly_price' => 0,
            'yearly_price' => 0,
            'product_limit' => 10,
            'staff_limit' => 2,
            'storage_limit' => 100,
            'description' => 'Free plan',
            'status' => 'active',
        ]);

        $this->starterPlan = Plan::firstOrCreate(['slug' => 'starter'], [
            'name' => 'Starter',
            'monthly_price' => 9.99,
            'yearly_price' => 99.99,
            'product_limit' => 100,
            'staff_limit' => 5,
            'storage_limit' => 1024,
            'description' => 'Starter plan',
            'status' => 'active',
        ]);

        $this->businessPlan = Plan::firstOrCreate(['slug' => 'business'], [
            'name' => 'Business',
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
        $this->assertFalse(FeatureGate::isDevMode());
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
}
