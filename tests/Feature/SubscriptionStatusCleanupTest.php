<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\PaymentIntent;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\SubscriptionCancellationRequest;
use App\Models\Tenant;
use App\Models\TenantCleanup;
use App\Models\User;
use App\Services\Payment\Platform\ManualPaymentService;
use App\Services\TenantCleanupService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SubscriptionStatusCleanupTest extends TestCase
{
    use DatabaseTransactions;

    private Plan $paidPlan;
    private Tenant $tenant;
    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['billing.view', 'billing.manage', 'billing.renew'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }
        $role = \App\Models\Role::withoutTenantScope()->firstOrCreate([
            'name' => 'admin', 'guard_name' => 'web', 'tenant_id' => null,
        ]);
        $role->syncPermissions(['billing.view', 'billing.manage', 'billing.renew']);

        $this->setPlatformSetting('billing_renewal_reminder_days', 7);

        $this->paidPlan = $this->makePlan('starter', 10, 100);

        $this->tenant = Tenant::create([
            'slug' => 'cleanup-' . uniqid(),
            'name' => 'Cleanup Shop',
            'status' => 'active',
        ]);

        $this->subscription = $this->makeSubscription($this->tenant, $this->paidPlan, 'active', now()->addDays(30));
    }

    protected function tearDown(): void
    {
        Tenant::setCurrent(null);
        Carbon::setTestNow();

        parent::tearDown();
    }

    /* ── 1. Approved paid subscription hides stale trial-expired UI ── */

    public function test_approved_paid_subscription_hides_stale_trial_expired_ui(): void
    {
        // Converted from trial: trial_ends_at is in the past but the subscription is active + paid.
        $this->subscription->update([
            'status' => 'active',
            'trial_ends_at' => now()->subDays(3),
            'expires_at' => now()->addDays(30),
        ]);

        $this->actingAs($this->makeOwner($this->tenant));
        $this->get("/store/{$this->tenant->slug}/admin/billing")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('trial.trial_ended', false)
                ->where('renewal.active_paid', true)
            );
    }

    /* ── 2. Unpaid expired trial still shows the correct paid-plan action ── */

    public function test_unpaid_expired_trial_shows_paid_plan_action(): void
    {
        $this->subscription->update([
            'status' => 'expired',
            'trial_ends_at' => now()->subDays(2),
            'expires_at' => now()->subDays(2),
        ]);

        $this->actingAs($this->makeOwner($this->tenant));
        $this->get("/store/{$this->tenant->slug}/admin/billing")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('trial.trial_ended', true)
                ->where('renewal.active_paid', false)
            );
    }

    /* ── 3. Renewal button appears according to the configured threshold ── */

    public function test_renewal_due_follows_configured_threshold(): void
    {
        $owner = $this->makeOwner($this->tenant);
        $this->actingAs($owner);

        $this->subscription->update(['status' => 'active', 'expires_at' => now()->addDays(10)]);
        $this->get("/store/{$this->tenant->slug}/admin/billing")
            ->assertInertia(fn ($page) => $page->where('renewal.due', false)->where('renewal.threshold_days', 7));

        $this->subscription->update(['expires_at' => now()->addDays(5)]);
        $this->get("/store/{$this->tenant->slug}/admin/billing")
            ->assertInertia(fn ($page) => $page->where('renewal.due', true));
    }

    /* ── 4. Cancellation actions and states follow existing policy ── */

    public function test_cancellation_states_and_effective_date(): void
    {
        $owner = $this->makeOwner($this->tenant);
        $this->actingAs($owner);

        $url = "/store/{$this->tenant->slug}/admin/billing/cancel-request";
        $this->from("/store/{$this->tenant->slug}/admin/billing")->post($url, ['reason' => 'too_expensive'])->assertRedirect();
        $this->from("/store/{$this->tenant->slug}/admin/billing")->post($url, ['reason' => 'no_longer_needed']);

        // Duplicate prevention: only one pending request.
        $this->assertSame(1, SubscriptionCancellationRequest::withoutTenantScope()->count());
        // No premature suspension/deletion.
        $this->assertSame('active', $this->subscription->fresh()->status);
        $this->assertNull($this->tenant->fresh()->locked_at);

        // Approve it and confirm the effective date reflects the paid period end.
        $request = SubscriptionCancellationRequest::withoutTenantScope()->first();
        $request->update(['status' => SubscriptionCancellationRequest::STATUS_APPROVED, 'reviewed_at' => now()]);

        $this->get("/store/{$this->tenant->slug}/admin/billing")
            ->assertInertia(fn ($page) => $page
                ->where('cancellation.latest.status', 'approved')
                ->where('cancellation.latest.effective_at', $this->subscription->fresh()->expires_at->toDateString())
            );
    }

    /* ── 5. Expiry begins the correct 15-day grace period ── */

    public function test_expiry_begins_fifteen_day_grace_period(): void
    {
        $expiresAt = now()->subDays(2);
        $this->subscription->update(['status' => 'expired', 'expires_at' => $expiresAt]);

        app(TenantCleanupService::class)->process();

        $cleanup = TenantCleanup::where('tenant_id', $this->tenant->id)->first();
        $this->assertNotNull($cleanup);
        $this->assertSame(TenantCleanup::STATUS_GRACE, $cleanup->status);
        $this->assertSame(
            $expiresAt->copy()->addDays(15)->toDateString(),
            $cleanup->grace_ends_at->toDateString()
        );
        $this->assertNull($cleanup->restricted_at);
        $this->assertSame('active', $this->tenant->fresh()->status);
    }

    /* ── 6. No cleanup before the deadline ── */

    public function test_no_cleanup_before_deadline(): void
    {
        $this->subscription->update(['status' => 'expired', 'expires_at' => now()->subDays(10)]);

        $result = app(TenantCleanupService::class)->process();

        $this->assertSame(0, $result['restricted']);
        $cleanup = TenantCleanup::where('tenant_id', $this->tenant->id)->first();
        $this->assertSame(TenantCleanup::STATUS_GRACE, $cleanup->status);
        $this->assertNull($cleanup->restricted_at);
        $this->assertNull($this->tenant->fresh()->locked_at);
    }

    /* ── 7. Eligible expired tenants are restricted after the deadline ── */

    public function test_expired_tenant_restricted_after_deadline(): void
    {
        $this->subscription->update(['status' => 'expired', 'expires_at' => now()->subDays(16)]);

        $result = app(TenantCleanupService::class)->process();

        $this->assertSame(1, $result['restricted']);
        $cleanup = TenantCleanup::where('tenant_id', $this->tenant->id)->first();
        $this->assertSame(TenantCleanup::STATUS_RESTRICTED, $cleanup->status);
        $this->assertNotNull($cleanup->restricted_at);

        $tenant = $this->tenant->fresh();
        $this->assertSame('expired', $tenant->status);
        $this->assertNotNull($tenant->locked_at);
        // Never hard-deleted.
        $this->assertDatabaseHas('tenants', ['id' => $this->tenant->id]);
    }

    /* ── 8. Pending payments prevent permanent deletion / restriction ── */

    public function test_pending_payment_prevents_restriction(): void
    {
        $this->subscription->update(['status' => 'expired', 'expires_at' => now()->subDays(16)]);
        $this->makeIntent($this->tenant, $this->paidPlan, 'waiting_review');

        $result = app(TenantCleanupService::class)->process();

        $this->assertSame(0, $result['restricted']);
        $this->assertSame(1, $result['flagged']);

        $cleanup = TenantCleanup::where('tenant_id', $this->tenant->id)->first();
        $this->assertSame(TenantCleanup::STATUS_FLAGGED, $cleanup->status);
        $this->assertNull($cleanup->restricted_at);
        $this->assertNull($this->tenant->fresh()->locked_at);
        $this->assertDatabaseHas('tenants', ['id' => $this->tenant->id]);
    }

    /* ── 9. Approved renewals cancel pending cleanup ── */

    public function test_approved_renewal_cancels_pending_cleanup(): void
    {
        $this->subscription->update(['status' => 'expired', 'expires_at' => now()->subDays(5)]);
        app(TenantCleanupService::class)->process();

        $this->assertSame(TenantCleanup::STATUS_GRACE, TenantCleanup::where('tenant_id', $this->tenant->id)->first()->status);

        $manual = app(ManualPaymentService::class);
        $intent = $manual->initiate(
            tenant: $this->tenant,
            plan: $this->paidPlan,
            billingCycle: 'monthly',
            amount: 10.0,
            currency: \App\Data\Currency::fromCode('MMK'),
        );
        $manual->confirmPayment($intent);
        $manual->approvePayment($intent->fresh());

        $this->assertSame(TenantCleanup::STATUS_CANCELLED, TenantCleanup::where('tenant_id', $this->tenant->id)->first()->status);
        $this->assertSame('active', $this->subscription->fresh()->status);
    }

    /* ── 10. Repeated scheduler execution is idempotent ── */

    public function test_repeated_scheduler_runs_are_idempotent(): void
    {
        $this->subscription->update(['status' => 'expired', 'expires_at' => now()->subDays(16)]);

        $first = app(TenantCleanupService::class)->process();
        $this->assertSame(1, $first['restricted']);

        $cleanupAfterFirst = TenantCleanup::where('tenant_id', $this->tenant->id)->first();
        $restrictedAt = $cleanupAfterFirst->restricted_at->toDateTimeString();
        $attemptsAfterFirst = $cleanupAfterFirst->attempts;

        $second = app(TenantCleanupService::class)->process();

        $this->assertSame(0, $second['restricted']);
        $this->assertGreaterThanOrEqual(1, $second['skipped']);

        $cleanupAfterSecond = TenantCleanup::where('tenant_id', $this->tenant->id)->first();
        $this->assertSame($restrictedAt, $cleanupAfterSecond->restricted_at->toDateTimeString());
        $this->assertSame($attemptsAfterFirst, $cleanupAfterSecond->attempts);
    }

    /* ── 11. Tenant isolation, shared records, retained billing history ── */

    public function test_tenant_isolation_and_history_retained(): void
    {
        $other = Tenant::create(['slug' => 'cleanup-other-' . uniqid(), 'name' => 'Other Shop', 'status' => 'active']);
        $otherSub = $this->makeSubscription($other, $this->paidPlan, 'active', now()->addDays(30));

        $invoice = $this->makeInvoice($this->tenant, $this->subscription);

        $this->subscription->update(['status' => 'expired', 'expires_at' => now()->subDays(16)]);
        app(TenantCleanupService::class)->process();

        // Expired tenant restricted, other tenant untouched.
        $this->assertSame('expired', $this->tenant->fresh()->status);
        $this->assertSame('active', $other->fresh()->status);
        $this->assertSame('active', $otherSub->fresh()->status);
        $this->assertNull($other->fresh()->locked_at);

        // Billing history retained.
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
        $this->assertDatabaseHas('tenants', ['id' => $this->tenant->id]);
    }

    /* ── Dry-run never mutates ── */

    public function test_dry_run_does_not_mutate(): void
    {
        $this->subscription->update(['status' => 'expired', 'expires_at' => now()->subDays(16)]);

        $result = app(TenantCleanupService::class)->process(true);

        $this->assertSame(1, $result['restricted']);
        $this->assertSame(0, TenantCleanup::count());
        $this->assertNull($this->tenant->fresh()->locked_at);
        $this->assertSame('active', $this->tenant->fresh()->status);
    }

    /* ── Helpers ── */

    private function makeIntent(Tenant $tenant, Plan $plan, string $status): PaymentIntent
    {
        $intent = new PaymentIntent([
            'plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'amount' => 10.0,
            'currency' => 'MMK',
            'gateway' => 'manual',
            'status' => $status,
            'reference_number' => 'PAY-' . strtoupper(uniqid()),
            'idempotency_key' => uniqid('idem-', true),
            'metadata' => ['source' => 'merchant_renewal'],
        ]);
        $intent->tenant_id = $tenant->id;
        $intent->save();

        return $intent;
    }

    private function makeInvoice(Tenant $tenant, Subscription $subscription): Invoice
    {
        return Invoice::create([
            'tenant_id' => $tenant->id,
            'invoice_number' => 'INV-TEST-' . uniqid(),
            'subscription_id' => $subscription->id,
            'plan_id' => $this->paidPlan->id,
            'billing_interval' => 'monthly',
            'billing_period_start' => now()->subMonth()->toDateString(),
            'billing_period_end' => now()->toDateString(),
            'amount' => 10, 'subtotal' => 10, 'tax' => 0, 'total' => 10,
            'currency' => 'MMK', 'status' => Invoice::STATUS_PAID,
            'issued_at' => now(), 'paid_at' => now(),
            'line_items' => [],
        ]);
    }

    private function setPlatformSetting(string $key, $value): void
    {
        $settings = PlatformSetting::first() ?? new PlatformSetting();
        $settings->fill([
            'site_name' => 'Test',
            'trial_enabled' => true,
            'trial_days' => 14,
            'allow_trial_renewal' => true,
            'max_trial_renewals' => 0,
            $key => $value,
        ]);
        $settings->save();
        PlatformSetting::clearCache();
    }

    private function makeOwner(Tenant $tenant): User
    {
        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Owner',
            'email' => 'owner-' . uniqid() . '@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
            'is_owner' => true,
        ]);
        $user->assignRole('admin');
        $user->givePermissionTo(['billing.view', 'billing.manage', 'billing.renew']);

        return $user;
    }

    private function makePlan(string $slug, float $monthly, float $yearly): Plan
    {
        return Plan::create([
            'name' => ucfirst($slug), 'slug' => $slug . '-' . uniqid(),
            'description' => 'Test plan', 'monthly_price' => $monthly, 'yearly_price' => $yearly,
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

    private function createSchema(): void
    {
        $tables = [
            'permissions' => function ($t) { $t->id(); $t->string('name'); $t->string('guard_name'); $t->timestamps(); $t->unique(['name','guard_name']); },
            'roles' => function ($t) { $t->id(); $t->string('name'); $t->string('guard_name'); $t->unsignedBigInteger('tenant_id')->nullable(); $t->timestamps(); $t->unique(['name','guard_name','tenant_id']); },
            'model_has_roles' => function ($t) { $t->unsignedBigInteger('role_id'); $t->string('model_type'); $t->unsignedBigInteger('model_id'); $t->index(['model_id','model_type']); },
            'role_has_permissions' => function ($t) { $t->unsignedBigInteger('permission_id'); $t->unsignedBigInteger('role_id'); $t->primary(['permission_id','role_id']); },
            'model_has_permissions' => function ($t) { $t->unsignedBigInteger('permission_id'); $t->string('model_type'); $t->unsignedBigInteger('model_id'); $t->index(['model_id','model_type']); $t->primary(['permission_id','model_id','model_type']); },
            'users' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->string('name'); $t->string('email')->unique(); $t->timestamp('email_verified_at')->nullable(); $t->string('password'); $t->string('status')->default('active'); $t->boolean('is_owner')->default(false); $t->boolean('is_admin')->default(false); $t->rememberToken(); $t->timestamps(); },
            'tenants' => function ($t) { $t->id(); $t->string('slug')->unique(); $t->string('name'); $t->string('email')->nullable(); $t->string('status')->default('active'); $t->string('store_url')->nullable(); $t->json('settings')->nullable(); $t->timestamp('activated_at')->nullable(); $t->timestamp('locked_at')->nullable(); $t->timestamps(); },
            'plans' => function ($t) { $t->id(); $t->string('name'); $t->string('slug')->unique(); $t->text('description')->nullable(); $t->decimal('monthly_price',10,2)->nullable(); $t->decimal('yearly_price',10,2)->nullable(); $t->string('status',20)->default('active'); $t->boolean('analytics_enabled')->default(false); $t->boolean('custom_domain_enabled')->default(false); $t->string('price')->nullable(); $t->string('currency')->nullable(); $t->string('interval')->nullable(); $t->boolean('is_default')->default(false); $t->boolean('is_active')->default(true); $t->integer('sort_order')->default(0); $t->timestamps(); },
            'subscriptions' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id'); $t->unsignedBigInteger('plan_id')->nullable(); $t->string('billing_interval')->nullable(); $t->string('status'); $t->timestamp('starts_at')->nullable(); $t->timestamp('expires_at')->nullable(); $t->timestamp('trial_ends_at')->nullable(); $t->integer('trial_renewals_count')->default(0); $t->unsignedBigInteger('pending_plan_id')->nullable(); $t->timestamp('pending_plan_effective_at')->nullable(); $t->timestamp('suspended_at')->nullable(); $t->timestamp('cancelled_at')->nullable(); $t->timestamp('extra_renewal_used_at')->nullable(); $t->text('notes')->nullable(); $t->timestamps(); $t->index('tenant_id'); },
            'payment_intents' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id'); $t->unsignedBigInteger('plan_id'); $t->unsignedBigInteger('subscription_id')->nullable(); $t->string('billing_cycle'); $t->decimal('amount',10,2); $t->string('currency',3); $t->string('gateway'); $t->string('status'); $t->timestamp('expires_at')->nullable(); $t->json('metadata')->nullable(); $t->timestamp('completed_at')->nullable(); $t->timestamp('cancelled_at')->nullable(); $t->timestamp('rejected_at')->nullable(); $t->string('reference_number')->nullable()->unique(); $t->string('idempotency_key',64)->nullable(); $t->timestamps(); $t->index('tenant_id'); $t->index('status'); },
            'payment_timeline_events' => function ($t) { $t->id(); $t->unsignedBigInteger('payment_intent_id'); $t->string('type'); $t->text('description')->nullable(); $t->json('metadata')->nullable(); $t->timestamp('occurred_at'); $t->timestamps(); },
            'payment_transactions' => function ($t) { $t->id(); $t->unsignedBigInteger('payment_intent_id')->unique(); $t->string('transaction_number')->unique(); $t->unsignedBigInteger('tenant_id'); $t->unsignedBigInteger('plan_id'); $t->unsignedBigInteger('subscription_id')->nullable(); $t->decimal('amount',10,2); $t->string('currency',3); $t->string('gateway'); $t->string('status')->default('completed'); $t->string('gateway_reference')->nullable(); $t->json('metadata')->nullable(); $t->timestamps(); $t->index('tenant_id'); },
            'payment_evidences' => function ($t) { $t->id(); $t->unsignedBigInteger('payment_intent_id'); $t->unsignedBigInteger('tenant_id')->nullable(); $t->string('file_path')->nullable(); $t->timestamps(); },
            'payment_reviews' => function ($t) { $t->id(); $t->unsignedBigInteger('payment_intent_id'); $t->string('action')->nullable(); $t->timestamps(); },
            'ledger_entries' => function ($t) { $t->id(); $t->unsignedBigInteger('transaction_id')->nullable(); $t->unsignedBigInteger('payment_intent_id')->nullable(); $t->string('type')->nullable(); $t->decimal('amount',10,2)->default(0); $t->string('currency',3)->nullable(); $t->text('description')->nullable(); $t->json('metadata')->nullable(); $t->timestamp('recorded_at')->nullable(); $t->timestamps(); },
            'subscription_audit_logs' => function ($t) { $t->id(); $t->unsignedBigInteger('subscription_id'); $t->unsignedBigInteger('tenant_id')->nullable(); $t->string('event'); $t->string('actor_type')->nullable(); $t->unsignedBigInteger('actor_id')->nullable(); $t->unsignedBigInteger('old_plan_id')->nullable(); $t->unsignedBigInteger('new_plan_id')->nullable(); $t->string('old_status')->nullable(); $t->string('new_status')->nullable(); $t->text('reason')->nullable(); $t->json('metadata')->nullable(); $t->timestamps(); },
            'subscription_cancellation_requests' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id'); $t->unsignedBigInteger('subscription_id'); $t->string('reason',50); $t->text('comment')->nullable(); $t->string('status',20)->default('pending'); $t->unsignedBigInteger('requested_by')->nullable(); $t->unsignedBigInteger('reviewed_by')->nullable(); $t->text('review_note')->nullable(); $t->timestamp('reviewed_at')->nullable(); $t->timestamps(); $t->index('tenant_id'); $t->index('status'); },
            'invoices' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id'); $t->string('invoice_number')->unique(); $t->unsignedBigInteger('subscription_id')->nullable(); $t->unsignedBigInteger('plan_id')->nullable(); $t->string('billing_interval')->nullable(); $t->date('billing_period_start')->nullable(); $t->date('billing_period_end')->nullable(); $t->decimal('amount',10,2)->default(0); $t->decimal('subtotal',10,2)->default(0); $t->decimal('tax',10,2)->default(0); $t->decimal('total',10,2)->default(0); $t->string('currency',3)->default('MMK'); $t->string('status')->default('unpaid'); $t->unsignedBigInteger('payment_intent_id')->nullable(); $t->text('notes')->nullable(); $t->timestamp('issued_at')->nullable(); $t->timestamp('paid_at')->nullable(); $t->json('line_items')->nullable(); $t->timestamp('deleted_at')->nullable(); $t->timestamps(); },
            'receipts' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id'); $t->unsignedBigInteger('invoice_id')->nullable(); $t->unsignedBigInteger('payment_intent_id')->nullable(); $t->string('receipt_number')->unique(); $t->decimal('amount',10,2)->default(0); $t->string('currency',3)->default('MMK'); $t->timestamp('paid_at')->nullable(); $t->json('details')->nullable(); $t->timestamps(); },
            'billing_payment_methods' => function ($t) { $t->id(); $t->string('display_name'); $t->string('type')->nullable(); $t->string('account_name')->nullable(); $t->string('account_number')->nullable(); $t->string('bank_name')->nullable(); $t->string('branch')->nullable(); $t->text('instructions')->nullable(); $t->string('currency',3)->nullable(); $t->string('qr_image_url')->nullable(); $t->boolean('is_active')->default(true); $t->boolean('supports_manual_payment')->default(true); $t->integer('sort_order')->default(0); $t->timestamps(); },
            'website_infos' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->string('site_name')->nullable(); $t->string('site_tagline')->nullable(); $t->string('theme_color')->default('#3B82F6'); $t->string('default_language')->default('en'); $t->string('timezone')->default('Asia/Yangon'); $t->string('currency_code')->default('MMK'); $t->string('currency_symbol')->default('K'); $t->string('date_format')->default('Y-m-d'); $t->boolean('allow_registration')->default(true); $t->boolean('maintenance_mode')->default(false); $t->string('logo')->nullable(); $t->string('favicon')->nullable(); $t->string('og_image')->nullable(); $t->string('footer_logo')->nullable(); $t->json('hero_images')->nullable(); $t->timestamps(); },
            'platform_settings' => function ($t) { $t->id(); $t->string('site_name')->default('My Application'); $t->boolean('trial_enabled')->default(false); $t->integer('trial_days')->default(14); $t->boolean('allow_trial_renewal')->default(true); $t->integer('max_trial_renewals')->default(0); $t->integer('billing_renewal_reminder_days')->default(7); $t->unsignedInteger('tenant_data_retention_days')->default(15); $t->boolean('tenant_auto_delete_enabled')->default(false); $t->timestamps(); },
            'reference_numbers' => function ($t) { $t->id(); $t->string('prefix',10); $t->date('date'); $t->unsignedInteger('last_sequence')->default(0); $t->timestamps(); $t->unique(['prefix','date']); },
            'tenant_cleanups' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->unique(); $t->unsignedBigInteger('subscription_id')->nullable(); $t->string('status',20)->default('grace'); $t->timestamp('expires_at')->nullable(); $t->timestamp('grace_ends_at')->nullable(); $t->timestamp('retention_ends_at')->nullable(); $t->string('deletion_status',20)->default('none'); $t->timestamp('restricted_at')->nullable(); $t->timestamp('deleted_at')->nullable(); $t->timestamp('archived_at')->nullable(); $t->timestamp('last_reminder_at')->nullable(); $t->json('reminders_sent')->nullable(); $t->unsignedInteger('attempts')->default(0); $t->string('reason')->nullable(); $t->text('last_error')->nullable(); $t->json('metadata')->nullable(); $t->timestamps(); $t->index('status'); $t->index('grace_ends_at'); },
            'accounts' => function ($t) { $t->id(); $t->string('name')->nullable(); $t->string('email')->unique(); $t->string('password'); $t->timestamp('email_verified_at')->nullable(); $t->string('remember_token', 100)->nullable(); $t->string('profile_image', 255)->nullable(); $t->string('status', 50)->default('active'); $t->json('notification_preferences')->nullable(); $t->timestamp('last_login_at')->nullable(); $t->string('last_login_ip', 45)->nullable(); $t->timestamps(); $t->softDeletes(); },
            'tenant_memberships' => function ($t) { $t->id(); $t->unsignedBigInteger('account_id')->nullable(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->unsignedBigInteger('role_id')->nullable(); $t->boolean('is_owner')->default(false); $t->string('status')->default('active'); $t->timestamps(); $t->softDeletes(); },
            'notifications' => function ($t) { $t->uuid('id')->primary(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->string('type'); $t->string('notifiable_type'); $t->unsignedBigInteger('notifiable_id'); $t->text('data'); $t->timestamp('read_at')->nullable(); $t->timestamps(); },
            'plan_features' => function ($t) { $t->id(); $t->unsignedBigInteger('plan_id'); $t->string('feature_key'); $t->boolean('is_enabled')->default(false); $t->string('display_label')->nullable(); $t->text('description')->nullable(); $t->timestamps(); },
            'payment_methods' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->string('name')->nullable(); $t->boolean('is_active')->default(true); $t->timestamps(); },
            'flash_sales' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->string('name')->nullable(); $t->boolean('is_active')->default(true); $t->timestamps(); },
            'wishlists' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->unsignedBigInteger('user_id')->nullable(); $t->string('user_type')->nullable(); $t->unsignedBigInteger('product_id')->nullable(); $t->timestamps(); },
            'settings' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->string('key'); $t->text('value')->nullable(); $t->timestamps(); },
            'messages' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->text('message')->nullable(); $t->timestamps(); },
            'telegram_integrations' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->timestamps(); },
            'products' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->unsignedBigInteger('category_id')->nullable(); $t->string('type')->default('single'); $t->string('name')->nullable(); $t->string('sku')->nullable(); $t->decimal('price',10,2)->default(0); $t->integer('stock')->default(0); $t->string('status')->default('active'); $t->boolean('is_active')->default(true); $t->timestamps(); $t->index('tenant_id'); },
            'categories' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->string('name'); $t->timestamps(); },
            'brands' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->string('name'); $t->string('slug')->nullable(); $t->timestamps(); },
            'units' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->string('name'); $t->string('short_name')->nullable(); $t->timestamps(); },
            'promotions' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->string('name')->nullable(); $t->string('code')->nullable(); $t->boolean('is_active')->default(true); $t->boolean('is_automatic')->default(false); $t->timestamps(); },
            'coupons' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->timestamps(); },
            'orders' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->timestamps(); },
            'activity_logs' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->string('log_name')->nullable(); $t->string('category', 50)->nullable(); $t->string('severity', 20)->default('info'); $t->text('description')->nullable(); $t->string('subject_type')->nullable(); $t->unsignedBigInteger('subject_id')->nullable(); $t->string('causer_type')->nullable(); $t->unsignedBigInteger('causer_id')->nullable(); $t->unsignedBigInteger('impersonator_id')->nullable(); $t->unsignedBigInteger('impersonated_user_id')->nullable(); $t->json('properties')->nullable(); $t->string('event')->nullable(); $t->string('batch_uuid')->nullable(); $t->timestamps(); },
        ];

        foreach ($tables as $name => $callback) {
            if (!Schema::hasTable($name)) {
                Schema::create($name, $callback);
            }
        }

        if (!Schema::hasColumn('platform_settings', 'billing_renewal_reminder_days')) {
            Schema::table('platform_settings', function ($t) {
                $t->integer('billing_renewal_reminder_days')->default(7);
            });
        }

        foreach (['retention_ends_at', 'deleted_at'] as $column) {
            if (!Schema::hasColumn('tenant_cleanups', $column)) {
                Schema::table('tenant_cleanups', function ($t) use ($column) {
                    $t->timestamp($column)->nullable();
                });
            }
        }

        if (!Schema::hasColumn('tenant_cleanups', 'deletion_status')) {
            Schema::table('tenant_cleanups', function ($t) {
                $t->string('deletion_status', 20)->default('none');
            });
        }
    }
}
