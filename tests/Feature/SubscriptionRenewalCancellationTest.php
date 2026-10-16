<?php

namespace Tests\Feature;

use App\Data\Currency;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionCancellationRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Payment\Platform\ManualPaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SubscriptionRenewalCancellationTest extends TestCase
{
    use DatabaseTransactions;

    private Plan $plan;
    private Tenant $tenant;
    private Tenant $otherTenant;
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

        $this->plan = Plan::create([
            'name' => 'Growth',
            'slug' => 'growth-' . uniqid(),
            'description' => 'Test plan',
            'monthly_price' => 100,
            'yearly_price' => 1000,
            'status' => 'active',
        ]);

        $this->tenant = Tenant::create([
            'slug' => 'renew-' . uniqid(),
            'name' => 'Renew Shop',
            'status' => 'active',
        ]);

        $this->subscription = new Subscription([
            'plan_id' => $this->plan->id,
            'billing_interval' => 'monthly',
            'status' => 'active',
            'starts_at' => now()->subMonth(),
            'expires_at' => now()->addDays(10),
            'trial_renewals_count' => 0,
        ]);
        $this->subscription->tenant_id = $this->tenant->id;
        $this->subscription->save();

        $this->otherTenant = Tenant::create([
            'slug' => 'renew-other-' . uniqid(),
            'name' => 'Other Shop',
            'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        Tenant::setCurrent(null);

        parent::tearDown();
    }

    // ── Renewal (merchant-initiated manual payment) ──

    public function test_renew_plan_page_opens_form_without_creating_intent(): void
    {
        $this->actingAs($this->makeOwner($this->tenant));

        $this->get("/store/{$this->tenant->slug}/admin/billing/renew-plan")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Billing/Payment')
                ->where('renewalMode', true)
                ->where('intent', null)
                ->where('selectedPlan.id', $this->plan->id)
            );

        // Opening the form must not create a payment record.
        $this->assertSame(0, \App\Models\PaymentIntent::where('tenant_id', $this->tenant->id)->count());
    }

    public function test_renew_plan_does_not_change_expiry_before_payment(): void
    {
        $originalExpiry = $this->subscription->expires_at->toDateTimeString();
        $this->actingAs($this->makeOwner($this->tenant));

        $response = $this->get("/store/{$this->tenant->slug}/admin/billing/renew-plan");
        $response->assertOk();

        $this->assertSame($originalExpiry, $this->subscription->fresh()->expires_at->toDateTimeString());
        $this->assertSame('active', $this->subscription->fresh()->status);
    }

    public function test_renew_plan_with_existing_pending_redirects_to_it(): void
    {
        $intent = new \App\Models\PaymentIntent([
            'plan_id' => $this->plan->id,
            'billing_cycle' => 'monthly',
            'amount' => 100,
            'currency' => 'MMK',
            'gateway' => 'manual',
            'status' => 'waiting_review',
            'reference_number' => 'PAY-' . strtoupper(uniqid()),
            'idempotency_key' => uniqid('idem-', true),
            'metadata' => ['source' => 'merchant_renewal'],
        ]);
        $intent->tenant_id = $this->tenant->id;
        $intent->save();

        $this->actingAs($this->makeOwner($this->tenant));

        $this->get("/store/{$this->tenant->slug}/admin/billing/renew-plan")->assertRedirect();

        $this->assertSame(1, \App\Models\PaymentIntent::where('tenant_id', $this->tenant->id)->count());
    }

    public function test_renew_plan_page_requires_authentication(): void
    {
        $this->get("/store/{$this->tenant->slug}/admin/billing/renew-plan")->assertRedirect();
    }

    public function test_renew_plan_blocked_for_free_plan(): void
    {
        $freePlan = Plan::create([
            'name' => 'Free', 'slug' => 'free-' . uniqid(), 'description' => 'Free',
            'monthly_price' => 0, 'yearly_price' => 0, 'status' => 'active',
        ]);
        $this->subscription->update(['plan_id' => $freePlan->id]);

        $this->actingAs($this->makeOwner($this->tenant));
        $this->get("/store/{$this->tenant->slug}/admin/billing/renew-plan")->assertRedirect();

        $this->assertSame(0, \App\Models\PaymentIntent::where('tenant_id', $this->tenant->id)->count());
    }

    public function test_completed_renewal_extends_from_future_expiry(): void
    {
        $oldExpiry = $this->subscription->expires_at->copy();

        $manual = app(ManualPaymentService::class);
        $intent = $manual->initiate(
            tenant: $this->tenant,
            plan: $this->plan,
            billingCycle: 'monthly',
            amount: 100.0,
            currency: Currency::fromCode('MMK'),
        );
        $manual->confirmPayment($intent);
        $manual->approvePayment($intent->fresh());

        $subscription = $this->subscription->fresh();
        $this->assertSame('active', $subscription->status);
        $this->assertEquals(
            $oldExpiry->copy()->addMonth()->endOfDay()->toDateTimeString(),
            $subscription->expires_at->toDateTimeString()
        );
    }

    public function test_completed_renewal_preserves_scheduled_downgrade(): void
    {
        $smaller = Plan::create([
            'name' => 'Smaller', 'slug' => 'smaller-' . uniqid(), 'description' => 'Small',
            'monthly_price' => 50, 'yearly_price' => 500, 'status' => 'active',
        ]);
        $this->subscription->update([
            'pending_plan_id' => $smaller->id,
            'pending_plan_effective_at' => now()->addDays(10),
        ]);

        $manual = app(ManualPaymentService::class);
        $intent = $manual->initiate(
            tenant: $this->tenant,
            plan: $this->plan,
            billingCycle: 'monthly',
            amount: 100.0,
            currency: Currency::fromCode('MMK'),
        );
        $manual->confirmPayment($intent);
        $manual->approvePayment($intent->fresh());

        $subscription = $this->subscription->fresh();
        $this->assertSame($smaller->id, $subscription->pending_plan_id);
        $this->assertSame($this->plan->id, $subscription->plan_id);
    }

    // ── Cancellation request (merchant → superadmin) ──

    public function test_cancel_request_requires_valid_reason(): void
    {
        $this->actingAs($this->makeOwner($this->tenant));

        $this->from("/store/{$this->tenant->slug}/admin/billing")
            ->post("/store/{$this->tenant->slug}/admin/billing/cancel-request", ['reason' => 'nonsense'])
            ->assertSessionHasErrors('reason');

        $this->assertSame(0, SubscriptionCancellationRequest::withoutTenantScope()->count());
    }

    public function test_cancel_request_other_requires_comment(): void
    {
        $this->actingAs($this->makeOwner($this->tenant));

        $this->from("/store/{$this->tenant->slug}/admin/billing")
            ->post("/store/{$this->tenant->slug}/admin/billing/cancel-request", ['reason' => 'other'])
            ->assertSessionHasErrors('comment');

        $this->assertSame(0, SubscriptionCancellationRequest::withoutTenantScope()->count());
    }

    public function test_cancel_request_creates_pending_request_without_changing_status(): void
    {
        $owner = $this->makeOwner($this->tenant);
        $this->actingAs($owner);

        $this->from("/store/{$this->tenant->slug}/admin/billing")
            ->post("/store/{$this->tenant->slug}/admin/billing/cancel-request", [
                'reason' => 'too_expensive',
                'comment' => 'Too costly for now.',
            ])
            ->assertRedirect();

        $request = SubscriptionCancellationRequest::withoutTenantScope()->first();
        $this->assertNotNull($request);
        $this->assertSame(SubscriptionCancellationRequest::STATUS_PENDING, $request->status);
        $this->assertSame($this->tenant->id, $request->tenant_id);
        $this->assertSame($this->subscription->id, $request->subscription_id);
        $this->assertSame($owner->id, $request->requested_by);

        $this->assertSame('active', $this->subscription->fresh()->status);
        $this->assertNull($this->subscription->fresh()->cancelled_at);
    }

    public function test_duplicate_cancel_request_is_blocked(): void
    {
        $this->actingAs($this->makeOwner($this->tenant));

        $url = "/store/{$this->tenant->slug}/admin/billing/cancel-request";
        $this->from("/store/{$this->tenant->slug}/admin/billing")->post($url, ['reason' => 'too_expensive']);
        $this->from("/store/{$this->tenant->slug}/admin/billing")->post($url, ['reason' => 'no_longer_needed']);

        $this->assertSame(1, SubscriptionCancellationRequest::withoutTenantScope()->count());
    }

    public function test_withdraw_cancel_request(): void
    {
        $this->actingAs($this->makeOwner($this->tenant));

        $this->from("/store/{$this->tenant->slug}/admin/billing")
            ->post("/store/{$this->tenant->slug}/admin/billing/cancel-request", ['reason' => 'too_expensive']);

        $this->from("/store/{$this->tenant->slug}/admin/billing")
            ->post("/store/{$this->tenant->slug}/admin/billing/cancel-request/withdraw")
            ->assertRedirect();

        $this->assertSame(
            SubscriptionCancellationRequest::STATUS_WITHDRAWN,
            SubscriptionCancellationRequest::withoutTenantScope()->first()->status
        );
    }

    public function test_cancel_request_requires_billing_manage_permission(): void
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

        $this->post("/store/{$this->tenant->slug}/admin/billing/cancel-request", ['reason' => 'too_expensive'])
            ->assertStatus(403);
    }

    public function test_cancellation_requests_are_tenant_scoped(): void
    {
        $this->actingAs($this->makeOwner($this->tenant));
        $this->from("/store/{$this->tenant->slug}/admin/billing")
            ->post("/store/{$this->tenant->slug}/admin/billing/cancel-request", ['reason' => 'too_expensive']);

        Tenant::setCurrent($this->otherTenant);
        $this->assertSame(0, SubscriptionCancellationRequest::count());
        $this->assertSame(1, SubscriptionCancellationRequest::withoutTenantScope()->count());
        Tenant::setCurrent(null);
    }

    // ── SuperAdmin review ──

    public function test_superadmin_approval_sets_cancelled_at_but_keeps_status(): void
    {
        $request = $this->makeCancellationRequest();
        $this->actingAs($this->makeSuperAdmin());

        $this->post("/superadmin/cancellation-requests/{$request->id}/review", ['action' => 'approve'])
            ->assertRedirect();

        $this->assertSame(SubscriptionCancellationRequest::STATUS_APPROVED, $request->fresh()->status);
        $this->assertNotNull($request->fresh()->reviewed_at);
        $this->assertNotNull($this->subscription->fresh()->cancelled_at);
        $this->assertSame('active', $this->subscription->fresh()->status);
    }

    public function test_superadmin_reject_does_not_cancel_subscription(): void
    {
        $request = $this->makeCancellationRequest();
        $this->actingAs($this->makeSuperAdmin());

        $this->post("/superadmin/cancellation-requests/{$request->id}/review", [
            'action' => 'reject',
            'review_note' => 'Please contact support.',
        ])->assertRedirect();

        $this->assertSame(SubscriptionCancellationRequest::STATUS_REJECTED, $request->fresh()->status);
        $this->assertNull($this->subscription->fresh()->cancelled_at);
    }

    // ── Helpers ──

    private function makeCancellationRequest(): SubscriptionCancellationRequest
    {
        $request = new SubscriptionCancellationRequest([
            'subscription_id' => $this->subscription->id,
            'reason' => 'too_expensive',
            'comment' => 'Test',
            'status' => SubscriptionCancellationRequest::STATUS_PENDING,
        ]);
        $request->tenant_id = $this->tenant->id;
        $request->save();

        return $request;
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

    private function makeSuperAdmin(): User
    {
        $role = \App\Models\Role::withoutTenantScope()->firstOrCreate([
            'name' => 'superadmin', 'guard_name' => 'web', 'tenant_id' => null,
        ]);
        $role->syncPermissions(['billing.view', 'billing.manage', 'billing.renew']);

        $user = User::create([
            'tenant_id' => null,
            'name' => 'Super Admin',
            'email' => 'super-' . uniqid() . '@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        \Illuminate\Support\Facades\DB::table('model_has_roles')->insert([
            'role_id' => $role->id,
            'model_type' => User::class,
            'model_id' => $user->id,
        ]);

        return $user->fresh();
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
                $table->string('status', 20)->default('active');
                $table->boolean('analytics_enabled')->default(false);
                $table->boolean('custom_domain_enabled')->default(false);
                $table->string('price')->nullable(); $table->string('currency')->nullable();
                $table->string('interval')->nullable(); $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true); $table->integer('sort_order')->default(0);
                $table->timestamps();
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
        if (!Schema::hasTable('subscription_cancellation_requests')) {
            Schema::create('subscription_cancellation_requests', function ($table) {
                $table->id(); $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('subscription_id'); $table->string('reason', 50);
                $table->text('comment')->nullable(); $table->string('status', 20)->default('pending');
                $table->unsignedBigInteger('requested_by')->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->text('review_note')->nullable(); $table->timestamp('reviewed_at')->nullable();
                $table->timestamps(); $table->index('tenant_id'); $table->index('status');
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
        if (!Schema::hasTable('subscription_audit_logs')) {
            Schema::create('subscription_audit_logs', function ($table) {
                $table->id(); $table->unsignedBigInteger('subscription_id'); $table->unsignedBigInteger('tenant_id');
                $table->string('event'); $table->string('actor_type')->nullable(); $table->unsignedBigInteger('actor_id')->nullable();
                $table->unsignedBigInteger('old_plan_id')->nullable(); $table->unsignedBigInteger('new_plan_id')->nullable();
                $table->string('old_status')->nullable(); $table->string('new_status')->nullable();
                $table->text('reason')->nullable(); $table->json('metadata')->nullable(); $table->timestamps();
            });
        }
        if (!Schema::hasTable('invoice_settings')) {
            Schema::create('invoice_settings', function ($table) {
                $table->id(); $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('prefix')->nullable(); $table->boolean('tax_enabled')->default(false);
                $table->decimal('tax_rate', 5, 2)->default(0); $table->timestamps();
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
                $table->integer('sort_order')->default(0); $table->timestamps(); $table->softDeletes();
            });
        }
        if (!Schema::hasTable('website_infos')) {
            Schema::create('website_infos', function ($table) {
                $table->id(); $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('site_name')->nullable(); $table->string('currency_code')->default('MMK');
                $table->string('currency_symbol')->default('K'); $table->timestamps();
            });
        }
        if (!Schema::hasTable('activity_logs')) {
            Schema::create('activity_logs', function ($table) {
                $table->id(); $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('log_name')->nullable(); $table->string('category', 50)->nullable();
                $table->string('severity', 20)->default('info'); $table->text('description')->nullable();
                $table->string('subject_type')->nullable(); $table->unsignedBigInteger('subject_id')->nullable();
                $table->string('causer_type')->nullable(); $table->unsignedBigInteger('causer_id')->nullable();
                $table->unsignedBigInteger('impersonator_id')->nullable();
                $table->unsignedBigInteger('impersonated_user_id')->nullable();
                $table->json('properties')->nullable(); $table->string('event')->nullable();
                $table->string('batch_uuid')->nullable(); $table->timestamps();
            });
        }
        if (!Schema::hasTable('invoices')) {
            Schema::create('invoices', function ($table) {
                $table->id(); $table->unsignedBigInteger('tenant_id');
                $table->string('invoice_number')->unique();
                $table->unsignedBigInteger('subscription_id')->nullable();
                $table->unsignedBigInteger('plan_id')->nullable();
                $table->unsignedBigInteger('payment_intent_id')->nullable();
                $table->string('billing_interval')->nullable();
                $table->date('billing_period_start')->nullable();
                $table->date('billing_period_end')->nullable();
                $table->decimal('amount', 10, 2)->default(0);
                $table->decimal('subtotal', 10, 2)->default(0);
                $table->decimal('tax', 10, 2)->default(0);
                $table->decimal('total', 10, 2)->default(0);
                $table->string('currency', 3)->default('MMK');
                $table->string('status')->default('unpaid');
                $table->text('notes')->nullable();
                $table->timestamp('issued_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->json('line_items')->nullable();
                $table->timestamps(); $table->softDeletes();
            });
        }
        if (!Schema::hasTable('accounts')) {
            Schema::create('accounts', function ($table) {
                $table->id(); $table->string('name')->nullable();
                $table->string('email')->unique(); $table->string('password');
                $table->timestamp('email_verified_at')->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->string('profile_image', 255)->nullable();
                $table->string('status', 50)->default('active');
                $table->json('notification_preferences')->nullable();
                $table->timestamp('last_login_at')->nullable();
                $table->string('last_login_ip', 45)->nullable();
                $table->timestamps(); $table->softDeletes();
            });
        }
        if (!Schema::hasTable('tenant_memberships')) {
            Schema::create('tenant_memberships', function ($table) {
                $table->id(); $table->unsignedBigInteger('account_id')->nullable();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->unsignedBigInteger('role_id')->nullable();
                $table->boolean('is_owner')->default(false);
                $table->string('status')->default('active');
                $table->timestamps(); $table->softDeletes();
            });
        }
        if (!Schema::hasTable('platform_settings')) {
            Schema::create('platform_settings', function ($table) {
                $table->id(); $table->string('site_name')->default('My Application');
                $table->boolean('trial_enabled')->default(false); $table->integer('trial_days')->default(14);
                $table->boolean('allow_trial_renewal')->default(true); $table->integer('max_trial_renewals')->default(0);
                $table->integer('billing_renewal_reminder_days')->default(7);
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('receipts')) {
            Schema::create('receipts', function ($table) {
                $table->id(); $table->unsignedBigInteger('tenant_id');
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
        if (!Schema::hasTable('reference_numbers')) {
            Schema::create('reference_numbers', function ($table) {
                $table->id(); $table->string('prefix', 10); $table->date('date');
                $table->unsignedInteger('last_sequence')->default(0); $table->timestamps();
                $table->unique(['prefix', 'date']);
            });
        }
    }
}
