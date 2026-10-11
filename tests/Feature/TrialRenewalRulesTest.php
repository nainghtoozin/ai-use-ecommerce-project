<?php

namespace Tests\Feature;

use App\Models\PaymentIntent;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Payment\Platform\ManualPaymentService;
use App\Services\SubscriptionPlanChangeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TrialRenewalRulesTest extends TestCase
{
    use DatabaseTransactions;

    private Carbon $now;
    private Plan $paidPlan;
    private Plan $higherPlan;
    private Plan $freePlan;
    private Tenant $tenant;
    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTrialSchema();

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

        $this->setPlatformSettings([
            'trial_enabled' => true,
            'trial_days' => 14,
            'allow_trial_renewal' => true,
            'max_trial_renewals' => 3,
        ]);

        PlatformSetting::clearCache();

        $this->freePlan = $this->makePlan('free', 0, 0);
        $this->paidPlan = $this->makePlan('starter', 10, 100);
        $this->higherPlan = $this->makePlan('pro', 30, 300);

        $this->tenant = Tenant::create([
            'slug' => 'trial-' . uniqid(),
            'name' => 'Trial Shop',
            'status' => 'active',
        ]);

        $this->subscription = $this->makeTrialSubscription($this->tenant, $this->paidPlan, $this->now->copy()->addDays(7));
    }

    protected function tearDown(): void
    {
        Tenant::setCurrent(null);
        Carbon::setTestNow();
        PlatformSetting::clearCache();

        parent::tearDown();
    }

    /* ── 1. Trial renewal disabled ── */

    public function test_trial_renewal_disabled_is_blocked(): void
    {
        $this->setPlatformSettings(['allow_trial_renewal' => false]);
        $oldTrialEnd = $this->subscription->trial_ends_at->toDateTimeString();

        $this->actingAs($this->makeOwner($this->tenant));
        $this->from("/store/{$this->tenant->slug}/admin/billing")
            ->post("/store/{$this->tenant->slug}/admin/billing/renew")
            ->assertSessionHas('error');

        $fresh = $this->subscription->fresh();
        $this->assertSame(0, $fresh->trial_renewals_count);
        $this->assertSame($oldTrialEnd, $fresh->trial_ends_at->toDateTimeString());
    }

    /* ── 2. Trial renewal enabled within the allowed limit ── */

    public function test_trial_renewal_within_limit_extends_and_counts(): void
    {
        $oldTrialEnd = $this->subscription->trial_ends_at->copy();

        $this->actingAs($this->makeOwner($this->tenant));
        $this->from("/store/{$this->tenant->slug}/admin/billing")
            ->post("/store/{$this->tenant->slug}/admin/billing/renew")
            ->assertRedirect();

        $fresh = $this->subscription->fresh();
        $this->assertSame(1, $fresh->trial_renewals_count);
        $this->assertSame('trialing', $fresh->status);
        $this->assertEquals(
            $oldTrialEnd->copy()->addDays(14)->endOfDay()->toDateTimeString(),
            $fresh->trial_ends_at->toDateTimeString()
        );
    }

    /* ── 3. Trial renewal rejected when the limit is exhausted ── */

    public function test_trial_renewal_blocked_when_limit_exhausted(): void
    {
        $this->setPlatformSettings(['max_trial_renewals' => 2]);
        $this->subscription->update(['trial_renewals_count' => 2]);

        $this->actingAs($this->makeOwner($this->tenant));
        $this->from("/store/{$this->tenant->slug}/admin/billing")
            ->post("/store/{$this->tenant->slug}/admin/billing/renew")
            ->assertSessionHas('error');

        $this->assertSame(2, $this->subscription->fresh()->trial_renewals_count);
    }

    /* ── 4. Max Trial Renewals = 0 means unlimited ── */

    public function test_max_trial_renewals_zero_allows_unlimited(): void
    {
        $this->setPlatformSettings(['max_trial_renewals' => 0]);

        $this->actingAs($this->makeOwner($this->tenant));

        for ($i = 1; $i <= 3; $i++) {
            $this->from("/store/{$this->tenant->slug}/admin/billing")
                ->post("/store/{$this->tenant->slug}/admin/billing/renew")
                ->assertRedirect();

            $this->assertSame($i, $this->subscription->fresh()->trial_renewals_count);
        }
    }

    /* ── 5. Rejected/pending requests do not consume the allowance ── */

    public function test_pending_and_rejected_conversion_do_not_consume_allowance(): void
    {
        $oldTrialEnd = $this->subscription->trial_ends_at->toDateTimeString();

        $this->actingAs($this->makeOwner($this->tenant));
        $this->post("/store/{$this->tenant->slug}/admin/billing/change-plan/submit", [
            'plan_id' => $this->higherPlan->id,
            'billing_interval' => 'monthly',
        ])->assertRedirect();

        // Submitting a request must not extend the trial or consume an allowance.
        $this->assertSame(0, $this->subscription->fresh()->trial_renewals_count);
        $this->assertSame($oldTrialEnd, $this->subscription->fresh()->trial_ends_at->toDateTimeString());

        $intent = $this->pendingIntent();
        $manual = app(ManualPaymentService::class);
        $manual->confirmPayment($intent);
        $manual->rejectPayment($intent->fresh(), 'Wrong amount.');

        $this->assertSame(0, $this->subscription->fresh()->trial_renewals_count);
        $this->assertSame('trialing', $this->subscription->fresh()->status);
        $this->assertSame($oldTrialEnd, $this->subscription->fresh()->trial_ends_at->toDateTimeString());
    }

    /* ── 6. Duplicate renewal prevention ── */

    public function test_duplicate_pending_conversion_is_prevented(): void
    {
        $this->actingAs($this->makeOwner($this->tenant));

        $url = "/store/{$this->tenant->slug}/admin/billing/change-plan/submit";
        $this->post($url, ['plan_id' => $this->higherPlan->id, 'billing_interval' => 'monthly']);
        $this->post($url, ['plan_id' => $this->higherPlan->id, 'billing_interval' => 'monthly']);

        $this->assertSame(1, PaymentIntent::withoutTenantScope()
            ->where('tenant_id', $this->tenant->id)
            ->whereIn('status', ['waiting_payment', 'waiting_review'])
            ->count());
    }

    /* ── 7. Trial-to-paid conversion requires the payment workflow ── */

    public function test_trial_to_paid_conversion_requires_payment(): void
    {
        $this->actingAs($this->makeOwner($this->tenant));

        $this->post("/store/{$this->tenant->slug}/admin/billing/change-plan/submit", [
            'plan_id' => $this->higherPlan->id,
            'billing_interval' => 'monthly',
        ])->assertRedirect();

        $intent = $this->pendingIntent();
        $this->assertNotNull($intent);
        $this->assertSame($this->higherPlan->id, $intent->plan_id);
        $this->assertTrue($intent->metadata['trial_conversion'] ?? false);
        $this->assertArrayNotHasKey('type', $intent->metadata);
        $this->assertEqualsWithDelta(30.0, (float) $intent->amount, 0.001);

        // Not activated yet.
        $this->assertSame($this->paidPlan->id, $this->subscription->fresh()->plan_id);
        $this->assertSame('trialing', $this->subscription->fresh()->status);

        $manual = app(ManualPaymentService::class);
        $manual->confirmPayment($intent);
        $manual->approvePayment($intent->fresh());

        $fresh = $this->subscription->fresh();
        $this->assertSame($this->higherPlan->id, $fresh->plan_id);
        $this->assertSame('active', $fresh->status);
        $this->assertFalse($fresh->isTrialing());
        $this->assertTrue($fresh->expires_at->isFuture());
        $this->assertGreaterThan($this->now->copy()->addDays(29)->timestamp, $fresh->expires_at->timestamp);
    }

    /* ── 8. Trial subscriptions receive no paid-plan unused-value credit ── */

    public function test_trial_proration_has_no_credit(): void
    {
        $quote = app(SubscriptionPlanChangeService::class)
            ->calculateProration($this->subscription, $this->higherPlan, 'monthly');

        $this->assertTrue($quote['is_trial_conversion']);
        $this->assertSame(0.0, $quote['credit_amount']);
        $this->assertSame(30.0, $quote['amount_due']);
        $this->assertSame(30.0, $quote['target_prorated']);
    }

    /* ── 9. Paid-plan renewal and prorated upgrades remain unchanged ── */

    public function test_paid_proration_unchanged_and_renewal_creates_payment(): void
    {
        $this->subscription->update([
            'status' => 'active',
            'trial_ends_at' => null,
            'expires_at' => $this->now->copy()->addDays(15),
        ]);

        $quote = app(SubscriptionPlanChangeService::class)
            ->calculateProration($this->subscription, $this->higherPlan, 'monthly');

        $this->assertFalse($quote['is_trial_conversion']);
        $this->assertSame(5.0, $quote['credit_amount']);
        $this->assertSame(10.0, $quote['amount_due']);

        $this->actingAs($this->makeOwner($this->tenant));
        $this->get("/store/{$this->tenant->slug}/admin/billing/renew-plan")->assertOk();

        $this->assertSame(1, PaymentIntent::withoutTenantScope()
            ->where('tenant_id', $this->tenant->id)
            ->where('plan_id', $this->paidPlan->id)
            ->whereIn('status', ['waiting_payment', 'waiting_review'])
            ->count());
    }

    /* ── 10. Tenant isolation and authorization ── */

    public function test_renew_requires_billing_renew_permission(): void
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

        $this->post("/store/{$this->tenant->slug}/admin/billing/renew")->assertStatus(403);
    }

    public function test_trial_renewal_is_tenant_scoped(): void
    {
        $other = Tenant::create([
            'slug' => 'trial-other-' . uniqid(),
            'name' => 'Other Shop',
            'status' => 'active',
        ]);
        $otherSub = $this->makeTrialSubscription($other, $this->paidPlan, $this->now->copy()->addDays(7));

        $this->actingAs($this->makeOwner($this->tenant));
        $this->from("/store/{$this->tenant->slug}/admin/billing")
            ->post("/store/{$this->tenant->slug}/admin/billing/renew")
            ->assertRedirect();

        $this->assertSame(1, $this->subscription->fresh()->trial_renewals_count);
        $this->assertSame(0, $otherSub->fresh()->trial_renewals_count);
    }

    /* ── Helpers ── */

    private function setPlatformSettings(array $values): void
    {
        $settings = PlatformSetting::first() ?? new PlatformSetting();
        $settings->fill(array_merge([
            'site_name' => 'Test',
            'trial_enabled' => true,
            'trial_days' => 14,
            'allow_trial_renewal' => true,
            'max_trial_renewals' => 3,
        ], $values));
        $settings->save();

        PlatformSetting::clearCache();
    }

    private function pendingIntent(): ?PaymentIntent
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

    private function makeTrialSubscription(Tenant $tenant, Plan $plan, Carbon $trialEndsAt): Subscription
    {
        $subscription = new Subscription([
            'plan_id' => $plan->id,
            'billing_interval' => 'monthly',
            'status' => 'trialing',
            'starts_at' => $this->now->copy(),
            'trial_ends_at' => $trialEndsAt,
            'expires_at' => $trialEndsAt,
            'trial_renewals_count' => 0,
        ]);
        $subscription->tenant_id = $tenant->id;
        $subscription->save();

        return $subscription;
    }

    private function createTrialSchema(): void
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
                $table->timestamp('extra_renewal_used_at')->nullable();
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
        if (!Schema::hasTable('platform_settings')) {
            Schema::create('platform_settings', function ($table) {
                $table->id(); $table->string('site_name')->default('My Application');
                $table->boolean('trial_enabled')->default(false);
                $table->integer('trial_days')->default(14);
                $table->boolean('allow_trial_renewal')->default(true);
                $table->integer('max_trial_renewals')->default(0);
                $table->timestamps();
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
