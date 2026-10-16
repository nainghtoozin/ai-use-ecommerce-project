<?php

namespace Tests\Feature;

use App\Models\PaymentIntent;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SubscriptionNoticeTest extends TestCase
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

        $this->setReminderDays(7);

        $this->paidPlan = Plan::create([
            'name' => 'Starter', 'slug' => 'starter-' . uniqid(), 'description' => 'Paid',
            'monthly_price' => 10, 'yearly_price' => 100, 'status' => 'active',
        ]);

        $this->tenant = Tenant::create([
            'slug' => 'notice-' . uniqid(), 'name' => 'Notice Shop', 'status' => 'active',
        ]);

        $this->subscription = $this->makeSubscription('active', now()->addDays(30));
    }

    protected function tearDown(): void
    {
        Tenant::setCurrent(null);
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_active_paid_healthy_has_no_notice(): void
    {
        $this->profile()->assertInertia(fn ($page) => $page->where('subscription_notice', null));
    }

    public function test_active_paid_beyond_threshold_has_no_notice(): void
    {
        $this->subscription->update(['expires_at' => now()->addDays(10)]);

        $this->profile()->assertInertia(fn ($page) => $page->where('subscription_notice', null));
    }

    public function test_active_paid_within_threshold_shows_renewal_notice(): void
    {
        $this->subscription->update(['expires_at' => now()->addDays(5)]);

        $this->profile()->assertInertia(fn ($page) => $page
            ->where('subscription_notice.key', 'active_due')
            ->where('subscription_notice.severity', 'warning')
            ->where('subscription_notice.dismissible', true)
            ->where('subscription_notice.action.path', '/admin/billing/renew-plan')
        );
    }

    public function test_trial_ending_shows_trial_notice(): void
    {
        $this->subscription->update([
            'status' => 'trialing',
            'trial_ends_at' => now()->addDays(3),
            'expires_at' => now()->addDays(3),
        ]);

        $this->profile()->assertInertia(fn ($page) => $page
            ->where('subscription_notice.key', 'trial_ending')
            ->where('subscription_notice.action.path', '/admin/billing/upgrade')
        );
    }

    public function test_trial_healthy_has_no_notice(): void
    {
        $this->subscription->update([
            'status' => 'trialing',
            'trial_ends_at' => now()->addDays(20),
            'expires_at' => now()->addDays(20),
        ]);

        $this->profile()->assertInertia(fn ($page) => $page->where('subscription_notice', null));
    }

    public function test_pending_payment_shows_review_notice(): void
    {
        $this->subscription->update(['expires_at' => now()->addDays(30)]);
        $intent = $this->makeIntent('waiting_review');

        $this->profile()->assertInertia(fn ($page) => $page
            ->where('subscription_notice.key', 'pending_payment')
            ->where('subscription_notice.dismissible', true)
            ->where('subscription_notice.action.path', '/admin/billing/payment?intent=' . $intent->reference_number)
        );
    }

    public function test_past_due_shows_grace_notice(): void
    {
        $this->subscription->update(['status' => 'past_due', 'expires_at' => now()->subDays(2)]);

        $this->profile()->assertInertia(fn ($page) => $page
            ->where('subscription_notice.key', 'past_due')
            ->where('subscription_notice.severity', 'warning')
            ->where('subscription_notice.dismissible', false)
        );
    }

    public function test_expired_shows_danger_notice_with_renew_action(): void
    {
        $this->subscription->update(['status' => 'expired', 'expires_at' => now()->subDays(20)]);

        $this->profile()->assertInertia(fn ($page) => $page
            ->where('subscription_notice.key', 'expired')
            ->where('subscription_notice.severity', 'danger')
            ->where('subscription_notice.dismissible', false)
            ->where('subscription_notice.action.path', '/admin/billing/renew-plan')
        );
    }

    public function test_suspended_shows_contact_notice_without_action(): void
    {
        $this->subscription->update(['status' => 'suspended']);
        $this->tenant->update(['status' => 'suspended']);

        // Suspended tenants are redirected away from admin pages (existing policy preserved)…
        $this->actingAs($this->owner());
        $this->get('/profile')->assertRedirect();

        // …and the notice builder still returns the correct suspended notice shape.
        $middleware = app(\App\Http\Middleware\HandleInertiaRequests::class);
        $method = new \ReflectionMethod($middleware, 'buildSubscriptionNotice');
        $method->setAccessible(true);
        $notice = $method->invoke($middleware, $this->subscription->fresh(), $this->tenant->fresh(), 7);

        $this->assertSame('suspended', $notice['key']);
        $this->assertSame('danger', $notice['severity']);
        $this->assertNull($notice['action']);
        $this->assertFalse($notice['dismissible']);
    }

    public function test_billing_route_suppresses_the_global_notice(): void
    {
        $this->subscription->update(['expires_at' => now()->addDays(3)]);

        $this->actingAs($this->owner());
        $this->get("/store/{$this->tenant->slug}/admin/billing")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('subscription_notice', null));
    }

    public function test_threshold_is_configurable(): void
    {
        $this->subscription->update(['expires_at' => now()->addDays(10)]);

        $this->profile()->assertInertia(fn ($page) => $page
            ->where('subscription_renewal_threshold_days', 7)
            ->where('subscription_notice', null)
        );

        $this->setReminderDays(14);

        $this->profile()->assertInertia(fn ($page) => $page
            ->where('subscription_renewal_threshold_days', 14)
            ->where('subscription_notice.key', 'active_due')
        );
    }

    /* ── Helpers ── */

    private ?User $ownerUser = null;

    private function profile()
    {
        $this->actingAs($this->owner());

        return $this->get('/profile')->assertOk();
    }

    private function owner(): User
    {
        return $this->ownerUser ??= $this->makeOwner();
    }

    private function makeOwner(): User
    {
        $user = User::create([
            'tenant_id' => $this->tenant->id,
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

    private function setReminderDays(int $days): void
    {
        $settings = PlatformSetting::first() ?? new PlatformSetting();
        $settings->fill([
            'site_name' => 'Test',
            'trial_enabled' => true,
            'trial_days' => 14,
            'allow_trial_renewal' => true,
            'max_trial_renewals' => 0,
            'billing_renewal_reminder_days' => $days,
        ]);
        $settings->save();
        PlatformSetting::clearCache();
    }

    private function makeIntent(string $status): PaymentIntent
    {
        $intent = new PaymentIntent([
            'plan_id' => $this->paidPlan->id,
            'billing_cycle' => 'monthly',
            'amount' => 10.0,
            'currency' => 'MMK',
            'gateway' => 'manual',
            'status' => $status,
            'reference_number' => 'PAY-' . strtoupper(uniqid()),
            'idempotency_key' => uniqid('idem-', true),
            'metadata' => ['source' => 'merchant_renewal', 'payment_type' => 'renewal'],
        ]);
        $intent->tenant_id = $this->tenant->id;
        $intent->save();

        return $intent;
    }

    private function makeSubscription(string $status, $expiresAt): Subscription
    {
        $subscription = new Subscription([
            'plan_id' => $this->paidPlan->id,
            'billing_interval' => 'monthly',
            'status' => $status,
            'starts_at' => now()->subMonth(),
            'expires_at' => $expiresAt,
            'trial_renewals_count' => 0,
        ]);
        $subscription->tenant_id = $this->tenant->id;
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
            'payment_timeline_events' => function ($t) { $t->id(); $t->unsignedBigInteger('payment_intent_id'); $t->string('type'); $t->text('description')->nullable(); $t->json('metadata')->nullable(); $t->timestamp('occurred_at'); $t->timestamps(); },
            'subscription_audit_logs' => function ($t) { $t->id(); $t->unsignedBigInteger('subscription_id'); $t->unsignedBigInteger('tenant_id')->nullable(); $t->string('event'); $t->string('actor_type')->nullable(); $t->unsignedBigInteger('actor_id')->nullable(); $t->unsignedBigInteger('old_plan_id')->nullable(); $t->unsignedBigInteger('new_plan_id')->nullable(); $t->string('old_status')->nullable(); $t->string('new_status')->nullable(); $t->text('reason')->nullable(); $t->json('metadata')->nullable(); $t->timestamps(); },
            'subscription_cancellation_requests' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id'); $t->unsignedBigInteger('subscription_id'); $t->string('reason',50); $t->text('comment')->nullable(); $t->string('status',20)->default('pending'); $t->unsignedBigInteger('requested_by')->nullable(); $t->unsignedBigInteger('reviewed_by')->nullable(); $t->text('review_note')->nullable(); $t->timestamp('reviewed_at')->nullable(); $t->timestamps(); },
            'billing_payment_methods' => function ($t) { $t->id(); $t->string('display_name'); $t->string('type')->nullable(); $t->string('account_name')->nullable(); $t->string('account_number')->nullable(); $t->string('bank_name')->nullable(); $t->string('branch')->nullable(); $t->text('instructions')->nullable(); $t->string('currency',3)->nullable(); $t->string('qr_image_url')->nullable(); $t->boolean('is_active')->default(true); $t->boolean('supports_manual_payment')->default(true); $t->integer('sort_order')->default(0); $t->timestamps(); },
            'website_infos' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->string('site_name')->nullable(); $t->string('currency_code')->default('MMK'); $t->string('currency_symbol')->default('K'); $t->timestamps(); },
            'platform_settings' => function ($t) { $t->id(); $t->string('site_name')->default('My Application'); $t->boolean('trial_enabled')->default(false); $t->integer('trial_days')->default(14); $t->boolean('allow_trial_renewal')->default(true); $t->integer('max_trial_renewals')->default(0); $t->integer('billing_renewal_reminder_days')->default(7); $t->timestamps(); },
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
