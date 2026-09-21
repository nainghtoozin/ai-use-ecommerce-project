<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Notifications\SubscriptionExpired;
use App\Notifications\SubscriptionPastDue;
use App\Services\SubscriptionExpiryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SubscriptionLifecycleNotificationsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('identity.use_accounts', true);
        $this->createLifecycleSchema();
    }

    public function test_active_to_past_due_sends_past_due_notification(): void
    {
        [$tenant, $subscription] = $this->makeStack('owner-pastdue@example.com', 'active', now()->subDay());
        $accountId = Account::where('email', 'owner-pastdue@example.com')->firstOrFail()->id;

        $result = app(SubscriptionExpiryService::class)->process();

        $this->assertSame(1, $result['active_to_past_due']);
        $this->assertSame('past_due', $subscription->fresh()->status);

        $rows = DB::table('notifications')
            ->where('notifiable_type', Account::class)
            ->where('notifiable_id', $accountId)
            ->where('type', SubscriptionPastDue::class)
            ->get();

        $this->assertCount(1, $rows);
        $data = json_decode($rows->first()->data, true);
        $this->assertSame($subscription->id, $data['subscription_id']);
        $this->assertSame('Payment Past Due', $data['title']);
    }

    public function test_active_to_past_due_does_not_send_expired_notification(): void
    {
        $this->makeStack('owner-noexpired@example.com', 'active', now()->subDay());
        $accountId = Account::where('email', 'owner-noexpired@example.com')->firstOrFail()->id;

        app(SubscriptionExpiryService::class)->process();

        $this->assertSame(0, DB::table('notifications')
            ->where('notifiable_type', Account::class)
            ->where('notifiable_id', $accountId)
            ->where('type', SubscriptionExpired::class)
            ->count());
    }

    public function test_past_due_to_expired_sends_expired_notification(): void
    {
        [$tenant, $subscription] = $this->makeStack('owner-expired@example.com', 'past_due', now()->subDays(8));
        $accountId = Account::where('email', 'owner-expired@example.com')->firstOrFail()->id;

        $result = app(SubscriptionExpiryService::class)->process();

        $this->assertSame(1, $result['past_due_to_expired']);
        $this->assertSame('expired', $subscription->fresh()->status);
        $this->assertNotNull($tenant->fresh()->locked_at);

        $rows = DB::table('notifications')
            ->where('notifiable_type', Account::class)
            ->where('notifiable_id', $accountId)
            ->where('type', SubscriptionExpired::class)
            ->get();

        $this->assertCount(1, $rows);
        $data = json_decode($rows->first()->data, true);
        $this->assertSame($subscription->id, $data['subscription_id']);
        $this->assertSame('Subscription Expired', $data['title']);
    }

    public function test_lifecycle_notifications_respect_tenant_isolation(): void
    {
        [$tenantA] = $this->makeStack('owner-iso-a@example.com', 'active', now()->subDay());
        $this->makeStack('owner-iso-b@example.com', 'active', now()->addMonth());

        app(SubscriptionExpiryService::class)->process();

        $outsiderId = Account::where('email', 'owner-iso-b@example.com')->firstOrFail()->id;

        $this->assertSame(0, DB::table('notifications')
            ->where('notifiable_type', Account::class)
            ->where('notifiable_id', $outsiderId)
            ->whereIn('type', [SubscriptionPastDue::class, SubscriptionExpired::class])
            ->count());
        $this->assertSame('past_due', $tenantA->subscription()->first()->status);
    }

    private function makeStack(string $ownerEmail, string $status, $expiresAt): array
    {
        $tenant = Tenant::create([
            'slug' => 'lifecycle-' . uniqid(),
            'name' => 'Lifecycle Shop',
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
            'expires_at' => $expiresAt,
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

    private function createLifecycleSchema(): void
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
    }
}
