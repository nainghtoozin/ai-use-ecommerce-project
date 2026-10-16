<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\SubscriptionCancellationRequest;
use App\Models\Tenant;
use App\Models\TenantCleanup;
use App\Models\User;
use App\Services\TenantCleanupService;
use App\Services\TenantDataPurgeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TenantRetentionDeletionTest extends TestCase
{
    use DatabaseTransactions;

    private Plan $paidPlan;
    private Tenant $tenant;
    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['billing.view', 'billing.manage', 'billing.renew'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }
        $role = \App\Models\Role::withoutTenantScope()->firstOrCreate([
            'name' => 'admin', 'guard_name' => 'web', 'tenant_id' => null,
        ]);
        $role->syncPermissions(['billing.view', 'billing.manage', 'billing.renew']);

        $this->paidPlan = $this->makePlan('retention', 10, 100);

        $this->tenant = Tenant::create([
            'slug' => 'retention-' . uniqid(),
            'name' => 'Retention Shop',
            'status' => 'active',
        ]);

        $this->subscription = $this->makeSubscription($this->tenant, $this->paidPlan, 'active', now()->addDays(30));

        config(['billing.tenant_cleanup.purge_enabled' => false]);
    }

    protected function tearDown(): void
    {
        try {
            $ids = Tenant::where('slug', 'like', 'retention-%')->pluck('id')->map(fn ($id) => (int) $id)->all();

            if ($ids !== []) {
                foreach (['products', 'categories', 'brands', 'units', 'subscriptions', 'subscription_cancellation_requests', 'tenant_cleanups', 'invoices', 'subscription_audit_logs', 'payment_intents', 'users'] as $table) {
                    try {
                        if (Schema::hasTable($table) && Schema::hasColumn($table, 'tenant_id')) {
                            DB::table($table)->whereIn('tenant_id', $ids)->delete();
                        }
                    } catch (\Throwable $e) {
                    }
                }

                Tenant::whereIn('id', $ids)->delete();

                try {
                    if (Schema::hasTable('plan_features') && Schema::hasTable('plans')) {
                        $planIds = DB::table('plans')->where('slug', 'like', 'retention-%')->pluck('id');
                        if ($planIds->isNotEmpty()) {
                            DB::table('plan_features')->whereIn('plan_id', $planIds)->delete();
                            DB::table('plans')->whereIn('id', $planIds)->delete();
                        }
                    }
                } catch (\Throwable $e) {
                }
            }
        } catch (\Throwable $e) {
        }

        Tenant::setCurrent(null);
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_approved_cancellation_preserves_access_until_expiry(): void
    {
        $owner = $this->makeOwner($this->tenant);
        $this->actingAs($owner);

        $this->from("/store/{$this->tenant->slug}/admin/billing")
            ->post("/store/{$this->tenant->slug}/admin/billing/cancel-request", ['reason' => 'too_expensive'])
            ->assertRedirect();

        $request = SubscriptionCancellationRequest::withoutTenantScope()->first();
        $request->update(['status' => SubscriptionCancellationRequest::STATUS_APPROVED, 'reviewed_at' => now()]);
        $this->subscription->update(['cancelled_at' => now()]);

        $fresh = $this->subscription->fresh();
        $this->assertTrue($fresh->expires_at->isFuture());
        $this->assertSame('active', $fresh->status);

        $this->subscription->update(['status' => 'canceled']);

        $httpRequest = \Illuminate\Http\Request::create('/admin/products', 'GET');
        $httpRequest->setUserResolver(fn () => $owner);

        $middleware = app()->make(\App\Http\Middleware\EnsureTenantIsActive::class);
        $response = $middleware->handle($httpRequest, fn () => response('passed'));

        $this->assertSame('passed', $response->getContent());
    }

    public function test_expiry_restricts_operations_but_retains_data(): void
    {
        $invoice = $this->makeInvoice($this->tenant, $this->subscription);
        $cat = $this->makeCategory($this->tenant);
        DB::table('products')->insert([
            'tenant_id' => $this->tenant->id, 'category_id' => $cat, 'type' => 'single', 'name' => 'Keep Me',
            'sku' => 'KEEP-1', 'price' => 5, 'stock' => 3, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->subscription->update(['status' => 'expired', 'expires_at' => now()->subDays(16)]);
        $result = app(TenantCleanupService::class)->process();

        $this->assertSame(1, $result['restricted']);

        $tenant = $this->tenant->fresh();
        $this->assertSame('expired', $tenant->status);
        $this->assertNotNull($tenant->locked_at);

        $this->assertDatabaseHas('tenants', ['id' => $this->tenant->id]);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
        $this->assertDatabaseHas('products', ['tenant_id' => $this->tenant->id, 'sku' => 'KEEP-1']);
        $this->assertDatabaseHas('subscriptions', ['id' => $this->subscription->id]);
    }

    public function test_retention_deadline_defaults_to_fifteen_days_after_restriction(): void
    {
        $this->subscription->update(['status' => 'expired', 'expires_at' => now()->subDays(16)]);
        app(TenantCleanupService::class)->process();

        $cleanup = TenantCleanup::where('tenant_id', $this->tenant->id)->first();
        $this->assertNotNull($cleanup->retention_ends_at);
        $this->assertSame(
            $cleanup->restricted_at->copy()->addDays(15)->toDateString(),
            $cleanup->retention_ends_at->toDateString()
        );
        $this->assertSame(TenantCleanup::DELETION_NONE, $cleanup->deletion_status);
    }

    public function test_retention_duration_follows_platform_setting(): void
    {
        $this->setPlatformSetting('tenant_data_retention_days', 20);

        $this->assertSame(20, app(TenantCleanupService::class)->retentionDays());

        $this->subscription->update(['status' => 'expired', 'expires_at' => now()->subDays(16)]);
        app(TenantCleanupService::class)->process();

        $cleanup = TenantCleanup::where('tenant_id', $this->tenant->id)->first();
        $this->assertSame(
            $cleanup->restricted_at->copy()->addDays(20)->toDateString(),
            $cleanup->retention_ends_at->toDateString()
        );
    }

    public function test_billing_page_shows_retention_deadline_and_export_access(): void
    {
        $this->actingAs($this->makeOwner($this->tenant));

        $this->subscription->update(['status' => 'expired', 'expires_at' => now()->subDays(16)]);
        app(TenantCleanupService::class)->process();

        $cleanup = TenantCleanup::where('tenant_id', $this->tenant->id)->first();

        $this->get("/store/{$this->tenant->slug}/admin/billing")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('retention.retention_deadline', $cleanup->retention_ends_at->toDateString())
                ->where('retention.export_allowed', true)
                ->where('retention.retention_days', 15)
            );
    }

    public function test_backup_download_allowed_within_retention(): void
    {
        $this->actingAs($this->makeOwner($this->tenant));

        $cat = $this->makeCategory($this->tenant);
        DB::table('products')->insert([
            'tenant_id' => $this->tenant->id, 'category_id' => $cat, 'type' => 'single', 'name' => 'Export Me',
            'sku' => 'EXP-1', 'price' => 9, 'stock' => 1, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->subscription->update(['status' => 'expired', 'expires_at' => now()->subDays(16)]);
        app(TenantCleanupService::class)->process();

        $this->get("/store/{$this->tenant->slug}/admin/billing/backup/products?format=csv")->assertOk();
    }

    public function test_backup_denied_after_retention_deadline(): void
    {
        $this->actingAs($this->makeOwner($this->tenant));

        $this->subscription->update(['status' => 'expired', 'expires_at' => now()->subDays(40)]);
        app(TenantCleanupService::class)->process();
        app(TenantCleanupService::class)->process();

        TenantCleanup::where('tenant_id', $this->tenant->id)->update(['retention_ends_at' => now()->subDay()]);

        $cleanup = TenantCleanup::where('tenant_id', $this->tenant->id)->first();
        $this->assertFalse($cleanup->exportAllowed());

        $this->get("/store/{$this->tenant->slug}/admin/billing/backup/products?format=csv")->assertForbidden();
        $this->get("/store/{$this->tenant->slug}/admin/billing/backup/orders?format=csv")->assertForbidden();
    }

    public function test_purge_gated_off_by_default_and_dry_run_safe(): void
    {
        $this->makePendingDeletion($this->tenant, $this->subscription);

        $service = app(TenantDataPurgeService::class);
        $this->assertFalse($service->purgeAllowed());

        $cat = $this->makeCategory($this->tenant);
        DB::table('products')->insert([
            'tenant_id' => $this->tenant->id, 'category_id' => $cat, 'type' => 'single', 'name' => 'Victim',
            'sku' => 'V-1', 'price' => 1, 'stock' => 1, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $dry = $service->process(true);
        $this->assertSame(1, $dry['candidates']);
        $this->assertSame(1, $dry['purged']);

        $this->assertDatabaseHas('products', ['tenant_id' => $this->tenant->id, 'sku' => 'V-1']);
        $this->assertDatabaseHas('tenants', ['id' => $this->tenant->id]);

        $live = $service->process(false);
        $this->assertSame(0, $live['purged']);
        $this->assertDatabaseHas('products', ['tenant_id' => $this->tenant->id, 'sku' => 'V-1']);
    }

    public function test_purge_deletes_tenant_data_but_preserves_protected_records(): void
    {
        config(['billing.tenant_cleanup.purge_enabled' => true]);
        $this->setPlatformSetting('tenant_auto_delete_enabled', true);

        $this->makePendingDeletion($this->tenant, $this->subscription);
        $invoice = $this->makeInvoice($this->tenant, $this->subscription);
        DB::table('subscription_audit_logs')->insert([
            'subscription_id' => $this->subscription->id, 'tenant_id' => $this->tenant->id,
            'event' => 'canceled', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $catA = $this->makeCategory($this->tenant);
        DB::table('products')->insert([
            'tenant_id' => $this->tenant->id, 'category_id' => $catA, 'type' => 'single', 'name' => 'Victim',
            'sku' => 'V-1', 'price' => 1, 'stock' => 1, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $other = Tenant::create(['slug' => 'retention-other-' . uniqid(), 'name' => 'Other', 'status' => 'active']);
        $otherSub = $this->makeSubscription($other, $this->paidPlan, 'active', now()->addDays(30));
        DB::table('products')->insert([
            'tenant_id' => $other->id, 'category_id' => $this->makeCategory($other), 'type' => 'single', 'name' => 'Neighbor',
            'sku' => 'N-1', 'price' => 1, 'stock' => 1, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $result = app(TenantDataPurgeService::class)->process(false);
        $this->assertSame(1, $result['purged']);

        $this->assertDatabaseMissing('products', ['tenant_id' => $this->tenant->id, 'sku' => 'V-1']);
        $this->assertDatabaseHas('products', ['tenant_id' => $other->id, 'sku' => 'N-1']);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
        $this->assertDatabaseHas('subscription_audit_logs', ['subscription_id' => $this->subscription->id]);
        $this->assertDatabaseHas('subscriptions', ['id' => $this->subscription->id]);
        $this->assertDatabaseHas('subscriptions', ['id' => $otherSub->id]);
        $this->assertSame('active', $other->fresh()->status);

        $cleanup = TenantCleanup::where('tenant_id', $this->tenant->id)->first();
        $this->assertSame(TenantCleanup::STATUS_DELETED, $cleanup->status);
        $this->assertSame(TenantCleanup::DELETION_DELETED, $cleanup->deletion_status);
        $this->assertNotNull($cleanup->deleted_at);

        $again = app(TenantDataPurgeService::class)->process(false);
        $this->assertSame(0, $again['purged']);
    }

    public function test_purge_deletes_only_owned_files_and_keeps_shared_assets(): void
    {
        config(['filesystems.default' => 'public']);
        Storage::fake('public');

        config(['billing.tenant_cleanup.purge_enabled' => true]);
        $this->setPlatformSetting('tenant_auto_delete_enabled', true);

        $this->makePendingDeletion($this->tenant, $this->subscription);

        Storage::disk('public')->put('products/owned.jpg', 'owned');
        Storage::disk('public')->put('products/shared.jpg', 'shared');
        Storage::disk('public')->put('defaults/logo.png', 'shared-default');

        $catF = $this->makeCategory($this->tenant);
        DB::table('products')->insert([
            'tenant_id' => $this->tenant->id, 'category_id' => $catF, 'type' => 'single', 'name' => 'Owned',
            'sku' => 'O-1', 'price' => 1, 'stock' => 1, 'status' => 'active',
            'photo1' => 'products/owned.jpg', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('products')->insert([
            'tenant_id' => $this->tenant->id, 'category_id' => $catF, 'type' => 'single', 'name' => 'Default',
            'sku' => 'O-2', 'price' => 1, 'stock' => 1, 'status' => 'active',
            'photo1' => 'defaults/logo.png', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $other = Tenant::create(['slug' => 'retention-files-' . uniqid(), 'name' => 'Other', 'status' => 'active']);
        $this->makeSubscription($other, $this->paidPlan, 'active', now()->addDays(30));
        DB::table('products')->insert([
            'tenant_id' => $other->id, 'category_id' => $this->makeCategory($other), 'type' => 'single', 'name' => 'Neighbor',
            'sku' => 'N-2', 'price' => 1, 'stock' => 1, 'status' => 'active',
            'photo1' => 'products/shared.jpg', 'created_at' => now(), 'updated_at' => now(),
        ]);

        app(TenantDataPurgeService::class)->process(false);

        Storage::disk('public')->assertMissing('products/owned.jpg');
        Storage::disk('public')->assertExists('products/shared.jpg');
        Storage::disk('public')->assertExists('defaults/logo.png');
        $this->assertDatabaseHas('products', ['tenant_id' => $other->id, 'sku' => 'N-2']);
    }

    public function test_failed_purge_is_retry_safe_and_logged(): void
    {
        $this->makePendingDeletion($this->tenant, $this->subscription);
        DB::table('products')->insert([
            'tenant_id' => $this->tenant->id, 'category_id' => $this->makeCategory($this->tenant), 'type' => 'single', 'name' => 'Retry',
            'sku' => 'R-1', 'price' => 1, 'stock' => 1, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $service = app(TenantDataPurgeService::class);

        $this->assertFalse($service->purgeTenant($this->tenant->id, false));
        $this->assertDatabaseHas('products', ['tenant_id' => $this->tenant->id, 'sku' => 'R-1']);

        config(['billing.tenant_cleanup.purge_enabled' => true]);
        $this->setPlatformSetting('tenant_auto_delete_enabled', true);

        $this->assertTrue($service->purgeTenant($this->tenant->id, false));
        $this->assertDatabaseMissing('products', ['tenant_id' => $this->tenant->id, 'sku' => 'R-1']);

        $cleanup = TenantCleanup::where('tenant_id', $this->tenant->id)->first();
        $this->assertTrue($cleanup->isDeleted());
    }

    public function test_renewal_before_retention_cancels_cleanup(): void
    {
        $this->subscription->update(['status' => 'expired', 'expires_at' => now()->subDays(5)]);
        app(TenantCleanupService::class)->process();
        $this->assertSame(TenantCleanup::STATUS_GRACE, TenantCleanup::where('tenant_id', $this->tenant->id)->first()->status);

        $intent = new \App\Models\PaymentIntent([
            'plan_id' => $this->paidPlan->id,
            'billing_cycle' => 'monthly',
            'amount' => 10.0,
            'currency' => 'MMK',
            'gateway' => 'manual',
            'status' => 'completed',
            'reference_number' => 'PAY-' . strtoupper(uniqid()),
        ]);
        $intent->tenant_id = $this->tenant->id;
        $intent->save();

        app(\App\Listeners\CancelTenantCleanupOnPaymentCompleted::class)
            ->handle(new \App\Events\Payments\PaymentIntentCompleted($intent));

        $this->assertSame(TenantCleanup::STATUS_CANCELLED, TenantCleanup::where('tenant_id', $this->tenant->id)->first()->status);

        $this->subscription->update(['status' => 'active', 'expires_at' => now()->addDays(30)]);
        $result = app(TenantCleanupService::class)->process();
        $this->assertSame(0, $result['restricted']);
    }

    private function makeCategory(Tenant $tenant): int
    {
        return DB::table('categories')->insertGetId([
            'tenant_id' => $tenant->id, 'name' => 'Cat-' . uniqid(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /* ── Helpers ── */

    private function makePendingDeletion(Tenant $tenant, Subscription $subscription): TenantCleanup
    {
        $subscription->update(['status' => 'expired', 'expires_at' => now()->subDays(40)]);

        return TenantCleanup::create([
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'status' => TenantCleanup::STATUS_PENDING_DELETION,
            'expires_at' => $subscription->expires_at,
            'grace_ends_at' => $subscription->expires_at->copy()->addDays(15),
            'retention_ends_at' => now()->subDay(),
            'restricted_at' => now()->subDays(20),
            'deletion_status' => TenantCleanup::DELETION_PENDING,
            'reminders_sent' => [],
        ]);
    }

    private function makeInvoice(Tenant $tenant, Subscription $subscription): Invoice
    {
        return Invoice::create([
            'tenant_id' => $tenant->id,
            'invoice_number' => 'INV-TEST-' . uniqid(),
            'subscription_id' => $subscription->id,
            'plan_id' => $this->paidPlan->id,
            'billing_interval' => 'monthly',
            'billing_period_start' => now()->subMonth()->toDateString(),
            'billing_period_end' => now()->toDateString(),
            'amount' => 10, 'subtotal' => 10, 'tax' => 0, 'total' => 10,
            'currency' => 'MMK', 'status' => Invoice::STATUS_PAID,
            'issued_at' => now(), 'paid_at' => now(),
            'line_items' => [],
        ]);
    }

    private function setPlatformSetting(string $key, $value): void
    {
        $settings = PlatformSetting::first() ?? new PlatformSetting();
        $settings->fill(array_merge([
            'site_name' => 'Test',
            'trial_enabled' => true,
            'trial_days' => 14,
            'allow_trial_renewal' => true,
            'max_trial_renewals' => 0,
        ], [$key => $value]));
        $settings->save();
        PlatformSetting::clearCache();
    }

    private function makeOwner(Tenant $tenant): User
    {
        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Owner',
            'email' => 'owner-' . uniqid() . '@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
            'is_owner' => true,
        ]);
        $user->assignRole('admin');
        $user->givePermissionTo(['billing.view', 'billing.manage', 'billing.renew']);

        return $user;
    }

    private function makePlan(string $slug, float $monthly, float $yearly): Plan
    {
        return Plan::create([
            'name' => ucfirst($slug), 'slug' => $slug . '-' . uniqid(),
            'description' => 'Test plan', 'monthly_price' => $monthly, 'yearly_price' => $yearly,
            'status' => 'active',
        ]);
    }

    private function makeSubscription(Tenant $tenant, Plan $plan, string $status, $expiresAt): Subscription
    {
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

        return $subscription;
    }

    private function createSchema(): void
    {
        $tables = [
            'permissions' => function ($t) { $t->id(); $t->string('name'); $t->string('guard_name'); $t->timestamps(); $t->unique(['name','guard_name']); },
            'roles' => function ($t) { $t->id(); $t->string('name'); $t->string('guard_name'); $t->unsignedBigInteger('tenant_id')->nullable(); $t->timestamps(); $t->unique(['name','guard_name','tenant_id']); },
            'model_has_roles' => function ($t) { $t->unsignedBigInteger('role_id'); $t->string('model_type'); $t->unsignedBigInteger('model_id'); $t->index(['model_id','model_type']); },
            'role_has_permissions' => function ($t) { $t->unsignedBigInteger('permission_id'); $t->unsignedBigInteger('role_id'); $t->primary(['permission_id','role_id']); },
            'model_has_permissions' => function ($t) { $t->unsignedBigInteger('permission_id'); $t->string('model_type'); $t->unsignedBigInteger('model_id'); $t->index(['model_id','model_type']); $t->primary(['permission_id','model_id','model_type']); },
            'users' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->string('name'); $t->string('email')->unique(); $t->timestamp('email_verified_at')->nullable(); $t->string('password'); $t->string('status')->default('active'); $t->boolean('is_owner')->default(false); $t->boolean('is_admin')->default(false); $t->rememberToken(); $t->timestamps(); },
            'tenants' => function ($t) { $t->id(); $t->string('slug')->unique(); $t->string('name'); $t->string('email')->nullable(); $t->string('status')->default('active'); $t->string('store_url')->nullable(); $t->json('settings')->nullable(); $t->timestamp('activated_at')->nullable(); $t->timestamp('locked_at')->nullable(); $t->timestamps(); },
            'plans' => function ($t) { $t->id(); $t->string('name'); $t->string('slug')->unique(); $t->text('description')->nullable(); $t->decimal('monthly_price',10,2)->nullable(); $t->decimal('yearly_price',10,2)->nullable(); $t->string('status',20)->default('active'); $t->boolean('analytics_enabled')->default(false); $t->boolean('custom_domain_enabled')->default(false); $t->string('price')->nullable(); $t->string('currency')->nullable(); $t->string('interval')->nullable(); $t->boolean('is_default')->default(false); $t->boolean('is_active')->default(true); $t->integer('sort_order')->default(0); $t->timestamps(); },
            'subscriptions' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id'); $t->unsignedBigInteger('plan_id')->nullable(); $t->string('billing_interval')->nullable(); $t->string('status'); $t->timestamp('starts_at')->nullable(); $t->timestamp('expires_at')->nullable(); $t->timestamp('trial_ends_at')->nullable(); $t->integer('trial_renewals_count')->default(0); $t->unsignedBigInteger('pending_plan_id')->nullable(); $t->timestamp('pending_plan_effective_at')->nullable(); $t->timestamp('suspended_at')->nullable(); $t->timestamp('cancelled_at')->nullable(); $t->timestamp('extra_renewal_used_at')->nullable(); $t->text('notes')->nullable(); $t->timestamps(); $t->index('tenant_id'); },
            'payment_intents' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id'); $t->unsignedBigInteger('plan_id'); $t->unsignedBigInteger('subscription_id')->nullable(); $t->string('billing_cycle'); $t->decimal('amount',10,2); $t->string('currency',3); $t->string('gateway'); $t->string('status'); $t->timestamp('expires_at')->nullable(); $t->json('metadata')->nullable(); $t->timestamp('completed_at')->nullable(); $t->timestamp('cancelled_at')->nullable(); $t->timestamp('rejected_at')->nullable(); $t->string('reference_number')->nullable()->unique(); $t->string('idempotency_key',64)->nullable(); $t->timestamps(); $t->index('tenant_id'); $t->index('status'); },
            'payment_timeline_events' => function ($t) { $t->id(); $t->unsignedBigInteger('payment_intent_id'); $t->string('type'); $t->text('description')->nullable(); $t->json('metadata')->nullable(); $t->timestamp('occurred_at'); $t->timestamps(); },
            'subscription_audit_logs' => function ($t) { $t->id(); $t->unsignedBigInteger('subscription_id'); $t->unsignedBigInteger('tenant_id')->nullable(); $t->string('event'); $t->string('actor_type')->nullable(); $t->unsignedBigInteger('actor_id')->nullable(); $t->unsignedBigInteger('old_plan_id')->nullable(); $t->unsignedBigInteger('new_plan_id')->nullable(); $t->string('old_status')->nullable(); $t->string('new_status')->nullable(); $t->text('reason')->nullable(); $t->json('metadata')->nullable(); $t->timestamps(); },
            'subscription_cancellation_requests' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id'); $t->unsignedBigInteger('subscription_id'); $t->string('reason',50); $t->text('comment')->nullable(); $t->string('status',20)->default('pending'); $t->unsignedBigInteger('requested_by')->nullable(); $t->unsignedBigInteger('reviewed_by')->nullable(); $t->text('review_note')->nullable(); $t->timestamp('reviewed_at')->nullable(); $t->timestamps(); $t->index('tenant_id'); $t->index('status'); },
            'invoices' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id'); $t->string('invoice_number')->unique(); $t->unsignedBigInteger('subscription_id')->nullable(); $t->unsignedBigInteger('plan_id')->nullable(); $t->string('billing_interval')->nullable(); $t->date('billing_period_start')->nullable(); $t->date('billing_period_end')->nullable(); $t->decimal('amount',10,2)->default(0); $t->decimal('subtotal',10,2)->default(0); $t->decimal('tax',10,2)->default(0); $t->decimal('total',10,2)->default(0); $t->string('currency',3)->default('MMK'); $t->string('status')->default('unpaid'); $t->unsignedBigInteger('payment_intent_id')->nullable(); $t->text('notes')->nullable(); $t->timestamp('issued_at')->nullable(); $t->timestamp('paid_at')->nullable(); $t->json('line_items')->nullable(); $t->timestamp('deleted_at')->nullable(); $t->timestamps(); },
            'website_infos' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->string('site_name')->nullable(); $t->string('site_tagline')->nullable(); $t->string('site_description')->nullable(); $t->string('site_keywords')->nullable(); $t->string('theme_color')->default('#3B82F6'); $t->string('default_language')->default('en'); $t->string('timezone')->default('Asia/Yangon'); $t->string('currency_code')->default('MMK'); $t->string('currency_symbol')->default('K'); $t->string('date_format')->default('Y-m-d'); $t->boolean('allow_registration')->default(true); $t->boolean('maintenance_mode')->default(false); $t->string('logo')->nullable(); $t->string('favicon')->nullable(); $t->string('og_image')->nullable(); $t->string('footer_logo')->nullable(); $t->string('contact_email')->nullable(); $t->string('support_email')->nullable(); $t->string('phone')->nullable(); $t->string('whatsapp_number')->nullable(); $t->text('address')->nullable(); $t->string('country')->nullable(); $t->string('google_maps_embed_url')->nullable(); $t->json('contact_address')->nullable(); $t->json('footer_settings')->nullable(); $t->json('hero_images')->nullable(); $t->boolean('enable_wishlist')->default(true); $t->boolean('enable_reviews')->default(true); $t->boolean('enable_compare')->default(true); $t->timestamps(); },
            'platform_settings' => function ($t) { $t->id(); $t->string('site_name')->default('My Application'); $t->boolean('trial_enabled')->default(false); $t->integer('trial_days')->default(14); $t->boolean('allow_trial_renewal')->default(true); $t->integer('max_trial_renewals')->default(0); $t->integer('billing_renewal_reminder_days')->default(7); $t->unsignedInteger('tenant_data_retention_days')->default(15); $t->boolean('tenant_auto_delete_enabled')->default(false); $t->timestamps(); },
            'categories' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->string('name'); $t->string('image')->nullable(); $t->timestamps(); },
            'brands' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->string('name'); $t->string('logo')->nullable(); $t->timestamps(); },
            'units' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->string('name'); $t->timestamps(); },
            'products' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id'); $t->unsignedBigInteger('category_id')->nullable(); $t->string('type')->default('single'); $t->string('name'); $t->string('sku')->nullable(); $t->decimal('price',10,2)->default(0); $t->integer('stock')->default(0); $t->string('status')->default('active'); $t->boolean('is_active')->default(true); $t->string('photo1')->nullable(); $t->string('photo2')->nullable(); $t->string('seo_image')->nullable(); $t->json('gallery_images')->nullable(); $t->timestamps(); $t->index('tenant_id'); },
            'activity_logs' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->string('log_name')->nullable(); $t->string('category', 50)->nullable(); $t->string('severity', 20)->default('info'); $t->text('description')->nullable(); $t->string('subject_type')->nullable(); $t->unsignedBigInteger('subject_id')->nullable(); $t->string('causer_type')->nullable(); $t->unsignedBigInteger('causer_id')->nullable(); $t->unsignedBigInteger('impersonator_id')->nullable(); $t->unsignedBigInteger('impersonated_user_id')->nullable(); $t->json('properties')->nullable(); $t->string('event')->nullable(); $t->string('batch_uuid')->nullable(); $t->timestamps(); },
            'accounts' => function ($t) { $t->id(); $t->string('name')->nullable(); $t->string('email')->unique(); $t->string('password'); $t->string('status', 50)->default('active'); $t->json('notification_preferences')->nullable(); $t->timestamps(); $t->softDeletes(); },
            'plan_features' => function ($t) { $t->id(); $t->unsignedBigInteger('plan_id'); $t->string('feature_key'); $t->boolean('is_enabled')->default(false); $t->string('display_label')->nullable(); $t->text('description')->nullable(); $t->timestamps(); },
            'notifications' => function ($t) { $t->uuid('id')->primary(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->string('type'); $t->string('notifiable_type'); $t->unsignedBigInteger('notifiable_id'); $t->text('data'); $t->timestamp('read_at')->nullable(); $t->timestamps(); },
            'payment_methods' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->string('name')->nullable(); $t->boolean('is_active')->default(true); $t->timestamps(); },
            'flash_sales' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->string('name')->nullable(); $t->boolean('is_active')->default(true); $t->timestamps(); },
            'wishlists' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->unsignedBigInteger('user_id')->nullable(); $t->string('user_type')->nullable(); $t->unsignedBigInteger('product_id')->nullable(); $t->timestamps(); },
            'settings' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->string('key'); $t->text('value')->nullable(); $t->timestamps(); },
            'orders' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->timestamps(); },
            'order_items' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->unsignedBigInteger('order_id')->nullable(); $t->timestamps(); },
            'coupons' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->timestamps(); },
            'promotions' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->string('name')->nullable(); $t->string('code')->nullable(); $t->boolean('is_active')->default(true); $t->boolean('is_automatic')->default(false); $t->timestamps(); },
            'promotion_banners' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->timestamps(); },
            'tenant_memberships' => function ($t) { $t->id(); $t->unsignedBigInteger('account_id')->nullable(); $t->unsignedBigInteger('tenant_id'); $t->unsignedBigInteger('role_id')->nullable(); $t->boolean('is_owner')->default(false); $t->string('status')->default('active'); $t->timestamps(); $t->softDeletes(); },
            'tenant_cleanups' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->unique(); $t->unsignedBigInteger('subscription_id')->nullable(); $t->string('status',20)->default('grace'); $t->timestamp('expires_at')->nullable(); $t->timestamp('grace_ends_at')->nullable(); $t->timestamp('retention_ends_at')->nullable(); $t->string('deletion_status',20)->default('none'); $t->timestamp('restricted_at')->nullable(); $t->timestamp('deleted_at')->nullable(); $t->timestamp('archived_at')->nullable(); $t->timestamp('last_reminder_at')->nullable(); $t->json('reminders_sent')->nullable(); $t->unsignedInteger('attempts')->default(0); $t->string('reason')->nullable(); $t->text('last_error')->nullable(); $t->json('metadata')->nullable(); $t->timestamps(); $t->index('status'); $t->index('grace_ends_at'); },
        ];

        foreach ($tables as $name => $callback) {
            if (!Schema::hasTable($name)) {
                Schema::create($name, $callback);
            }
        }

        if (!Schema::hasColumn('platform_settings', 'tenant_data_retention_days')) {
            Schema::table('platform_settings', function ($t) {
                $t->unsignedInteger('tenant_data_retention_days')->default(15);
            });
        }

        if (!Schema::hasColumn('platform_settings', 'tenant_auto_delete_enabled')) {
            Schema::table('platform_settings', function ($t) {
                $t->boolean('tenant_auto_delete_enabled')->default(false);
            });
        }

        foreach (['retention_ends_at', 'deleted_at'] as $column) {
            if (!Schema::hasColumn('tenant_cleanups', $column)) {
                Schema::table('tenant_cleanups', function ($t) use ($column) {
                    $t->timestamp($column)->nullable();
                });
            }
        }

        if (!Schema::hasColumn('tenant_cleanups', 'deletion_status')) {
            Schema::table('tenant_cleanups', function ($t) {
                $t->string('deletion_status', 20)->default('none');
            });
        }
    }
}
