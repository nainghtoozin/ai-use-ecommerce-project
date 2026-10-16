<?php

namespace Tests\Feature;

use App\Data\Currency;
use App\Mail\Billing\PaymentReviewMail;
use App\Models\PaymentEvidence;
use App\Models\PaymentIntent;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BillingEmailService;
use App\Services\ImageService;
use App\Services\Payment\Platform\ManualPaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ManualPaymentFormAuditTest extends TestCase
{
    use DatabaseTransactions;

    private Plan $plan;
    private Plan $higherPlan;
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
        $adminRole = \App\Models\Role::withoutTenantScope()->firstOrCreate([
            'name' => 'admin', 'guard_name' => 'web', 'tenant_id' => null,
        ]);
        $adminRole->syncPermissions(['billing.view', 'billing.manage', 'billing.renew']);

        $this->plan = $this->makePlan('starter', 10, 100);
        $this->higherPlan = $this->makePlan('pro', 30, 300);

        $this->tenant = Tenant::create([
            'slug' => 'pay-' . uniqid(),
            'name' => 'Payment Shop',
            'status' => 'active',
        ]);

        $this->subscription = $this->makeSubscription($this->tenant, $this->plan, 'active', now()->addDays(20));
    }

    protected function tearDown(): void
    {
        Tenant::setCurrent(null);

        parent::tearDown();
    }

    /* ── 1. Current date/time defaults ── */

    public function test_payment_page_provides_transfer_date_and_time_defaults(): void
    {
        $this->actingAs($this->makeOwner($this->tenant));

        $this->get("/store/{$this->tenant->slug}/admin/billing/payment")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Billing/Payment')
                ->where('timezone', config('app.timezone'))
                ->where('defaultTransferDate', now()->format('Y-m-d'))
                ->where('defaultTransferTime', now()->format('H:i'))
            );
    }

    /* ── 2. User-edited transfer date/time preserved ── */

    public function test_submitted_transfer_date_and_time_are_persisted_as_edited(): void
    {
        $editedDate = now()->subDays(3)->format('Y-m-d');
        $intent = $this->makeIntent($this->tenant, $this->plan, 'waiting_payment');
        $this->mockImageService();

        $this->actingAs($this->makeOwner($this->tenant));
        $this->post("/store/{$this->tenant->slug}/admin/billing/payment/submit", $this->payload($intent, [
            'transfer_date' => $editedDate,
            'transfer_time' => '14:30',
        ]))->assertRedirect();

        $evidence = PaymentEvidence::where('payment_intent_id', $intent->id)->latest()->first();
        $this->assertNotNull($evidence);
        $this->assertSame($editedDate, $evidence->transfer_date->format('Y-m-d'));
        $this->assertSame('14:30', $evidence->metadata['transfer_time']);
    }

    /* ── 3. Receipt upload validation limits ── */

    public function test_receipt_upload_rejects_invalid_type_and_oversize(): void
    {
        $this->actingAs($this->makeOwner($this->tenant));
        $this->mockImageService();

        $intent1 = $this->makeIntent($this->tenant, $this->plan, 'waiting_payment');
        $this->from("/store/{$this->tenant->slug}/admin/billing/payment?intent={$intent1->reference_number}")
            ->post("/store/{$this->tenant->slug}/admin/billing/payment/submit", $this->payload($intent1, [
                'evidence' => UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf'),
            ]))
            ->assertSessionHasErrors('evidence');

        $intent2 = $this->makeIntent($this->tenant, $this->plan, 'waiting_payment');
        $this->from("/store/{$this->tenant->slug}/admin/billing/payment?intent={$intent2->reference_number}")
            ->post("/store/{$this->tenant->slug}/admin/billing/payment/submit", $this->payload($intent2, [
                'evidence' => UploadedFile::fake()->image('big.jpg')->size(6000),
            ]))
            ->assertSessionHasErrors('evidence');
    }

    public function test_receipt_upload_accepts_valid_image(): void
    {
        $intent = $this->makeIntent($this->tenant, $this->plan, 'waiting_payment');
        $this->mockImageService();

        $this->actingAs($this->makeOwner($this->tenant));
        $this->from("/store/{$this->tenant->slug}/admin/billing/payment?intent={$intent->reference_number}")
            ->post("/store/{$this->tenant->slug}/admin/billing/payment/submit", $this->payload($intent, [
                'evidence' => UploadedFile::fake()->image('receipt.jpg', 300, 300),
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame('waiting_review', $intent->fresh()->status);
    }

    /* ── 4. Reason selection and custom Other reason ── */

    public function test_other_reason_requires_custom_text(): void
    {
        $intent = $this->makeIntent($this->tenant, $this->plan, 'waiting_payment');
        $this->mockImageService();

        $this->actingAs($this->makeOwner($this->tenant));
        $this->from("/store/{$this->tenant->slug}/admin/billing/payment?intent={$intent->reference_number}")
            ->post("/store/{$this->tenant->slug}/admin/billing/payment/submit", $this->payload($intent, [
                'payment_reason' => 'other',
                'payment_reason_text' => '',
                'payment_remark' => '',
                'note' => '',
            ]))
            ->assertSessionHasErrors('payment_remark');
    }

    public function test_selected_reason_is_stored_structurally(): void
    {
        $intent = $this->makeIntent($this->tenant, $this->plan, 'waiting_payment');
        $this->mockImageService();

        $this->actingAs($this->makeOwner($this->tenant));
        $this->from("/store/{$this->tenant->slug}/admin/billing/payment?intent={$intent->reference_number}")
            ->post("/store/{$this->tenant->slug}/admin/billing/payment/submit", $this->payload($intent, [
                'payment_reason' => 'plan_change',
            ]))
            ->assertRedirect();

        $fresh = $intent->fresh();
        $this->assertSame('plan_change', $fresh->metadata['payment_reason']);
        $this->assertSame(PaymentIntent::TYPE_PLAN_CHANGE, $fresh->metadata['payment_type']);
    }

    public function test_other_reason_stores_custom_text(): void
    {
        $intent = $this->makeIntent($this->tenant, $this->plan, 'waiting_payment');
        $this->mockImageService();

        $this->actingAs($this->makeOwner($this->tenant));
        $this->from("/store/{$this->tenant->slug}/admin/billing/payment?intent={$intent->reference_number}")
            ->post("/store/{$this->tenant->slug}/admin/billing/payment/submit", $this->payload($intent, [
                'payment_reason' => 'other',
                'payment_reason_text' => 'Bank fee reconciliation',
            ]))
            ->assertRedirect();

        $fresh = $intent->fresh();
        $this->assertSame('other', $fresh->metadata['payment_reason']);
        $this->assertSame('Bank fee reconciliation', $fresh->metadata['payment_reason_text']);
        $this->assertSame('Bank fee reconciliation', $fresh->paymentReasonLabel());
    }

    /* ── 5. Payment-purpose classification ── */

    public function test_payment_type_classification_matrix(): void
    {
        $this->assertSame(PaymentIntent::TYPE_UPGRADE, PaymentIntent::classifyPaymentType(['type' => 'plan_upgrade']));
        $this->assertSame(PaymentIntent::TYPE_UPGRADE, PaymentIntent::classifyPaymentType(['source' => 'merchant_upgrade']));
        $this->assertSame(PaymentIntent::TYPE_RENEWAL, PaymentIntent::classifyPaymentType(['source' => 'merchant_renewal']));
        $this->assertSame(PaymentIntent::TYPE_NEW_SUBSCRIPTION, PaymentIntent::classifyPaymentType(['source' => 'merchant_trial_conversion']));
        $this->assertSame(PaymentIntent::TYPE_NEW_SUBSCRIPTION, PaymentIntent::classifyPaymentType(['trial_conversion' => true]));
        $this->assertSame(PaymentIntent::TYPE_NEW_SUBSCRIPTION, PaymentIntent::classifyPaymentType(['payment_reason' => 'new_subscription']));
        $this->assertSame(PaymentIntent::TYPE_RENEWAL, PaymentIntent::classifyPaymentType(['payment_reason' => 'subscription_renewal']));
        $this->assertSame(PaymentIntent::TYPE_UPGRADE, PaymentIntent::classifyPaymentType(['payment_reason' => 'plan_upgrade']));
        $this->assertSame(PaymentIntent::TYPE_PLAN_CHANGE, PaymentIntent::classifyPaymentType(['payment_reason' => 'plan_change']));
        $this->assertSame(PaymentIntent::TYPE_OTHER, PaymentIntent::classifyPaymentType([]));
        // Structured source wins over a conflicting declared reason.
        $this->assertSame(PaymentIntent::TYPE_UPGRADE, PaymentIntent::classifyPaymentType(['type' => 'plan_upgrade', 'payment_reason' => 'plan_change']));
    }

    public function test_renewal_intent_is_classified_and_plan_change_is_upgrade(): void
    {
        $this->actingAs($this->makeOwner($this->tenant));

        // Renewal via the manual-payment workflow (opening the form creates nothing;
        // the intent is created on submission — classified as a renewal).
        $renewal = app(ManualPaymentService::class)->initiate(
            tenant: $this->tenant,
            plan: $this->plan,
            billingCycle: 'monthly',
            amount: 10.0,
            currency: Currency::fromCode('MMK'),
            metadata: ['source' => 'merchant_renewal', 'payment_type' => PaymentIntent::TYPE_RENEWAL],
        );
        $this->assertSame(PaymentIntent::TYPE_RENEWAL, $renewal->paymentType());

        // Upgrade via the prorated plan-change workflow.
        PaymentIntent::withoutTenantScope()->where('tenant_id', $this->tenant->id)
            ->update(['status' => 'cancelled']);

        $this->post("/store/{$this->tenant->slug}/admin/billing/change-plan/submit", [
            'plan_id' => $this->higherPlan->id,
            'billing_interval' => 'monthly',
        ])->assertRedirect();

        $upgrade = $this->latestPendingIntent($this->tenant);
        $this->assertSame(PaymentIntent::TYPE_UPGRADE, $upgrade->paymentType());
    }

    /* ── 6. Email content for each payment type ── */

    public function test_review_email_shows_payment_type_and_plan_change(): void
    {
        Mail::fake();
        $reviewer = $this->makeSuperAdmin();

        $upgrade = $this->makeIntent($this->tenant, $this->higherPlan, 'waiting_review', [
            'type' => 'plan_upgrade',
            'from_plan_id' => $this->plan->id,
            'from_plan_name' => $this->plan->name,
        ]);

        app(BillingEmailService::class)->sendReviewEmail($upgrade);

        Mail::assertQueued(PaymentReviewMail::class, function ($mail) {
            return $mail->hasTo($this->superAdminEmail)
                && $mail->data['payment_type_label'] === 'Upgrade'
                && $mail->data['from_plan'] === $this->plan->name
                && $mail->data['to_plan'] === $this->higherPlan->name;
        });
    }

    public function test_review_email_type_labels_for_each_purpose(): void
    {
        Mail::fake();
        $this->makeSuperAdmin();

        $cases = [
            [['source' => 'merchant_renewal'], 'Renewal'],
            [['source' => 'merchant_trial_conversion'], 'New Subscription'],
            [['type' => 'plan_upgrade'], 'Upgrade'],
            [['payment_reason' => 'plan_change'], 'Plan Change'],
        ];

        foreach ($cases as [$meta, $label]) {
            $intent = $this->makeIntent($this->tenant, $this->plan, 'waiting_review', $meta);
            app(BillingEmailService::class)->sendReviewEmail($intent);
        }

        foreach ($cases as [$meta, $label]) {
            Mail::assertQueued(PaymentReviewMail::class, fn ($mail) => $mail->data['payment_type_label'] === $label);
        }
    }

    /* ── 7. Super Admin console shows the correct type ── */

    public function test_superadmin_console_shows_payment_type_and_filters(): void
    {
        $renewal = $this->makeIntent($this->tenant, $this->plan, 'waiting_review', ['source' => 'merchant_renewal']);
        $this->makeSuperAdmin();
        $this->actingAs($this->superadmin);

        $this->get('/superadmin/billing')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('SuperAdmin/Billing/Index')
                ->where('intents.data.0.payment_type', 'renewal')
                ->where('intents.data.0.payment_type_label', 'Renewal')
            );

        $this->get('/superadmin/billing?type=renewal')
            ->assertInertia(fn ($page) => $page->has('intents.data', 1));

        $this->get('/superadmin/billing?type=upgrade')
            ->assertInertia(fn ($page) => $page->has('intents.data', 0));
    }

    /* ── 8. Legacy records with missing metadata render safely ── */

    public function test_legacy_intent_without_metadata_is_safe(): void
    {
        $legacy = $this->makeIntent($this->tenant, $this->plan, 'waiting_review');
        $legacy->update(['metadata' => null]);

        $this->assertSame(PaymentIntent::TYPE_OTHER, $legacy->fresh()->paymentType());
        $this->assertSame('Other', $legacy->fresh()->paymentTypeLabel());
        $this->assertNull($legacy->fresh()->paymentReasonLabel());

        Mail::fake();
        $this->makeSuperAdmin();
        app(BillingEmailService::class)->sendReviewEmail($legacy->fresh());
        Mail::assertQueued(PaymentReviewMail::class, fn ($mail) => $mail->data['payment_type_label'] === 'Other');

        $this->actingAs($this->superadmin);
        $this->get('/superadmin/billing')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('intents.data.0.payment_type', 'other'));
    }

    /* ── 9. Tenant isolation and existing approval behavior ── */

    public function test_tenant_cannot_open_another_tenants_payment(): void
    {
        $other = Tenant::create(['slug' => 'pay-other-' . uniqid(), 'name' => 'Other Shop', 'status' => 'active']);
        $intent = $this->makeIntent($this->tenant, $this->plan, 'waiting_payment');

        $this->actingAs($this->makeOwner($other));
        $this->get("/store/{$other->slug}/admin/billing/payment?intent={$intent->reference_number}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('intent', null));
    }

    public function test_superadmin_approval_still_completes_payment(): void
    {
        $intent = $this->makeIntent($this->tenant, $this->plan, 'waiting_review', ['source' => 'merchant_renewal']);
        $this->makeSuperAdmin();
        $this->actingAs($this->superadmin);

        $this->post("/superadmin/billing/{$intent->id}/approve")->assertRedirect();

        $this->assertSame('completed', $intent->fresh()->status);
        $this->assertSame('active', $this->subscription->fresh()->status);
    }

    /* ── Helpers ── */

    private ?User $superadmin = null;
    private ?string $superAdminEmail = null;

    private function payload(PaymentIntent $intent, array $overrides = []): array
    {
        return array_merge([
            'intent_reference' => $intent->reference_number,
            'submission_key' => 'key-' . uniqid(),
            'sender_name' => 'John Doe',
            'sender_account' => '09123456789',
            'transaction_reference' => 'TRX-' . uniqid(),
            'transferred_amount' => (float) $intent->amount,
            'transfer_date' => now()->format('Y-m-d'),
            'transfer_time' => now()->format('H:i'),
            'payment_reason' => 'subscription_renewal',
            'note' => 'Test',
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

    private function makeIntent(Tenant $tenant, Plan $plan, string $status, array $metadata = []): PaymentIntent
    {
        if (!array_key_exists('payment_type', $metadata)) {
            $metadata['payment_type'] = PaymentIntent::classifyPaymentType($metadata);
        }

        $intent = new PaymentIntent([
            'plan_id' => $plan->id,
            'subscription_id' => $this->subscription->id,
            'billing_cycle' => 'monthly',
            'amount' => (float) ($plan->monthly_price ?? 10),
            'currency' => 'MMK',
            'gateway' => 'manual',
            'status' => $status,
            'reference_number' => 'PAY-' . strtoupper(uniqid()),
            'idempotency_key' => uniqid('idem-', true),
            'metadata' => $metadata,
        ]);
        $intent->tenant_id = $tenant->id;
        $intent->save();

        return $intent;
    }

    private function latestPendingIntent(Tenant $tenant): ?PaymentIntent
    {
        return PaymentIntent::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', ['waiting_payment', 'waiting_review'])
            ->latest()
            ->first();
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
        if (!$this->superadmin) {
            $roleId = \Illuminate\Support\Facades\DB::table('roles')->insertGetId([
                'name' => 'superadmin', 'guard_name' => 'web',
                'tenant_id' => null,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            $this->superAdminEmail = 'super-' . uniqid() . '@test.com';
            $this->superadmin = User::create([
                'tenant_id' => null,
                'name' => 'Super Admin',
                'email' => $this->superAdminEmail,
                'password' => bcrypt('password'),
                'status' => 'active',
            ]);

            \Illuminate\Support\Facades\DB::table('model_has_roles')->insert([
                'role_id' => $roleId,
                'model_type' => User::class,
                'model_id' => $this->superadmin->id,
            ]);

            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
            $this->superadmin = $this->superadmin->fresh();
        }

        return $this->superadmin;
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
                $table->unsignedBigInteger('reviewer_id')->nullable(); $table->string('reviewer_name')->nullable();
                $table->string('action')->nullable(); $table->text('reason')->nullable();
                $table->json('metadata')->nullable(); $table->timestamps();
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
                $table->boolean('trial_enabled')->default(false); $table->integer('trial_days')->default(14);
                $table->boolean('allow_trial_renewal')->default(true); $table->integer('max_trial_renewals')->default(0);
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
