<?php

namespace Tests\Feature;

use App\Data\Currency;
use App\Jobs\SendTelegramMessageJob;
use App\Models\Account;
use App\Models\PaymentIntent;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\TelegramIntegration;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Services\BillingNotificationService;
use App\Services\Payment\Platform\ManualPaymentService;
use App\Services\SubscriptionExpiryService;
use App\Services\TelegramRecipientResolver;
use App\Services\TelegramService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BillingTelegramNotificationsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('identity.use_accounts', true);
        $this->createBillingTelegramSchema();
        Mail::fake();
    }

    public function test_resolver_returns_only_tenant_enabled_verified(): void
    {
        [$tenantA] = $this->makeStack('owner-tgres-a@example.com');
        [$tenantB] = $this->makeStack('owner-tgres-b@example.com');

        $integrationA = $this->makeIntegration($tenantA->id, true, true);
        $this->makeIntegration($tenantA->id, false, true);
        $this->makeIntegration($tenantB->id, true, true);

        $resolved = app(TelegramRecipientResolver::class)->resolve(null, $tenantA->id);

        $this->assertSame([$integrationA->id], $resolved->pluck('id')->all());
    }

    public function test_payment_approved_dispatches_telegram(): void
    {
        Queue::fake();
        [$tenant, , $plan] = $this->makeStack('owner-tgapproved@example.com');
        $integration = $this->makeIntegration($tenant->id, true, true);
        $intent = $this->makeIntent($tenant, $plan);

        app(BillingNotificationService::class)->notifyPaymentApproved($intent);

        Queue::assertPushed(SendTelegramMessageJob::class, function ($job) use ($integration) {
            return $job->telegramIntegration->id === $integration->id
                && $job->chatId === $integration->personal_chat_id
                && ($job->payload['notification_type'] ?? null) === 'billing.payment_approved';
        });
    }

    public function test_payment_submitted_dispatches_telegram_via_observer(): void
    {
        Queue::fake();
        [$tenant, , $plan] = $this->makeStack('owner-tgsubmitted@example.com');
        $this->makeSuperadmin('superadmin-tgsubmitted@example.com');
        $integration = $this->makeIntegration($tenant->id, true, true);

        $manual = app(ManualPaymentService::class);
        $intent = $manual->initiate(
            tenant: $tenant, plan: $plan, billingCycle: 'monthly',
            amount: 100.00, currency: Currency::fromCode('MMK'),
        );
        $manual->confirmPayment($intent);

        Queue::assertPushed(SendTelegramMessageJob::class, function ($job) use ($integration) {
            return $job->telegramIntegration->id === $integration->id
                && ($job->payload['notification_type'] ?? null) === 'billing.payment_submitted';
        });
    }

    public function test_payment_rejected_dispatches_telegram(): void
    {
        Queue::fake();
        [$tenant, , $plan] = $this->makeStack('owner-tgrejected@example.com');
        $integration = $this->makeIntegration($tenant->id, true, true);
        $intent = $this->makeIntent($tenant, $plan);

        app(BillingNotificationService::class)->notifyPaymentRejected($intent->fresh());

        Queue::assertPushed(SendTelegramMessageJob::class, function ($job) use ($integration) {
            return $job->telegramIntegration->id === $integration->id
                && ($job->payload['notification_type'] ?? null) === 'billing.payment_rejected';
        });
    }

    public function test_reminder_dispatches_expiring_only_once(): void
    {
        Queue::fake();
        [$tenant] = $this->makeStack('owner-tgreminder@example.com', now()->addDays(3)->startOfDay());
        $this->makeIntegration($tenant->id, true, true);

        $this->artisan('subscriptions:send-reminders')->assertSuccessful();
        $this->artisan('subscriptions:send-reminders')->assertSuccessful();

        Queue::assertPushed(SendTelegramMessageJob::class, 1);
        Queue::assertPushed(SendTelegramMessageJob::class, function ($job) {
            return ($job->payload['notification_type'] ?? null) === 'billing.subscription_expiring'
                && ($job->payload['context']['days_remaining'] ?? null) === 3;
        });
    }

    public function test_lifecycle_dispatches_past_due_and_expired(): void
    {
        Queue::fake();
        [$tenantA] = $this->makeStack('owner-tgpastdue@example.com', now()->subDay());
        [$tenantB] = $this->makeStack('owner-tgexpired@example.com', now()->subDays(8), 'past_due');
        $this->makeIntegration($tenantA->id, true, true);
        $this->makeIntegration($tenantB->id, true, true);

        app(SubscriptionExpiryService::class)->process();

        Queue::assertPushed(SendTelegramMessageJob::class, function ($job) {
            return ($job->payload['notification_type'] ?? null) === 'billing.subscription_past_due';
        });
        Queue::assertPushed(SendTelegramMessageJob::class, function ($job) {
            return ($job->payload['notification_type'] ?? null) === 'billing.subscription_expired';
        });
    }

    public function test_renew_dispatches_renewed(): void
    {
        Queue::fake();
        [$tenant, $subscription] = $this->makeStack('owner-tgrenewed@example.com', now()->subDay());
        $this->makeIntegration($tenant->id, true, true);

        $subscription->renew(now()->addMonth());

        Queue::assertPushed(SendTelegramMessageJob::class, function ($job) {
            return ($job->payload['notification_type'] ?? null) === 'billing.subscription_renewed';
        });
    }

    public function test_no_integration_no_dispatch(): void
    {
        Queue::fake();
        [$tenantA] = $this->makeStack('owner-tgno-a@example.com');
        [$tenantB] = $this->makeStack('owner-tgno-b@example.com', now()->addMonth());
        $this->makeIntegration($tenantB->id, true, true);
        $intent = $this->makeIntent($tenantA, Plan::first());

        app(BillingNotificationService::class)->notifyPaymentApproved($intent);

        Queue::assertNotPushed(SendTelegramMessageJob::class);
    }

    public function test_master_switch_off_suppresses_dispatch(): void
    {
        Queue::fake();
        [$tenant, , $plan] = $this->makeStack('owner-tgoff@example.com');
        $this->makeIntegration($tenant->id, true, true);
        $setting = new Setting(['key' => 'notifications_enabled', 'value' => 'false']);
        $setting->tenant_id = $tenant->id;
        $setting->save();
        $intent = $this->makeIntent($tenant, $plan);

        app(BillingNotificationService::class)->notifyPaymentApproved($intent);

        Queue::assertNotPushed(SendTelegramMessageJob::class);
    }

    public function test_queued_job_handle_sends_message(): void
    {
        [$tenant] = $this->makeStack('owner-tghandle@example.com');
        $integration = $this->makeIntegration($tenant->id, true, true);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200),
        ]);

        $job = new SendTelegramMessageJob(
            $integration,
            '<b>✅ test</b>',
            $integration->personal_chat_id,
            ['notification_type' => 'billing.test'],
        );
        $job->handle(app(TelegramService::class));

        $this->assertNotNull($integration->fresh()->last_verified_at);
    }

    private function makeStack(string $ownerEmail, $expiresAt = null, string $status = 'active'): array
    {
        $tenant = Tenant::create([
            'slug' => 'btg-' . uniqid(),
            'name' => 'Billing Telegram Shop',
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
            'status' => $status,
            'starts_at' => now()->subMonth(),
            'expires_at' => $expiresAt ?? now()->addMonth(),
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
        $role = Role::withoutTenantScope()->firstOrCreate([
            'name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id,
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

    private function makeSuperadmin(string $email): void
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

    private function makeIntegration(int $tenantId, bool $enabled, bool $verified): TelegramIntegration
    {
        $integration = new TelegramIntegration([
            'bot_name' => 'Test Bot',
            'bot_username' => 'testbot_' . uniqid(),
            'bot_token' => 'test-token-' . uniqid(),
            'personal_chat_id' => 'pchat-' . uniqid(),
            'is_enabled' => $enabled,
        ]);
        $integration->tenant_id = $tenantId;
        if ($verified) {
            $integration->personal_verified_at = now();
        }
        $integration->save();

        return $integration;
    }

    private function createBillingTelegramSchema(): void
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
        }, 'payment_timeline_events' => function ($table) {
            $table->id();
            $table->unsignedBigInteger('payment_intent_id');
            $table->string('type');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index('payment_intent_id');
        }, 'users' => function ($table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
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
        }, 'notifications' => function ($table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        }, 'reference_numbers' => function ($table) {
            $table->id();
            $table->string('prefix', 10);
            $table->date('date');
            $table->unsignedInteger('last_sequence')->default(0);
            $table->timestamps();
            $table->unique(['prefix', 'date']);
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
        }, 'telegram_integrations' => function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('bot_name');
            $table->string('bot_username');
            $table->text('bot_token');
            $table->string('chat_id')->nullable();
            $table->string('parse_mode')->default('HTML');
            $table->boolean('is_enabled')->default(false);
            $table->string('webhook_secret', 64)->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->string('verification_status')->nullable();
            $table->string('personal_chat_id')->nullable();
            $table->timestamp('personal_verified_at')->nullable();
            $table->string('group_chat_id')->nullable();
            $table->timestamp('group_verified_at')->nullable();
            $table->string('group_chat_type')->nullable();
            $table->string('default_destination')->nullable();
            $table->string('payment_destination')->nullable();
            $table->timestamps();
        }, 'settings' => function ($table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('key');
            $table->text('value')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'key']);
        }] as $name => $blueprint) {
            if (!Schema::hasTable($name)) {
                Schema::create($name, $blueprint);
            }
        }
    }
}
