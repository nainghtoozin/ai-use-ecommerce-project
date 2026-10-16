<?php

namespace Tests\Feature;

use App\Models\PaymentIntent;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ImageService;
use App\Services\Payment\Platform\ManualPaymentService;
use App\Services\SubscriptionLifecycleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ManualSubscriptionRenewalTest extends TestCase
{
    use DatabaseTransactions;

    private Plan $plan;
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

        $settings = PlatformSetting::first() ?? new PlatformSetting();
        $settings->fill([
            'site_name' => 'Test', 'trial_enabled' => true, 'trial_days' => 14,
            'allow_trial_renewal' => true, 'max_trial_renewals' => 0,
            'billing_renewal_reminder_days' => 7,
        ]);
        $settings->save();
        PlatformSetting::clearCache();

        $this->plan = $this->makePlan('starter', 10, 100);

        $this->tenant = Tenant::create([
            'slug' => 'renewal-' . uniqid(), 'name' => 'Renewal Shop', 'status' => 'active',
        ]);

        $this->subscription = $this->makeSubscription($this->tenant, 'active', now()->addDays(20));
    }

    protected function tearDown(): void
    {
        Tenant::setCurrent(null);
        Carbon::setTestNow();

        parent::tearDown();
    }

    /* ── 1. Renewal visibility ── */

    public function test_renewal_visible_only_within_threshold(): void
    {
        $owner = $this->makeOwner($this->tenant);
        $this->actingAs($owner);

        $this->subscription->update(['expires_at' => now()->addDays(31)]);
        $this->billingPage()->assertInertia(fn ($p) => $p
            ->where('renewal.due', false)->where('renewal.can_renew', false)->where('renewal.active_paid', true));

        $this->subscription->update(['expires_at' => now()->addDays(5)]);
        $this->billingPage()->assertInertia(fn ($p) => $p
            ->where('renewal.due', true)->where('renewal.can_renew', true));
    }

    public function test_expired_subscription_is_renewable(): void
    {
        $this->subscription->update(['status' => 'expired', 'expires_at' => now()->subDays(3)]);

        $this->actingAs($this->makeOwner($this->tenant));
        $this->billingPage()->assertInertia(fn ($p) => $p
            ->where('renewal.is_lapsed', true)->where('renewal.can_renew', true));
    }

    public function test_trial_does_not_offer_paid_renewal(): void
    {
        $this->subscription->update([
            'status' => 'trialing', 'trial_ends_at' => now()->addDays(10), 'expires_at' => now()->addDays(10),
        ]);

        $this->actingAs($this->makeOwner($this->tenant));
        $this->billingPage()->assertInertia(fn ($p) => $p
            ->where('renewal.active_paid', false)->where('renewal.can_renew', false));
    }

    /* ── 2. Renewal submission lifecycle ── */

    public function test_opening_renew_form_does_not_create_payment(): void
    {
        $oldExpiry = $this->subscription->expires_at->toDateTimeString();

        $this->actingAs($this->makeOwner($this->tenant));
        $this->get("/store/{$this->tenant->slug}/admin/billing/renew-plan")
            ->assertOk()
            ->assertInertia(fn ($p) => $p->where('renewalMode', true)->where('intent', null));

        $this->assertSame(0, PaymentIntent::withoutTenantScope()->where('tenant_id', $this->tenant->id)->count());
        $this->assertSame($oldExpiry, $this->subscription->fresh()->expires_at->toDateTimeString());
        $this->assertSame('active', $this->subscription->fresh()->status);
    }

    public function test_abandoned_renew_form_leaves_state_unchanged(): void
    {
        $this->actingAs($this->makeOwner($this->tenant));
        $this->get("/store/{$this->tenant->slug}/admin/billing/renew-plan")->assertOk();

        // Nothing submitted.
        $this->assertSame(0, PaymentIntent::withoutTenantScope()->where('tenant_id', $this->tenant->id)->count());
        $this->assertSame('active', $this->subscription->fresh()->status);
    }

    public function test_successful_renew_submission_creates_pending_review(): void
    {
        $oldExpiry = $this->subscription->expires_at->toDateTimeString();
        $this->mockImageService();
        $this->actingAs($this->makeOwner($this->tenant));

        $this->post("/store/{$this->tenant->slug}/admin/billing/payment/submit", $this->renewPayload())
            ->assertRedirect();

        $intent = $this->renewalIntent();
        $this->assertNotNull($intent);
        $this->assertSame($this->plan->id, $intent->plan_id);
        $this->assertSame('monthly', $intent->billing_cycle);
        $this->assertSame('waiting_review', $intent->status);
        $this->assertSame('merchant_renewal', $intent->metadata['source']);
        $this->assertSame(PaymentIntent::TYPE_RENEWAL, $intent->metadata['payment_type']);
        $this->assertSame('Subscription renewal', $intent->metadata['payment_remark']);
        $this->assertEqualsWithDelta(10.0, (float) $intent->amount, 0.001);

        // Submission alone must not extend the subscription.
        $this->assertSame($oldExpiry, $this->subscription->fresh()->expires_at->toDateTimeString());
        $this->assertSame('active', $this->subscription->fresh()->status);
    }

    public function test_duplicate_pending_renewal_is_prevented(): void
    {
        $this->mockImageService();
        $this->actingAs($this->makeOwner($this->tenant));

        $url = "/store/{$this->tenant->slug}/admin/billing/payment/submit";
        $this->post($url, $this->renewPayload(['submission_key' => 'k1', 'transaction_reference' => 'T1']));
        $this->post($url, $this->renewPayload(['submission_key' => 'k2', 'transaction_reference' => 'T2']));

        $this->assertSame(1, PaymentIntent::withoutTenantScope()
            ->where('tenant_id', $this->tenant->id)
            ->whereIn('status', ['waiting_payment', 'waiting_review'])
            ->count());

        // Re-opening the form redirects to the existing pending payment.
        $this->get("/store/{$this->tenant->slug}/admin/billing/renew-plan")->assertRedirect();
    }

    public function test_renew_submission_requires_remark(): void
    {
        $this->mockImageService();
        $this->actingAs($this->makeOwner($this->tenant));

        $this->from("/store/{$this->tenant->slug}/admin/billing/renew-plan")
            ->post("/store/{$this->tenant->slug}/admin/billing/payment/submit", $this->renewPayload([
                'payment_remark' => '',
                'note' => '',
                'payment_purpose' => null,
            ]))
            ->assertSessionHasErrors('payment_remark');

        $this->assertSame(0, PaymentIntent::withoutTenantScope()->where('tenant_id', $this->tenant->id)->count());
    }

    /* ── 2b. Opening the form: preselection, stale/actionable pending ── */

    public function test_open_form_preselects_current_plan_cycle_and_amount(): void
    {
        $this->actingAs($this->makeOwner($this->tenant));

        $this->get("/store/{$this->tenant->slug}/admin/billing/renew-plan")
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('Admin/Billing/Payment')
                ->where('renewalMode', true)
                ->where('intent', null)
                ->where('selectedPlan.id', $this->plan->id)
                ->where('billingCycle', 'monthly')
                ->where('renewalAmount', 10)
            );
    }

    public function test_abandoned_expired_pending_does_not_block_renew_form(): void
    {
        // Regression: a stale, never-submitted payment must not block renewal forever.
        $this->subscription->update(['expires_at' => now()->addDays(5)]);
        $stale = $this->makeIntent('waiting_payment', $this->plan, ['expires_at' => now()->subDay()]);

        $this->actingAs($this->makeOwner($this->tenant));
        $this->get("/store/{$this->tenant->slug}/admin/billing/renew-plan")
            ->assertOk()
            ->assertInertia(fn ($p) => $p->where('renewalMode', true)->where('intent', null));

        // The stale intent is still there but no new one was created by opening the form.
        $this->assertSame(1, PaymentIntent::withoutTenantScope()->where('tenant_id', $this->tenant->id)->count());
    }

    public function test_pending_review_for_current_plan_redirects_to_payment(): void
    {
        $this->subscription->update(['expires_at' => now()->addDays(5)]);
        $this->makeIntent('waiting_review', $this->plan);

        $this->actingAs($this->makeOwner($this->tenant));
        $this->get("/store/{$this->tenant->slug}/admin/billing/renew-plan")
            ->assertRedirect()
            ->assertSessionHas('warning');
    }

    public function test_pending_review_for_other_plan_shows_warning_not_silent(): void
    {
        $otherPlan = $this->makePlan('pro', 30, 300);
        $this->makeIntent('waiting_review', $otherPlan);

        $this->actingAs($this->makeOwner($this->tenant));
        $this->get("/store/{$this->tenant->slug}/admin/billing/renew-plan")
            ->assertRedirect()
            ->assertSessionHas('warning');
    }

    public function test_renew_button_hidden_when_any_actionable_pending_exists(): void
    {
        $this->subscription->update(['expires_at' => now()->addDays(5)]);
        $otherPlan = $this->makePlan('pro2', 30, 300);
        $this->makeIntent('waiting_review', $otherPlan);

        $this->actingAs($this->makeOwner($this->tenant));
        $this->billingPage()->assertInertia(fn ($p) => $p
            ->where('renewal.blocked_by_pending', true)
            ->where('renewal.can_renew', false)
        );
    }

    /* ── 2c. Form UX data + reason/remark validation ── */

    public function test_renew_form_exposes_data_needed_for_instructions(): void
    {
        \App\Models\BillingPaymentMethod::create([
            'display_name' => 'KBZ Bank', 'type' => 'bank', 'account_name' => 'Shop',
            'account_number' => '123456', 'is_active' => true,
            'supports_manual_payment' => true, 'sort_order' => 0,
        ]);

        $this->actingAs($this->makeOwner($this->tenant));
        $this->get("/store/{$this->tenant->slug}/admin/billing/renew-plan")
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('Admin/Billing/Payment')
                ->where('renewalMode', true)
                ->where('renewalAmount', 10)
                ->where('billingCycle', 'monthly')
                ->has('paymentMethods', 1)
            );
    }

    public function test_other_purpose_requires_meaningful_remark(): void
    {
        $this->mockImageService();
        $this->actingAs($this->makeOwner($this->tenant));
        $url = "/store/{$this->tenant->slug}/admin/billing/payment/submit";

        // "abcd" is a valid remark but too short for the "Other" purpose.
        $this->from("/store/{$this->tenant->slug}/admin/billing/renew-plan")
            ->post($url, $this->renewPayload(['payment_purpose' => 'other', 'payment_remark' => 'abcd']))
            ->assertSessionHasErrors('payment_remark');

        $this->assertSame(0, PaymentIntent::withoutTenantScope()->where('tenant_id', $this->tenant->id)->count());

        // A meaningful custom explanation is accepted.
        $this->post($url, $this->renewPayload([
            'payment_purpose' => 'other',
            'payment_remark' => 'Bank fee correction',
            'submission_key' => 'ok-' . uniqid(),
            'transaction_reference' => 'TX-' . uniqid(),
        ]))->assertRedirect();

        $intent = $this->renewalIntent();
        $this->assertNotNull($intent);
        $this->assertSame('Bank fee correction', $intent->metadata['payment_remark']);
        $this->assertSame('other', $intent->metadata['payment_reason']);
    }

    /* ── 3. Approval + expiry calculation ── */

    public function test_approval_extends_from_existing_expiry_preserving_unused_time(): void
    {
        $oldExpiry = $this->subscription->expires_at->copy();

        $intent = $this->createRenewalIntent();
        $this->confirmAndApprove($intent);

        $subscription = $this->subscription->fresh();
        $this->assertSame('active', $subscription->status);
        $this->assertEquals(
            $oldExpiry->copy()->addMonth()->endOfDay()->toDateTimeString(),
            $subscription->expires_at->toDateTimeString()
        );
    }

    public function test_approval_when_expired_starts_from_now(): void
    {
        $this->subscription->update(['status' => 'expired', 'expires_at' => now()->subDays(5)]);

        $intent = $this->createRenewalIntent();
        $this->confirmAndApprove($intent);

        $subscription = $this->subscription->fresh();
        $this->assertSame('active', $subscription->status);
        $this->assertEquals(
            now()->addMonth()->endOfDay()->toDateTimeString(),
            $subscription->expires_at->toDateTimeString()
        );
    }

    public function test_repeated_approval_does_not_extend_twice(): void
    {
        $intent = $this->createRenewalIntent();
        $this->confirmAndApprove($intent);
        $afterFirst = $this->subscription->fresh()->expires_at->toDateTimeString();

        // A second service-level approval must be rejected/guarded.
        $manual = app(ManualPaymentService::class);
        try {
            $manual->approvePayment($intent->fresh());
        } catch (\Throwable $e) {
            // Expected: already executed.
        }

        // Even a forced lifecycle re-application must be a no-op.
        app(SubscriptionLifecycleService::class)->handleCompletedPayment($intent->fresh());

        $this->assertSame($afterFirst, $this->subscription->fresh()->expires_at->toDateTimeString());
    }

    /* ── 4. Rejection keeps the subscription unchanged ── */

    public function test_rejection_keeps_subscription_unchanged(): void
    {
        $oldExpiry = $this->subscription->expires_at->toDateTimeString();
        $intent = $this->createRenewalIntent();
        $manual = app(ManualPaymentService::class);
        $manual->confirmPayment($intent);
        $manual->rejectPayment($intent->fresh(), 'Evidence unreadable.');

        $subscription = $this->subscription->fresh();
        $this->assertSame('rejected', $intent->fresh()->status);
        $this->assertSame($oldExpiry, $subscription->expires_at->toDateTimeString());
        $this->assertSame('active', $subscription->status);
    }

    /* ── 5. Pending renewal hides the action ── */

    public function test_pending_renewal_hides_button_and_shows_status(): void
    {
        $this->subscription->update(['expires_at' => now()->addDays(3)]);
        $intent = $this->createRenewalIntent(); // waiting_payment

        $this->actingAs($this->makeOwner($this->tenant));
        $this->billingPage()->assertInertia(fn ($p) => $p
            ->where('renewal.due', true)
            ->where('renewal.blocked_by_pending', true)
            ->where('renewal.can_renew', false)
            ->where('renewal.pending.reference_number', $intent->reference_number)
            ->where('renewal.pending.status', 'waiting_payment')
        );
    }

    /* ── 6. Tenant isolation ── */
    public function test_renewal_is_tenant_scoped(): void
    {
        $other = Tenant::create(['slug' => 'renewal-other-' . uniqid(), 'name' => 'Other', 'status' => 'active']);
        $this->makeSubscription($other, 'active', now()->addDays(20));

        // Create both owners before any request (avoids tenant-scoped role lookups mid-suite).
        $ownerA = $this->makeOwner($this->tenant);
        $ownerB = $this->makeOwner($other);
        $this->mockImageService();

        $this->actingAs($ownerA);
        $this->post("/store/{$this->tenant->slug}/admin/billing/payment/submit", $this->renewPayload())->assertRedirect();

        $this->actingAs($ownerB);
        $this->post("/store/{$other->slug}/admin/billing/payment/submit", $this->renewPayload())->assertRedirect();

        $this->assertSame(1, PaymentIntent::withoutTenantScope()->where('tenant_id', $this->tenant->id)->count());
        $this->assertSame(1, PaymentIntent::withoutTenantScope()->where('tenant_id', $other->id)->count());
    }

    /* ── Helpers ── */

    private function billingPage()
    {
        return $this->get("/store/{$this->tenant->slug}/admin/billing")->assertOk();
    }

    private function renewalIntent(): ?PaymentIntent
    {
        return PaymentIntent::withoutTenantScope()
            ->where('tenant_id', $this->tenant->id)
            ->whereIn('status', ['waiting_payment', 'waiting_review'])
            ->latest()
            ->first();
    }

    private function createRenewalIntent(): PaymentIntent
    {
        return app(ManualPaymentService::class)->initiate(
            tenant: $this->tenant,
            plan: $this->plan,
            billingCycle: 'monthly',
            amount: 10.0,
            currency: \App\Data\Currency::fromCode('MMK'),
            metadata: ['source' => 'merchant_renewal', 'payment_type' => PaymentIntent::TYPE_RENEWAL],
        );
    }

    private function renewPayload(array $overrides = []): array
    {
        return array_merge([
            'plan_slug' => $this->plan->slug,
            'billing_cycle' => 'monthly',
            'renewal_mode' => '1',
            'submission_key' => 'key-' . uniqid(),
            'sender_name' => 'John Doe',
            'sender_account' => '09123456789',
            'transaction_reference' => 'TRX-' . uniqid(),
            'transferred_amount' => 10,
            'transfer_date' => now()->format('Y-m-d'),
            'transfer_time' => now()->format('H:i'),
            'payment_purpose' => 'subscription_renewal',
            'payment_remark' => 'Subscription renewal',
            'evidence' => UploadedFile::fake()->image('receipt.jpg', 200, 200),
        ], $overrides);
    }

    private function mockImageService(): void
    {
        $this->mock(ImageService::class, function ($mock) {
            $mock->shouldReceive('upload')->andReturn('payment-evidence/test.jpg');
            $mock->shouldReceive('url')->andReturn('https://cdn.example.com/payment-evidence/test.jpg');
        });
    }

    private function makeIntent(string $status, Plan $plan, array $overrides = []): PaymentIntent
    {
        $intent = new PaymentIntent(array_merge([
            'plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'amount' => 10.0,
            'currency' => 'MMK',
            'gateway' => 'manual',
            'status' => $status,
            'reference_number' => 'PAY-' . strtoupper(uniqid()),
            'idempotency_key' => uniqid('idem-', true),
            'metadata' => ['source' => 'merchant_renewal', 'payment_type' => PaymentIntent::TYPE_RENEWAL],
        ], $overrides));
        $intent->tenant_id = $this->tenant->id;
        $intent->save();

        return $intent;
    }

    private function confirmAndApprove(PaymentIntent $intent): void
    {
        $manual = app(ManualPaymentService::class);
        $manual->confirmPayment($intent);
        $manual->approvePayment($intent->fresh());
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

    private function makeSubscription(Tenant $tenant, string $status, $expiresAt): Subscription
    {
        $subscription = new Subscription([
            'plan_id' => $this->plan->id,
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
            'plans' => function ($t) { $t->id(); $t->string('name'); $t->string('slug')->unique(); $t->text('description')->nullable(); $t->decimal('monthly_price',10,2)->nullable(); $t->decimal('yearly_price',10,2)->nullable(); $t->string('status',20)->default('active'); $t->timestamps(); },
            'subscriptions' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id'); $t->unsignedBigInteger('plan_id')->nullable(); $t->string('billing_interval')->nullable(); $t->string('status'); $t->timestamp('starts_at')->nullable(); $t->timestamp('expires_at')->nullable(); $t->timestamp('trial_ends_at')->nullable(); $t->integer('trial_renewals_count')->default(0); $t->unsignedBigInteger('pending_plan_id')->nullable(); $t->timestamp('pending_plan_effective_at')->nullable(); $t->timestamp('suspended_at')->nullable(); $t->timestamp('cancelled_at')->nullable(); $t->timestamp('extra_renewal_used_at')->nullable(); $t->text('notes')->nullable(); $t->timestamps(); $t->index('tenant_id'); },
            'payment_intents' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id'); $t->unsignedBigInteger('plan_id'); $t->unsignedBigInteger('subscription_id')->nullable(); $t->string('billing_cycle'); $t->decimal('amount',10,2); $t->string('currency',3); $t->string('gateway'); $t->string('status'); $t->timestamp('expires_at')->nullable(); $t->json('metadata')->nullable(); $t->timestamp('completed_at')->nullable(); $t->timestamp('cancelled_at')->nullable(); $t->timestamp('rejected_at')->nullable(); $t->string('reference_number')->nullable()->unique(); $t->string('idempotency_key',64)->nullable(); $t->timestamps(); $t->index('tenant_id'); $t->index('status'); },
            'payment_evidences' => function ($t) { $t->id(); $t->unsignedBigInteger('payment_intent_id'); $t->string('type'); $t->string('file_path')->nullable(); $t->text('note')->nullable(); $t->json('metadata')->nullable(); $t->string('sender_name')->nullable(); $t->string('sender_account')->nullable(); $t->string('transaction_reference')->nullable(); $t->decimal('transferred_amount',10,2)->nullable(); $t->date('transfer_date')->nullable(); $t->timestamps(); },
            'payment_timeline_events' => function ($t) { $t->id(); $t->unsignedBigInteger('payment_intent_id'); $t->string('type'); $t->text('description')->nullable(); $t->json('metadata')->nullable(); $t->timestamp('occurred_at'); $t->timestamps(); },
            'payment_transactions' => function ($t) { $t->id(); $t->unsignedBigInteger('payment_intent_id')->unique(); $t->string('transaction_number')->unique(); $t->unsignedBigInteger('tenant_id'); $t->unsignedBigInteger('plan_id'); $t->unsignedBigInteger('subscription_id')->nullable(); $t->decimal('amount',10,2); $t->string('currency',3); $t->string('gateway'); $t->string('status')->default('completed'); $t->string('gateway_reference')->nullable(); $t->json('metadata')->nullable(); $t->timestamps(); $t->index('tenant_id'); },
            'payment_reviews' => function ($t) { $t->id(); $t->unsignedBigInteger('payment_intent_id'); $t->unsignedBigInteger('reviewer_id')->nullable(); $t->string('reviewer_name')->nullable(); $t->string('action')->nullable(); $t->text('reason')->nullable(); $t->json('metadata')->nullable(); $t->timestamps(); },
            'subscription_audit_logs' => function ($t) { $t->id(); $t->unsignedBigInteger('subscription_id'); $t->unsignedBigInteger('tenant_id')->nullable(); $t->string('event'); $t->string('actor_type')->nullable(); $t->unsignedBigInteger('actor_id')->nullable(); $t->unsignedBigInteger('old_plan_id')->nullable(); $t->unsignedBigInteger('new_plan_id')->nullable(); $t->string('old_status')->nullable(); $t->string('new_status')->nullable(); $t->text('reason')->nullable(); $t->json('metadata')->nullable(); $t->timestamps(); },
            'invoices' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id'); $t->string('invoice_number')->unique(); $t->unsignedBigInteger('subscription_id')->nullable(); $t->unsignedBigInteger('plan_id')->nullable(); $t->string('billing_interval')->nullable(); $t->date('billing_period_start')->nullable(); $t->date('billing_period_end')->nullable(); $t->decimal('amount',10,2)->default(0); $t->decimal('subtotal',10,2)->default(0); $t->decimal('tax',10,2)->default(0); $t->decimal('total',10,2)->default(0); $t->string('currency',3)->default('MMK'); $t->string('status')->default('unpaid'); $t->unsignedBigInteger('payment_intent_id')->nullable(); $t->text('notes')->nullable(); $t->timestamp('issued_at')->nullable(); $t->timestamp('paid_at')->nullable(); $t->json('line_items')->nullable(); $t->timestamp('deleted_at')->nullable(); $t->timestamps(); },
            'receipts' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id'); $t->unsignedBigInteger('invoice_id')->nullable(); $t->unsignedBigInteger('payment_intent_id')->nullable(); $t->string('receipt_number')->unique(); $t->decimal('amount',10,2)->default(0); $t->string('currency',3)->default('MMK'); $t->timestamp('paid_at')->nullable(); $t->json('details')->nullable(); $t->timestamps(); },
            'billing_payment_methods' => function ($t) { $t->id(); $t->string('display_name'); $t->string('type')->nullable(); $t->string('account_name')->nullable(); $t->string('account_number')->nullable(); $t->string('bank_name')->nullable(); $t->string('branch')->nullable(); $t->text('instructions')->nullable(); $t->string('currency',3)->nullable(); $t->string('qr_image_url')->nullable(); $t->boolean('is_active')->default(true); $t->boolean('supports_manual_payment')->default(true); $t->integer('sort_order')->default(0); $t->timestamps(); },
            'website_infos' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->string('site_name')->nullable(); $t->string('currency_code')->default('MMK'); $t->string('currency_symbol')->default('K'); $t->timestamps(); },
            'platform_settings' => function ($t) { $t->id(); $t->string('site_name')->default('My Application'); $t->boolean('trial_enabled')->default(false); $t->integer('trial_days')->default(14); $t->boolean('allow_trial_renewal')->default(true); $t->integer('max_trial_renewals')->default(0); $t->integer('billing_renewal_reminder_days')->default(7); $t->timestamps(); },
            'reference_numbers' => function ($t) { $t->id(); $t->string('prefix',10); $t->date('date'); $t->unsignedInteger('last_sequence')->default(0); $t->timestamps(); $t->unique(['prefix','date']); },
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
    }
}
