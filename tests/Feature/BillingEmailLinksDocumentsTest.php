<?php

namespace Tests\Feature;

use App\Data\Currency;
use App\Mail\Billing\InvoiceIssuedMail;
use App\Mail\Billing\PaymentApprovedMail;
use App\Mail\Billing\PaymentRejectedMail;
use App\Mail\Billing\PaymentReviewMail;
use App\Mail\Billing\PaymentSubmittedMail;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\PaymentIntent;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Services\BillingEmailService;
use App\Services\InvoiceService;
use App\Services\Payment\Platform\ManualPaymentService;
use App\Services\ReceiptService;
use App\Services\SubscriptionDocumentPdfService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class BillingEmailLinksDocumentsTest extends TestCase
{
    use DatabaseTransactions;

    private bool $originalUseAccounts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createLinksSchema();
        $this->originalUseAccounts = (bool) config('identity.use_accounts');
        Mail::fake();
    }

    protected function tearDown(): void
    {
        config(['identity.use_accounts' => $this->originalUseAccounts]);

        parent::tearDown();
    }

    public function test_merchant_email_links_use_tenant_storefront_url(): void
    {
        [$tenant] = $this->makeStack('owner-links@example.com');
        $intent = $this->makeIntent($tenant);

        app(BillingEmailService::class)->sendSubmittedEmail($intent);

        Mail::assertQueued(PaymentSubmittedMail::class, function ($mail) use ($tenant) {
            return str_contains($mail->data['billing_url'], "/store/{$tenant->slug}/admin/billing");
        });
    }

    public function test_rejected_email_cta_uses_tenant_url(): void
    {
        [$tenant] = $this->makeStack('owner-rejectcta@example.com');
        $intent = $this->makeIntent($tenant);

        app(BillingEmailService::class)->sendRejectedEmail($intent);

        Mail::assertQueued(PaymentRejectedMail::class, function ($mail) use ($tenant) {
            return str_contains($mail->data['billing_url'], "/store/{$tenant->slug}/admin/billing");
        });
    }

    public function test_invoice_pdf_download_has_correct_filename_and_content(): void
    {
        [$tenant, $plan, $subscription] = $this->makeStack('owner-pdf@example.com');
        $intent = $this->makeIntent($tenant, $plan, $subscription);
        $invoice = app(InvoiceService::class)->generateFromPaymentIntent($intent);

        $response = app(SubscriptionDocumentPdfService::class)->invoice($invoice->fresh());

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('Invoice_' . $invoice->invoice_number . '.pdf', $disposition);

        $body = $response->getContent();
        $this->assertStringStartsWith('%PDF', $body);
        $this->assertStringContainsString($invoice->invoice_number, $body);
        $this->assertStringContainsString($plan->name, $body);
    }

    public function test_invoice_pdf_inline_view_renders_in_browser(): void
    {
        [$tenant, $plan, $subscription] = $this->makeStack('owner-inline@example.com');
        $intent = $this->makeIntent($tenant, $plan, $subscription);
        $invoice = app(InvoiceService::class)->generateFromPaymentIntent($intent);

        $response = app(SubscriptionDocumentPdfService::class)->invoice($invoice->fresh(), true);

        $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Invoice_' . $invoice->invoice_number . '.pdf', $response->headers->get('Content-Disposition'));
    }

    public function test_receipt_pdf_has_correct_filename_and_content(): void
    {
        [$tenant, $plan, $subscription] = $this->makeStack('owner-receipt@example.com');
        $intent = $this->makeIntent($tenant, $plan, $subscription);

        $manual = app(ManualPaymentService::class);
        $manual->confirmPayment($intent);
        $manual->approvePayment($intent->fresh());

        $receipt = app(ReceiptService::class)->createFromCompletedIntent($intent->fresh());
        $response = app(SubscriptionDocumentPdfService::class)->receipt($receipt->fresh());

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('Receipt_' . $receipt->receipt_number . '.pdf', $disposition);

        $body = $response->getContent();
        $this->assertStringStartsWith('%PDF', $body);
        $this->assertStringContainsString($receipt->receipt_number, $body);
    }

    public function test_admin_review_email_goes_to_reviewer_with_review_link(): void
    {
        config(['identity.use_accounts' => true]);

        $superadmin = Account::create([
            'name' => 'Super Admin',
            'email' => 'reviewer@example.com',
            'password' => 'secret',
            'status' => 'active',
        ]);
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'superadmin',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('model_has_roles')->insert([
            'role_id' => $roleId,
            'model_type' => Account::class,
            'model_id' => $superadmin->id,
        ]);

        [$tenant] = $this->makeStack('owner-review@example.com');
        $intent = $this->makeIntent($tenant);

        app(BillingEmailService::class)->sendReviewEmail($intent);

        Mail::assertQueued(PaymentReviewMail::class, function ($mail) {
            return $mail->hasTo('reviewer@example.com')
                && $mail->envelope()->subject === 'New payment requires review — Email Shop — Numbered'
                && str_contains($mail->data['review_url'], '/superadmin/billing');
        });
    }

    public function test_review_email_link_opens_current_payment_state(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'superadmin',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $superadmin = \App\Models\User::create([
            'name' => 'Console Admin',
            'email' => 'console-admin-' . uniqid() . '@example.com',
            'password' => 'secret',
            'status' => 'active',
        ]);
        DB::table('model_has_roles')->insert([
            'role_id' => $roleId,
            'model_type' => \App\Models\User::class,
            'model_id' => $superadmin->id,
        ]);
        $superadmin = $superadmin->fresh();

        [$tenant] = $this->makeStack('owner-stale-link@example.com');
        $intent = $this->makeIntent($tenant);
        $intent->update(['status' => 'waiting_review']);

        $mail = null;
        Mail::assertQueued(PaymentReviewMail::class, function ($m) use (&$mail) {
            $mail = $m;

            return true;
        });

        $url = $mail->data['review_url'];

        $this->assertStringContainsString('/superadmin/billing', $url);
        $this->assertStringNotContainsString('waiting_review', $url);
        $this->assertStringNotContainsString('status=', $url);
        $this->assertStringContainsString($intent->reference_number, $url);

        app(ManualPaymentService::class)->approvePayment($intent->fresh());
        $this->assertSame('completed', $intent->fresh()->status);

        $this->actingAs($superadmin);

        $target = parse_url($url, PHP_URL_PATH) . '?' . parse_url($url, PHP_URL_QUERY);
        $response = $this->get($target);
        $response->assertOk();
        $response->assertSee($intent->reference_number, false);
        $response->assertSee('completed', false);

        $this->get('/superadmin/billing?status=waiting_review')->assertOk();
    }

    public function test_review_email_is_not_duplicated(): void
    {
        config(['identity.use_accounts' => true]);

        $superadmin = Account::create([
            'name' => 'Super Admin',
            'email' => 'reviewer-dedup@example.com',
            'password' => 'secret',
            'status' => 'active',
        ]);
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'superadmin',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('model_has_roles')->insert([
            'role_id' => $roleId,
            'model_type' => Account::class,
            'model_id' => $superadmin->id,
        ]);

        [$tenant] = $this->makeStack('owner-review-dedup@example.com');
        $intent = $this->makeIntent($tenant);

        $service = app(BillingEmailService::class);
        $service->sendReviewEmail($intent);
        $service->sendReviewEmail($intent->fresh());

        Mail::assertQueued(PaymentReviewMail::class, 1);
    }

    public function test_approved_email_uses_invoice_period_not_live_subscription(): void
    {
        [$tenant, $plan, $subscription] = $this->makeStack('owner-period@example.com');
        $intent = $this->makeIntent($tenant, $plan, $subscription);
        $invoice = app(InvoiceService::class)->generateFromPaymentIntent($intent);

        $subscription->update(['expires_at' => now()->addMonths(3)]);

        app(BillingEmailService::class)->sendApprovedEmail($intent->fresh());

        $expected = $invoice->billing_period_start->toDateString() . ' → ' . $invoice->billing_period_end->toDateString();

        Mail::assertQueued(PaymentApprovedMail::class, function ($mail) use ($expected) {
            return $mail->data['subscription_period'] === $expected;
        });
    }

    public function test_invoice_email_links_to_view_and_download(): void
    {
        [$tenant, $plan, $subscription] = $this->makeStack('owner-doclinks@example.com');
        $intent = $this->makeIntent($tenant, $plan, $subscription);
        $invoice = app(InvoiceService::class)->generateFromPaymentIntent($intent);

        app(BillingEmailService::class)->sendInvoiceEmail($intent, $invoice);

        Mail::assertQueued(InvoiceIssuedMail::class, function ($mail) use ($invoice) {
            return str_contains($mail->data['invoice_url'], "/billing/documents/invoices/{$invoice->id}")
                && str_contains($mail->data['invoice_download_url'], "/billing/documents/invoices/{$invoice->id}/pdf");
        });
    }

    private function makeStack(string $ownerEmail): array
    {
        $tenant = Tenant::create([
            'slug' => 'links-' . uniqid(),
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

    private function createLinksSchema(): void
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

        if (!Schema::hasTable('platform_settings')) {
            Schema::create('platform_settings', function ($table) {
                $table->id();
                $table->string('site_name')->nullable();
                $table->string('support_email')->nullable();
                $table->boolean('trial_enabled')->default(true);
                $table->integer('trial_days')->default(14);
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

    public function test_signed_invoice_view_opens_without_login(): void
    {
        [$tenant, $plan, $subscription] = $this->makeStack('owner-signed-inv@example.com');
        $intent = $this->makeIntent($tenant, $plan, $subscription);
        $invoice = app(InvoiceService::class)->generateFromPaymentIntent($intent);

        $url = URL::temporarySignedRoute(
            'billing.documents.invoice',
            now()->addDays(30),
            ['invoice' => $invoice->id]
        );

        $response = $this->get($url);

        $response->assertStatus(200);
        $response->assertSee($invoice->invoice_number, false);
    }

    public function test_signed_invoice_pdf_download_uses_correct_filename(): void
    {
        [$tenant, $plan, $subscription] = $this->makeStack('owner-signed-pdf@example.com');
        $intent = $this->makeIntent($tenant, $plan, $subscription);
        $invoice = app(InvoiceService::class)->generateFromPaymentIntent($intent);

        $url = URL::temporarySignedRoute(
            'billing.documents.invoice.pdf',
            now()->addDays(30),
            ['invoice' => $invoice->id]
        );

        $response = $this->get($url);

        $response->assertStatus(200);
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString(
            'Invoice_' . $invoice->invoice_number . '.pdf',
            $response->headers->get('Content-Disposition')
        );
    }

    public function test_signed_receipt_view_and_pdf(): void
    {
        [$tenant, $plan, $subscription] = $this->makeStack('owner-signed-rec@example.com');
        $intent = $this->makeIntent($tenant, $plan, $subscription);

        $manual = app(ManualPaymentService::class);
        $manual->confirmPayment($intent);
        $manual->approvePayment($intent->fresh());

        $receipt = app(ReceiptService::class)->createFromCompletedIntent($intent->fresh());

        $viewUrl = URL::temporarySignedRoute(
            'billing.documents.receipt',
            now()->addDays(30),
            ['receipt' => $receipt->id]
        );
        $pdfUrl = URL::temporarySignedRoute(
            'billing.documents.receipt.pdf',
            now()->addDays(30),
            ['receipt' => $receipt->id]
        );

        $view = $this->get($viewUrl);
        $view->assertStatus(200);
        $view->assertSee($receipt->receipt_number, false);

        $pdf = $this->get($pdfUrl);
        $pdf->assertStatus(200);
        $this->assertStringContainsString(
            'Receipt_' . $receipt->receipt_number . '.pdf',
            $pdf->headers->get('Content-Disposition')
        );
    }

    public function test_tampered_document_url_is_rejected(): void
    {
        [$tenant, $plan, $subscription] = $this->makeStack('owner-tamper@example.com');
        $intent = $this->makeIntent($tenant, $plan, $subscription);
        $invoice = app(InvoiceService::class)->generateFromPaymentIntent($intent);

        $other = $this->makeStack('owner-tamper-b@example.com');

        $url = URL::temporarySignedRoute(
            'billing.documents.invoice',
            now()->addDays(30),
            ['invoice' => $invoice->id]
        );

        $tampered = preg_replace('/signature=[a-f0-9]+/', 'signature=0', $url);

        $this->get($tampered)->assertStatus(403);
        $this->get('/billing/documents/invoices/' . $invoice->id)->assertStatus(403);
    }

    public function test_expired_document_url_is_rejected(): void
    {
        [$tenant, $plan, $subscription] = $this->makeStack('owner-expired@example.com');
        $intent = $this->makeIntent($tenant, $plan, $subscription);
        $invoice = app(InvoiceService::class)->generateFromPaymentIntent($intent);

        $url = URL::temporarySignedRoute(
            'billing.documents.invoice',
            now()->subMinute(),
            ['invoice' => $invoice->id]
        );

        $this->get($url)->assertStatus(403);
    }
}
