<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\PaymentIntent;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\InvoiceService;
use App\Services\SubscriptionLifecycleService;
use App\Services\SubscriptionPlanChangeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class BillingCoreStabilizationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createBillingSchema();
    }

    public function test_proration_total_due_matches_price_difference_on_upgrade(): void
    {
        $current = new Plan(['monthly_price' => 100, 'yearly_price' => 1000]);
        $target = new Plan(['monthly_price' => 200, 'yearly_price' => 2000]);

        $subscription = new Subscription([
            'billing_interval' => 'monthly',
            'expires_at' => now()->addDays(15),
        ]);
        $subscription->setRelation('plan', $current);

        $result = app(SubscriptionPlanChangeService::class)
            ->calculateProration($subscription, $target, 'monthly');

        $this->assertTrue($result['is_upgrade']);
        $this->assertSame(100.0, $result['price_difference']);
        $this->assertSame(100.0, $result['total_due']);
        $this->assertGreaterThan(0, $result['days_remaining']);
        $this->assertLessThanOrEqual(15, $result['days_remaining']);
        $this->assertEqualsWithDelta(
            round(100 / 30 * $result['days_remaining'], 2),
            $result['credit_amount'],
            0.01
        );
    }

    public function test_proration_total_due_is_zero_on_downgrade(): void
    {
        $current = new Plan(['monthly_price' => 200, 'yearly_price' => 2000]);
        $target = new Plan(['monthly_price' => 100, 'yearly_price' => 1000]);

        $subscription = new Subscription([
            'billing_interval' => 'monthly',
            'expires_at' => now()->addDays(15),
        ]);
        $subscription->setRelation('plan', $current);

        $result = app(SubscriptionPlanChangeService::class)
            ->calculateProration($subscription, $target, 'monthly');

        $this->assertTrue($result['is_downgrade']);
        $this->assertSame(0.0, $result['total_due']);
    }

    public function test_change_plan_preserves_current_period_on_upgrade(): void
    {
        $tenant = $this->makeTenant();
        $current = $this->makePlan('basic', 100);
        $target = $this->makePlan('pro', 200);

        $expiresAt = now()->addDays(20)->startOfDay();
        $subscription = $this->makeSubscription($tenant, $current, 'active', $expiresAt);

        $subscription->changePlan($target, 'monthly');
        $subscription->refresh();

        $this->assertSame($target->id, $subscription->plan_id);
        $this->assertEquals(
            $expiresAt->toDateTimeString(),
            $subscription->expires_at->toDateTimeString()
        );
    }

    public function test_change_plan_starts_fresh_period_when_expired(): void
    {
        $tenant = $this->makeTenant();
        $current = $this->makePlan('basic2', 100);
        $target = $this->makePlan('pro2', 200);

        $subscription = $this->makeSubscription($tenant, $current, 'expired', now()->subDays(5));

        $subscription->changePlan($target, 'monthly');
        $subscription->refresh();

        $this->assertSame($target->id, $subscription->plan_id);
        $this->assertTrue($subscription->expires_at->isFuture());
    }

    public function test_completed_payment_reactivates_suspended_subscription(): void
    {
        $tenant = $this->makeTenant(['status' => 'suspended', 'locked_at' => now()->subDay()]);
        $plan = $this->makePlan('standard', 150);

        $subscription = $this->makeSubscription($tenant, $plan, 'suspended', now()->subDays(10));
        $subscription->update(['suspended_at' => now()->subDays(9)]);

        $intent = $this->makeIntent($tenant, $plan, $subscription, 'completed');

        app(SubscriptionLifecycleService::class)->handleCompletedPayment($intent);

        $subscription->refresh();
        $tenant->refresh();

        $this->assertSame('active', $subscription->status);
        $this->assertTrue($subscription->expires_at->isFuture());
        $this->assertSame('active', $tenant->status);
        $this->assertNull($tenant->locked_at);
    }

    public function test_invoice_generation_is_idempotent_per_intent(): void
    {
        $tenant = $this->makeTenant();
        $plan = $this->makePlan('plus', 120);
        $subscription = $this->makeSubscription($tenant, $plan, 'active', now()->addMonth());
        $intent = $this->makeIntent($tenant, $plan, $subscription, 'completed');

        $service = app(InvoiceService::class);

        $first = $service->generateFromPaymentIntent($intent);
        $second = $service->generateFromPaymentIntent($intent->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Invoice::where('payment_intent_id', $intent->id)->count());
        $this->assertSame(Invoice::STATUS_PAID, $second->status);
    }

    public function test_payment_guard_rejects_duplicate_execution(): void
    {
        $tenant = $this->makeTenant();
        $plan = $this->makePlan('guard', 50);

        $manual = app(\App\Services\Payment\Platform\ManualPaymentService::class);
        $intent = $manual->initiate(
            tenant: $tenant,
            plan: $plan,
            billingCycle: 'monthly',
            amount: 50.00,
            currency: \App\Data\Currency::fromCode('USD'),
        );

        $manual->confirmPayment($intent);

        $this->expectException(\InvalidArgumentException::class);
        $manual->confirmPayment($intent->fresh());
    }

    public function test_permission_seeder_grants_billing_manage(): void
    {
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);

        $this->assertTrue(
            Permission::where('name', 'billing.manage')->where('guard_name', 'web')->exists()
        );
    }

    public function test_billing_manage_backfill_grants_tenant_admin_roles(): void
    {
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);

        $template = \App\Models\Role::withoutTenantScope()->firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => null]
        );

        $tenant = $this->makeTenant();
        $tenantRole = \App\Models\Role::withoutTenantScope()->firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]
        );

        $migration = require database_path(
            'migrations/2026_09_16_000001_grant_billing_manage_to_admin_roles.php'
        );
        $migration->up();

        $this->assertTrue($template->fresh()->hasPermissionTo('billing.manage'));
        $this->assertTrue($tenantRole->fresh()->hasPermissionTo('billing.manage'));
    }

    public function test_billing_routes_exist_on_both_prefixes(): void
    {
        $this->assertTrue(Route::has('admin.billing'));
        $this->assertTrue(Route::has('admin.billing.change-plan.execute'));
        $this->assertTrue(Route::has('admin.billing.payment.submit'));
        $this->assertTrue(Route::has('admin.billing.invoices'));
        $this->assertTrue(Route::has('storefront.admin.billing'));
        $this->assertTrue(Route::has('storefront.admin.billing.change-plan.execute'));
        $this->assertTrue(Route::has('storefront.admin.billing.payment.submit'));
        $this->assertTrue(Route::has('storefront.admin.billing.invoices'));
        $this->assertTrue(Route::has('superadmin.billing.approve'));
    }

    private function makeTenant(array $overrides = []): Tenant
    {
        return Tenant::create(array_merge([
            'slug' => 'billing-stab-' . uniqid(),
            'name' => 'Billing Stabilization',
            'status' => 'active',
        ], $overrides));
    }

    private function makePlan(string $slug, float $monthly): Plan
    {
        return Plan::create([
            'name' => ucfirst($slug),
            'slug' => $slug . '-' . uniqid(),
            'description' => 'Test plan',
            'monthly_price' => $monthly,
            'yearly_price' => $monthly * 10,
            'status' => 'active',
        ]);
    }

    private function makeSubscription(Tenant $tenant, Plan $plan, string $status, $expiresAt): Subscription
    {
        $subscription = new Subscription([
            'plan_id' => $plan->id,
            'billing_interval' => 'monthly',
            'status' => $status,
            'starts_at' => now()->subMonth(),
            'expires_at' => $expiresAt,
            'trial_renewals_count' => 0,
        ]);
        $subscription->tenant_id = $tenant->id;
        $subscription->save();

        return $subscription;
    }

    private function makeIntent(Tenant $tenant, Plan $plan, Subscription $subscription, string $status): PaymentIntent
    {
        $intent = new PaymentIntent([
            'plan_id' => $plan->id,
            'subscription_id' => $subscription->id,
            'billing_cycle' => 'monthly',
            'amount' => 150,
            'currency' => 'MMK',
            'gateway' => 'manual',
            'status' => $status,
            'reference_number' => 'PAY-' . strtoupper(uniqid()),
            'idempotency_key' => uniqid('idem-', true),
            'metadata' => [],
        ]);
        $intent->tenant_id = $tenant->id;
        $intent->save();

        return $intent;
    }

    private function createBillingSchema(): void
    {
        if (!Schema::hasTable('tenants')) {
            Schema::create('tenants', function ($table) {
                $table->id();
                $table->string('slug')->unique();
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('status')->default('active');
                $table->string('store_url')->nullable();
                $table->json('settings')->nullable();
                $table->timestamp('activated_at')->nullable();
                $table->timestamp('locked_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('plans')) {
            Schema::create('plans', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->decimal('monthly_price', 10, 2)->nullable();
                $table->decimal('yearly_price', 10, 2)->nullable();
                $table->string('status', 20)->default('active');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('subscriptions')) {
            Schema::create('subscriptions', function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('plan_id')->nullable();
                $table->string('billing_interval')->nullable();
                $table->string('status');
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('trial_ends_at')->nullable();
                $table->integer('trial_renewals_count')->default(0);
                $table->unsignedBigInteger('pending_plan_id')->nullable();
                $table->timestamp('pending_plan_effective_at')->nullable();
                $table->timestamp('suspended_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index('tenant_id');
            });
        }

        if (!Schema::hasTable('payment_intents')) {
            Schema::create('payment_intents', function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('plan_id');
                $table->unsignedBigInteger('subscription_id')->nullable();
                $table->string('billing_cycle');
                $table->decimal('amount', 10, 2);
                $table->string('currency', 3);
                $table->string('gateway');
                $table->string('status');
                $table->timestamp('expires_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->string('reference_number')->nullable()->unique();
                $table->string('idempotency_key', 64)->nullable();
                $table->timestamps();
                $table->index('tenant_id');
                $table->index('status');
            });
        }

        if (!Schema::hasTable('invoices')) {
            Schema::create('invoices', function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('invoice_number')->unique();
                $table->unsignedBigInteger('subscription_id')->nullable();
                $table->unsignedBigInteger('plan_id')->nullable();
                $table->string('billing_interval')->nullable();
                $table->date('billing_period_start')->nullable();
                $table->date('billing_period_end')->nullable();
                $table->decimal('amount', 10, 2)->default(0);
                $table->decimal('subtotal', 10, 2)->default(0);
                $table->decimal('tax', 10, 2)->default(0);
                $table->decimal('total', 10, 2)->default(0);
                $table->string('currency', 3)->default('MMK');
                $table->string('status')->default('unpaid');
                $table->unsignedBigInteger('payment_intent_id')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('issued_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->json('line_items')->nullable();
                $table->timestamp('deleted_at')->nullable();
                $table->timestamps();
                $table->index(['tenant_id', 'status']);
            });
        }

        if (!Schema::hasTable('payment_timeline_events')) {
            Schema::create('payment_timeline_events', function ($table) {
                $table->id();
                $table->unsignedBigInteger('payment_intent_id');
                $table->string('type');
                $table->text('description')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('occurred_at');
                $table->timestamps();
                $table->index('payment_intent_id');
            });
        }

        if (!Schema::hasTable('subscription_audit_logs')) {
            Schema::create('subscription_audit_logs', function ($table) {
                $table->id();
                $table->unsignedBigInteger('subscription_id');
                $table->unsignedBigInteger('tenant_id');
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
            });
        }

        if (!Schema::hasTable('users')) {
            Schema::create('users', function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('password')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('roles')) {
            Schema::create('roles', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('guard_name');
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->timestamps();
                $table->unique(['name', 'guard_name', 'tenant_id']);
            });
        }

        if (!Schema::hasTable('model_has_roles')) {
            Schema::create('model_has_roles', function ($table) {
                $table->unsignedBigInteger('role_id');
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
                $table->index(['model_id', 'model_type']);
            });
        }

        if (!Schema::hasTable('permissions')) {
            Schema::create('permissions', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('guard_name');
                $table->timestamps();
                $table->unique(['name', 'guard_name']);
            });
        }

        if (!Schema::hasTable('role_has_permissions')) {
            Schema::create('role_has_permissions', function ($table) {
                $table->unsignedBigInteger('permission_id');
                $table->unsignedBigInteger('role_id');
                $table->primary(['permission_id', 'role_id']);
            });
        }

        if (!Schema::hasTable('reference_numbers')) {
            Schema::create('reference_numbers', function ($table) {
                $table->id();
                $table->string('prefix', 10);
                $table->date('date');
                $table->unsignedInteger('last_sequence')->default(0);
                $table->timestamps();
                $table->unique(['prefix', 'date']);
            });
        }
    }
}
