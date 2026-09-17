<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\PaymentIntent;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BillingInvoiceNumberTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createNumberSchema();
        $this->forgetCurrentTenant();
    }

    protected function tearDown(): void
    {
        $this->forgetCurrentTenant();

        parent::tearDown();
    }

    public function test_normal_creation_uses_yearly_sequential_format(): void
    {
        [$tenant, $plan, $subscription] = $this->makeStack();
        $intent = $this->makeIntent($tenant, $plan, $subscription);

        $invoice = app(InvoiceService::class)->generateFromPaymentIntent($intent);

        $this->assertMatchesRegularExpression('/^INV-\d{4}-\d{5}$/', $invoice->invoice_number);
    }

    public function test_existing_invoice_for_intent_is_reused(): void
    {
        [$tenant, $plan, $subscription] = $this->makeStack();
        $intent = $this->makeIntent($tenant, $plan, $subscription);

        $service = app(InvoiceService::class);
        $first = $service->generateFromPaymentIntent($intent);
        $second = $service->generateFromPaymentIntent($intent->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Invoice::withoutTenantScope()->where('payment_intent_id', $intent->id)->count());
    }

    public function test_new_tenant_does_not_reuse_another_tenants_number(): void
    {
        [$tenantA, $planA, $subA] = $this->makeStack();
        Tenant::setCurrent($tenantA);
        $intentA = $this->makeIntent($tenantA, $planA, $subA);
        $invoiceA = app(InvoiceService::class)->generateFromPaymentIntent($intentA);
        $this->forgetCurrentTenant();

        [$tenantB, $planB, $subB] = $this->makeStack();
        Tenant::setCurrent($tenantB);
        $intentB = $this->makeIntent($tenantB, $planB, $subB);
        $invoiceB = app(InvoiceService::class)->generateFromPaymentIntent($intentB);
        $this->forgetCurrentTenant();

        $this->assertNotSame($invoiceA->invoice_number, $invoiceB->invoice_number);
        $this->assertGreaterThan(
            (int) substr($invoiceA->invoice_number, -5),
            (int) substr($invoiceB->invoice_number, -5)
        );
    }

    public function test_sequence_continues_past_nine_invoices(): void
    {
        [$tenant, $plan, $subscription] = $this->makeStack();
        $service = app(InvoiceService::class);

        $numbers = [];
        for ($i = 0; $i < 12; $i++) {
            $numbers[] = $service->generateFromPaymentIntent(
                $this->makeIntent($tenant, $plan, $subscription)
            )->invoice_number;
        }

        $sequences = array_map(fn ($n) => (int) substr($n, -5), $numbers);
        $this->assertCount(12, array_unique($numbers));

        foreach ($sequences as $index => $sequence) {
            if ($index === 0) {
                continue;
            }
            $this->assertSame($sequences[$index - 1] + 1, $sequence);
        }
    }

    public function test_rapid_multi_tenant_creation_stays_unique_and_sequential(): void
    {
        [$tenantA, $planA, $subA] = $this->makeStack();
        [$tenantB, $planB, $subB] = $this->makeStack();
        $service = app(InvoiceService::class);

        $before = (int) substr(Invoice::generateNumber(), -5);

        $numbers = [];
        for ($i = 0; $i < 3; $i++) {
            $numbers[] = $service->generateFromPaymentIntent($this->makeIntent($tenantA, $planA, $subA))->invoice_number;
            $numbers[] = $service->generateFromPaymentIntent($this->makeIntent($tenantB, $planB, $subB))->invoice_number;
        }

        $this->assertCount(6, array_unique($numbers));
        $sequences = array_map(fn ($n) => (int) substr($n, -5), $numbers);
        sort($sequences);
        $this->assertSame(range($before, $before + 5), $sequences);
    }

    private function forgetCurrentTenant(): void
    {
        if (app()->bound('current.tenant')) {
            app()->forgetInstance('current.tenant');
        }
    }

    private function makeStack(): array
    {
        $tenant = Tenant::create([
            'slug' => 'invoice-num-' . uniqid(),
            'name' => 'Invoice Numbers',
            'status' => 'active',
        ]);
        $plan = Plan::create([
            'name' => 'Numbered',
            'slug' => 'numbered-' . uniqid(),
            'description' => 'Test plan',
            'monthly_price' => 100,
            'yearly_price' => 1000,
            'status' => 'active',
        ]);
        $subscription = new Subscription([
            'plan_id' => $plan->id,
            'billing_interval' => 'monthly',
            'status' => 'active',
            'starts_at' => now()->subMonth(),
            'expires_at' => now()->addMonth(),
            'trial_renewals_count' => 0,
        ]);
        $subscription->tenant_id = $tenant->id;
        $subscription->save();

        return [$tenant, $plan, $subscription];
    }

    private function makeIntent(Tenant $tenant, Plan $plan, Subscription $subscription): PaymentIntent
    {
        $intent = new PaymentIntent([
            'plan_id' => $plan->id,
            'subscription_id' => $subscription->id,
            'billing_cycle' => 'monthly',
            'amount' => 100,
            'currency' => 'MMK',
            'gateway' => 'manual',
            'status' => 'waiting_payment',
            'reference_number' => 'PAY-' . strtoupper(uniqid()),
            'idempotency_key' => uniqid('idem-', true),
            'metadata' => [],
        ]);
        $intent->tenant_id = $tenant->id;
        $intent->save();

        return $intent;
    }

    private function createNumberSchema(): void
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
    }
}
