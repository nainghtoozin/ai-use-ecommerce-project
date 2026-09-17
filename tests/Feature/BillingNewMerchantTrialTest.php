<?php

namespace Tests\Feature;

use App\Data\Currency;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Payment\Platform\ManualPaymentService;
use App\Services\SubscriptionLifecycleService;
use App\Services\SubscriptionPlanChangeService;
use App\Services\TenantBootstrapService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BillingNewMerchantTrialTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTrialSchema();
        PlatformSetting::clearCache();
    }

    protected function tearDown(): void
    {
        PlatformSetting::clearCache();

        parent::tearDown();
    }

    public function test_new_merchant_gets_starter_plan(): void
    {
        $this->seedPlans();
        $tenant = $this->makeTenant();

        $subscription = $this->bootstrapSubscription($tenant);

        $this->assertSame('free', $subscription->plan->slug);
        $this->assertSame('Starter', $subscription->plan->name);
    }

    public function test_new_merchant_does_not_get_pro_as_trial_plan(): void
    {
        $this->seedPlans();
        $tenant = $this->makeTenant();

        $subscription = $this->bootstrapSubscription($tenant);

        $this->assertNotSame('starter', $subscription->plan->slug);
        $this->assertNotSame('Pro', $subscription->plan->name);
    }

    public function test_starter_trial_duration_uses_configured_trial_days(): void
    {
        $this->seedPlans();
        PlatformSetting::query()->firstOrFail()->update(['trial_days' => 21]);
        PlatformSetting::clearCache();

        $tenant = $this->makeTenant();
        $subscription = $this->bootstrapSubscription($tenant);

        $this->assertSame(
            now()->addDays(21)->toDateString(),
            $subscription->trial_ends_at->toDateString()
        );
    }

    public function test_starter_trial_status_and_expiry_are_correct(): void
    {
        $this->seedPlans();
        $tenant = $this->makeTenant();

        $subscription = $this->bootstrapSubscription($tenant);
        $tenant->refresh();

        $this->assertSame('trialing', $subscription->status);
        $this->assertTrue($subscription->isTrialing());
        $this->assertEquals(
            $subscription->trial_ends_at->toDateString(),
            $subscription->expires_at->toDateString()
        );
        $this->assertSame('trialing', $tenant->status);
    }

    public function test_billing_reports_starter_as_current_plan(): void
    {
        $this->seedPlans();
        $tenant = $this->makeTenant();

        $subscription = $this->bootstrapSubscription($tenant);

        $current = $tenant->subscription()->first();
        $this->assertSame($subscription->id, $current->id);
        $this->assertSame('Starter', $current->plan->name);
        $this->assertTrue($subscription->isUpgrade(Plan::where('slug', 'starter')->first()));
        $this->assertTrue($subscription->isUpgrade(Plan::where('slug', 'business')->first()));
    }

    public function test_pro_upgrade_creates_payment_intent_flow(): void
    {
        $this->seedPlans([
            ['slug' => 'starter', 'monthly_price' => 50000, 'yearly_price' => 500000],
        ]);
        $tenant = $this->makeTenant();
        $this->bootstrapSubscription($tenant);

        $pro = Plan::where('slug', 'starter')->firstOrFail();
        $manual = app(ManualPaymentService::class);
        $intent = $manual->initiate(
            tenant: $tenant, plan: $pro, billingCycle: 'monthly',
            amount: 50000.00, currency: Currency::fromCode('MMK'),
        );

        $this->assertSame('waiting_payment', $intent->status);
        $this->assertSame($pro->id, $intent->plan_id);
        $this->assertEqualsWithDelta(50000.0, (float) $intent->amount, 0.001);
    }

    public function test_business_upgrade_creates_payment_intent_flow(): void
    {
        $this->seedPlans([
            ['slug' => 'business', 'monthly_price' => 1000000, 'yearly_price' => 10000000],
        ]);
        $tenant = $this->makeTenant();
        $this->bootstrapSubscription($tenant);

        $business = Plan::where('slug', 'business')->firstOrFail();
        $manual = app(ManualPaymentService::class);
        $intent = $manual->initiate(
            tenant: $tenant, plan: $business, billingCycle: 'monthly',
            amount: 1000000.00, currency: Currency::fromCode('MMK'),
        );

        $this->assertSame('waiting_payment', $intent->status);
        $this->assertEqualsWithDelta(1000000.0, (float) $intent->amount, 0.001);
    }

    public function test_paid_upgrade_does_not_activate_before_approval(): void
    {
        $this->seedPlans();
        $tenant = $this->makeTenant();
        $subscription = $this->bootstrapSubscription($tenant);
        $pro = Plan::where('slug', 'starter')->firstOrFail();

        $manual = app(ManualPaymentService::class);
        $intent = $manual->initiate(
            tenant: $tenant, plan: $pro, billingCycle: 'monthly',
            amount: (float) $pro->monthly_price, currency: Currency::fromCode('MMK'),
        );
        $manual->confirmPayment($intent);

        $subscription->refresh();

        $this->assertSame('trialing', $subscription->status);
        $this->assertSame('free', $subscription->plan->slug);
    }

    public function test_paid_upgrade_activates_selected_plan_after_approval(): void
    {
        $this->seedPlans();
        $tenant = $this->makeTenant();
        $subscription = $this->bootstrapSubscription($tenant);
        $pro = Plan::where('slug', 'starter')->firstOrFail();

        $manual = app(ManualPaymentService::class);
        $intent = $manual->initiate(
            tenant: $tenant, plan: $pro, billingCycle: 'monthly',
            amount: (float) $pro->monthly_price, currency: Currency::fromCode('MMK'),
        );
        $manual->confirmPayment($intent);
        $manual->approvePayment($intent->fresh());

        app(SubscriptionLifecycleService::class)->handleCompletedPayment($intent->fresh());
        $subscription->refresh();

        $this->assertSame('active', $subscription->status);
        $this->assertSame($pro->id, $subscription->plan_id);
        $this->assertSame('Pro', $subscription->plan->name);
    }

    public function test_existing_merchant_subscription_is_not_modified(): void
    {
        $this->seedPlans();
        $tenant = $this->makeTenant();
        $pro = Plan::where('slug', 'starter')->firstOrFail();

        $existing = new Subscription([
            'plan_id' => $pro->id,
            'billing_interval' => 'monthly',
            'status' => 'active',
            'starts_at' => now()->subMonth(),
            'expires_at' => now()->addMonth(),
            'trial_renewals_count' => 0,
        ]);
        $existing->tenant_id = $tenant->id;
        $existing->save();

        $result = $this->bootstrapSubscription($tenant);

        $this->assertSame($existing->id, $result->id);
        $this->assertSame('starter', $result->fresh()->plan->slug);
        $this->assertSame('active', $result->fresh()->status);
    }

    public function test_plan_ids_and_slugs_remain_stable(): void
    {
        $this->seedPlans();

        $this->assertSame(
            ['business', 'free', 'starter'],
            Plan::whereIn('slug', ['free', 'starter', 'business'])->orderBy('slug')->pluck('slug')->all()
        );
    }

    private function bootstrapSubscription(Tenant $tenant): Subscription
    {
        $service = app(TenantBootstrapService::class);
        $method = new \ReflectionMethod($service, 'createSubscription');
        $method->setAccessible(true);

        return $method->invoke($service, $tenant, null, 'pending');
    }

    private function seedPlans(array $overrides = []): void
    {
        $defaults = [
            'free' => ['Starter', 0, 0],
            'starter' => ['Pro', 9.99, 99.99],
            'business' => ['Business', 29.99, 299.99],
        ];

        foreach ($defaults as $slug => [$name, $monthly, $yearly]) {
            foreach ($overrides as $override) {
                if ($override['slug'] === $slug) {
                    $monthly = $override['monthly_price'];
                    $yearly = $override['yearly_price'];
                }
            }

            Plan::updateOrCreate(['slug' => $slug], [
                'name' => $name,
                'monthly_price' => $monthly,
                'yearly_price' => $yearly,
                'status' => 'active',
            ]);
        }

        PlatformSetting::query()->delete();
        PlatformSetting::create([
            'trial_enabled' => true,
            'trial_days' => 14,
        ]);
        PlatformSetting::clearCache();
    }

    private function makeTenant(): Tenant
    {
        return Tenant::create([
            'slug' => 'new-merchant-' . uniqid(),
            'name' => 'New Merchant',
            'status' => 'pending',
        ]);
    }

    private function createTrialSchema(): void
    {
        if (!Schema::hasTable('tenants')) {
            Schema::create('tenants', function ($table) {
                $table->id();
                $table->string('slug')->unique();
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('status')->default('pending');
                $table->string('store_url')->nullable();
                $table->json('settings')->nullable();
                $table->unsignedBigInteger('subscription_plan_id')->nullable();
                $table->timestamp('expires_at')->nullable();
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
                $table->timestamp('extra_renewal_used_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index('tenant_id');
            });
        } elseif (!Schema::hasColumn('subscriptions', 'extra_renewal_used_at')) {
            Schema::table('subscriptions', function ($table) {
                $table->timestamp('extra_renewal_used_at')->nullable();
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

        if (!Schema::hasTable('platform_settings')) {
            Schema::create('platform_settings', function ($table) {
                $table->id();
                $table->string('site_name')->nullable();
                $table->boolean('trial_enabled')->default(true);
                $table->integer('trial_days')->default(14);
                $table->boolean('allow_trial_renewal')->default(false);
                $table->integer('max_trial_renewals')->default(0);
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
