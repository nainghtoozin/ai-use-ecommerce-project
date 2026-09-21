<?php

namespace Tests\Feature;

use App\Events\BillingPaymentApproved;
use App\Events\OrderPlaced;
use App\Auth\IdentityResolver;
use App\Jobs\ProcessOrderStatusChange;
use App\Models\Account;
use App\Models\Order;
use App\Models\PaymentIntent;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BroadcastDeliveryTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('identity.use_accounts', true);
        $this->createBroadcastSchema();
    }

    public function test_broadcast_auth_route_accepts_both_guards(): void
    {
        $route = collect(\Illuminate\Support\Facades\Route::getRoutes())
            ->first(fn ($r) => $r->uri() === 'broadcasting/auth');

        $this->assertNotNull($route);
        $this->assertContains('auth:web,accounts', $route->gatherMiddleware());
    }

    public function test_private_channel_auth_allows_self(): void
    {
        $this->usePusherDriver();
        $user = $this->makeUser('self-auth@example.com');

        $response = $this->withoutMiddleware(VerifyCsrfToken::class)
            ->actingAs($user)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-notifications.user.' . $user->id,
            ]);

        $response->assertOk();
        $response->assertJsonStructure(['auth']);
    }

    public function test_private_channel_auth_denies_other_user(): void
    {
        $this->usePusherDriver();
        $userA = $this->makeUser('auth-a@example.com');
        $userB = $this->makeUser('auth-b@example.com');

        $response = $this->withoutMiddleware(VerifyCsrfToken::class)
            ->actingAs($userA)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-notifications.user.' . $userB->id,
            ]);

        $response->assertForbidden();
    }

    public function test_private_channel_auth_allows_account_guard(): void
    {
        $this->usePusherDriver();
        $account = Account::create([
            'name' => 'Merchant',
            'email' => 'auth-acct@example.com',
            'password' => 'secret',
            'status' => 'active',
        ]);

        $this->actingAs($account, 'accounts');
        Auth::guard('web')->logout();

        $response = $this->withoutMiddleware(VerifyCsrfToken::class)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-notifications.account.' . $account->id,
            ]);

        $response->assertOk();
        $response->assertJsonStructure(['auth']);
    }

    private function usePusherDriver(): void
    {
        config()->set('broadcasting.default', 'pusher');
        config()->set('broadcasting.connections.pusher.key', 'test-key');
        config()->set('broadcasting.connections.pusher.secret', 'test-secret');
        config()->set('broadcasting.connections.pusher.app_id', '12345');
        config()->set('broadcasting.connections.pusher.options.cluster', 'ap1');

        app()->forgetInstance('Illuminate\Broadcasting\BroadcastManager');
        require base_path('routes/channels.php');
    }

    public function test_user_and_account_channels_do_not_collide(): void
    {
        $userChannel = IdentityResolver::notificationChannel(5, User::class);
        $accountChannel = IdentityResolver::notificationChannel(5, Account::class);

        $this->assertSame('notifications.user.5', $userChannel);
        $this->assertSame('notifications.account.5', $accountChannel);
        $this->assertNotSame($userChannel, $accountChannel);

        $user = $this->makeUser('collide-user@example.com');
        $this->assertSame(
            'notifications.user.' . $user->id,
            IdentityResolver::notificationChannelFor($user)
        );
    }

    public function test_user_cannot_auth_account_channel_with_same_id(): void
    {
        $this->usePusherDriver();
        $user = $this->makeUser('collide-a@example.com');

        $account = new Account([
            'name' => 'Same Id',
            'email' => 'collide-acct@example.com',
            'password' => 'secret',
            'status' => 'active',
        ]);
        $account->id = $user->id;
        $account->save();

        $this->withoutMiddleware(VerifyCsrfToken::class)
            ->actingAs($user)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-notifications.account.' . $account->id,
            ])
            ->assertForbidden();

        $this->actingAs($account, 'accounts');
        Auth::guard('web')->logout();

        $this->withoutMiddleware(VerifyCsrfToken::class)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-notifications.user.' . $user->id,
            ])
            ->assertForbidden();

        $this->withoutMiddleware(VerifyCsrfToken::class)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-notifications.account.' . $account->id,
            ])
            ->assertOk();
    }

    public function test_broadcast_event_contract_matches_frontend(): void
    {
        [$tenant, $order, $owner] = $this->makeOrderStack('contract@example.com');
        [, , $plan] = $this->makeSubscriptionStack('contract-plan@example.com');
        $intent = $this->makeIntent($tenant, $plan);

        $placed = new OrderPlaced($order);
        $channels = collect($placed->broadcastOn())->map(fn ($c) => $c->name)->all();

        $this->assertContains('private-notifications.account.' . $order->user_id, $channels);
        $this->assertContains('private-notifications.account.' . $owner->id, $channels);
        $this->assertSame('order.placed', $placed->broadcastAs());
        $this->assertArrayHasKey('title', $placed->broadcastWith());
        $this->assertArrayHasKey('message', $placed->broadcastWith());
        $this->assertSame($order->id, $placed->broadcastWith()['id']);

        $approved = new BillingPaymentApproved($intent);

        $this->assertSame('billing.payment_approved', $approved->broadcastAs());
        $this->assertArrayHasKey('title', $approved->broadcastWith());
        $this->assertArrayHasKey('message', $approved->broadcastWith());
        $this->assertArrayHasKey('action_url', $approved->broadcastWith());
        $this->assertContains(
            'private-notifications.account.' . $owner->id,
            collect($approved->broadcastOn())->map(fn ($c) => $c->name)->all()
        );
    }

    public function test_queued_job_processes_end_to_end(): void
    {
        config()->set('queue.default', 'database');
        [, $order, , $customer] = $this->makeOrderStack('e2e-cust@example.com', 'processing');

        ProcessOrderStatusChange::dispatch($order, 'shipped', 'processing');

        $this->assertSame(1, DB::table('jobs')->count());

        Artisan::call('queue:work', ['--once' => true, '--queue' => 'default']);

        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertSame(1, DB::table('notifications')
            ->where('notifiable_type', Account::class)
            ->where('notifiable_id', $customer->id)
            ->where('type', \App\Notifications\OrderShippedNotification::class)
            ->count());
    }

    private function makeUser(string $email): User
    {
        $user = new User([
            'name' => 'Test User',
            'email' => $email,
            'password' => 'secret',
        ]);
        $user->save();

        return $user;
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

    private function makeOrderStack(string $customerEmail, string $orderStatus = 'pending'): array
    {
        $tenant = Tenant::create([
            'slug' => 'bcast-' . uniqid(),
            'name' => 'Broadcast Shop',
            'status' => 'active',
        ]);

        $customer = Account::create([
            'name' => 'Customer',
            'email' => $customerEmail,
            'password' => 'secret',
            'status' => 'active',
        ]);

        $owner = $this->makeOwner($tenant->id, 'owner-' . $customerEmail);

        $order = new Order([
            'customer_name' => 'Customer',
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'phone' => '09123456789',
            'address' => 'Test Street 1',
            'subtotal' => 50000,
            'total_amount' => 50000,
            'order_status' => $orderStatus,
            'payment_status' => 'paid',
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

        return [$tenant, $order, $owner, $customer];
    }

    private function makeSubscriptionStack(string $ownerEmail): array
    {
        $tenant = Tenant::create([
            'slug' => 'bcast-sub-' . uniqid(),
            'name' => 'Broadcast Sub Shop',
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

    private function createBroadcastSchema(): void
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
        }, 'users' => function ($table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
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
