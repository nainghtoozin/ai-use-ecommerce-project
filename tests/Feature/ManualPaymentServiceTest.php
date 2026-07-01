<?php

namespace Tests\Feature;

use App\Data\Currency;
use App\Enums\Payment\TransactionStatus;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Payment\Platform\CheckoutService;
use App\Services\Payment\Platform\ManualPaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ManualPaymentServiceTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;
    private Plan $plan;
    private ManualPaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createMinimalSchema();

        $this->tenant = Tenant::create([
            'slug' => 'manual-test',
            'name' => 'Manual Payment Test',
            'status' => 'active',
        ]);

        $this->plan = Plan::create([
            'name' => 'Starter', 'slug' => 'starter-manual',
            'description' => 'Starter plan',
            'monthly_price' => 29, 'yearly_price' => 290, 'status' => 'active',
            'analytics_enabled' => true, 'custom_domain_enabled' => true,
            'product_limit' => 100, 'staff_limit' => 5, 'storage_limit' => 1024,
            'orders_monthly_limit' => 500, 'coupon_limit' => 20,
            'promotion_limit' => 10, 'flash_sale_limit' => 5,
            'branch_limit' => 3, 'warehouse_limit' => 2, 'pos_device_limit' => 3,
        ]);

        $this->service = $this->app->make(ManualPaymentService::class);
    }

    private static function usd(): Currency
    {
        return Currency::fromCode('USD');
    }

    public function test_initiate_creates_intent_at_waiting_payment(): void
    {
        $intent = $this->service->initiate(
            tenant: $this->tenant,
            plan: $this->plan,
            billingCycle: 'monthly',
            amount: 29.00,
            currency: self::usd(),
        );

        $this->assertNotNull($intent);
        $this->assertSame(TransactionStatus::WAITING_PAYMENT->value, $intent->status);
        $this->assertSame($this->tenant->id, $intent->tenant_id);
        $this->assertSame($this->plan->id, $intent->plan_id);
        $this->assertSame(29.00, (float) $intent->amount);
        $this->assertSame('USD', $intent->currency);
        $this->assertSame('manual', $intent->gateway);
        $this->assertNotNull($intent->reference_number);
        $this->assertStringStartsWith('PAY-', $intent->reference_number);
        $this->assertNotNull($intent->idempotency_key);
    }

    public function test_initiate_reuses_existing_non_terminal_intent(): void
    {
        $first = $this->service->initiate(
            tenant: $this->tenant,
            plan: $this->plan,
            billingCycle: 'monthly',
            amount: 29.00,
            currency: self::usd(),
        );

        $second = $this->service->initiate(
            tenant: $this->tenant,
            plan: $this->plan,
            billingCycle: 'monthly',
            amount: 29.00,
            currency: self::usd(),
        );

        $this->assertSame($first->id, $second->id);
    }

    public function test_find_reusable_ignores_terminal_intents(): void
    {
        $intent = $this->service->initiate(
            tenant: $this->tenant,
            plan: $this->plan,
            billingCycle: 'monthly',
            amount: 29.00,
            currency: self::usd(),
        );

        $this->assertSame(TransactionStatus::WAITING_PAYMENT->value, $intent->status);

        $cancelled = $this->service->cancelPayment($intent);

        $this->assertSame(TransactionStatus::CANCELLED->value, $cancelled->status);

        $checkout = $this->app->make(CheckoutService::class);
        $found = $checkout->findReusableIntent(
            $this->tenant, $this->plan, 'monthly', 'manual',
        );

        $this->assertNull($found);
    }

    public function test_confirm_payment_transitions_to_waiting_review(): void
    {
        $intent = $this->service->initiate(
            tenant: $this->tenant,
            plan: $this->plan,
            billingCycle: 'monthly',
            amount: 29.00,
            currency: self::usd(),
        );

        $result = $this->service->confirmPayment($intent);

        $this->assertSame(TransactionStatus::WAITING_REVIEW->value, $result->status);
    }

    public function test_confirm_payment_is_idempotent_with_guard(): void
    {
        $intent = $this->service->initiate(
            tenant: $this->tenant,
            plan: $this->plan,
            billingCycle: 'monthly',
            amount: 29.00,
            currency: self::usd(),
        );

        $this->service->confirmPayment($intent);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('has already been executed');

        $this->service->confirmPayment($intent->fresh());
    }

    public function test_approve_payment_completes_full_flow(): void
    {
        $intent = $this->service->initiate(
            tenant: $this->tenant,
            plan: $this->plan,
            billingCycle: 'monthly',
            amount: 29.00,
            currency: self::usd(),
        );

        $confirmed = $this->service->confirmPayment($intent);

        $result = $this->service->approvePayment($confirmed);

        $this->assertSame(TransactionStatus::COMPLETED->value, $result->status);
        $this->assertNotNull($result->completed_at);
    }

    public function test_approve_payment_is_idempotent(): void
    {
        $intent = $this->service->initiate(
            tenant: $this->tenant,
            plan: $this->plan,
            billingCycle: 'monthly',
            amount: 29.00,
            currency: self::usd(),
        );

        $confirmed = $this->service->confirmPayment($intent);
        $this->service->approvePayment($confirmed);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->approvePayment($confirmed->fresh());
    }

    public function test_cancel_payment_cancels_pending_intent(): void
    {
        $intent = $this->service->initiate(
            tenant: $this->tenant,
            plan: $this->plan,
            billingCycle: 'monthly',
            amount: 29.00,
            currency: self::usd(),
        );

        $result = $this->service->cancelPayment($intent);

        $this->assertSame(TransactionStatus::CANCELLED->value, $result->status);
        $this->assertNotNull($result->cancelled_at);
    }

    public function test_reject_payment_transitions_to_rejected(): void
    {
        $intent = $this->service->initiate(
            tenant: $this->tenant,
            plan: $this->plan,
            billingCycle: 'monthly',
            amount: 29.00,
            currency: self::usd(),
        );

        $confirmed = $this->service->confirmPayment($intent);

        $rejected = $this->service->rejectPayment($confirmed, 'Invalid evidence');

        $this->assertSame(TransactionStatus::REJECTED->value, $rejected->status);
        $this->assertNotNull($rejected->rejected_at);
        $this->assertSame('Invalid evidence', $rejected->metadata['rejection_reason']);
    }

    public function test_reject_payment_fails_on_terminal_intent(): void
    {
        $intent = $this->service->initiate(
            tenant: $this->tenant,
            plan: $this->plan,
            billingCycle: 'monthly',
            amount: 29.00,
            currency: self::usd(),
        );

        $confirmed = $this->service->confirmPayment($intent);
        $completed = $this->service->approvePayment($confirmed);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->rejectPayment($completed->fresh(), 'Too late');
    }

    public function test_full_lifecycle_succeeds(): void
    {
        $intent = $this->service->initiate(
            tenant: $this->tenant,
            plan: $this->plan,
            billingCycle: 'yearly',
            amount: 290.00,
            currency: self::usd(),
        );

        $this->assertSame(TransactionStatus::WAITING_PAYMENT->value, $intent->status);

        $confirmed = $this->service->confirmPayment($intent);
        $this->assertSame(TransactionStatus::WAITING_REVIEW->value, $confirmed->status);

        $completed = $this->service->approvePayment($confirmed);
        $this->assertSame(TransactionStatus::COMPLETED->value, $completed->status);
        $this->assertNotNull($completed->completed_at);
    }

    public function test_initiate_with_metadata_preserves_it(): void
    {
        $intent = $this->service->initiate(
            tenant: $this->tenant,
            plan: $this->plan,
            billingCycle: 'monthly',
            amount: 29.00,
            currency: self::usd(),
            metadata: ['order_id' => 'ORD-123', 'notes' => 'Test order'],
        );

        $this->assertSame('ORD-123', $intent->metadata['order_id']);
        $this->assertSame('Test order', $intent->metadata['notes']);
    }

    private function createMinimalSchema(): void
    {
        Schema::create('tenants', function ($table) {
            $table->id(); $table->string('slug')->unique();
            $table->string('name'); $table->string('email')->nullable();
            $table->string('status')->default('active');
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->json('settings')->nullable();
            $table->string('logo')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
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
            $table->integer('branch_limit')->nullable();
            $table->integer('warehouse_limit')->nullable();
            $table->integer('pos_device_limit')->nullable();
            $table->boolean('analytics_enabled')->default(false);
            $table->boolean('custom_domain_enabled')->default(false);
            $table->string('status', 20)->default('active');
            $table->string('price')->nullable();
            $table->string('currency')->nullable();
            $table->string('interval')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

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
            $table->string('idempotency_key')->nullable();
            $table->timestamps();
            $table->index('tenant_id');
            $table->index('status');
            $table->index('expires_at');
        });

        Schema::create('reference_numbers', function ($table) {
            $table->id();
            $table->string('prefix', 10);
            $table->date('date');
            $table->unsignedInteger('last_sequence')->default(0);
            $table->timestamps();
            $table->unique(['prefix', 'date']);
        });

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
            $table->index('transaction_number');
            $table->index('status');
        });

        Schema::create('payment_timeline_events', function ($table) {
            $table->id();
            $table->unsignedBigInteger('payment_intent_id');
            $table->string('type');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index('payment_intent_id');
            $table->index('type');
            $table->index('occurred_at');
        });

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
            $table->index('transaction_id');
            $table->index('payment_intent_id');
            $table->index('type');
        });
    }
}
