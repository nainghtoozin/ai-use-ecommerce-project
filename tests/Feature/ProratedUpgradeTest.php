<?php

namespace Tests\Feature;

use App\Data\Currency;
use App\Models\PaymentIntent;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionAuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Payment\Platform\ManualPaymentService;
use App\Services\Payment\Platform\PaymentIntentService;
use App\Services\SubscriptionPlanChangeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProratedUpgradeTest extends TestCase
{
    use DatabaseTransactions;

    private Carbon $now;
    private Plan $current;
    private Plan $target;
    private Tenant $tenant;
    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createBillingSchema();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['billing.view', 'billing.manage', 'billing.renew'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $role = \App\Models\Role::withoutTenantScope()->firstOrCreate([
            'name' => 'admin', 'guard_name' => 'web', 'tenant_id' => null,
        ]);
        $role->syncPermissions(['billing.view', 'billing.manage', 'billing.renew']);

        $this->now = Carbon::parse('2026-06-15 12:00:00');
        $this->travelTo($this->now);

        $this->current = $this->makePlan('basic', 100, 1000);
        $this->target = $this->makePlan('pro', 200, 2000);

        $this->tenant = Tenant::create([
            'slug' => 'prorate-' . uniqid(),
            'name' => 'Prorate Shop',
            'status' => 'active',
        ]);

        $this->subscription = $this->makeSubscription($this->tenant, $this->current, 'active', $this->now->copy()->addDays(15));
    }

    protected function tearDown(): void
    {
        Tenant::setCurrent(null);
        Carbon::setTestNow();

        parent::tearDown();
    }

    /* ── 4. Correct credit, prorated price, final amount after rounding ── */

    public function test_proration_breakdown_monthly(): void
    {
        $result = app(SubscriptionPlanChangeService::class)
            ->calculateProration($this->subscription, $this->target, 'monthly');

        $this->assertTrue($result['is_upgrade']);
        $this->assertSame(30, $result['period_days']);
        $this->assertSame(15, $result['days_remaining']);
        $this->assertEqualsWithDelta(0.5, $result['remaining_fraction'], 0.0001);
        $this->assertSame(50.0, $result['credit_amount']);
        $this->assertSame(100.0, $result['target_prorated']);
        $this->assertSame(50.0, $result['amount_due']);
        $this->assertSame(50.0, $result['total_due']);
    }

    public function test_proration_rounding_is_two_decimals(): void
    {
        $this->subscription->update(['expires_at' => $this->now->copy()->addDays(10)]);

        $result = app(SubscriptionPlanChangeService::class)
            ->calculateProration($this->subscription, $this->target, 'monthly');

        $this->assertSame(33.33, $result['credit_amount']);
        $this->assertSame(66.67, $result['target_prorated']);
        $this->assertSame(33.33, $result['amount_due']);
    }

    /* ── 1. Upgrade with a partially used monthly subscription ── */

    public function test_partial_monthly_upgrade_creates_pending_payment(): void
    {
        $this->actingAs($this->makeOwner($this->tenant));
        $oldExpiry = $this->subscription->expires_at->toDateTimeString();

        $this->from("/store/{$this->tenant->slug}/admin/billing")
            ->post("/store/{$this->tenant->slug}/admin/billing/change-plan/submit", [
                'plan_id' => $this->target->id,
                'billing_interval' => 'monthly',
            ])
            ->assertRedirect();

        $intent = $this->pendingUpgradeIntent();
        $this->assertNotNull($intent);
        $this->assertSame($this->target->id, $intent->plan_id);
        $this->assertEqualsWithDelta(50.0, (float) $intent->amount, 0.001);
        $this->assertSame(SubscriptionPlanChangeService::TYPE_PLAN_UPGRADE, $intent->metadata['type']);
        $this->assertEqualsWithDelta(50.0, (float) $intent->metadata['proration']['amount_due'], 0.001);

        $this->assertSame($this->current->id, $this->subscription->fresh()->plan_id);
        $this->assertSame($oldExpiry, $this->subscription->fresh()->expires_at->toDateTimeString());
    }

    /* ── 2. Upgrade with a yearly subscription ── */

    public function test_yearly_upgrade_uses_yearly_period(): void
    {
        $this->subscription->update([
            'billing_interval' => 'yearly',
            'expires_at' => $this->now->copy()->addDays(180),
        ]);

        $result = app(SubscriptionPlanChangeService::class)
            ->calculateProration($this->subscription, $this->target, 'yearly');

        $this->assertSame(365, $result['period_days']);
        $this->assertTrue($result['is_upgrade']);

        $expected = round((2000 - 1000) * (180 / 365), 2);
        $this->assertSame($expected, $result['amount_due']);

        $this->actingAs($this->makeOwner($this->tenant));
        $this->post("/store/{$this->tenant->slug}/admin/billing/change-plan/submit", [
            'plan_id' => $this->target->id,
            'billing_interval' => 'yearly',
        ])->assertRedirect();

        $intent = $this->pendingUpgradeIntent();
        $this->assertNotNull($intent);
        $this->assertEqualsWithDelta($expected, (float) $intent->amount, 0.01);
    }

    /* ── 3. Upgrade when the subscription has almost no time remaining ── */

    public function test_upgrade_near_expiry_charges_small_prorated_amount(): void
    {
        $this->subscription->update(['expires_at' => $this->now->copy()->addHours(2)]);

        $result = app(SubscriptionPlanChangeService::class)
            ->calculateProration($this->subscription, $this->target, 'monthly');

        $this->assertGreaterThan(0.0, $result['amount_due']);
        $this->assertLessThan(5.0, $result['amount_due']);

        $this->actingAs($this->makeOwner($this->tenant));
        $this->post("/store/{$this->tenant->slug}/admin/billing/change-plan/submit", [
            'plan_id' => $this->target->id,
            'billing_interval' => 'monthly',
        ])->assertRedirect();

        $intent = $this->pendingUpgradeIntent();
        $this->assertNotNull($intent);
        $this->assertEqualsWithDelta($result['amount_due'], (float) $intent->amount, 0.01);
        $this->assertSame($this->current->id, $this->subscription->fresh()->plan_id);
    }

    /* ── 5. Pending payment does not activate the new plan ── */

    public function test_pending_payment_does_not_activate_new_plan(): void
    {
        $owner = $this->makeOwner($this->tenant);
        $this->actingAs($owner);
        $oldExpiry = $this->subscription->expires_at->toDateTimeString();

        $this->post("/store/{$this->tenant->slug}/admin/billing/change-plan/submit", [
            'plan_id' => $this->target->id,
            'billing_interval' => 'monthly',
        ]);

        $intent = $this->pendingUpgradeIntent();
        app(ManualPaymentService::class)->confirmPayment($intent);
        $intent->refresh();

        $this->assertSame('waiting_review', $intent->status);
        $this->assertSame($this->current->id, $this->subscription->fresh()->plan_id);
        $this->assertSame($oldExpiry, $this->subscription->fresh()->expires_at->toDateTimeString());
    }

    /* ── 6. Approved payment applies the upgrade correctly ── */

    public function test_approved_payment_applies_upgrade_and_preserves_expiry(): void
    {
        $owner = $this->makeOwner($this->tenant);
        $this->actingAs($owner);
        $oldExpiry = $this->subscription->expires_at->toDateTimeString();

        $this->post("/store/{$this->tenant->slug}/admin/billing/change-plan/submit", [
            'plan_id' => $this->target->id,
            'billing_interval' => 'monthly',
        ]);

        $intent = $this->pendingUpgradeIntent();
        $manual = app(ManualPaymentService::class);
        $manual->confirmPayment($intent);
        $manual->approvePayment($intent->fresh());

        $subscription = $this->subscription->fresh();
        $this->assertSame($this->target->id, $subscription->plan_id);
        $this->assertSame('active', $subscription->status);
        $this->assertSame($oldExpiry, $subscription->expires_at->toDateTimeString());

        $log = SubscriptionAuditLog::where('subscription_id', $subscription->id)
            ->where('event', 'plan_upgraded')
            ->latest()
            ->first();

        $this->assertNotNull($log);
        $this->assertEqualsWithDelta(50.0, (float) $log->metadata['approved_amount'], 0.001);
        $this->assertEqualsWithDelta(50.0, (float) $log->metadata['requested_amount'], 0.001);
    }

    /* ── 7. Rejected payment preserves the current subscription ── */

    public function test_rejected_payment_preserves_subscription(): void
    {
        $owner = $this->makeOwner($this->tenant);
        $this->actingAs($owner);
        $oldExpiry = $this->subscription->expires_at->toDateTimeString();

        $this->post("/store/{$this->tenant->slug}/admin/billing/change-plan/submit", [
            'plan_id' => $this->target->id,
            'billing_interval' => 'monthly',
        ]);

        $intent = $this->pendingUpgradeIntent();
        $manual = app(ManualPaymentService::class);
        $manual->confirmPayment($intent);
        $manual->rejectPayment($intent->fresh(), 'Evidence unclear.');

        $subscription = $this->subscription->fresh();
        $this->assertSame('rejected', $intent->fresh()->status);
        $this->assertSame($this->current->id, $subscription->plan_id);
        $this->assertSame($oldExpiry, $subscription->expires_at->toDateTimeString());
        $this->assertFalse(SubscriptionAuditLog::where('subscription_id', $subscription->id)->where('event', 'plan_upgraded')->exists());
    }

    /* ── 8. Duplicate pending requests are prevented ── */

    public function test_duplicate_pending_requests_are_prevented(): void
    {
        $this->actingAs($this->makeOwner($this->tenant));

        $url = "/store/{$this->tenant->slug}/admin/billing/change-plan/submit";
        $this->post($url, ['plan_id' => $this->target->id, 'billing_interval' => 'monthly']);
        $this->post($url, ['plan_id' => $this->target->id, 'billing_interval' => 'monthly']);

        $count = PaymentIntent::withoutTenantScope()
            ->where('tenant_id', $this->tenant->id)
            ->where('plan_id', $this->target->id)
            ->whereIn('status', ['waiting_payment', 'waiting_review'])
            ->count();

        $this->assertSame(1, $count);
    }

    /* ── 9. Scheduled plan changes handled explicitly ── */

    public function test_scheduled_change_requires_explicit_decision(): void
    {
        $smaller = $this->makePlan('small', 50, 500);
        $this->subscription->update([
            'pending_plan_id' => $smaller->id,
            'pending_plan_effective_at' => $this->subscription->expires_at,
        ]);

        $this->actingAs($this->makeOwner($this->tenant));

        $this->from("/store/{$this->tenant->slug}/admin/billing/upgrade")
            ->post("/store/{$this->tenant->slug}/admin/billing/change-plan/submit", [
                'plan_id' => $this->target->id,
                'billing_interval' => 'monthly',
            ])
            ->assertSessionHas('error');

        $this->assertSame(0, PaymentIntent::withoutTenantScope()
            ->where('tenant_id', $this->tenant->id)
            ->whereIn('status', ['waiting_payment', 'waiting_review'])
            ->count());
    }

    public function test_scheduled_change_can_be_cancelled_on_approval(): void
    {
        $smaller = $this->makePlan('small2', 50, 500);
        $this->subscription->update([
            'pending_plan_id' => $smaller->id,
            'pending_plan_effective_at' => $this->subscription->expires_at,
        ]);

        $this->actingAs($this->makeOwner($this->tenant));
        $this->post("/store/{$this->tenant->slug}/admin/billing/change-plan/submit", [
            'plan_id' => $this->target->id,
            'billing_interval' => 'monthly',
            'scheduled_change_action' => 'cancel',
        ]);

        $intent = $this->pendingUpgradeIntent();
        $this->assertSame('cancel', $intent->metadata['scheduled_change_action']);

        $manual = app(ManualPaymentService::class);
        $manual->confirmPayment($intent);
        $manual->approvePayment($intent->fresh());

        $subscription = $this->subscription->fresh();
        $this->assertSame($this->target->id, $subscription->plan_id);
        $this->assertNull($subscription->pending_plan_id);
    }

    public function test_scheduled_change_can_be_kept_on_approval(): void
    {
        $smaller = $this->makePlan('small3', 50, 500);
        $this->subscription->update([
            'pending_plan_id' => $smaller->id,
            'pending_plan_effective_at' => $this->subscription->expires_at,
        ]);

        $this->actingAs($this->makeOwner($this->tenant));
        $this->post("/store/{$this->tenant->slug}/admin/billing/change-plan/submit", [
            'plan_id' => $this->target->id,
            'billing_interval' => 'monthly',
            'scheduled_change_action' => 'keep',
        ]);

        $intent = $this->pendingUpgradeIntent();
        $manual = app(ManualPaymentService::class);
        $manual->confirmPayment($intent);
        $manual->approvePayment($intent->fresh());

        $subscription = $this->subscription->fresh();
        $this->assertSame($this->target->id, $subscription->plan_id);
        $this->assertSame($smaller->id, $subscription->pending_plan_id);
    }

    /* ── 10. Tenant isolation and authorization ── */

    public function test_submit_requires_billing_manage_permission(): void
    {
        $user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Staff',
            'email' => 'staff-' . uniqid() . '@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $user->givePermissionTo('billing.view');
        $this->actingAs($user);

        $this->post("/store/{$this->tenant->slug}/admin/billing/change-plan/submit", [
            'plan_id' => $this->target->id,
            'billing_interval' => 'monthly',
        ])->assertStatus(403);
    }

    public function test_upgrade_intent_is_tenant_isolated(): void
    {
        $other = Tenant::create([
            'slug' => 'prorate-other-' . uniqid(),
            'name' => 'Other',
            'status' => 'active',
        ]);

        $this->actingAs($this->makeOwner($this->tenant));
        $this->post("/store/{$this->tenant->slug}/admin/billing/change-plan/submit", [
            'plan_id' => $this->target->id,
            'billing_interval' => 'monthly',
        ]);

        $intent = $this->pendingUpgradeIntent();
        $service = app(PaymentIntentService::class);

        $this->assertNotNull($service->findByReferenceForTenant($this->tenant, $intent->reference_number));
        $this->assertNull($service->findByReferenceForTenant($other, $intent->reference_number));
    }

    /* ── 11. Existing renewals and scheduled downgrades continue working ── */

    public function test_renewal_via_payment_still_extends_expiry(): void
    {
        $oldExpiry = $this->subscription->expires_at->copy();

        $manual = app(ManualPaymentService::class);
        $intent = $manual->initiate(
            tenant: $this->tenant,
            plan: $this->current,
            billingCycle: 'monthly',
            amount: 100.0,
            currency: Currency::fromCode('MMK'),
        );
        $manual->confirmPayment($intent);
        $manual->approvePayment($intent->fresh());

        $subscription = $this->subscription->fresh();
        $this->assertSame($this->current->id, $subscription->plan_id);
        $this->assertEquals(
            $oldExpiry->copy()->addMonth()->endOfDay()->toDateTimeString(),
            $subscription->expires_at->toDateTimeString()
        );
    }

    public function test_scheduled_downgrade_still_applies(): void
    {
        $smaller = $this->makePlan('smaller', 50, 500);

        app(SubscriptionPlanChangeService::class)->executeDowngrade($this->subscription, $smaller, 'monthly');
        $this->assertSame($smaller->id, $this->subscription->fresh()->pending_plan_id);

        $this->travelTo($this->subscription->fresh()->pending_plan_effective_at->copy()->addDay());
        app(SubscriptionPlanChangeService::class)->applyScheduledChanges();

        $this->assertSame($smaller->id, $this->subscription->fresh()->plan_id);
        $this->assertNull($this->subscription->fresh()->pending_plan_id);
    }

    /* ── Helpers ── */

    private function pendingUpgradeIntent(): ?PaymentIntent
    {
        return PaymentIntent::withoutTenantScope()
            ->where('tenant_id', $this->tenant->id)
            ->whereIn('status', ['waiting_payment', 'waiting_review'])
            ->latest()
            ->first();
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
            'name' => ucfirst($slug),
            'slug' => $slug . '-' . uniqid(),
            'description' => 'Test plan',
            'monthly_price' => $monthly,
            'yearly_price' => $yearly,
            'status' => 'active',
        ]);
    }

    private function makeSubscription(Tenant $tenant, Plan $plan, string $status, Carbon $expiresAt): Subscription
    {
        $subscription = new Subscription([
            'plan_id' => $plan->id,
            'billing_interval' => 'monthly',
            'status' => $status,
            'starts_at' => $expiresAt->copy()->subMonth(),
            'expires_at' => $expiresAt,
            'trial_renewals_count' => 0,
        ]);
        $subscription->tenant_id = $tenant->id;
        $subscription->save();

        return $subscription;
    }

    private function createBillingSchema(): void
    {
        if (!Schema::hasTable('permissions')) {
            Schema::create('permissions', function ($table) {
                $table->id(); $table->string('name'); $table->string('guard_name');
                $table->timestamps(); $table->unique(['name', 'guard_name']);
            });
        }
        if (!Schema::hasTable('roles')) {
            Schema::create('roles', function ($table) {
                $table->id(); $table->string('name'); $table->string('guard_name');
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->timestamps(); $table->unique(['name', 'guard_name', 'tenant_id']);
            });
        }
        if (!Schema::hasTable('model_has_roles')) {
            Schema::create('model_has_roles', function ($table) {
                $table->unsignedBigInteger('role_id'); $table->string('model_type');
                $table->unsignedBigInteger('model_id'); $table->index(['model_id', 'model_type']);
            });
        }
        if (!Schema::hasTable('role_has_permissions')) {
            Schema::create('role_has_permissions', function ($table) {
                $table->unsignedBigInteger('permission_id'); $table->unsignedBigInteger('role_id');
                $table->primary(['permission_id', 'role_id']);
            });
        }
        if (!Schema::hasTable('model_has_permissions')) {
            Schema::create('model_has_permissions', function ($table) {
                $table->unsignedBigInteger('permission_id'); $table->string('model_type');
                $table->unsignedBigInteger('model_id'); $table->index(['model_id', 'model_type']);
                $table->primary(['permission_id', 'model_id', 'model_type']);
            });
        }
        if (!Schema::hasTable('users')) {
            Schema::create('users', function ($table) {
                $table->id(); $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('name'); $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable(); $table->string('password');
                $table->string('status')->default('active');
                $table->boolean('is_owner')->default(false); $table->boolean('is_admin')->default(false);
                $table->rememberToken(); $table->timestamps();
            });
        }
        if (!Schema::hasTable('tenants')) {
            Schema::create('tenants', function ($table) {
                $table->id(); $table->string('slug')->unique(); $table->string('name');
                $table->string('email')->nullable(); $table->string('status')->default('active');
                $table->string('store_url')->nullable(); $table->json('settings')->nullable();
                $table->timestamp('activated_at')->nullable(); $table->timestamp('locked_at')->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('plans')) {
            Schema::create('plans', function ($table) {
                $table->id(); $table->string('name'); $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->decimal('monthly_price', 10, 2)->nullable();
                $table->decimal('yearly_price', 10, 2)->nullable();
                $table->string('status', 20)->default('active'); $table->timestamps();
            });
        }
        if (!Schema::hasTable('subscriptions')) {
            Schema::create('subscriptions', function ($table) {
                $table->id(); $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('plan_id')->nullable(); $table->string('billing_interval')->nullable();
                $table->string('status'); $table->timestamp('starts_at')->nullable();
                $table->timestamp('expires_at')->nullable(); $table->timestamp('trial_ends_at')->nullable();
                $table->integer('trial_renewals_count')->default(0);
                $table->unsignedBigInteger('pending_plan_id')->nullable();
                $table->timestamp('pending_plan_effective_at')->nullable();
                $table->timestamp('suspended_at')->nullable(); $table->timestamp('cancelled_at')->nullable();
                $table->text('notes')->nullable(); $table->timestamps(); $table->index('tenant_id');
            });
        }
        if (!Schema::hasTable('payment_intents')) {
            Schema::create('payment_intents', function ($table) {
                $table->id(); $table->unsignedBigInteger('tenant_id'); $table->unsignedBigInteger('plan_id');
                $table->unsignedBigInteger('subscription_id')->nullable(); $table->string('billing_cycle');
                $table->decimal('amount', 10, 2); $table->string('currency', 3); $table->string('gateway');
                $table->string('status'); $table->timestamp('expires_at')->nullable(); $table->json('metadata')->nullable();
                $table->timestamp('completed_at')->nullable(); $table->timestamp('cancelled_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->string('reference_number')->nullable()->unique();
                $table->string('idempotency_key', 64)->nullable();
                $table->timestamps(); $table->index('tenant_id'); $table->index('status');
            });
        }
        if (!Schema::hasTable('payment_evidences')) {
            Schema::create('payment_evidences', function ($table) {
                $table->id(); $table->unsignedBigInteger('payment_intent_id'); $table->string('type');
                $table->string('file_path')->nullable(); $table->text('note')->nullable(); $table->json('metadata')->nullable();
                $table->string('sender_name')->nullable(); $table->string('sender_account')->nullable();
                $table->string('transaction_reference')->nullable();
                $table->decimal('transferred_amount', 10, 2)->nullable(); $table->date('transfer_date')->nullable();
                $table->timestamps(); $table->index('payment_intent_id');
            });
        }
        if (!Schema::hasTable('payment_timeline_events')) {
            Schema::create('payment_timeline_events', function ($table) {
                $table->id(); $table->unsignedBigInteger('payment_intent_id'); $table->string('type');
                $table->text('description')->nullable(); $table->json('metadata')->nullable();
                $table->timestamp('occurred_at'); $table->timestamps(); $table->index('payment_intent_id');
            });
        }
        if (!Schema::hasTable('payment_transactions')) {
            Schema::create('payment_transactions', function ($table) {
                $table->id(); $table->unsignedBigInteger('payment_intent_id')->unique();
                $table->string('transaction_number')->unique(); $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('plan_id'); $table->unsignedBigInteger('subscription_id')->nullable();
                $table->decimal('amount', 10, 2); $table->string('currency', 3); $table->string('gateway');
                $table->string('status')->default('completed'); $table->string('gateway_reference')->nullable();
                $table->json('metadata')->nullable(); $table->timestamps(); $table->index('tenant_id');
            });
        }
        if (!Schema::hasTable('payment_reviews')) {
            Schema::create('payment_reviews', function ($table) {
                $table->id(); $table->unsignedBigInteger('payment_intent_id');
                $table->unsignedBigInteger('reviewed_by')->nullable(); $table->string('action')->nullable();
                $table->text('reason')->nullable(); $table->timestamp('reviewed_at')->nullable(); $table->timestamps();
            });
        }
        if (!Schema::hasTable('subscription_audit_logs')) {
            Schema::create('subscription_audit_logs', function ($table) {
                $table->id(); $table->unsignedBigInteger('subscription_id'); $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('event'); $table->string('actor_type')->nullable(); $table->unsignedBigInteger('actor_id')->nullable();
                $table->unsignedBigInteger('old_plan_id')->nullable(); $table->unsignedBigInteger('new_plan_id')->nullable();
                $table->string('old_status')->nullable(); $table->string('new_status')->nullable();
                $table->text('reason')->nullable(); $table->json('metadata')->nullable(); $table->timestamps();
            });
        }
        if (!Schema::hasTable('invoices')) {
            Schema::create('invoices', function ($table) {
                $table->id(); $table->unsignedBigInteger('tenant_id');
                $table->string('invoice_number')->unique();
                $table->unsignedBigInteger('subscription_id')->nullable(); $table->unsignedBigInteger('plan_id')->nullable();
                $table->string('billing_interval')->nullable();
                $table->date('billing_period_start')->nullable(); $table->date('billing_period_end')->nullable();
                $table->decimal('amount', 10, 2)->default(0); $table->decimal('subtotal', 10, 2)->default(0);
                $table->decimal('tax', 10, 2)->default(0); $table->decimal('total', 10, 2)->default(0);
                $table->string('currency', 3)->default('MMK'); $table->string('status')->default('unpaid');
                $table->unsignedBigInteger('payment_intent_id')->nullable(); $table->text('notes')->nullable();
                $table->timestamp('issued_at')->nullable(); $table->timestamp('paid_at')->nullable();
                $table->json('line_items')->nullable(); $table->timestamp('deleted_at')->nullable(); $table->timestamps();
            });
        }
        if (!Schema::hasTable('receipts')) {
            Schema::create('receipts', function ($table) {
                $table->id(); $table->unsignedBigInteger('tenant_id'); $table->unsignedBigInteger('invoice_id')->nullable();
                $table->unsignedBigInteger('payment_intent_id')->nullable(); $table->string('receipt_number')->unique();
                $table->decimal('amount', 10, 2)->default(0); $table->string('currency', 3)->default('MMK');
                $table->timestamp('paid_at')->nullable(); $table->json('details')->nullable(); $table->timestamps();
            });
        }
        if (!Schema::hasTable('billing_payment_methods')) {
            Schema::create('billing_payment_methods', function ($table) {
                $table->id(); $table->string('display_name'); $table->string('type')->nullable();
                $table->string('account_name')->nullable(); $table->string('account_number')->nullable();
                $table->string('bank_name')->nullable(); $table->string('branch')->nullable();
                $table->text('instructions')->nullable(); $table->string('currency', 3)->nullable();
                $table->string('qr_image_url')->nullable(); $table->boolean('is_active')->default(true);
                $table->boolean('supports_manual_payment')->default(true);
                $table->integer('sort_order')->default(0); $table->timestamps();
            });
        }
        if (!Schema::hasTable('website_infos')) {
            Schema::create('website_infos', function ($table) {
                $table->id(); $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('site_name')->nullable(); $table->string('currency_code')->default('MMK');
                $table->string('currency_symbol')->default('K'); $table->timestamps();
            });
        }
        if (!Schema::hasTable('reference_numbers')) {
            Schema::create('reference_numbers', function ($table) {
                $table->id(); $table->string('prefix', 10); $table->date('date');
                $table->unsignedInteger('last_sequence')->default(0); $table->timestamps();
                $table->unique(['prefix', 'date']);
            });
        }
    }
}
