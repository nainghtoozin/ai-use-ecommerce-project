<?php

namespace Tests\Feature;

use App\Jobs\ProcessOrderStatusChange;
use App\Jobs\SendTelegramMessageJob;
use App\Models\Account;
use App\Models\Order;
use App\Models\Role;
use App\Models\TelegramIntegration;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Notifications\PaymentConfirmedNotification;
use App\Services\NotificationPreferenceService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderStatusNotificationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('identity.use_accounts', true);
        $this->createOrderNotificationSchema();
    }

    public function test_generic_status_update_dispatches_canonical_job(): void
    {
        Queue::fake();
        [$tenant, $order] = $this->makeOrder('cust-svc@example.com', 'pending', 'paid');

        $result = app(OrderService::class)->updateOrderStatus($order, Order::ORDER_STATUS_CONFIRMED);

        $this->assertSame(Order::ORDER_STATUS_CONFIRMED, $result->order_status);

        Queue::assertPushed(ProcessOrderStatusChange::class, 1);
        Queue::assertPushed(ProcessOrderStatusChange::class, function ($job) use ($order, $tenant) {
            return $job->order->id === $order->id
                && $job->order->tenant_id === $tenant->id
                && $job->event === 'confirmed';
        });
    }

    public function test_canonical_flow_notifies_customer_exactly_once(): void
    {
        Queue::fake();
        [$tenant, $order] = $this->makeOrder('cust-once@example.com', 'pending', 'paid');
        $customerId = $order->user_id;
        $this->makeIntegration($tenant->id);

        (new ProcessOrderStatusChange($order->fresh(), 'confirmed', 'pending'))
            ->handle(app(NotificationPreferenceService::class));

        $rows = DB::table('notifications')
            ->where('notifiable_type', Account::class)
            ->where('notifiable_id', $customerId)
            ->where('type', PaymentConfirmedNotification::class)
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame($tenant->id, (int) $rows->first()->tenant_id);

        Queue::assertPushed(SendTelegramMessageJob::class, 1);
    }

    public function test_same_status_update_dispatches_nothing(): void
    {
        Queue::fake();
        [, $order] = $this->makeOrder('cust-same@example.com', 'confirmed', 'paid');

        app(OrderService::class)->updateOrderStatus($order, Order::ORDER_STATUS_CONFIRMED);

        Queue::assertNotPushed(ProcessOrderStatusChange::class);
    }

    public function test_recipient_isolation(): void
    {
        Queue::fake();
        [$tenantA, $orderA] = $this->makeOrder('cust-iso-a@example.com', 'pending', 'paid');
        $this->makeOrder('cust-iso-b@example.com', 'pending', 'paid');

        app(OrderService::class)->updateOrderStatus($orderA, Order::ORDER_STATUS_CONFIRMED);

        (new ProcessOrderStatusChange($orderA->fresh(), 'confirmed', 'pending'))
            ->handle(app(NotificationPreferenceService::class));

        $outsiderId = Account::where('email', 'cust-iso-b@example.com')->firstOrFail()->id;

        $this->assertSame(0, DB::table('notifications')
            ->where('notifiable_type', Account::class)
            ->where('notifiable_id', $outsiderId)
            ->count());
        $this->assertSame(1, DB::table('notifications')
            ->where('notifiable_type', Account::class)
            ->where('type', PaymentConfirmedNotification::class)
            ->where('tenant_id', $tenantA->id)
            ->count());
    }

    private function makeOrder(string $customerEmail, string $orderStatus, string $paymentStatus): array
    {
        $tenant = Tenant::create([
            'slug' => 'ord-' . uniqid(),
            'name' => 'Order Shop',
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

    private function createOrderNotificationSchema(): void
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
        }, 'accounts' => function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        }, 'roles' => function ($table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();
            $table->unique(['name', 'guard_name', 'tenant_id']);
        }, 'tenant_memberships' => function ($table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('role_id')->nullable();
            $table->boolean('is_owner')->default(false);
            $table->string('status')->default('active');
            $table->timestamps();
            $table->index(['tenant_id', 'account_id']);
        }, 'orders' => function ($table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('user_type')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('invoice_number')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('phone')->nullable();
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
