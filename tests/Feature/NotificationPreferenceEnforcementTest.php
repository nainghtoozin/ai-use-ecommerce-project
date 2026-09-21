<?php

namespace Tests\Feature;

use App\Jobs\ProcessOrderStatusChange;
use App\Jobs\SendTelegramMessageJob;
use App\Models\Account;
use App\Models\Order;
use App\Models\PaymentIntent;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\TelegramIntegration;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Notifications\BillingPaymentSubmittedAdminNotification;
use App\Notifications\OrderShippedNotification;
use App\Notifications\SubscriptionPastDue;
use App\Services\BillingNotificationService;
use App\Services\NotificationPreferenceService;
use App\Services\SubscriptionExpiryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NotificationPreferenceEnforcementTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('identity.use_accounts', true);
        $this->createPreferenceSchema();
        Mail::fake();
    }

    public function test_item_toggle_blocks_order_status_notification(): void
    {
        Queue::fake();
        [$tenant, $order] = $this->makeOrder('cust-item@example.com', 'processing', 'paid');
        $customerId = $order->user_id;
        $this->makeIntegration($tenant->id);
        $this->setTenantSetting($tenant->id, 'item_order_status_changed', 'false');

        (new ProcessOrderStatusChange($order->fresh(), 'shipped', 'processing'))
            ->handle(app(NotificationPreferenceService::class));

        $this->assertSame(0, DB::table('notifications')
            ->where('notifiable_type', Account::class)
            ->where('notifiable_id', $customerId)
            ->count());
        Queue::assertNotPushed(SendTelegramMessageJob::class);

        Setting::where('tenant_id', $tenant->id)->where('key', 'item_order_status_changed')->delete();

        (new ProcessOrderStatusChange($order->fresh(), 'shipped', 'processing'))
            ->handle(app(NotificationPreferenceService::class));

        $this->assertSame(1, DB::table('notifications')
            ->where('notifiable_type', Account::class)
            ->where('notifiable_id', $customerId)
            ->where('type', OrderShippedNotification::class)
            ->count());
        Queue::assertPushed(SendTelegramMessageJob::class, 1);
    }

    public function test_master_switch_blocks_notices_but_not_transitions(): void
    {
        [$tenant, $subscription] = $this->makeSubscription('owner-master@example.com', now()->subDay());
        $accountId = Account::where('email', 'owner-master@example.com')->firstOrFail()->id;
        $this->setTenantSetting($tenant->id, 'notifications_enabled', 'false');

        $result = app(SubscriptionExpiryService::class)->process();

        $this->assertSame(1, $result['active_to_past_due']);
        $this->assertSame('past_due', $subscription->fresh()->status);
        $this->assertSame(0, DB::table('notifications')
            ->where('notifiable_type', Account::class)
            ->where('notifiable_id', $accountId)
            ->where('type', SubscriptionPastDue::class)
            ->count());
    }

    public function test_billing_respects_master_switch(): void
    {
        Queue::fake();
        [$tenant, , $plan] = $this->makeSubscription('owner-billpref@example.com', now()->addMonth());
        $accountId = Account::where('email', 'owner-billpref@example.com')->firstOrFail()->id;
        $this->makeIntegration($tenant->id);
        $intent = $this->makeIntent($tenant, $plan);

        app(BillingNotificationService::class)->notifyPaymentApproved($intent);

        $this->assertSame(1, DB::table('notifications')
            ->where('notifiable_type', Account::class)
            ->where('notifiable_id', $accountId)
            ->count());
        Queue::assertPushed(SendTelegramMessageJob::class, 1);

        $this->setTenantSetting($tenant->id, 'notifications_enabled', 'false');
        DB::table('notifications')->where('notifiable_id', $accountId)->delete();

        app(BillingNotificationService::class)->notifyPaymentApproved($intent->fresh());

        $this->assertSame(0, DB::table('notifications')
            ->where('notifiable_type', Account::class)
            ->where('notifiable_id', $accountId)
            ->count());
    }

    public function test_superadmin_submitted_notice_is_mandatory(): void
    {
        Queue::fake();
        [$tenant, , $plan] = $this->makeSubscription('owner-mandatory@example.com', now()->addMonth());
        $this->setTenantSetting($tenant->id, 'notifications_enabled', 'false');
        $superadmin = $this->makeSuperadmin('superadmin-mandatory@example.com');
        $intent = $this->makeIntent($tenant, $plan);

        Notification::send($superadmin, new BillingPaymentSubmittedAdminNotification($intent));
        app(BillingNotificationService::class)->notifyPaymentSubmitted($intent->fresh());

        $rows = DB::table('notifications')
            ->where('notifiable_type', Account::class)
            ->where('notifiable_id', $superadmin->id)
            ->where('type', BillingPaymentSubmittedAdminNotification::class)
            ->get();

        $this->assertGreaterThanOrEqual(1, $rows->count());
        $this->assertTrue($rows->every(fn ($row) => $row->tenant_id === null));
        Queue::assertNotPushed(SendTelegramMessageJob::class);
    }

    public function test_settings_are_tenant_isolated(): void
    {
        Queue::fake();
        [$tenantA, , $planA] = $this->makeSubscription('owner-iso-a@example.com', now()->addMonth());
        [$tenantB, , $planB] = $this->makeSubscription('owner-iso-b@example.com', now()->addMonth());
        $this->setTenantSetting($tenantA->id, 'notifications_enabled', 'false');

        app(BillingNotificationService::class)->notifyPaymentApproved($this->makeIntent($tenantA, $planA));
        app(BillingNotificationService::class)->notifyPaymentApproved($this->makeIntent($tenantB, $planB));

        $this->assertSame(0, DB::table('notifications')
            ->where('notifiable_type', Account::class)
            ->where('notifiable_id', Account::where('email', 'owner-iso-a@example.com')->firstOrFail()->id)
            ->count());
        $this->assertSame(1, DB::table('notifications')
            ->where('notifiable_type', Account::class)
            ->where('notifiable_id', Account::where('email', 'owner-iso-b@example.com')->firstOrFail()->id)
            ->count());
    }

    public function test_reminder_command_respects_master_switch(): void
    {
        [$tenant] = $this->makeSubscription('owner-rempref@example.com', now()->addDays(3)->startOfDay());
        $this->setTenantSetting($tenant->id, 'notifications_enabled', 'false');

        $this->artisan('subscriptions:send-reminders')->assertSuccessful();

        $accountId = Account::where('email', 'owner-rempref@example.com')->firstOrFail()->id;

        $this->assertSame(0, DB::table('notifications')
            ->where('notifiable_type', Account::class)
            ->where('notifiable_id', $accountId)
            ->count());
    }

    public function test_tenant_allows_matrix(): void
    {
        $service = app(NotificationPreferenceService::class);
        $tenantId = Tenant::create([
            'slug' => 'pref-matrix-' . uniqid(),
            'name' => 'Matrix Shop',
            'status' => 'active',
        ])->id;

        $this->assertTrue($service->tenantAllows($tenantId));
        $this->assertTrue($service->tenantAllows($tenantId, 'notification_orders_enabled', 'item_new_order'));

        $this->setTenantSetting($tenantId, 'item_new_order', 'false');
        $this->assertFalse($service->tenantAllows($tenantId, 'notification_orders_enabled', 'item_new_order'));
        $this->assertTrue($service->tenantAllows($tenantId, 'notification_orders_enabled', 'item_order_cancelled'));

        $this->setTenantSetting($tenantId, 'notifications_enabled', 'false');
        $this->assertFalse($service->tenantAllows($tenantId));
        $this->assertFalse($service->tenantAllows($tenantId, 'notification_orders_enabled', 'item_order_cancelled'));
    }

    private function setTenantSetting(int $tenantId, string $key, string $value): void
    {
        $setting = new Setting(['key' => $key, 'value' => $value]);
        $setting->tenant_id = $tenantId;
        $setting->save();
    }

    private function makeSubscription(string $ownerEmail, $expiresAt): array
    {
        $tenant = Tenant::create([
            'slug' => 'pref-' . uniqid(),
            'name' => 'Pref Shop',
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

        $this->makeOwner($tenant->id, $ownerEmail);

        return [$tenant, $subscription, $plan];
    }

    private function makeOrder(string $customerEmail, string $orderStatus, string $paymentStatus): array
    {
        $tenant = Tenant::create([
            'slug' => 'ordpref-' . uniqid(),
            'name' => 'Order Pref Shop',
            'status' => 'active',
        ]);

        $customer = Account::create([
            'name' => 'Customer',
            'email' => $customerEmail,
            'password' => 'secret',
            'status' => 'active',
        ]);

        $order = new Order([
            'customer_name' => 'Customer',
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'phone' => '09123456789',
            'address' => 'Test Street 1',
            'subtotal' => 50000,
            'total_amount' => 50000,
            'order_status' => $orderStatus,
            'payment_status' => $paymentStatus,
        ]);
        $order->user_id = $customer->id;
        $order->user_type = Account::class;
        $order->tenant_id = $tenant->id;
        $order->invoice_number = 'INV-TEST-' . strtoupper(uniqid());

        Schema::disableForeignKeyConstraints();
        try {
            $order->save();
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        return [$tenant, $order, $customer];
    }

    private function makeOwner(int $tenantId, string $email): Account
    {
        $account = Account::create([
            'name' => 'Owner',
            'email' => $email,
            'password' => 'secret',
            'status' => 'active',
        ]);
        $role = Role::withoutTenantScope()->firstOrCreate([
            'name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenantId,
        ]);
        TenantMembership::create([
            'account_id' => $account->id,
            'tenant_id' => $tenantId,
            'role_id' => $role->id,
            'is_owner' => true,
            'status' => 'active',
        ]);

        return $account;
    }

    private function makeSuperadmin(string $email): Account
    {
        $account = Account::create([
            'name' => 'Super Admin',
            'email' => $email,
            'password' => 'secret',
            'status' => 'active',
        ]);
        $role = Role::withoutTenantScope()->firstOrCreate([
            'name' => 'superadmin', 'guard_name' => 'web', 'tenant_id' => null,
        ]);
        DB::table('model_has_roles')->insert([
            'role_id' => $role->id,
            'model_type' => Account::class,
            'model_id' => $account->id,
        ]);

        return $account;
    }

    private function makeIntent(Tenant $tenant, Plan $plan): PaymentIntent
    {
        $intent = new PaymentIntent([
            'plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'amount' => 100,
            'currency' => 'MMK',
            'gateway' => 'manual',
            'status' => 'waiting_review',
            'reference_number' => 'PAY-' . strtoupper(uniqid()),
            'idempotency_key' => uniqid('idem-', true),
            'metadata' => [],
        ]);
        $intent->tenant_id = $tenant->id;
        $intent->save();

        return $intent;
    }

    private function makeIntegration(int $tenantId): TelegramIntegration
    {
        $integration = new TelegramIntegration([
            'bot_name' => 'Test Bot',
            'bot_username' => 'testbot_' . uniqid(),
            'bot_token' => 'test-token-' . uniqid(),
            'personal_chat_id' => 'pchat-' . uniqid(),
            'is_enabled' => true,
            'personal_verified_at' => now(),
        ]);
        $integration->tenant_id = $tenantId;
        $integration->save();

        return $integration;
    }

    private function createPreferenceSchema(): void
    {
        foreach (['tenants' => function ($table) {
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
        }, 'plans' => function ($table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('monthly_price', 10, 2)->nullable();
            $table->decimal('yearly_price', 10, 2)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        }, 'subscriptions' => function ($table) {
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
        }, 'subscription_audit_logs' => function ($table) {
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
        }, 'payment_intents' => function ($table) {
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
        }, 'accounts' => function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        }, 'tenant_memberships' => function ($table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('role_id')->nullable();
            $table->boolean('is_owner')->default(false);
            $table->string('status')->default('active');
            $table->timestamps();
            $table->index(['tenant_id', 'account_id']);
        }, 'roles' => function ($table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();
            $table->unique(['name', 'guard_name', 'tenant_id']);
        }, 'model_has_roles' => function ($table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type']);
        }, 'orders' => function ($table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('user_type')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('invoice_number')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->string('order_status')->default('pending');
            $table->string('payment_status')->nullable();
            $table->timestamp('telegram_notified_at')->nullable();
            $table->timestamps();
        }, 'activity_logs' => function ($table) {
            $table->id();
            $table->string('description')->nullable();
            $table->string('event')->nullable();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('properties')->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();
        }, 'telegram_integrations' => function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('bot_name');
            $table->string('bot_username');
            $table->text('bot_token');
            $table->string('personal_chat_id')->nullable();
            $table->timestamp('personal_verified_at')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();
        }, 'settings' => function ($table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('key');
            $table->text('value')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'key']);
        }, 'notifications' => function ($table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        }] as $name => $blueprint) {
            if (!Schema::hasTable($name)) {
                Schema::create($name, $blueprint);
            }
        }

        if (!Schema::hasColumn('notifications', 'tenant_id')) {
            Schema::table('notifications', function ($table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
            });
        }
    }
}
