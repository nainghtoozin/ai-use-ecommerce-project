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

class AdminBillingPageTest extends TestCase
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

        Permission::firstOrCreate(['name' => 'billing.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'billing.renew', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->freePlan = Plan::firstOrCreate(['slug' => 'free'], [
            'name' => 'Free', 'description' => 'Free plan',
            'monthly_price' => 0, 'yearly_price' => 0, 'status' => 'active',
            'analytics_enabled' => false, 'custom_domain_enabled' => false,
            'product_limit' => 10, 'staff_limit' => 2, 'storage_limit' => 100,
            'orders_monthly_limit' => 50, 'coupon_limit' => 5,
            'promotion_limit' => 3, 'flash_sale_limit' => 1,
            'branch_limit' => 1, 'warehouse_limit' => 1, 'pos_device_limit' => 1,
        ]);

        $this->starterPlan = Plan::firstOrCreate(['slug' => 'starter'], [
            'name' => 'Starter', 'description' => 'Starter plan',
            'monthly_price' => 29, 'yearly_price' => 290, 'status' => 'active',
            'analytics_enabled' => true, 'custom_domain_enabled' => true,
            'product_limit' => 100, 'staff_limit' => 5, 'storage_limit' => 1024,
            'orders_monthly_limit' => 500, 'coupon_limit' => 20,
            'promotion_limit' => 10, 'flash_sale_limit' => 5,
            'branch_limit' => 3, 'warehouse_limit' => 2, 'pos_device_limit' => 3,
        ]);

        $this->businessPlan = Plan::firstOrCreate(['slug' => 'business'], [
            'name' => 'Business', 'description' => 'Business plan',
            'monthly_price' => 99, 'yearly_price' => 990, 'status' => 'active',
            'analytics_enabled' => true, 'custom_domain_enabled' => true,
            'product_limit' => null, 'staff_limit' => null, 'storage_limit' => null,
            'orders_monthly_limit' => null, 'coupon_limit' => null,
            'promotion_limit' => null, 'flash_sale_limit' => null,
            'branch_limit' => null, 'warehouse_limit' => null, 'pos_device_limit' => null,
        ]);

        $this->freePlan->update([
            'monthly_price' => 0, 'yearly_price' => 0, 'status' => 'active',
            'analytics_enabled' => false, 'custom_domain_enabled' => false,
            'product_limit' => 10, 'staff_limit' => 2, 'storage_limit' => 100,
            'orders_monthly_limit' => 50, 'coupon_limit' => 5,
            'promotion_limit' => 3, 'flash_sale_limit' => 1,
            'branch_limit' => 1, 'warehouse_limit' => 1, 'pos_device_limit' => 1,
        ]);
        $this->starterPlan->update([
            'monthly_price' => 29, 'yearly_price' => 290, 'status' => 'active',
            'analytics_enabled' => true, 'custom_domain_enabled' => true,
            'product_limit' => 100, 'staff_limit' => 5, 'storage_limit' => 1024,
            'orders_monthly_limit' => 500, 'coupon_limit' => 20,
            'promotion_limit' => 10, 'flash_sale_limit' => 5,
            'branch_limit' => 3, 'warehouse_limit' => 2, 'pos_device_limit' => 3,
        ]);
        $this->businessPlan->update([
            'monthly_price' => 99, 'yearly_price' => 990, 'status' => 'active',
            'analytics_enabled' => true, 'custom_domain_enabled' => true,
        ]);

        $this->seedPlanFeatures($this->freePlan, ['single_products', 'payment_gateways_cod']);
        $this->seedPlanFeatures($this->starterPlan, ['single_products', 'variable_products', 'coupons', 'reports', 'payment_gateways_cod']);
        $allKeys = array_column(FeatureGate::getAllFeatureDefinitions(), 'key');
        $this->seedPlanFeatures($this->businessPlan, $allKeys);

        $this->tenant = Tenant::firstOrCreate(['slug' => 'test-tenant'], [
            'name' => 'Test Tenant',
            'status' => 'active',
            'plan_id' => $this->freePlan->id,
        ]);
        $this->tenant->update(['status' => 'active']);
    }

    private const SCHEMA_TABLES = [
        'permissions', 'roles', 'model_has_roles', 'role_has_permissions',
        'model_has_permissions', 'users', 'tenants', 'plans', 'plan_features',
        'subscriptions', 'website_infos', 'platform_settings', 'notifications',
        'categories', 'products', 'orders', 'coupons', 'promotions',
        'promotion_banners', 'settings', 'activity_logs', 'subscription_audit_logs',
        'accounts', 'tenant_memberships', 'payment_methods', 'flash_sales', 'wishlists',
    ];

    private function createMinimalSchema(): void
    {
        $missing = array_values(array_filter(
            self::SCHEMA_TABLES,
            fn ($table) => !Schema::hasTable($table)
        ));

        if ($missing !== []) {
            $this->createMissingSchemaTables($missing);
        }

        $this->ensureBillingSchema();
    }

    private function createMissingSchemaTables(array $missing): void
    {
        $all = array_fill_keys(self::SCHEMA_TABLES, true);
        $need = array_fill_keys($missing, true);

        if (count($need) !== count($all)) {
            throw new \RuntimeException(
                'AdminBillingPageTest requires a fresh database or a database where its schema was fully created before; ' .
                'partial schema would collide with other suites. Missing: ' . implode(',', $missing)
            );
        }

        $this->buildSchemaTables();
    }

    private function buildSchemaTables(): void
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
            $table->unsignedBigInteger('store_category_id')->nullable();
            $table->string('store_url')->nullable();
            $table->json('settings')->nullable();
            $table->string('logo')->nullable(); $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('activated_at')->nullable();
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
            $table->integer('trial_renewals_count')->default(0);
            $table->timestamps();
        });
        Schema::create('website_infos', function ($table) {
            $table->id(); $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('site_name')->nullable();
            $table->string('site_tagline')->nullable();
            $table->string('site_description')->nullable();
            $table->string('site_keywords')->nullable();
            $table->string('theme_color')->default('#3B82F6');
            $table->string('default_language')->default('en');
            $table->string('timezone')->default('Asia/Yangon');
            $table->string('currency_code')->default('MMK');
            $table->string('currency_symbol')->default('K');
            $table->string('date_format')->default('Y-m-d');
            $table->boolean('allow_registration')->default(true);
            $table->boolean('maintenance_mode')->default(false);
            $table->string('logo')->nullable();
            $table->string('favicon')->nullable();
            $table->string('og_image')->nullable();
            $table->string('footer_logo')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('support_email')->nullable();
            $table->string('phone')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->text('address')->nullable();
            $table->string('country')->nullable();
            $table->string('google_maps_embed_url')->nullable();
            $table->json('contact_address')->nullable();
            $table->json('footer_settings')->nullable();
            $table->json('hero_images')->nullable();
            $table->boolean('enable_wishlist')->default(true);
            $table->boolean('enable_reviews')->default(true);
            $table->boolean('enable_compare')->default(true);
            $table->timestamps();
        });
        Schema::create('platform_settings', function ($table) {
            $table->id(); $table->boolean('allow_trial_renewal')->default(true);
            $table->integer('max_trial_renewals')->default(3);
            $table->timestamps();
        });
        Schema::create('notifications', function ($table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->string('notifiable_type');
            $table->unsignedBigInteger('notifiable_id');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['notifiable_type', 'notifiable_id']);
        });
        Schema::create('categories', function ($table) {
            $table->id(); $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('name'); $table->string('slug')->nullable();
            $table->timestamps();
        });
        Schema::create('products', function ($table) {
            $table->id(); $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('name')->nullable(); $table->timestamps();
        });
        Schema::create('orders', function ($table) {
            $table->id(); $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();
        });
        Schema::create('coupons', function ($table) {
            $table->id(); $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();
        });
        Schema::create('promotions', function ($table) {
            $table->id(); $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('name')->nullable(); $table->string('code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_automatic')->default(false);
            $table->timestamps();
        });
        Schema::create('promotion_banners', function ($table) {
            $table->id(); $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();
        });
        Schema::create('settings', function ($table) {
            $table->id(); $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('key'); $table->text('value')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'key']);
        });
        Schema::create('activity_logs', function ($table) {
            $table->id(); $table->string('log_name')->nullable();
            $table->string('category', 50)->nullable();
            $table->string('severity', 20)->default('info');
            $table->text('description'); $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('causer_type')->nullable();
            $table->unsignedBigInteger('causer_id')->nullable();
            $table->unsignedBigInteger('impersonator_id')->nullable();
            $table->unsignedBigInteger('impersonated_user_id')->nullable();
            $table->json('properties')->nullable();
            $table->string('event')->nullable();
            $table->string('batch_uuid')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
        });
        Schema::create('subscription_audit_logs', function ($table) {
            $table->id(); $table->unsignedBigInteger('subscription_id');
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('event'); $table->string('actor_type')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('old_status')->nullable();
            $table->string('new_status')->nullable();
            $table->unsignedBigInteger('old_plan_id')->nullable();
            $table->unsignedBigInteger('new_plan_id')->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
        Schema::create('accounts', function ($table) {
            $table->id(); $table->string('name')->nullable();
            $table->string('email')->unique(); $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->string('profile_image', 255)->nullable();
            $table->string('status', 50)->default('active');
            $table->json('notification_preferences')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->timestamps(); $table->softDeletes();
        });
        Schema::create('tenant_memberships', function ($table) {
            $table->id(); $table->unsignedBigInteger('account_id')->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->boolean('is_owner')->default(false);
            $table->string('status')->default('active');
            $table->timestamps(); $table->softDeletes();
        });
        Schema::create('payment_methods', function ($table) {
            $table->id(); $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('name')->nullable(); $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('flash_sales', function ($table) {
            $table->id(); $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('name')->nullable(); $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('wishlists', function ($table) {
            $table->id(); $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_type')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->timestamps();
        });
    }

    private function ensureBillingSchema(): void
    {
        if (!Schema::hasTable('subscription_cancellation_requests')) {
            Schema::create('subscription_cancellation_requests', function ($table) {
                $table->id(); $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('subscription_id');
                $table->string('reason', 50); $table->text('comment')->nullable();
                $table->string('status', 20)->default('pending');
                $table->unsignedBigInteger('requested_by')->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->text('review_note')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('payment_intents')) {
            Schema::create('payment_intents', function ($table) {
                $table->id(); $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('plan_id');
                $table->unsignedBigInteger('subscription_id')->nullable();
                $table->string('billing_cycle'); $table->decimal('amount', 10, 2);
                $table->string('currency', 3); $table->string('gateway');
                $table->string('status'); $table->timestamp('expires_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->string('reference_number')->nullable()->unique();
                $table->string('idempotency_key', 64)->nullable();
                $table->timestamps();
            });
        }

        foreach (['billing_interval', 'starts_at', 'suspended_at', 'cancelled_at', 'extra_renewal_used_at', 'pending_plan_id', 'pending_plan_effective_at'] as $column) {
            if (!Schema::hasColumn('subscriptions', $column)) {
                Schema::table('subscriptions', function ($table) use ($column) {
                    if (in_array($column, ['pending_plan_id'], true)) {
                        $table->unsignedBigInteger($column)->nullable();
                    } elseif (str_ends_with($column, '_at')) {
                        $table->timestamp($column)->nullable();
                    } else {
                        $table->string($column)->nullable();
                    }
                });
            }
        }
    }

    private function seedPlanFeatures(Plan $plan, array $enabledFeatures): void
    {
        $definitions = FeatureGate::getAllFeatureDefinitions();
        foreach ($definitions as $def) {
            PlanFeature::updateOrCreate(
                ['plan_id' => $plan->id, 'feature_key' => $def['key']],
                [
                    'is_enabled' => in_array($def['key'], $enabledFeatures),
                    'display_label' => $def['label'],
                    'description' => in_array($def['key'], $enabledFeatures) ? $def['label'] : null,
                ]
            );
        }
        FeatureGate::clearCache($plan);
    }

    private function createUserAndLogin(): void
    {
        $user = \App\Models\User::firstOrCreate(['email' => 'admin@test.com'], [
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin User',
            'password' => bcrypt('password'),
            'status' => 'active',
            'is_owner' => true,
        ]);
        $user->update(['tenant_id' => $this->tenant->id, 'status' => 'active']);
        $user->assignRole('admin');
        $user->givePermissionTo('billing.view');
        $user->givePermissionTo('billing.renew');
        $this->actingAs($user);
    }

    private function createSubscription(): void
    {
        $this->tenant->subscriptions()->delete();
        $this->tenant->subscription()->create([
            'plan_id' => $this->freePlan->id,
            'status' => 'active',
            'expires_at' => now()->addMonth(),
        ]);
    }

    // ── Tests ──

    public function test_billing_page_returns_200(): void
    {
        $this->createSubscription();
        $this->createUserAndLogin();

        $response = $this->get('/admin/billing');
        $response->assertStatus(200);
    }

    public function test_billing_page_has_subscription_data(): void
    {
        $this->createSubscription();
        $this->createUserAndLogin();

        $response = $this->get('/admin/billing');
        $response->assertInertia(fn($page) => $page
            ->component('Admin/Billing/Index')
            ->has('subscription')
            ->has('usage')
            ->has('plans')
            ->has('featureCategories')
            ->has('allFeatureDefs')
        );
    }

    public function test_billing_page_returns_all_three_plans(): void
    {
        $this->createSubscription();
        $this->createUserAndLogin();

        $response = $this->get('/admin/billing');
        $response->assertInertia(fn($page) => $page
            ->has('plans')
            ->where('plans', fn ($plans) => collect($plans)->pluck('slug')->intersect(['free', 'starter', 'business'])->count() === 3)
        );
    }

    public function test_billing_page_current_plan_is_free(): void
    {
        $this->createSubscription();
        $this->createUserAndLogin();

        $response = $this->get('/admin/billing');
        $response->assertInertia(fn($page) => $page
            ->where('subscription.plan.slug', 'free')
            ->has('plans.0', fn($plan) => $plan
                ->where('slug', 'free')
                ->where('is_current', true)
                ->etc()
            )
        );
    }

    public function test_billing_page_has_usage_data(): void
    {
        $this->createSubscription();
        $this->createUserAndLogin();

        $response = $this->get('/admin/billing');
        $response->assertInertia(fn($page) => $page
            ->has('usage.product_limit', fn($u) => $u
                ->where('current', 0)
                ->where('limit', 10)
                ->where('is_unlimited', false)
                ->etc()
            )
        );
    }

    public function test_billing_page_has_feature_categories(): void
    {
        $this->createSubscription();
        $this->createUserAndLogin();

        $response = $this->get('/admin/billing');
        $response->assertInertia(fn($page) => $page
            ->has('featureCategories.0', fn($cat) => $cat
                ->where('label', 'Product Features')
                ->has('features')
                ->etc()
            )
        );
    }

    public function test_billing_page_has_all_feature_defs(): void
    {
        $this->createSubscription();
        $this->createUserAndLogin();

        $response = $this->get('/admin/billing');
        $response->assertInertia(fn($page) => $page
            ->has('allFeatureDefs.0', fn($def) => $def
                ->where('key', 'single_products')
                ->where('label', 'Standard Products')
                ->etc()
            )
        );
    }

    public function test_billing_page_subscription_status_is_active(): void
    {
        $this->createSubscription();
        $this->createUserAndLogin();

        $response = $this->get('/admin/billing');
        $response->assertInertia(fn($page) => $page
            ->where('subscription.status', 'active')
        );
    }

    public function test_billing_page_requires_authentication(): void
    {
        $response = $this->get('/admin/billing');
        $response->assertStatus(302);
    }

    public function test_billing_page_requires_billing_view_permission(): void
    {
        $this->createSubscription();
        $user = \App\Models\User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'No Perm User',
            'email' => 'noperm@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $user->assignRole('admin');
        $this->actingAs($user);

        $response = $this->get('/admin/billing');
        $response->assertStatus(403);
    }

    public function test_free_plan_limits_in_response(): void
    {
        $this->createSubscription();
        $this->createUserAndLogin();

        $response = $this->get('/admin/billing');
        $response->assertInertia(fn($page) => $page
            ->where('subscription.plan.limits.product_limit', 10)
            ->where('subscription.plan.limits.staff_limit', 2)
            ->where('subscription.plan.limits.coupon_limit', 5)
        );
    }

    public function test_each_plan_has_feature_array(): void
    {
        $this->createSubscription();
        $this->createUserAndLogin();

        $response = $this->get('/admin/billing');
        $response->assertInertia(fn($page) => $page
            ->has('plans.0.features')
            ->has('plans.1.features')
            ->has('plans.2.features')
        );
    }

    public function test_each_plan_has_limits_object(): void
    {
        $this->createSubscription();
        $this->createUserAndLogin();

        $response = $this->get('/admin/billing');
        $response->assertInertia(fn($page) => $page
            ->has('plans.0.limits.product_limit')
            ->has('plans.0.limits.coupon_limit')
            ->has('plans.0.limits.staff_limit')
        );
    }
}
