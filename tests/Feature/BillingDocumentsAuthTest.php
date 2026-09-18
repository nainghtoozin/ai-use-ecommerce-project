<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\PaymentIntent;
use App\Models\Plan;
use App\Models\Receipt;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class BillingDocumentsAuthTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;
    private Tenant $otherTenant;
    private Invoice $invoice;
    private Receipt $receipt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createDocumentsSchema();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'billing.view', 'guard_name' => 'web']);
        $role = \App\Models\Role::withoutTenantScope()->firstOrCreate([
            'name' => 'admin', 'guard_name' => 'web', 'tenant_id' => null,
        ]);
        $role->givePermissionTo('billing.view');

        $plan = Plan::create([
            'name' => 'Numbered', 'slug' => 'numbered-' . uniqid(),
            'description' => 'Test plan', 'monthly_price' => 100,
            'yearly_price' => 1000, 'status' => 'active',
        ]);

        $this->tenant = Tenant::create([
            'slug' => 'docs-' . uniqid(), 'name' => 'Docs Shop', 'status' => 'active',
        ]);
        $subscription = new Subscription([
            'plan_id' => $plan->id, 'billing_interval' => 'monthly', 'status' => 'active',
            'starts_at' => now()->subMonth(), 'expires_at' => now()->addMonth(),
            'trial_renewals_count' => 0,
        ]);
        $subscription->tenant_id = $this->tenant->id;
        $subscription->save();

        $intent = new PaymentIntent([
            'plan_id' => $plan->id, 'subscription_id' => $subscription->id,
            'billing_cycle' => 'monthly', 'amount' => 100, 'currency' => 'MMK',
            'gateway' => 'manual', 'status' => 'completed',
            'reference_number' => 'PAY-' . strtoupper(uniqid()),
            'idempotency_key' => uniqid('idem-', true), 'metadata' => [],
        ]);
        $intent->tenant_id = $this->tenant->id;
        $intent->save();

        $this->invoice = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'invoice_number' => 'INV-2026-00999',
            'subscription_id' => $subscription->id,
            'plan_id' => $plan->id,
            'billing_interval' => 'monthly',
            'billing_period_start' => now()->subMonth()->toDateString(),
            'billing_period_end' => now()->addMonth()->toDateString(),
            'amount' => 100, 'subtotal' => 100, 'tax' => 0, 'total' => 100,
            'currency' => 'MMK', 'status' => Invoice::STATUS_PAID,
            'payment_intent_id' => $intent->id,
            'issued_at' => now(), 'paid_at' => now(),
            'line_items' => [['description' => 'Numbered (Monthly)', 'quantity' => 1, 'unit_price' => 100, 'amount' => 100]],
        ]);

        $this->receipt = Receipt::create([
            'tenant_id' => $this->tenant->id,
            'invoice_id' => $this->invoice->id,
            'payment_intent_id' => $intent->id,
            'receipt_number' => 'REC-2026-00999',
            'amount' => 100, 'currency' => 'MMK',
            'paid_at' => now(), 'details' => ['plan_name' => 'Numbered'],
        ]);

        $this->otherTenant = Tenant::create([
            'slug' => 'docs-other-' . uniqid(), 'name' => 'Other Shop', 'status' => 'active',
        ]);
    }

    public function test_guest_is_redirected_from_invoice_view(): void
    {
        $response = $this->get("/store/{$this->tenant->slug}/admin/billing/documents/invoices/{$this->invoice->id}");

        $response->assertRedirect();
        $this->assertNotSame(200, $response->getStatusCode());
    }

    public function test_owner_can_view_own_invoice(): void
    {
        $this->actingAs($this->makeOwner($this->tenant));

        $response = $this->get("/store/{$this->tenant->slug}/admin/billing/documents/invoices/{$this->invoice->id}");

        $response->assertStatus(200);
        $response->assertSee($this->invoice->invoice_number, false);
    }

    public function test_owner_cannot_view_other_tenants_invoice(): void
    {
        $this->actingAs($this->makeOwner($this->otherTenant));

        $response = $this->get("/store/{$this->tenant->slug}/admin/billing/documents/invoices/{$this->invoice->id}");

        // Blocked by the storefront middleware cross-tenant guard (403).
        $response->assertStatus(403);
    }

    public function test_owner_can_view_own_receipt(): void
    {
        $this->actingAs($this->makeOwner($this->tenant));

        $response = $this->get("/store/{$this->tenant->slug}/admin/billing/documents/receipts/{$this->receipt->id}");

        $response->assertStatus(200);
        $response->assertSee($this->receipt->receipt_number, false);
    }

    public function test_owner_cannot_view_other_tenants_receipt(): void
    {
        $this->actingAs($this->makeOwner($this->otherTenant));

        $response = $this->get("/store/{$this->tenant->slug}/admin/billing/documents/receipts/{$this->receipt->id}");

        // Blocked by the storefront middleware cross-tenant guard (403).
        $response->assertStatus(403);
    }

    public function test_user_without_billing_permission_is_forbidden(): void
    {
        $adminRole = \App\Models\Role::withoutTenantScope()->firstOrCreate([
            'name' => 'admin', 'guard_name' => 'web', 'tenant_id' => null,
        ]);
        $adminRole->givePermissionTo('billing.view');

        $owner = $this->makeOwner($this->tenant);
        $this->assertTrue($owner->can('billing.view'));

        $staffRole = \App\Models\Role::withoutTenantScope()->firstOrCreate([
            'name' => 'staff-no-billing-' . uniqid(), 'guard_name' => 'web', 'tenant_id' => null,
        ]);
        $user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Staff', 'email' => 'staff-' . uniqid() . '@test.com',
            'password' => bcrypt('password'), 'status' => 'active',
        ]);
        $user->assignRole($staffRole);

        $this->assertFalse($user->can('billing.view'));
    }

    private function makeOwner(Tenant $tenant): User
    {
        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Owner', 'email' => 'owner-' . uniqid() . '@test.com',
            'password' => bcrypt('password'), 'status' => 'active', 'is_owner' => true,
        ]);
        $user->assignRole('admin');
        $user->givePermissionTo('billing.view');

        return $user;
    }

    private function createDocumentsSchema(): void
    {
        if (!Schema::hasTable('permissions')) {
            Schema::create('permissions', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('guard_name');
                $table->timestamps();
                $table->unique(['name', 'guard_name']);
            });
        }

        if (!Schema::hasTable('roles')) {
            Schema::create('roles', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('guard_name');
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->timestamps();
                $table->unique(['name', 'guard_name', 'tenant_id']);
            });
        }

        if (!Schema::hasTable('model_has_roles')) {
            Schema::create('model_has_roles', function ($table) {
                $table->unsignedBigInteger('role_id');
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
                $table->index(['model_id', 'model_type']);
            });
        }

        if (!Schema::hasTable('role_has_permissions')) {
            Schema::create('role_has_permissions', function ($table) {
                $table->unsignedBigInteger('permission_id');
                $table->unsignedBigInteger('role_id');
                $table->primary(['permission_id', 'role_id']);
            });
        }

        if (!Schema::hasTable('model_has_permissions')) {
            Schema::create('model_has_permissions', function ($table) {
                $table->unsignedBigInteger('permission_id');
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
                $table->index(['model_id', 'model_type']);
                $table->primary(['permission_id', 'model_id', 'model_type']);
            });
        }

        if (!Schema::hasTable('users')) {
            Schema::create('users', function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->string('status')->default('active');
                $table->boolean('is_owner')->default(false);
                $table->boolean('is_admin')->default(false);
                $table->rememberToken();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('tenants')) {
            Schema::create('tenants', function ($table) {
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
            });
        }

        if (!Schema::hasTable('plans')) {
            Schema::create('plans', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->decimal('monthly_price', 10, 2)->nullable();
                $table->decimal('yearly_price', 10, 2)->nullable();
                $table->string('status', 20)->default('active');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('subscriptions')) {
            Schema::create('subscriptions', function ($table) {
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
            });
        } elseif (!Schema::hasColumn('subscriptions', 'extra_renewal_used_at')) {
            Schema::table('subscriptions', function ($table) {
                $table->timestamp('extra_renewal_used_at')->nullable();
            });
        }

        if (!Schema::hasTable('payment_intents')) {
            Schema::create('payment_intents', function ($table) {
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
            });
        }

        if (!Schema::hasTable('invoices')) {
            Schema::create('invoices', function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('invoice_number')->unique();
                $table->unsignedBigInteger('subscription_id')->nullable();
                $table->unsignedBigInteger('plan_id')->nullable();
                $table->string('billing_interval')->nullable();
                $table->date('billing_period_start')->nullable();
                $table->date('billing_period_end')->nullable();
                $table->decimal('amount', 10, 2)->default(0);
                $table->decimal('subtotal', 10, 2)->default(0);
                $table->decimal('tax', 10, 2)->default(0);
                $table->decimal('total', 10, 2)->default(0);
                $table->string('currency', 3)->default('MMK');
                $table->string('status')->default('unpaid');
                $table->unsignedBigInteger('payment_intent_id')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('issued_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->json('line_items')->nullable();
                $table->timestamp('deleted_at')->nullable();
                $table->timestamps();
                $table->index(['tenant_id', 'status']);
            });
        }

        if (!Schema::hasTable('receipts')) {
            Schema::create('receipts', function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('invoice_id')->nullable();
                $table->unsignedBigInteger('payment_intent_id')->nullable();
                $table->string('receipt_number')->unique();
                $table->decimal('amount', 10, 2)->default(0);
                $table->string('currency', 3)->default('MMK');
                $table->timestamp('paid_at')->nullable();
                $table->json('details')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('platform_settings')) {
            Schema::create('platform_settings', function ($table) {
                $table->id();
                $table->string('site_name')->nullable();
                $table->string('support_email')->nullable();
                $table->boolean('trial_enabled')->default(true);
                $table->integer('trial_days')->default(14);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('reference_numbers')) {
            Schema::create('reference_numbers', function ($table) {
                $table->id();
                $table->string('prefix', 10);
                $table->date('date');
                $table->unsignedInteger('last_sequence')->default(0);
                $table->timestamps();
                $table->unique(['prefix', 'date']);
            });
        }
    }
}
