<?php

namespace Tests\Feature;

use App\Data\Currency;
use App\Models\Invoice;
use App\Models\PaymentIntent;
use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\Receipt;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\InvoiceService;
use App\Services\Payment\Platform\CheckoutService;
use App\Services\Payment\Platform\ManualPaymentService;
use App\Services\ReceiptService;
use App\Services\SubscriptionExpiryService;
use App\Services\SubscriptionLifecycleService;
use App\Services\SubscriptionPlanChangeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BillingFinalAuditTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createAuditSchema();
    }

    public function test_trial_converts_to_active_on_payment(): void
    {
        $tenant = $this->makeTenant();
        $plan = $this->makePlan('trial-pro', 200, 2000);
        $subscription = $this->makeSubscription($tenant, $plan, 'trialing', now()->addDays(5));
        $subscription->update(['trial_ends_at' => now()->addDays(5)]);

        $intent = $this->makeIntent($tenant, $plan, $subscription, 'completed');

        app(SubscriptionLifecycleService::class)->handleCompletedPayment($intent);
        $subscription->refresh();

        $this->assertSame('active', $subscription->status);
        $this->assertTrue($subscription->expires_at->isFuture());
        $this->assertTrue($subscription->trial_ends_at->isPast());
    }

    public function test_active_moves_to_past_due_after_expiry(): void
    {
        $tenant = $this->makeTenant();
        $plan = $this->makePlan('lifecycle-a', 100, 1000);
        $this->makeSubscription($tenant, $plan, 'active', now()->subDay());

        $result = app(SubscriptionExpiryService::class)->process();

        $this->assertSame(1, $result['active_to_past_due']);
        $this->assertSame('past_due', $tenant->subscription()->first()->status);
    }

    public function test_past_due_moves_to_expired_after_grace_and_locks(): void
    {
        $tenant = $this->makeTenant();
        $plan = $this->makePlan('lifecycle-b', 100, 1000);
        $this->makeSubscription($tenant, $plan, 'past_due', now()->subDays(8));

        $result = app(SubscriptionExpiryService::class)->process();

        $this->assertSame(1, $result['past_due_to_expired']);
        $this->assertSame('expired', $tenant->subscription()->first()->status);
        $this->assertNotNull($tenant->fresh()->locked_at);
    }

    public function test_expired_moves_to_suspended_and_suspends_tenant(): void
    {
        $tenant = $this->makeTenant();
        $plan = $this->makePlan('lifecycle-c', 100, 1000);
        $subscription = $this->makeSubscription($tenant, $plan, 'expired', now()->subDays(10));

        DB::table('subscriptions')->where('id', $subscription->id)->update([
            'updated_at' => now()->subDays(2),
        ]);

        $result = app(SubscriptionExpiryService::class)->process();

        $this->assertSame(1, $result['expired_to_suspended']);
        $this->assertSame('suspended', $subscription->fresh()->status);
        $this->assertSame('suspended', $tenant->fresh()->status);
        $this->assertNotNull($tenant->fresh()->locked_at);
    }

    public function test_scheduled_downgrade_applies_at_effective_date(): void
    {
        $tenant = $this->makeTenant();
        $current = $this->makePlan('downgrade-big', 300, 3000);
        $target = $this->makePlan('downgrade-small', 100, 1000);
        $subscription = $this->makeSubscription($tenant, $current, 'active', now()->addDays(20));

        app(SubscriptionPlanChangeService::class)->executeDowngrade($subscription, $target, 'monthly');
        $this->assertTrue($subscription->fresh()->hasPendingDowngrade());

        $subscription->update(['pending_plan_effective_at' => now()->subMinute()]);

        $applied = app(SubscriptionPlanChangeService::class)->applyScheduledChanges();

        $this->assertSame(1, $applied);
        $subscription->refresh();
        $this->assertSame($target->id, $subscription->plan_id);
        $this->assertFalse($subscription->hasPendingDowngrade());
    }

    public function test_rejected_payment_allows_brand_new_payment(): void
    {
        $tenant = $this->makeTenant();
        $plan = $this->makePlan('reject-retry', 150, 1500);

        $manual = app(ManualPaymentService::class);
        $first = $manual->initiate(
            tenant: $tenant, plan: $plan, billingCycle: 'monthly',
            amount: 150.00, currency: Currency::fromCode('MMK'),
        );
        $manual->confirmPayment($first);
        $manual->rejectPayment($first->fresh(), 'Blurry evidence');

        $this->assertSame('rejected', $first->fresh()->status);

        $second = $manual->initiate(
            tenant: $tenant, plan: $plan, billingCycle: 'monthly',
            amount: 150.00, currency: Currency::fromCode('MMK'),
        );

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame('waiting_payment', $second->status);
    }

    public function test_invoice_receipt_transaction_are_consistent(): void
    {
        $tenant = $this->makeTenant();
        $plan = $this->makePlan('consistency', 250, 2500);
        $subscription = $this->makeSubscription($tenant, $plan, 'expired', now()->subDays(2));

        $manual = app(ManualPaymentService::class);
        $intent = $manual->initiate(
            tenant: $tenant, plan: $plan, billingCycle: 'monthly',
            amount: 250.00, currency: Currency::fromCode('MMK'),
        );
        $manual->confirmPayment($intent);
        $manual->approvePayment($intent->fresh());
        $intent = $intent->fresh();

        app(SubscriptionLifecycleService::class)->handleCompletedPayment($intent);
        $invoice = app(InvoiceService::class)->generateFromPaymentIntent($intent);
        $receipt = app(ReceiptService::class)->createFromCompletedIntent($intent);

        $this->assertSame('completed', $intent->status);
        $this->assertSame(Invoice::STATUS_PAID, $invoice->status);
        $this->assertEqualsWithDelta((float) $intent->amount, (float) $invoice->total, 0.001);
        $this->assertEqualsWithDelta((float) $intent->amount, (float) $receipt->amount, 0.001);
        $this->assertSame($invoice->id, $receipt->invoice_id);
        $this->assertSame($intent->id, $receipt->payment_intent_id);
        $this->assertTrue(PaymentTransaction::where('payment_intent_id', $intent->id)->exists());
        $this->assertMatchesRegularExpression('/^INV-\d{4}-\d{5}$/', $invoice->invoice_number);
        $this->assertMatchesRegularExpression('/^REC-\d{4}-\d{5}$/', $receipt->receipt_number);
    }

    public function test_tenant_isolation_of_billing_records(): void
    {
        $tenantA = $this->makeTenant();
        $tenantB = $this->makeTenant();
        $plan = $this->makePlan('isolation', 100, 1000);

        $subA = $this->makeSubscription($tenantA, $plan, 'active', now()->addMonth());
        $subB = $this->makeSubscription($tenantB, $plan, 'active', now()->addMonth());

        $intentA = $this->makeIntent($tenantA, $plan, $subA, 'waiting_payment');
        $this->makeIntent($tenantB, $plan, $subB, 'waiting_payment');

        $visibleToA = PaymentIntent::forTenant($tenantA->id)->pluck('id')->all();

        $this->assertContains($intentA->id, $visibleToA);
        $this->assertCount(1, $visibleToA);
    }

    public function test_billing_routes_registered_on_both_prefixes(): void
    {
        $uris = collect(Route::getRoutes())->map->uri()->all();

        foreach ([
            'admin/billing',
            'admin/billing/payment/submit',
            'admin/billing/change-plan/execute',
            'admin/billing/invoices',
            'store/{store_slug}/admin/billing',
            'store/{store_slug}/admin/billing/payment/submit',
            'store/{store_slug}/admin/billing/change-plan/execute',
            'store/{store_slug}/admin/billing/invoices',
            'store/{store_slug}/checkout',
            'superadmin/billing/{intent}/approve',
        ] as $uri) {
            $this->assertContains($uri, $uris, "Missing route URI: {$uri}");
        }
    }

    public function test_subscription_cron_commands_are_registered(): void
    {
        $commands = array_keys(Artisan::all());

        foreach ([
            'subscriptions:process-expired',
            'subscriptions:send-expiry-warnings',
            'subscriptions:send-reminders',
            'subscriptions:apply-scheduled-changes',
        ] as $command) {
            $this->assertContains($command, $commands, "Missing command: {$command}");
        }
    }

    private function makeTenant(array $overrides = []): Tenant
    {
        return Tenant::create(array_merge([
            'slug' => 'final-audit-' . uniqid(),
            'name' => 'Final Audit',
            'status' => 'active',
        ], $overrides));
    }

    private function makePlan(string $slug, float $monthly, float $yearly): Plan
    {
        return Plan::create([
            'name' => ucfirst($slug),
            'slug' => $slug . '-' . uniqid(),
            'description' => 'Test plan',
            'monthly_price' => $monthly,
            'yearly_price' => $yearly,
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
            'amount' => 100,
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

    private function createAuditSchema(): void
    {
        foreach (['tenants' => function ($table) {
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
        }, 'plans' => function ($table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('monthly_price', 10, 2)->nullable();
            $table->decimal('yearly_price', 10, 2)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        }, 'subscriptions' => function ($table) {
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
        }, 'payment_intents' => function ($table) {
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
        }, 'invoices' => function ($table) {
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
        }, 'receipts' => function ($table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('payment_intent_id')->nullable();
            $table->string('receipt_number')->unique();
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('currency', 3)->default('MMK');
            $table->timestamp('paid_at')->nullable();
            $table->json('details')->nullable();
            $table->timestamps();
        }, 'payment_transactions' => function ($table) {
            $table->id();
            $table->unsignedBigInteger('payment_intent_id')->unique();
            $table->string('transaction_number')->unique();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('plan_id');
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3);
            $table->string('gateway');
            $table->string('status')->default('completed');
            $table->string('gateway_reference')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index('tenant_id');
        }, 'ledger_entries' => function ($table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->unsignedBigInteger('payment_intent_id')->nullable();
            $table->string('type');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3);
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();
        }, 'payment_timeline_events' => function ($table) {
            $table->id();
            $table->unsignedBigInteger('payment_intent_id');
            $table->string('type');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index('payment_intent_id');
        }, 'subscription_audit_logs' => function ($table) {
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
        }, 'users' => function ($table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
        }, 'reference_numbers' => function ($table) {
            $table->id();
            $table->string('prefix', 10);
            $table->date('date');
            $table->unsignedInteger('last_sequence')->default(0);
            $table->timestamps();
            $table->unique(['prefix', 'date']);
        }] as $name => $blueprint) {
            if (!Schema::hasTable($name)) {
                Schema::create($name, $blueprint);
            }
        }
    }
}
