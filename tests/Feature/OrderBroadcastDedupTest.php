<?php

namespace Tests\Feature;

use App\Events\PaymentRejected;
use App\Events\PaymentVerified;
use App\Jobs\ProcessOrderStatusChange;
use App\Models\Account;
use App\Models\Order;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PaymentRejectedNotification;
use App\Notifications\PaymentVerifiedNotification;
use App\Services\NotificationPreferenceService;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class OrderBroadcastDedupTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('identity.use_accounts', true);
        $this->createDedupSchema();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_verify_payment_broadcasts_exactly_once(): void
    {
        Event::fake([PaymentVerified::class]);
        [$tenant, $admin, $order] = $this->makeStack('dedup-verify@example.com');

        $response = $this->withoutMiddleware(VerifyCsrfToken::class)
            ->actingAs($admin)
            ->post("/admin/orders/{$order->id}/verify-payment");

        $response->assertRedirect();
        Event::assertDispatched(PaymentVerified::class, 1);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame(1, DB::table('notifications')
            ->where('type', PaymentVerifiedNotification::class)
            ->where('notifiable_id', $order->user_id)
            ->count());
    }

    public function test_reject_payment_broadcasts_exactly_once(): void
    {
        Event::fake([PaymentRejected::class]);
        [$tenant, $admin, $order] = $this->makeStack('dedup-reject@example.com');

        $response = $this->withoutMiddleware(VerifyCsrfToken::class)
            ->actingAs($admin)
            ->post("/admin/orders/{$order->id}/reject-payment", ['rejection_reason' => 'Blurry proof']);

        $response->assertRedirect();
        Event::assertDispatched(PaymentRejected::class, 1);
        $this->assertSame(1, DB::table('notifications')
            ->where('type', PaymentRejectedNotification::class)
            ->where('notifiable_id', $order->user_id)
            ->count());
    }

    public function test_canonical_job_broadcasts_verified_once(): void
    {
        Event::fake([PaymentVerified::class]);
        [, , $order] = $this->makeStack('dedup-job-v@example.com');

        (new ProcessOrderStatusChange($order->fresh(), 'payment_verified'))
            ->handle(app(NotificationPreferenceService::class));

        Event::assertDispatched(PaymentVerified::class, 1);
    }

    public function test_canonical_job_broadcasts_rejected_once(): void
    {
        Event::fake([PaymentRejected::class]);
        [, , $order] = $this->makeStack('dedup-job-r@example.com');

        (new ProcessOrderStatusChange($order->fresh(), 'payment_rejected', rejectionReason: 'Blurry'))
            ->handle(app(NotificationPreferenceService::class));

        Event::assertDispatched(PaymentRejected::class, 1);
    }

    private function makeStack(string $customerEmail): array
    {
        $tenant = Tenant::create([
            'slug' => 'dedup-' . uniqid(),
            'name' => 'Dedup Shop',
            'status' => 'active',
        ]);

        $admin = new User(['name' => 'Admin', 'email' => 'admin-' . $customerEmail, 'password' => 'secret']);
        $admin->tenant_id = $tenant->id;
        $admin->save();

        $role = Role::withoutTenantScope()->firstOrCreate([
            'name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id,
        ]);
        Permission::firstOrCreate(['name' => 'orders.update-status', 'guard_name' => 'web']);
        $role->givePermissionTo('orders.update-status');
        $admin->assignRole($role);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

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
            'order_status' => 'pending',
            'payment_status' => 'pending',
        ]);
        $order->user_id = $customer->id;
        $order->user_type = Account::class;
        $order->tenant_id = $tenant->id;
        $order->invoice_number = 'INV-DEDUP-' . strtoupper(uniqid());
        $order->save();

        return [$tenant, $admin, $order, $customer];
    }

    private function createDedupSchema(): void
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
        }, 'permissions' => function ($table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        }, 'role_has_permissions' => function ($table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
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
            $table->timestamp('payment_verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
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
