<?php

namespace Tests\Feature;

use App\Data\Currency;
use App\Mail\Billing\InvoiceIssuedMail;
use App\Mail\Billing\PaymentApprovedMail;
use App\Mail\Billing\PaymentRejectedMail;
use App\Mail\Billing\PaymentSubmittedMail;
use App\Mail\Billing\ReceiptIssuedMail;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\PaymentIntent;
use App\Models\PaymentReview;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Services\BillingEmailService;
use App\Services\InvoiceService;
use App\Services\Payment\Platform\ManualPaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BillingEmailNotificationsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createEmailSchema();
        Mail::fake();
    }

    public function test_submitted_email_goes_to_owner_with_correct_subject(): void
    {
        [$tenant] = $this->makeStack('owner-submitted@example.com');
        $intent = $this->makeIntent($tenant);

        app(BillingEmailService::class)->sendSubmittedEmail($intent);

        Mail::assertQueued(PaymentSubmittedMail::class, function ($mail) {
            return $mail->hasTo('owner-submitted@example.com')
                && $mail->envelope()->subject === 'Payment received — awaiting review';
        });
    }

    public function test_approved_email_contains_plan_amount_invoice_and_period(): void
    {
        [$tenant, $plan, $subscription] = $this->makeStack('owner-approved@example.com');
        $intent = $this->makeIntent($tenant, $plan, $subscription);

        $manual = app(ManualPaymentService::class);
        $manual->confirmPayment($intent);
        $manual->approvePayment($intent->fresh());

        $invoice = app(InvoiceService::class)->generateFromPaymentIntent($intent->fresh());

        app(BillingEmailService::class)->sendApprovedEmail($intent->fresh());

        Mail::assertQueued(PaymentApprovedMail::class, function ($mail) use ($invoice) {
            return $mail->hasTo('owner-approved@example.com')
                && $mail->envelope()->subject === 'Payment approved — Numbered'
                && $mail->data['invoice_number'] === $invoice->invoice_number
                && $mail->data['approval_status'] === 'Approved'
                && !empty($mail->data['subscription_period']);
        });
    }

    public function test_rejected_email_contains_reason_and_resubmit_instruction(): void
    {
        [$tenant, $plan, $subscription] = $this->makeStack('owner-rejected@example.com');
        $intent = $this->makeIntent($tenant, $plan, $subscription);

        $manual = app(ManualPaymentService::class);
        $manual->confirmPayment($intent);
        $manual->rejectPayment($intent->fresh(), 'Blurry screenshot');

        app(BillingEmailService::class)->sendRejectedEmail($intent->fresh());

        Mail::assertQueued(PaymentRejectedMail::class, function ($mail) {
            return $mail->hasTo('owner-rejected@example.com')
                && $mail->envelope()->subject === 'Payment rejected — action required'
                && $mail->data['rejection_reason'] === 'Blurry screenshot';
        });
    }

    public function test_invoice_email_contains_invoice_data(): void
    {
        [$tenant, $plan, $subscription] = $this->makeStack('owner-invoice@example.com');
        $intent = $this->makeIntent($tenant, $plan, $subscription);
        $invoice = app(InvoiceService::class)->generateFromPaymentIntent($intent);

        app(BillingEmailService::class)->sendInvoiceEmail($intent, $invoice);

        Mail::assertQueued(InvoiceIssuedMail::class, function ($mail) use ($invoice) {
            return $mail->hasTo('owner-invoice@example.com')
                && $mail->envelope()->subject === 'Invoice ' . $invoice->invoice_number
                && $mail->data['invoice_number'] === $invoice->invoice_number
                && !empty($mail->data['billing_period']);
        });
    }

    public function test_duplicate_processing_sends_only_once(): void
    {
        [$tenant] = $this->makeStack('owner-dedup@example.com');
        $intent = $this->makeIntent($tenant);

        $service = app(BillingEmailService::class);
        $service->sendSubmittedEmail($intent);
        $service->sendSubmittedEmail($intent->fresh());

        Mail::assertQueued(PaymentSubmittedMail::class, 1);
    }

    public function test_receipt_email_contains_receipt_links_and_no_duplicates(): void
    {
        [$tenant, $plan, $subscription] = $this->makeStack('owner-receiptmail@example.com');
        $intent = $this->makeIntent($tenant, $plan, $subscription);

        $manual = app(ManualPaymentService::class);
        $manual->confirmPayment($intent);
        $manual->approvePayment($intent->fresh());

        $receipt = app(\App\Services\ReceiptService::class)->createFromCompletedIntent($intent->fresh());

        $service = app(BillingEmailService::class);
        $service->sendReceiptEmail($intent->fresh(), $receipt);
        $service->sendReceiptEmail($intent->fresh(), $receipt);

        Mail::assertQueued(ReceiptIssuedMail::class, function ($mail) use ($tenant, $receipt) {
            return $mail->hasTo('owner-receiptmail@example.com')
                && $mail->envelope()->subject === 'Payment receipt — ' . $receipt->receipt_number
                && $mail->data['receipt_number'] === $receipt->receipt_number
                && str_contains($mail->data['receipt_url'], "/store/{$tenant->slug}/admin/billing/documents/receipts/{$receipt->id}")
                && str_contains($mail->data['receipt_download_url'], "/store/{$tenant->slug}/admin/billing/documents/receipts/{$receipt->id}/pdf");
        });
        Mail::assertQueued(ReceiptIssuedMail::class, 1);
    }

    public function test_email_failure_does_not_break_flow(): void
    {
        [$tenant] = $this->makeStack('owner-failure@example.com');
        $intent = $this->makeIntent($tenant);

        Mail::shouldReceive('to')->andThrow(new \Exception('smtp down'));

        app(BillingEmailService::class)->sendSubmittedEmail($intent);

        $timeline = app(\App\Services\Payment\Platform\PaymentTimelineService::class)
            ->getByType($intent, 'email.submitted');

        $this->assertCount(0, $timeline);
    }

    public function test_no_email_without_recipient(): void
    {
        $tenant = Tenant::create([
            'slug' => 'no-owner-' . uniqid(),
            'name' => 'No Owner',
            'status' => 'active',
        ]);
        $plan = Plan::create([
            'name' => 'Numbered', 'slug' => 'numbered-' . uniqid(),
            'description' => 'Test plan', 'monthly_price' => 100,
            'yearly_price' => 1000, 'status' => 'active',
        ]);
        $intent = new PaymentIntent([
            'plan_id' => $plan->id,
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

        app(BillingEmailService::class)->sendSubmittedEmail($intent);

        Mail::assertNothingQueued();
    }

    private function makeStack(string $ownerEmail): array
    {
        $tenant = Tenant::create([
            'slug' => 'email-' . uniqid(),
            'name' => 'Email Shop',
            'status' => 'active',
        ]);
        $plan = Plan::create([
            'name' => 'Numbered', 'slug' => 'numbered-' . uniqid(),
            'description' => 'Test plan', 'monthly_price' => 100,
            'yearly_price' => 1000, 'status' => 'active',
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

        $account = Account::create([
            'name' => 'Owner',
            'email' => $ownerEmail,
            'password' => 'secret',
            'status' => 'active',
        ]);
        $role = \App\Models\Role::withoutTenantScope()->firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
            'tenant_id' => $tenant->id,
        ]);
        TenantMembership::create([
            'account_id' => $account->id,
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
            'is_owner' => true,
            'status' => 'active',
        ]);

        return [$tenant, $plan, $subscription];
    }

    private function makeIntent(Tenant $tenant, ?Plan $plan = null, ?Subscription $subscription = null): PaymentIntent
    {
        $plan ??= Plan::create([
            'name' => 'Numbered', 'slug' => 'numbered-' . uniqid(),
            'description' => 'Test plan', 'monthly_price' => 100,
            'yearly_price' => 1000, 'status' => 'active',
        ]);

        $intent = new PaymentIntent([
            'plan_id' => $plan->id,
            'subscription_id' => $subscription?->id,
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

    private function createEmailSchema(): void
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

        if (!Schema::hasTable('payment_reviews')) {
            Schema::create('payment_reviews', function ($table) {
                $table->id();
                $table->unsignedBigInteger('payment_intent_id');
                $table->string('action');
                $table->unsignedBigInteger('reviewer_id')->nullable();
                $table->string('reviewer_name')->nullable();
                $table->text('reason')->nullable();
                $table->json('metadata')->nullable();
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

        if (!Schema::hasTable('accounts')) {
            Schema::create('accounts', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password')->nullable();
                $table->string('status')->default('active');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('tenant_memberships')) {
            Schema::create('tenant_memberships', function ($table) {
                $table->id();
                $table->unsignedBigInteger('account_id');
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('role_id')->nullable();
                $table->boolean('is_owner')->default(false);
                $table->string('status')->default('active');
                $table->timestamps();
                $table->index(['tenant_id', 'account_id']);
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
