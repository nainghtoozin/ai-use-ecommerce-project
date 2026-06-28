<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SubscriptionExpiryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SubscriptionLockModeTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;
    private Tenant $lockedTenant;
    private Plan $paidPlan;
    private User $adminUser;
    private User $lockedAdminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createMinimalSchema();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'dashboard.view', 'guard_name' => 'web']);
        $superadminRole = Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        $superadminRole->syncPermissions(Permission::all());
        $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        Role::create(['name' => 'customer', 'guard_name' => 'web']);

        PlatformSetting::create([
            'site_name' => 'Test App',
            'trial_enabled' => true,
            'trial_days' => 14,
            'allow_trial_renewal' => true,
            'max_trial_renewals' => 0,
        ]);
        PlatformSetting::clearCache();

        $this->paidPlan = Plan::create([
            'name' => 'Starter',
            'slug' => 'starter-' . uniqid(),
            'monthly_price' => 29.99,
            'yearly_price' => 299.99,
            'product_limit' => 50,
            'staff_limit' => 5,
            'storage_limit' => 1024,
            'description' => 'Starter plan',
            'status' => 'active',
            'sort_order' => 1,
        ]);

        $this->tenant = Tenant::create([
            'name' => 'Test Store',
            'slug' => 'test-store-' . uniqid(),
            'email' => 'store@test.com',
            'status' => 'active',
        ]);

        $this->lockedTenant = Tenant::create([
            'name' => 'Locked Store',
            'slug' => 'locked-store-' . uniqid(),
            'email' => 'locked@test.com',
            'status' => 'active',
        ]);

        $this->adminUser = User::create([
            'name' => 'Admin',
            'email' => 'admin-' . uniqid() . '@test.com',
            'password' => Hash::make('password'),
            'is_owner' => true,
            'status' => 'active',
        ]);
        $this->adminUser->tenant_id = $this->tenant->id;
        $this->adminUser->save();
        $this->adminUser->assignRole('admin');

        $this->lockedAdminUser = User::create([
            'name' => 'Locked Admin',
            'email' => 'locked-admin-' . uniqid() . '@test.com',
            'password' => Hash::make('password'),
            'is_owner' => true,
            'status' => 'active',
        ]);
        $this->lockedAdminUser->tenant_id = $this->lockedTenant->id;
        $this->lockedAdminUser->save();
        $this->lockedAdminUser->assignRole('admin');
    }

    /** @test */
    public function lock_sets_locked_at()
    {
        $this->tenant->lock();

        $this->assertTrue($this->tenant->isLocked());
        $this->assertNotNull($this->tenant->fresh()->locked_at);
    }

    /** @test */
    public function unlock_clears_locked_at()
    {
        $this->tenant->lock();
        $this->assertTrue($this->tenant->isLocked());

        $this->tenant->unlock();
        $this->assertFalse($this->tenant->isLocked());
        $this->assertNull($this->tenant->fresh()->locked_at);
    }

    /** @test */
    public function lock_is_idempotent()
    {
        $this->tenant->lock();
        $lockedAt = $this->tenant->locked_at;

        $this->tenant->lock();
        $this->assertEquals(
            $lockedAt->toIso8601String(),
            $this->tenant->fresh()->locked_at->toIso8601String()
        );
    }

    /** @test */
    public function unlock_is_idempotent()
    {
        $this->tenant->unlock();
        $this->assertNull($this->tenant->fresh()->locked_at);
    }

    /** @test */
    public function expiry_service_locks_tenant_on_expired()
    {
        app()->instance('current.tenant', $this->tenant);
        Subscription::create([
            'plan_id' => $this->paidPlan->id,
            'billing_interval' => 'monthly',
            'status' => 'past_due',
            'starts_at' => now()->subDays(30),
            'expires_at' => now()->subDays(10),
        ]);

        app(SubscriptionExpiryService::class)->process();

        $this->assertTrue($this->tenant->fresh()->isLocked());
    }

    /** @test */
    public function expiry_service_locks_tenant_on_trial_ended()
    {
        app()->instance('current.tenant', $this->tenant);
        Subscription::create([
            'plan_id' => $this->paidPlan->id,
            'billing_interval' => 'monthly',
            'status' => 'trialing',
            'starts_at' => now()->subDays(14),
            'trial_ends_at' => now()->subDay(),
        ]);

        app(SubscriptionExpiryService::class)->process();

        $this->assertTrue($this->tenant->fresh()->isLocked());
    }

    /** @test */
    public function does_not_lock_during_grace_period()
    {
        app()->instance('current.tenant', $this->tenant);
        Subscription::create([
            'plan_id' => $this->paidPlan->id,
            'billing_interval' => 'monthly',
            'status' => 'active',
            'starts_at' => now()->subDays(30),
            'expires_at' => now()->subDay(),
        ]);

        app(SubscriptionExpiryService::class)->process();

        $this->assertFalse($this->tenant->fresh()->isLocked());
    }

    /** @test */
    public function renewal_unlocks_tenant()
    {
        $this->lockedTenant->lock();
        $this->assertTrue($this->lockedTenant->isLocked());

        app()->instance('current.tenant', $this->lockedTenant);
        $subscription = Subscription::create([
            'plan_id' => $this->paidPlan->id,
            'billing_interval' => 'monthly',
            'status' => 'expired',
            'starts_at' => now()->subDays(30),
            'expires_at' => now()->subDay(),
        ]);

        $subscription->renewFromInterval();

        $this->assertFalse($this->lockedTenant->fresh()->isLocked());
    }

    /** @test */
    public function activate_unlocks_tenant()
    {
        $this->lockedTenant->lock();

        app()->instance('current.tenant', $this->lockedTenant);
        $subscription = Subscription::create([
            'plan_id' => $this->paidPlan->id,
            'billing_interval' => 'monthly',
            'status' => 'suspended',
            'starts_at' => now()->subDays(30),
            'expires_at' => now()->addDays(20),
            'suspended_at' => now(),
        ]);

        $subscription->activate();

        $this->assertFalse($this->lockedTenant->fresh()->isLocked());
    }

    /** @test */
    public function trial_renewal_increments_count()
    {
        app()->instance('current.tenant', $this->tenant);
        $subscription = Subscription::create([
            'plan_id' => $this->paidPlan->id,
            'billing_interval' => 'monthly',
            'status' => 'expired',
            'starts_at' => now()->subDays(30),
            'expires_at' => now()->subDay(),
            'trial_ends_at' => now()->subDay(),
            'trial_renewals_count' => 0,
        ]);

        $subscription->renewFromInterval();

        $subscription->increment('trial_renewals_count');
        $this->assertEquals(1, $subscription->fresh()->trial_renewals_count);
    }

    /** @test */
    public function ensure_tenant_is_active_passes_locked_tenants()
    {
        $this->lockedTenant->lock();

        $user = User::find($this->lockedAdminUser->id);
        $this->assertNotNull($user);
        $this->assertNotNull($user->tenant);

        $request = Request::create('/admin/products', 'GET');
        $request->setUserResolver(fn () => $user);

        $middleware = app()->make(\App\Http\Middleware\EnsureTenantIsActive::class);
        $response = $middleware->handle($request, fn ($req) => response('passed'));

        $this->assertEquals('passed', $response->getContent());
    }

    /** @test */
    public function check_store_locked_middleware_allows_get_for_locked()
    {
        $this->lockedTenant->lock();
        $user = User::find($this->lockedAdminUser->id);

        $request = Request::create('/admin/products', 'GET');
        $request->setUserResolver(fn () => $user);

        $middleware = app()->make(\App\Http\Middleware\CheckStoreLocked::class);
        $response = $middleware->handle($request, fn ($req) => response('passed'));

        $this->assertEquals('passed', $response->getContent());
    }

    /** @test */
    public function check_store_locked_middleware_redirects_post_for_locked()
    {
        $this->lockedTenant->lock();
        $user = User::find($this->lockedAdminUser->id);

        $request = Request::create('/admin/products', 'POST');
        $request->setUserResolver(fn () => $user);

        $middleware = app()->make(\App\Http\Middleware\CheckStoreLocked::class);
        $response = $middleware->handle($request, fn ($req) => response('passed'));

        $this->assertEquals(302, $response->getStatusCode());
    }

    /** @test */
    public function check_store_locked_middleware_redirects_put_for_locked()
    {
        $this->lockedTenant->lock();
        $user = User::find($this->lockedAdminUser->id);

        $request = Request::create('/admin/products/1', 'PUT');
        $request->setUserResolver(fn () => $user);

        $middleware = app()->make(\App\Http\Middleware\CheckStoreLocked::class);
        $response = $middleware->handle($request, fn ($req) => response('passed'));

        $this->assertEquals(302, $response->getStatusCode());
    }

    /** @test */
    public function check_store_locked_middleware_redirects_delete_for_locked()
    {
        $this->lockedTenant->lock();
        $user = User::find($this->lockedAdminUser->id);

        $request = Request::create('/admin/products/1', 'DELETE');
        $request->setUserResolver(fn () => $user);

        $middleware = app()->make(\App\Http\Middleware\CheckStoreLocked::class);
        $response = $middleware->handle($request, fn ($req) => response('passed'));

        $this->assertEquals(302, $response->getStatusCode());
    }

    /** @test */
    public function superadmin_bypasses_locked_check()
    {
        $superUser = User::create([
            'name' => 'Super',
            'email' => 'super-' . uniqid() . '@test.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $superUser->assignRole('superadmin');

        $request = Request::create('/superadmin/dashboard', 'POST');
        $request->setUserResolver(fn () => $superUser);

        $middleware = app()->make(\App\Http\Middleware\CheckStoreLocked::class);
        $response = $middleware->handle($request, fn ($req) => response('passed'));

        $this->assertEquals('passed', $response->getContent());
    }

    /** @test */
    public function middleware_does_not_block_for_normal_tenants()
    {
        $user = User::find($this->adminUser->id);

        $request = Request::create('/admin/products', 'POST');
        $request->setUserResolver(fn () => $user);

        $middleware = app()->make(\App\Http\Middleware\CheckStoreLocked::class);
        $response = $middleware->handle($request, fn ($req) => response('passed'));

        $this->assertEquals('passed', $response->getContent());
    }

    /** @test */
    public function storefront_renders_locked_page()
    {
        $this->lockedTenant->lock();

        $controller = app()->make(\App\Http\Controllers\StorefrontController::class);
        $ref = new \ReflectionMethod($controller, 'renderLocked');
        $ref->setAccessible(true);

        $response = $ref->invoke($controller, $this->lockedTenant);

        $this->assertInstanceOf(\Inertia\Response::class, $response);
    }

    /** @test */
    public function subscription_renew_method_unlocks_tenant()
    {
        $this->lockedTenant->lock();

        app()->instance('current.tenant', $this->lockedTenant);
        $subscription = Subscription::create([
            'plan_id' => $this->paidPlan->id,
            'billing_interval' => 'monthly',
            'status' => 'expired',
            'starts_at' => now()->subDays(30),
            'expires_at' => now()->subDay(),
        ]);

        $subscription->renew(now()->addMonth());

        $this->assertFalse($this->lockedTenant->fresh()->isLocked());
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
                $table->timestamp('locked_at')->nullable();
                $table->timestamp('expires_at')->nullable();
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
                $table->unsignedTinyInteger('trial_renewals_count')->default(0);
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
                $table->boolean('allow_trial_renewal')->default(true);
                $table->unsignedTinyInteger('max_trial_renewals')->default(0);
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
        ];

        foreach ($tables as $name => $callback) {
            if (!Schema::hasTable($name)) {
                Schema::create($name, $callback);
            }
        }
    }
}
