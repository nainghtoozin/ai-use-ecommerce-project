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
use App\Services\SubscriptionLifecycleService;
use App\Services\SubscriptionPlanChangeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BillingCanonicalFlowTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createFlowSchema();
    }

    public function test_checkout_creates_intent_with_plan_price_and_cycle(): void
    {
        $tenant = $this->makeTenant();
        $plan = $this->makePlan('growth', 199, 1990);

        $intent = $this->checkout()->initiateCheckout(
            tenant: $tenant,
            plan: $plan,
            billingCycle: 'yearly',
            amount: (float) $plan->getPriceForInterval('yearly'),
            currency: Currency::fromCode('MMK'),
            gateway: 'manual',
            metadata: ['source' => 'merchant_checkout'],
        );

        $this->assertSame('waiting_payment', $intent->status);
        $this->assertSame($plan->id, $intent->plan_id);
        $this->assertSame('yearly', $intent->billing_cycle);
        $this->assertEqualsWithDelta(1990.0, (float) $intent->amount, 0.001);
    }

    public function test_checkout_reuses_pending_intent_for_same_plan_cycle(): void
    {
        $tenant = $this->makeTenant();
        $plan = $this->makePlan('growth2', 199, 1990);

        $args = [
            'tenant' => $tenant,
            'plan' => $plan,
            'billingCycle' => 'monthly',
            'amount' => 199.0,
            'currency' => Currency::fromCode('MMK'),
            'gateway' => 'manual',
        ];

        $first = $this->checkout()->initiateCheckout(...$args);
        $second = $this->checkout()->initiateCheckout(...$args);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, PaymentIntent::where('tenant_id', $tenant->id)->count());
    }

    public function test_full_paid_flow_from_pending_to_active_with_documents(): void
    {
        $tenant = $this->makeTenant();
        $plan = $this->makePlan('scale', 299, 2990);
        $subscription = $this->makeSubscription($tenant, $plan, 'expired', now()->subDays(3));

        $manual = app(ManualPaymentService::class);
        $intent = $manual->initiate(
            tenant: $tenant,
            plan: $plan,
            billingCycle: 'monthly',
            amount: 299.00,
            currency: Currency::fromCode('MMK'),
        );
        $this->assertSame('waiting_payment', $intent->status);

        $manual->confirmPayment($intent);
        $this->assertSame('waiting_review', $intent->fresh()->status);

        $manual->approvePayment($intent->fresh());
        $intent = $intent->fresh();
        $this->assertSame('completed', $intent->status);

        app(SubscriptionLifecycleService::class)->handleCompletedPayment($intent);

        $subscription->refresh();
        $this->assertSame('active', $subscription->status);
        $this->assertTrue($subscription->expires_at->isFuture());

        $invoice = app(InvoiceService::class)->generateFromPaymentIntent($intent);
        $this->assertSame(Invoice::STATUS_PAID, $invoice->status);
        $this->assertEqualsWithDelta(299.0, (float) $invoice->total, 0.001);

        $receipt = app(ReceiptService::class)->createFromCompletedIntent($intent);
        $this->assertSame($invoice->id, $receipt->invoice_id);
        $this->assertTrue(PaymentTransaction::where('payment_intent_id', $intent->id)->exists());
    }

    public function test_free_upgrade_keeps_period_and_creates_no_intent(): void
    {
        $tenant = $this->makeTenant();
        $current = $this->makePlan('starterx', 100, 1000);
        $target = $this->makePlan('prox', 200, 2000);

        $expiresAt = now()->addDays(20)->startOfDay();
        $subscription = $this->makeSubscription($tenant, $current, 'active', $expiresAt);

        app(SubscriptionPlanChangeService::class)->executeUpgrade($subscription, $target, 'monthly');
        $subscription->refresh();

        $this->assertSame($target->id, $subscription->plan_id);
        $this->assertEquals($expiresAt->toDateTimeString(), $subscription->expires_at->toDateTimeString());
        $this->assertSame(0, PaymentIntent::where('tenant_id', $tenant->id)->count());
    }

    public function test_free_renewal_extends_by_interval(): void
    {
        $tenant = $this->makeTenant();
        $plan = $this->makePlan('renewx', 120, 1200);

        $expiresAt = now()->addDays(10)->startOfDay();
        $subscription = $this->makeSubscription($tenant, $plan, 'active', $expiresAt);

        $subscription->renewFromInterval();
        $subscription->refresh();

        $this->assertSame('active', $subscription->status);
        $this->assertEquals(
            $expiresAt->copy()->addMonth()->endOfDay()->toDateString(),
            $subscription->expires_at->toDateString()
        );
    }

    public function test_downgrade_schedules_for_future_expiry(): void
    {
        $tenant = $this->makeTenant();
        $current = $this->makePlan('bigx', 300, 3000);
        $target = $this->makePlan('smallx', 100, 1000);

        $subscription = $this->makeSubscription($tenant, $current, 'active', now()->addDays(20));

        app(SubscriptionPlanChangeService::class)->executeDowngrade($subscription, $target, 'monthly');
        $subscription->refresh();

        $this->assertSame($current->id, $subscription->plan_id);
        $this->assertSame($target->id, $subscription->pending_plan_id);
        $this->assertTrue($subscription->hasPendingDowngrade());
    }

    public function test_evidence_metadata_persists_transfer_time(): void
    {
        $tenant = $this->makeTenant();
        $plan = $this->makePlan('evidence-time', 100, 1000);

        $intent = app(\App\Services\Payment\Platform\ManualPaymentService::class)->initiate(
            tenant: $tenant,
            plan: $plan,
            billingCycle: 'monthly',
            amount: 100.00,
            currency: \App\Data\Currency::fromCode('MMK'),
        );

        $evidence = app(\App\Services\Payment\Platform\PaymentEvidenceService::class)->store(
            intent: $intent,
            type: 'bank_transfer',
            filePath: 'payment-evidence/test.png',
            note: null,
            metadata: ['transfer_time' => '14:30'],
            senderName: 'Aung',
            senderAccount: '09123456789',
            transactionReference: 'TXN-1',
            transferredAmount: 100.00,
            transferDate: now()->toDateString(),
        );

        $this->assertSame('14:30', $evidence->fresh()->metadata['transfer_time']);
    }

    private function checkout(): CheckoutService
    {
        return app(CheckoutService::class);
    }

    private function makeTenant(array $overrides = []): Tenant
    {
        return Tenant::create(array_merge([
            'slug' => 'billing-flow-' . uniqid(),
            'name' => 'Billing Flow',
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

    private function createFlowSchema(): void
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

        if (!Schema::hasTable('receipts')) {
            Schema::create('receipts', function ($table) {
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
            });
        }

        if (!Schema::hasTable('payment_transactions')) {
            Schema::create('payment_transactions', function ($table) {
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
            });
        }

        if (!Schema::hasTable('ledger_entries')) {
            Schema::create('ledger_entries', function ($table) {
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
            });
        }

        if (!Schema::hasTable('payment_evidences')) {
            Schema::create('payment_evidences', function ($table) {
                $table->id();
                $table->unsignedBigInteger('payment_intent_id');
                $table->string('type');
                $table->string('file_path')->nullable();
                $table->text('note')->nullable();
                $table->json('metadata')->nullable();
                $table->string('sender_name')->nullable();
                $table->string('sender_account')->nullable();
                $table->string('transaction_reference')->nullable();
                $table->decimal('transferred_amount', 10, 2)->nullable();
                $table->date('transfer_date')->nullable();
                $table->timestamps();
                $table->index('payment_intent_id');
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
