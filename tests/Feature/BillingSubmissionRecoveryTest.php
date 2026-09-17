<?php

namespace Tests\Feature;

use App\Data\Currency;
use App\Http\Controllers\Admin\AdminBillingController;
use App\Models\Invoice;
use App\Models\PaymentIntent;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\InvoiceService;
use App\Services\Payment\Platform\ManualPaymentService;
use App\Services\Payment\Platform\PaymentEvidenceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BillingSubmissionRecoveryTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createRecoverySchema();
    }

    public function test_normal_submission_creates_evidence_review_state_and_invoice(): void
    {
        $tenant = $this->makeTenant();
        $plan = $this->makePlan('recovery-a', 100, 1000);
        $subscription = $this->makeSubscription($tenant, $plan, 'active', now()->addMonth());

        $manual = app(ManualPaymentService::class);
        $intent = $manual->initiate(
            tenant: $tenant, plan: $plan, billingCycle: 'monthly',
            amount: 100.00, currency: Currency::fromCode('MMK'),
        );

        app(PaymentEvidenceService::class)->store(
            intent: $intent, type: 'bank_transfer', filePath: 'payment-evidence/t.png',
            senderName: 'Aung', senderAccount: '091234', transactionReference: 'TXN-NORMAL-1',
            transferredAmount: 100.00, transferDate: now()->toDateString(),
        );

        $manual->confirmPayment($intent);
        $invoice = app(InvoiceService::class)->generateFromPaymentIntent($intent->fresh());

        $this->assertSame('waiting_review', $intent->fresh()->status);
        $this->assertSame(1, $intent->evidences()->count());
        $this->assertSame(Invoice::STATUS_UNPAID, $invoice->status);
    }

    public function test_retry_after_partial_failure_creates_missing_invoice(): void
    {
        $tenant = $this->makeTenant();
        $plan = $this->makePlan('recovery-b', 100, 1000);
        $subscription = $this->makeSubscription($tenant, $plan, 'active', now()->addMonth());

        $intent = $this->makePartialIntent($tenant, $plan, $subscription);
        $this->assertSame(0, Invoice::where('payment_intent_id', $intent->id)->count());

        $this->ensureInvoice($intent);

        $this->assertSame(1, Invoice::where('payment_intent_id', $intent->id)->count());
        $this->assertSame(Invoice::STATUS_UNPAID, Invoice::where('payment_intent_id', $intent->id)->first()->status);
        $this->assertSame('waiting_review', $intent->fresh()->status);
    }

    public function test_retry_when_invoice_exists_does_not_duplicate(): void
    {
        $tenant = $this->makeTenant();
        $plan = $this->makePlan('recovery-c', 100, 1000);
        $subscription = $this->makeSubscription($tenant, $plan, 'active', now()->addMonth());

        $intent = $this->makePartialIntent($tenant, $plan, $subscription);

        $this->ensureInvoice($intent);
        $this->ensureInvoice($intent);

        $this->assertSame(1, Invoice::where('payment_intent_id', $intent->id)->count());
    }

    public function test_failed_recovery_logs_error_and_returns_safe_message(): void
    {
        Log::spy();

        $tenant = $this->makeTenant();
        $plan = $this->makePlan('recovery-d', 100, 1000);

        $intent = new PaymentIntent([
            'plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'amount' => 100,
            'currency' => 'MMK',
            'gateway' => 'manual',
            'status' => 'waiting_review',
            'reference_number' => 'PAY-' . strtoupper(uniqid()),
            'idempotency_key' => uniqid('idem-', true),
            'metadata' => [],
        ]);
        $intent->tenant_id = $tenant->id;
        $intent->save();

        try {
            $this->ensureInvoice($intent);
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Failed to submit payment. Please try again.', $e->getMessage());
        }

        Log::shouldHaveReceived('error')->atLeast()->once();
    }

    public function test_duplicate_confirm_remains_blocked_after_recovery(): void
    {
        $tenant = $this->makeTenant();
        $plan = $this->makePlan('recovery-e', 100, 1000);
        $subscription = $this->makeSubscription($tenant, $plan, 'active', now()->addMonth());

        $intent = $this->makePartialIntent($tenant, $plan, $subscription);
        $this->ensureInvoice($intent);

        $this->expectException(\InvalidArgumentException::class);
        app(ManualPaymentService::class)->confirmPayment($intent->fresh());
    }

    private function ensureInvoice(PaymentIntent $intent): void
    {
        $controller = (new \ReflectionClass(AdminBillingController::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod($controller, 'ensureIntentInvoice');
        $method->setAccessible(true);
        $method->invoke($controller, $intent);
    }

    private function makePartialIntent(Tenant $tenant, Plan $plan, Subscription $subscription): PaymentIntent
    {
        $manual = app(ManualPaymentService::class);
        $intent = $manual->initiate(
            tenant: $tenant, plan: $plan, billingCycle: 'monthly',
            amount: 100.00, currency: Currency::fromCode('MMK'),
        );

        app(PaymentEvidenceService::class)->store(
            intent: $intent, type: 'bank_transfer', filePath: 'payment-evidence/t.png',
            senderName: 'Aung', senderAccount: '091234', transactionReference: 'TXN-PARTIAL-' . uniqid(),
            transferredAmount: 100.00, transferDate: now()->toDateString(),
        );

        $manual->confirmPayment($intent);

        return $intent->fresh();
    }

    private function makeTenant(): Tenant
    {
        return Tenant::create([
            'slug' => 'submission-recovery-' . uniqid(),
            'name' => 'Submission Recovery',
            'status' => 'active',
        ]);
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

    private function createRecoverySchema(): void
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
