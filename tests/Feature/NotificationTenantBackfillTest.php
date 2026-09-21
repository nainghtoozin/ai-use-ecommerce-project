<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationTenantBackfillTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createBackfillSchema();
    }

    public function test_data_references_resolve_tenant(): void
    {
        [$tenantA, $subA] = $this->makeSubscription('bf-sub-a@example.com');
        [$tenantB] = $this->makeSubscription('bf-sub-b@example.com');
        $order = $this->makeOrder($tenantB->id);
        $product = $this->makeProduct($tenantA->id);

        $subRow = $this->makeNote('App\\Notifications\\SubscriptionExpired', Account::class, 9001, [
            'subscription_id' => $subA->id,
        ]);
        $orderRow = $this->makeNote('App\\Notifications\\NewOrderAdminNotification', Account::class, 9002, [
            'order_id' => $order->id,
        ]);
        $productRow = $this->makeNote('App\\Notifications\\LowStockNotification', Account::class, 9003, [
            'product_id' => $product->id,
        ]);
        $inviteRow = $this->makeNote('App\\Notifications\\TeamInvitationNotification', Account::class, 9004, [
            'tenant_id' => $tenantB->id,
        ]);

        $this->artisan('notifications:backfill-tenant')->assertSuccessful();

        $this->assertSame($tenantA->id, (int) DB::table('notifications')->where('id', $subRow)->value('tenant_id'));
        $this->assertSame($tenantB->id, (int) DB::table('notifications')->where('id', $orderRow)->value('tenant_id'));
        $this->assertSame($tenantA->id, (int) DB::table('notifications')->where('id', $productRow)->value('tenant_id'));
        $this->assertSame($tenantB->id, (int) DB::table('notifications')->where('id', $inviteRow)->value('tenant_id'));
    }

    public function test_legacy_user_notifiable_resolves(): void
    {
        $tenant = Tenant::create(['slug' => 'bf-user-' . uniqid(), 'name' => 'BF User', 'status' => 'active']);
        $user = new User(['name' => 'Legacy', 'email' => 'bf-legacy@example.com', 'password' => 'secret']);
        $user->tenant_id = $tenant->id;
        $user->save();

        $row = $this->makeNote('App\\Notifications\\OrderPlacedClientNotification', User::class, $user->id, []);

        $this->artisan('notifications:backfill-tenant')->assertSuccessful();

        $this->assertSame($tenant->id, (int) DB::table('notifications')->where('id', $row)->value('tenant_id'));
    }

    public function test_single_membership_account_resolves(): void
    {
        [$tenant] = $this->makeSubscription('bf-single@example.com');
        $accountId = Account::where('email', 'bf-single@example.com')->firstOrFail()->id;

        $row = $this->makeNote('App\\Notifications\\BillingPaymentApprovedMerchantNotification', Account::class, $accountId, [
            'title' => 'Payment Approved',
        ]);

        $this->artisan('notifications:backfill-tenant')->assertSuccessful();

        $this->assertSame($tenant->id, (int) DB::table('notifications')->where('id', $row)->value('tenant_id'));
    }

    public function test_ambiguous_and_global_rows_stay_null(): void
    {
        [$tenantA] = $this->makeSubscription('bf-multi-a@example.com');
        [$tenantB] = $this->makeSubscription('bf-multi-b@example.com');
        $account = Account::create(['name' => 'Multi', 'email' => 'bf-multi@example.com', 'password' => 'secret', 'status' => 'active']);
        foreach ([$tenantA->id, $tenantB->id] as $tenantId) {
            $role = Role::withoutTenantScope()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenantId]);
            TenantMembership::create(['account_id' => $account->id, 'tenant_id' => $tenantId, 'role_id' => $role->id, 'is_owner' => false, 'status' => 'active']);
        }

        $multiRow = $this->makeNote('App\\Notifications\\BillingPaymentApprovedMerchantNotification', Account::class, $account->id, []);
        $danglingRow = $this->makeNote('App\\Notifications\\SubscriptionExpired', Account::class, $account->id, ['subscription_id' => 999999999]);

        $superadmin = Account::create(['name' => 'Super', 'email' => 'bf-super@example.com', 'password' => 'secret', 'status' => 'active']);
        $globalRow = $this->makeNote('App\\Notifications\\BillingPaymentSubmittedAdminNotification', Account::class, $superadmin->id, [
            'title' => 'New Payment Submitted',
        ]);

        $this->artisan('notifications:backfill-tenant')->assertSuccessful();

        $this->assertNull(DB::table('notifications')->where('id', $multiRow)->value('tenant_id'));
        $this->assertNull(DB::table('notifications')->where('id', $danglingRow)->value('tenant_id'));
        $this->assertNull(DB::table('notifications')->where('id', $globalRow)->value('tenant_id'));
    }

    public function test_backfill_is_idempotent_and_dry_run_safe(): void
    {
        [$tenant] = $this->makeSubscription('bf-idem@example.com');
        $accountId = Account::where('email', 'bf-idem@example.com')->firstOrFail()->id;
        $row = $this->makeNote('App\\Notifications\\SubscriptionRenewed', Account::class, $accountId, []);

        $this->artisan('notifications:backfill-tenant', ['--dry-run' => true])->assertSuccessful();
        $this->assertNull(DB::table('notifications')->where('id', $row)->value('tenant_id'));

        $this->artisan('notifications:backfill-tenant')->assertSuccessful();
        $this->assertSame($tenant->id, (int) DB::table('notifications')->where('id', $row)->value('tenant_id'));

        $this->artisan('notifications:backfill-tenant')->assertSuccessful();
        $this->assertSame($tenant->id, (int) DB::table('notifications')->where('id', $row)->value('tenant_id'));
    }

    private function makeNote(string $type, string $notifiableType, int $notifiableId, array $data): string
    {
        $id = (string) Str::uuid();

        DB::table('notifications')->insert([
            'id' => $id,
            'type' => $type,
            'notifiable_type' => $notifiableType,
            'notifiable_id' => $notifiableId,
            'data' => json_encode($data),
            'read_at' => null,
            'created_at' => now()->subDays(100)->toDateTimeString(),
            'updated_at' => now()->subDays(100)->toDateTimeString(),
            'tenant_id' => null,
        ]);

        return $id;
    }

    private function makeSubscription(string $ownerEmail): array
    {
        $tenant = Tenant::create(['slug' => 'bf-' . uniqid(), 'name' => 'BF Shop', 'status' => 'active']);
        $plan = Plan::create(['name' => 'Numbered', 'slug' => 'numbered-' . uniqid(), 'description' => 'Test plan', 'monthly_price' => 100, 'yearly_price' => 1000, 'status' => 'active']);
        $subscription = new Subscription(['plan_id' => $plan->id, 'billing_interval' => 'monthly', 'status' => 'active', 'starts_at' => now()->subMonth(), 'expires_at' => now()->addMonth(), 'trial_renewals_count' => 0]);
        $subscription->tenant_id = $tenant->id;
        $subscription->save();

        $account = Account::create(['name' => 'Owner', 'email' => $ownerEmail, 'password' => 'secret', 'status' => 'active']);
        $role = Role::withoutTenantScope()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        TenantMembership::create(['account_id' => $account->id, 'tenant_id' => $tenant->id, 'role_id' => $role->id, 'is_owner' => true, 'status' => 'active']);

        return [$tenant, $subscription, $plan];
    }

    private function makeOrder(int $tenantId): Order
    {
        $user = new User(['name' => 'Buyer', 'email' => 'bf-buyer-' . uniqid() . '@example.com', 'password' => 'secret']);
        $user->tenant_id = $tenantId;
        $user->save();

        $order = new Order(['customer_name' => 'Buyer', 'first_name' => 'B', 'last_name' => 'U', 'phone' => '09', 'address' => 'X', 'subtotal' => 10, 'total_amount' => 10, 'order_status' => 'pending', 'payment_status' => 'unpaid']);
        $order->user_id = $user->id;
        $order->user_type = User::class;
        $order->tenant_id = $tenantId;
        $order->invoice_number = 'INV-BF-' . strtoupper(uniqid());
        $order->save();

        return $order;
    }

    private function makeProduct(int $tenantId): object
    {
        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'BF Category',
            'tenant_id' => $tenantId,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);
        $id = DB::table('products')->insertGetId([
            'name' => 'BF Widget',
            'price' => 100,
            'stock' => 1,
            'category_id' => $categoryId,
            'tenant_id' => $tenantId,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        return (object) ['id' => $id, 'tenant_id' => $tenantId];
    }

    private function createBackfillSchema(): void
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
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('tenant_id');
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
        }, 'categories' => function ($table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('name');
            $table->timestamps();
        }, 'products' => function ($table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->integer('stock')->default(0);
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
