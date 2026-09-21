<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\PaymentIntent;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Notifications\BillingPaymentApprovedMerchantNotification;
use App\Notifications\BillingPaymentSubmittedAdminNotification;
use App\Notifications\SubscriptionPastDue;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NotificationTenantContextTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('identity.use_accounts', true);
        $this->createTenantContextSchema();
    }

    public function test_tenant_scoped_notification_writes_tenant_id(): void
    {
        [$tenant, $subscription] = $this->makeStack('owner-ctx@example.com');
        $accountId = Account::where('email', 'owner-ctx@example.com')->firstOrFail()->id;

        $tenant->notifyAdmins(new SubscriptionPastDue($subscription));

        $rows = DB::table('notifications')
            ->where('notifiable_type', Account::class)
            ->where('notifiable_id', $accountId)
            ->where('type', SubscriptionPastDue::class)
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame($tenant->id, (int) $rows->first()->tenant_id);
    }

    public function test_other_tenant_does_not_share_tenant_id(): void
    {
        [$tenantA] = $this->makeStack('owner-ctx-a@example.com');
        $this->makeStack('owner-ctx-b@example.com');

        $tenantA->notifyAdmins(new SubscriptionPastDue($tenantA->subscription()->first()));

        $outsiderId = Account::where('email', 'owner-ctx-b@example.com')->firstOrFail()->id;

        $this->assertSame(0, DB::table('notifications')
            ->where('notifiable_type', Account::class)
            ->where('notifiable_id', $outsiderId)
            ->count());
        $this->assertSame(0, DB::table('notifications')
            ->where('type', SubscriptionPastDue::class)
            ->where('tenant_id', '!=', $tenantA->id)
            ->whereNotNull('tenant_id')
            ->count());
    }

    public function test_global_superadmin_notification_keeps_null_tenant_id(): void
    {
        [$tenant, , $plan] = $this->makeStack('owner-ctx-global@example.com');
        $superadmin = Account::create([
            'name' => 'Super Admin',
            'email' => 'superadmin-ctx@example.com',
            'password' => 'secret',
            'status' => 'active',
        ]);
        $intent = $this->makeIntent($tenant, $plan);

        Notification::send($superadmin, new BillingPaymentSubmittedAdminNotification($intent));

        $rows = DB::table('notifications')
            ->where('notifiable_type', Account::class)
            ->where('notifiable_id', $superadmin->id)
            ->where('type', BillingPaymentSubmittedAdminNotification::class)
            ->get();

        $this->assertCount(1, $rows);
        $this->assertNull($rows->first()->tenant_id);
    }

    public function test_billing_approved_notification_writes_merchant_tenant_id(): void
    {
        [$tenant, , $plan] = $this->makeStack('owner-ctx-approved@example.com');
        $accountId = Account::where('email', 'owner-ctx-approved@example.com')->firstOrFail()->id;
        $intent = $this->makeIntent($tenant, $plan);

        $tenant->notifyAdmins(new BillingPaymentApprovedMerchantNotification($intent));

        $rows = DB::table('notifications')
            ->where('notifiable_type', Account::class)
            ->where('notifiable_id', $accountId)
            ->where('type', BillingPaymentApprovedMerchantNotification::class)
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame($tenant->id, (int) $rows->first()->tenant_id);
    }

    private function makeStack(string $ownerEmail): array
    {
        $tenant = Tenant::create([
            'slug' => 'ctx-' . uniqid(),
            'name' => 'Ctx Shop',
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
            'expires_at' => now()->addMonth(),
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

    private function createTenantContextSchema(): void
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
        }, 'payment_intents' => function ($table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('plan_id')->nullable();
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
