<?php

namespace Tests\Feature;

use App\Listeners\ActivateTenantOnVerified;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SubscriptionExpiryService;
use App\Services\TenantBootstrapService;
use Carbon\Carbon;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TrialLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    private Plan $freePlan;
    private Plan $paidPlan;

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

        PlatformSetting::create([
            'site_name' => 'Test App',
            'trial_enabled' => true,
            'trial_days' => 14,
        ]);
        PlatformSetting::clearCache();

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

        $this->paidPlan = Plan::create([
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
    }

    public function test_trial_subscription_created_during_bootstrap(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 28, 12, 0, 0));

        $tenant = Tenant::create([
            'name' => 'Trial Store',
            'slug' => 'trial-store',
            'store_url' => '/store/trial-store',
            'status' => 'pending',
        ]);

        app(TenantBootstrapService::class)->bootstrap($tenant, [
            'owner_name' => 'Trial Owner',
            'owner_email' => 'owner@trial.com',
            'owner_password' => 'password',
            'plan_id' => $this->paidPlan->id,
        ]);

        $subscription = $tenant->subscription()->first();

        $this->assertNotNull($subscription);
        $this->assertTrue($subscription->isTrialing());
        $this->assertEquals($this->paidPlan->id, $subscription->plan_id);
        $this->assertTrue($subscription->starts_at->eq(Carbon::now()));
        $this->assertTrue($subscription->trial_ends_at->eq(Carbon::now()->addDays(14)));
        $this->assertTrue($subscription->onTrial());
        $this->assertEquals(14, $subscription->daysLeftInTrial());
    }

    public function test_free_plan_skips_trial(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 28, 12, 0, 0));

        $tenant = Tenant::create([
            'name' => 'Free Store',
            'slug' => 'free-store',
            'store_url' => '/store/free-store',
            'status' => 'pending',
        ]);

        app(TenantBootstrapService::class)->bootstrap($tenant, [
            'owner_name' => 'Free Owner',
            'owner_email' => 'owner@free.com',
            'owner_password' => 'password',
            'plan_id' => $this->freePlan->id,
        ]);

        $subscription = $tenant->subscription()->first();

        $this->assertNotNull($subscription);
        $this->assertEquals('pending', $subscription->status);
        $this->assertNull($subscription->trial_ends_at);
        $this->assertFalse($subscription->isTrialing());
        $this->assertFalse($subscription->onTrial());
    }

    public function test_trial_disabled_skips_trial(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 28, 12, 0, 0));

        $settings = PlatformSetting::current();
        $settings->trial_enabled = false;
        $settings->save();
        PlatformSetting::clearCache();

        $tenant = Tenant::create([
            'name' => 'NoTrial Store',
            'slug' => 'notrial-store',
            'store_url' => '/store/notrial-store',
            'status' => 'pending',
        ]);

        app(TenantBootstrapService::class)->bootstrap($tenant, [
            'owner_name' => 'NoTrial Owner',
            'owner_email' => 'owner@notrial.com',
            'owner_password' => 'password',
            'plan_id' => $this->paidPlan->id,
        ]);

        $subscription = $tenant->subscription()->first();

        $this->assertNotNull($subscription);
        $this->assertEquals('pending', $subscription->status);
        $this->assertNull($subscription->trial_ends_at);
        $this->assertFalse($subscription->isTrialing());
    }

    public function test_trial_enabled_auto_selects_paid_plan_when_no_plan_id(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 28, 12, 0, 0));

        $tenant = Tenant::create([
            'name' => 'Auto Plan Store',
            'slug' => 'auto-plan-store',
            'store_url' => '/store/auto-plan-store',
            'status' => 'pending',
        ]);

        app(TenantBootstrapService::class)->bootstrap($tenant, [
            'owner_name' => 'Auto Plan Owner',
            'owner_email' => 'owner@autoplan.com',
            'owner_password' => 'password',
        ]);

        $subscription = $tenant->subscription()->first();

        $this->assertNotNull($subscription);
        $this->assertTrue($subscription->isTrialing());
        $this->assertEquals($this->paidPlan->id, $subscription->plan_id);
        $this->assertTrue($subscription->starts_at->eq(Carbon::now()));
        $this->assertTrue($subscription->trial_ends_at->eq(Carbon::now()->addDays(14)));
        $this->assertTrue($subscription->expires_at->eq($subscription->trial_ends_at));
        $this->assertTrue($subscription->onTrial());
        $this->assertEquals(14, $subscription->daysLeftInTrial());
    }

    public function test_trial_enabled_sets_expires_at_equal_trial_ends_at(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 28, 12, 0, 0));

        $tenant = Tenant::create([
            'name' => 'Expiry Store',
            'slug' => 'expiry-store',
            'store_url' => '/store/expiry-store',
            'status' => 'pending',
        ]);

        app(TenantBootstrapService::class)->bootstrap($tenant, [
            'owner_name' => 'Expiry Owner',
            'owner_email' => 'owner@expiry.com',
            'owner_password' => 'password',
            'plan_id' => $this->paidPlan->id,
        ]);

        $subscription = $tenant->subscription()->first();

        $this->assertNotNull($subscription);
        $this->assertTrue($subscription->isTrialing());
        $this->assertNotNull($subscription->trial_ends_at);
        $this->assertNotNull($subscription->expires_at);
        $this->assertTrue($subscription->expires_at->eq($subscription->trial_ends_at));
    }

    public function test_trial_enabled_with_custom_trial_days(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 28, 12, 0, 0));

        $settings = PlatformSetting::current();
        $settings->trial_days = 21;
        $settings->save();
        PlatformSetting::clearCache();

        $tenant = Tenant::create([
            'name' => 'Custom Trial Store',
            'slug' => 'custom-trial-store',
            'store_url' => '/store/custom-trial-store',
            'status' => 'pending',
        ]);

        app(TenantBootstrapService::class)->bootstrap($tenant, [
            'owner_name' => 'Custom Owner',
            'owner_email' => 'owner@custom.com',
            'owner_password' => 'password',
            'plan_id' => $this->paidPlan->id,
        ]);

        $subscription = $tenant->subscription()->first();

        $this->assertNotNull($subscription);
        $this->assertTrue($subscription->isTrialing());
        $this->assertTrue($subscription->trial_ends_at->eq(Carbon::now()->addDays(21)));
        $this->assertEquals(21, $subscription->daysLeftInTrial());
    }

    public function test_trial_enabled_explicit_free_plan_skips_trial(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 28, 12, 0, 0));

        $tenant = Tenant::create([
            'name' => 'Explicit Free Store',
            'slug' => 'explicit-free-store',
            'store_url' => '/store/explicit-free-store',
            'status' => 'pending',
        ]);

        app(TenantBootstrapService::class)->bootstrap($tenant, [
            'owner_name' => 'Free Owner',
            'owner_email' => 'owner@explicitfree.com',
            'owner_password' => 'password',
            'plan_id' => $this->freePlan->id,
        ]);

        $subscription = $tenant->subscription()->first();

        $this->assertNotNull($subscription);
        $this->assertEquals('pending', $subscription->status);
        $this->assertNull($subscription->trial_ends_at);
        $this->assertNull($subscription->expires_at);
        $this->assertFalse($subscription->isTrialing());
    }

    public function test_trial_disabled_uses_free_plan_when_no_plan_id(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 28, 12, 0, 0));

        $settings = PlatformSetting::current();
        $settings->trial_enabled = false;
        $settings->save();
        PlatformSetting::clearCache();

        $tenant = Tenant::create([
            'name' => 'NoTrial Free Store',
            'slug' => 'notrial-free-store',
            'store_url' => '/store/notrial-free-store',
            'status' => 'pending',
        ]);

        app(TenantBootstrapService::class)->bootstrap($tenant, [
            'owner_name' => 'NoTrial Free Owner',
            'owner_email' => 'owner@notrialfree.com',
            'owner_password' => 'password',
        ]);

        $subscription = $tenant->subscription()->first();

        $this->assertNotNull($subscription);
        $this->assertEquals('pending', $subscription->status);
        $this->assertEquals($this->freePlan->id, $subscription->plan_id);
        $this->assertNull($subscription->trial_ends_at);
        $this->assertNull($subscription->expires_at);
        $this->assertFalse($subscription->isTrialing());
    }

    public function test_trial_enabled_no_paid_plans_falls_back_to_free(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 28, 12, 0, 0));

        $this->paidPlan->update(['status' => 'inactive']);
        PlatformSetting::clearCache();

        $tenant = Tenant::create([
            'name' => 'No Paid Store',
            'slug' => 'no-paid-store',
            'store_url' => '/store/no-paid-store',
            'status' => 'pending',
        ]);

        app(TenantBootstrapService::class)->bootstrap($tenant, [
            'owner_name' => 'No Paid Owner',
            'owner_email' => 'owner@nopaid.com',
            'owner_password' => 'password',
        ]);

        $subscription = $tenant->subscription()->first();

        $this->assertNotNull($subscription);
        $this->assertEquals('pending', $subscription->status);
        $this->assertEquals($this->freePlan->id, $subscription->plan_id);
        $this->assertNull($subscription->trial_ends_at);
        $this->assertFalse($subscription->isTrialing());
    }

    public function test_activate_tenant_leaves_trialing_subscription(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 28, 12, 0, 0));
        Notification::fake();

        $tenant = Tenant::create([
            'name' => 'Verify Store',
            'slug' => 'verify-store',
            'store_url' => '/store/verify-store',
            'status' => 'pending',
        ]);

        $subscription = $tenant->subscription()->create([
            'plan_id' => $this->paidPlan->id,
            'billing_interval' => 'monthly',
            'status' => 'trialing',
            'starts_at' => now(),
            'trial_ends_at' => now()->addDays(14),
        ]);

        $owner = new User();
        $owner->name = 'Verify Owner';
        $owner->email = 'owner@verify.com';
        $owner->password = Hash::make('password');
        $owner->tenant_id = $tenant->id;
        $owner->is_owner = true;
        $owner->status = 'active';
        $owner->save();

        $this->assertTrue($owner->is_owner);
        $this->assertEquals($tenant->id, $owner->tenant_id);

        $this->assertEquals('pending', $tenant->fresh()->status);

        $listener = app(ActivateTenantOnVerified::class);
        $listener->handle(new Verified($owner));

        $this->assertEquals('active', $tenant->fresh()->status);
        $this->assertTrue($subscription->fresh()->isTrialing());
        $this->assertFalse($subscription->fresh()->isExpired());
    }

    public function test_ensure_tenant_is_active_allows_trialing_subscription(): void
    {
        $tenant = Tenant::create([
            'name' => 'Middleware Store',
            'slug' => 'middleware-store',
            'store_url' => '/store/middleware-store',
            'status' => 'active',
        ]);

        $tenant->subscription()->create([
            'plan_id' => $this->paidPlan->id,
            'billing_interval' => 'monthly',
            'status' => 'trialing',
            'starts_at' => now(),
            'trial_ends_at' => now()->addDays(14),
        ]);

        $this->assertTrue($tenant->hasActiveSubscription());
        $this->assertFalse($tenant->subscriptionExpired());
    }

    public function test_subscription_is_active_allows_trialing_subscription(): void
    {
        $tenant = Tenant::create([
            'name' => 'Sub Store',
            'slug' => 'sub-store',
            'store_url' => '/store/sub-store',
            'status' => 'active',
        ]);

        $subscription = $tenant->subscription()->create([
            'plan_id' => $this->paidPlan->id,
            'billing_interval' => 'monthly',
            'status' => 'trialing',
            'starts_at' => now(),
            'trial_ends_at' => now()->addDays(14),
        ]);

        $this->assertTrue($subscription->isInGoodStanding());
        $this->assertFalse($subscription->isExpired());
    }

    public function test_trial_expiry_transitions_via_cron(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 28, 12, 0, 0));

        $tenant = Tenant::create([
            'name' => 'Expired Trial Store',
            'slug' => 'expired-trial-store',
            'store_url' => '/store/expired-trial-store',
            'status' => 'active',
        ]);

        $tenant->subscription()->create([
            'plan_id' => $this->paidPlan->id,
            'billing_interval' => 'monthly',
            'status' => 'trialing',
            'starts_at' => now()->subDays(14),
            'trial_ends_at' => now()->subDay(),
        ]);

        $subscription = $tenant->subscription;
        $this->assertTrue($subscription->trialExpired());
        $this->assertEquals(0, $subscription->daysLeftInTrial());

        app(SubscriptionExpiryService::class)->process();

        $subscription->refresh();

        $this->assertTrue($subscription->isExpired());
        $this->assertFalse($subscription->isTrialing());
    }

    public function test_trial_does_not_expire_before_trial_ends(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 28, 12, 0, 0));

        $tenant = Tenant::create([
            'name' => 'Active Trial Store',
            'slug' => 'active-trial-store',
            'store_url' => '/store/active-trial-store',
            'status' => 'active',
        ]);

        $tenant->subscription()->create([
            'plan_id' => $this->paidPlan->id,
            'billing_interval' => 'monthly',
            'status' => 'trialing',
            'starts_at' => now(),
            'trial_ends_at' => now()->addDays(10),
        ]);

        $subscription = $tenant->subscription;
        $this->assertFalse($subscription->trialExpired());

        app(SubscriptionExpiryService::class)->process();

        $subscription->refresh();

        $this->assertTrue($subscription->isTrialing());
        $this->assertFalse($subscription->isExpired());
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
                $table->timestamp('activated_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('locked_at')->nullable();
                $table->bigInteger('used_storage_bytes')->default(0);
                $table->timestamps();
            },
            'plans' => function ($table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->decimal('monthly_price', 10, 2)->nullable();
                $table->decimal('yearly_price', 10, 2)->nullable();
                $table->unsignedInteger('product_limit')->nullable();
                $table->unsignedInteger('staff_limit')->nullable();
                $table->unsignedInteger('storage_limit')->nullable();
                $table->boolean('analytics_enabled')->default(false);
                $table->boolean('custom_domain_enabled')->default(false);
                $table->decimal('price', 10, 2)->nullable();
                $table->string('currency', 10)->default('USD');
                $table->string('interval', 20)->default('monthly');
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->text('description')->nullable();
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
            'platform_settings' => function ($table) {
                $table->id();
                $table->string('site_name')->default('My Application');
                $table->string('site_logo')->nullable();
                $table->string('favicon')->nullable();
                $table->string('support_email')->nullable();
                $table->boolean('maintenance_mode')->default(false);
                $table->boolean('registration_enabled')->default(true);
                $table->boolean('trial_enabled')->default(false);
                $table->integer('trial_days')->default(14);
                $table->timestamps();
            },
            'subscription_audit_logs' => function ($table) {
                $table->id();
                $table->unsignedBigInteger('subscription_id');
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('event');
                $table->string('actor_type')->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->unsignedBigInteger('old_plan_id')->nullable();
                $table->unsignedBigInteger('new_plan_id')->nullable();
                $table->string('old_status')->nullable();
                $table->string('new_status')->nullable();
                $table->text('reason')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index('subscription_id');
                $table->index('tenant_id');
                $table->index('event');
                $table->index('created_at');
            },
            'units' => function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('name');
                $table->string('short_name');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['tenant_id', 'name']);
            },
            'categories' => function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('name');
                $table->text('description')->nullable();
                $table->timestamps();
            },
            'brands' => function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('name');
                $table->string('slug');
                $table->text('description')->nullable();
                $table->string('logo')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['tenant_id', 'name']);
                $table->unique(['tenant_id', 'slug']);
            },
            'activity_logs' => function ($table) {
                $table->id();
                $table->string('log_name')->nullable();
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
                $table->timestamps();
                $table->index(['subject_type', 'subject_id']);
            },
            'notifications' => function ($table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            },
            'payment_methods' => function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('name');
                $table->string('type', 20)->nullable();
                $table->string('account_name')->nullable();
                $table->string('account_number')->nullable();
                $table->string('qr_image')->nullable();
                $table->string('bank_name')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['tenant_id', 'name']);
            },
        ];

        foreach ($tables as $name => $callback) {
            if (!Schema::hasTable($name)) {
                Schema::create($name, $callback);
            }
        }
    }
}
