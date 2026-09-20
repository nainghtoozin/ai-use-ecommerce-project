<?php

namespace Tests\Feature;

use App\Mail\Billing\SubscriptionRenewalReminderMail;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\PaymentIntent;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Receipt;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Services\BillingEmailService;
use App\Services\Payment\Platform\ManualPaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SubscriptionRenewalReminderTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createReminderSchema();
        PlatformSetting::clearCache();
        Mail::fake();
    }

    public function test_reminder_setting_defaults_to_seven_days(): void
    {
        PlatformSetting::query()->delete();
        PlatformSetting::create([]);
        PlatformSetting::clearCache();

        $this->assertSame(7, (int) PlatformSetting::current()->billing_renewal_reminder_days);
        $this->assertSame(7, app(BillingEmailService::class)->renewalReminderDays());
    }

    public function test_reminder_sent_at_configured_threshold(): void
    {
        $this->seedReminderDays(7);

        [$tenant, $subscription] = $this->makeSubscription('owner-threshold@example.com', now()->addDays(7));

        $this->assertTrue(app(BillingEmailService::class)->sendRenewalReminder($subscription));

        Mail::assertQueued(SubscriptionRenewalReminderMail::class, function ($mail) {
            return $mail->hasTo('owner-threshold@example.com')
                && $mail->envelope()->subject === 'Your subscription renews soon — Numbered'
                && str_contains($mail->data['billing_url'], '/billing');
        });

        $mail = null;
        Mail::assertQueued(SubscriptionRenewalReminderMail::class, function ($m) use (&$mail) {
            $mail = $m;

            return true;
        });

        $this->assertSame(
            '/store/' . $tenant->slug . '/admin/billing',
            parse_url($mail->data['billing_url'], PHP_URL_PATH)
        );
    }

    public function test_reminder_not_sent_too_early(): void
    {
        $this->seedReminderDays(7);

        [, $subscription] = $this->makeSubscription('owner-early@example.com', now()->addDays(30));

        $this->assertFalse(app(BillingEmailService::class)->sendRenewalReminder($subscription));

        Mail::assertNotQueued(SubscriptionRenewalReminderMail::class);
    }

    public function test_reminder_not_repeated_on_daily_cron(): void
    {
        $this->seedReminderDays(7);

        $this->makeSubscription('owner-dedup@example.com', now()->addDays(7));

        $this->artisan('subscriptions:send-reminders')->assertSuccessful();
        $this->artisan('subscriptions:send-reminders')->assertSuccessful();

        Mail::assertQueued(SubscriptionRenewalReminderMail::class, 1);
    }

    public function test_renewal_allows_reminder_for_new_cycle(): void
    {
        $this->seedReminderDays(7);

        [, $subscription] = $this->makeSubscription('owner-cycle@example.com', now()->addDays(7));

        $service = app(BillingEmailService::class);
        $this->assertTrue($service->sendRenewalReminder($subscription));

        $subscription->update(['expires_at' => now()->addDays(6)]);

        $this->assertTrue($service->sendRenewalReminder($subscription->fresh()));

        Mail::assertQueued(SubscriptionRenewalReminderMail::class, 2);
    }

    public function test_reminder_creates_no_invoice(): void
    {
        $this->seedReminderDays(7);

        [, $subscription] = $this->makeSubscription('owner-noinvoice@example.com', now()->addDays(7));

        app(BillingEmailService::class)->sendRenewalReminder($subscription);

        $this->assertSame(0, Invoice::count());
    }

    public function test_payment_approval_still_creates_invoice_and_receipt(): void
    {
        [$tenant, $subscription, $plan] = $this->makeSubscription('owner-approve@example.com', now()->addMonth());

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

        $manual = app(ManualPaymentService::class);
        $manual->confirmPayment($intent);
        $manual->approvePayment($intent->fresh());

        $this->assertTrue(Invoice::where('payment_intent_id', $intent->id)->exists());
        $this->assertTrue(Receipt::where('payment_intent_id', $intent->id)->exists());
    }

    public function test_tenant_isolation_preserved(): void
    {
        $this->seedReminderDays(7);

        [$tenantA, $subA] = $this->makeSubscription('owner-tenant-a@example.com', now()->addDays(7));
        $this->makeSubscription('owner-tenant-b@example.com', now()->addDays(7));

        $subA->load('plan');
        Tenant::setCurrent($tenantA->fresh());
        try {
            $otherTenant = Tenant::where('id', '!=', $tenantA->id)->firstOrFail();
            Tenant::setCurrent($otherTenant);
            $this->assertTrue(app(BillingEmailService::class)->sendRenewalReminder($subA));
        } finally {
            \Illuminate\Support\Facades\App::forgetInstance('current.tenant');
        }

        Mail::assertQueued(SubscriptionRenewalReminderMail::class, 1);
        Mail::assertQueued(SubscriptionRenewalReminderMail::class, function ($mail) {
            return $mail->hasTo('owner-tenant-a@example.com');
        });
    }

    private function seedReminderDays(int $days): void
    {
        PlatformSetting::query()->delete();
        PlatformSetting::create(['billing_renewal_reminder_days' => $days]);
        PlatformSetting::clearCache();
    }

    private function makeSubscription(string $ownerEmail, $expiresAt): array
    {
        $tenant = Tenant::create([
            'slug' => 'renew-' . uniqid(),
            'name' => 'Renew Shop',
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
            'expires_at' => $expiresAt,
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

        return [$tenant, $subscription, $plan];
    }

    private function createReminderSchema(): void
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
                $table->string('currency', 10)->nullable();
                $table->string('status', 20)->default('active');
                $table->timestamps();
            });
        } elseif (!Schema::hasColumn('plans', 'currency')) {
            Schema::table('plans', function ($table) {
                $table->string('currency', 10)->nullable();
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

        if (!Schema::hasTable('platform_settings')) {
            Schema::create('platform_settings', function ($table) {
                $table->id();
                $table->string('site_name')->nullable();
                $table->string('site_logo')->nullable();
                $table->string('favicon')->nullable();
                $table->string('support_email')->nullable();
                $table->boolean('maintenance_mode')->default(false);
                $table->boolean('registration_enabled')->default(true);
                $table->boolean('trial_enabled')->default(true);
                $table->integer('trial_days')->default(14);
                $table->boolean('allow_trial_renewal')->default(true);
                $table->integer('max_trial_renewals')->default(0);
                $table->unsignedInteger('billing_renewal_reminder_days')->default(7);
                $table->integer('audit_retention_days')->default(90);
                $table->string('platform_currency_code')->nullable();
                $table->string('platform_currency_symbol')->nullable();
                $table->string('platform_currency_position')->nullable();
                $table->integer('platform_decimal_places')->default(0);
                $table->timestamps();
            });
        } elseif (!Schema::hasColumn('platform_settings', 'billing_renewal_reminder_days')) {
            Schema::table('platform_settings', function ($table) {
                $table->unsignedInteger('billing_renewal_reminder_days')->default(7);
            });
        }
    }
}
