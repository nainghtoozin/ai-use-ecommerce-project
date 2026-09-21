<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Order;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Notifications\NewOrderAdminNotification;
use App\Notifications\OrderPlacedClientNotification;
use App\Services\OrderNotificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderCustomerIdentityTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('identity.use_accounts', true);
        $this->createIdentitySchema();
    }

    public function test_user_order_persists_and_notifies_customer(): void
    {
        [$tenant, $customer] = $this->makeTenantWithCustomer('id-user@example.com', false);

        $order = $this->makeOrder($tenant->id, $customer->id, User::class);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'user_id' => $customer->id]);
        $this->assertInstanceOf(User::class, $order->fresh()->user);
        $this->assertSame(User::class, $order->fresh()->user_type);

        app(OrderNotificationService::class)->notifyOrderPlaced($order->fresh());

        $this->assertSame(1, DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $customer->id)
            ->where('type', OrderPlacedClientNotification::class)
            ->count());
    }

    public function test_account_order_persists_despite_relaxed_fk(): void
    {
        [$tenant, $customer] = $this->makeTenantWithCustomer('id-acct@example.com', true);

        $order = $this->makeOrder($tenant->id, $customer->id, Account::class);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'user_id' => $customer->id]);
        $this->assertInstanceOf(Account::class, $order->fresh()->user);
        $this->assertSame(Account::class, $order->fresh()->user_type);
    }

    public function test_account_order_notifies_customer_and_admins(): void
    {
        [$tenant, $customer] = $this->makeTenantWithCustomer('id-notify@example.com', true);
        $this->makeOwner($tenant->id, 'id-owner@example.com');

        $order = $this->makeOrder($tenant->id, $customer->id, Account::class);

        app(OrderNotificationService::class)->notifyOrderPlaced($order->fresh());

        $this->assertSame(1, DB::table('notifications')
            ->where('notifiable_type', Account::class)
            ->where('notifiable_id', $customer->id)
            ->where('type', OrderPlacedClientNotification::class)
            ->count());
        $this->assertSame(1, DB::table('notifications')
            ->where('notifiable_type', Account::class)
            ->where('type', NewOrderAdminNotification::class)
            ->where('tenant_id', $tenant->id)
            ->count());
    }

    public function test_user_fk_is_removed_from_orders(): void
    {
        $exists = DB::table('information_schema.table_constraints')
            ->where('constraint_schema', DB::getDatabaseName())
            ->where('table_name', 'orders')
            ->where('constraint_name', 'orders_user_id_foreign')
            ->exists();

        $this->assertFalse($exists);
    }

    private function makeTenantWithCustomer(string $email, bool $asAccount): array
    {
        $tenant = Tenant::create([
            'slug' => 'idc-' . uniqid(),
            'name' => 'Identity Shop',
            'status' => 'active',
        ]);

        if ($asAccount) {
            $customer = Account::create([
                'name' => 'Customer',
                'email' => $email,
                'password' => 'secret',
                'status' => 'active',
            ]);
        } else {
            $customer = new User(['name' => 'Customer', 'email' => $email, 'password' => 'secret']);
            $customer->tenant_id = $tenant->id;
            $customer->save();
        }

        return [$tenant, $customer];
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

    private function makeOrder(int $tenantId, int $customerId, string $customerType): Order
    {
        $order = new Order([
            'customer_name' => 'Customer',
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'phone' => '09123456789',
            'address' => 'Test Street 1',
            'subtotal' => 50000,
            'total_amount' => 50000,
            'order_status' => 'pending',
            'payment_status' => 'pending',
        ]);
        $order->user_id = $customerId;
        $order->user_type = $customerType;
        $order->tenant_id = $tenantId;
        $order->invoice_number = 'INV-IDC-' . strtoupper(uniqid());
        $order->save();

        return $order;
    }

    private function createIdentitySchema(): void
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
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->string('order_status')->default('pending');
            $table->string('payment_status')->nullable();
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
