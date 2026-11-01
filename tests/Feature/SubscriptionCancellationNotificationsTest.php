<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\SubscriptionAuditLog;
use App\Models\SubscriptionCancellationRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\SubscriptionCancellationScheduled;
use App\Notifications\SubscriptionExpired;
use App\Notifications\SubscriptionExpiringSoon;
use App\Notifications\SubscriptionPastDue;
use App\Services\BillingEmailService;
use App\Services\SubscriptionExpiryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SubscriptionCancellationNotificationsTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $adminA;
    private User $adminB;
    private Subscription $subscriptionA;
    private Subscription $subscriptionB;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('identity.use_accounts', false);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'billing.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'billing.manage', 'guard_name' => 'web']);

        $plan = Plan::firstOrCreate(
            ['slug' => 'cancellation-test-plan'],
            ['name' => 'Cancellation Test Plan', 'monthly_price' => 100, 'yearly_price' => 1000, 'status' => 'active']
        );

        $this->tenantA = Tenant::create([
            'name' => 'Cancellation Store A',
            'slug' => 'cancel-store-a-' . uniqid(),
            'status' => 'active',
        ]);
        $this->tenantB = Tenant::create([
            'name' => 'Cancellation Store B',
            'slug' => 'cancel-store-b-' . uniqid(),
            'status' => 'active',
        ]);

        $roleA = $this->makeAdminRole($this->tenantA);
        $roleB = $this->makeAdminRole($this->tenantB);

        $this->subscriptionA = $this->makeSubscription($this->tenantA, $plan, 'active', now()->addDays(10)->endOfDay());
        $this->subscriptionB = $this->makeSubscription($this->tenantB, $plan, 'active', now()->addDays(10)->endOfDay());

        $this->adminA = $this->makeAdmin($this->tenantA, 'cancel-admin-a-' . uniqid() . '@test.com', $roleA);
        $this->adminB = $this->makeAdmin($this->tenantB, 'cancel-admin-b-' . uniqid() . '@test.com', $roleB);
    }

    protected function tearDown(): void
    {
        Tenant::setCurrent(null);
        parent::tearDown();
    }

    public function test_cancellation_request_notifies_merchant_with_paid_through_and_export_link(): void
    {
        $this->actAsA();

        $this->post('/admin/billing/cancel-request', [
            'reason' => 'too_expensive',
            'comment' => 'Budget review',
        ])->assertRedirect();

        $request = SubscriptionCancellationRequest::where('subscription_id', $this->subscriptionA->id)->firstOrFail();
        $this->assertSame(SubscriptionCancellationRequest::STATUS_SCHEDULED, $request->status);

        $data = $this->notificationData($this->adminA, SubscriptionCancellationScheduled::class);

        $this->assertSame('Cancellation Scheduled', $data['title']);
        $this->assertTrue($data['cancellation_scheduled']);
        $this->assertSame($request->id, $data['cancellation_request_id']);
        $this->assertSame($this->subscriptionA->expires_at->toDateString(), $data['effective_at']);
        $this->assertSame($this->subscriptionA->expires_at->toDateString(), $data['paid_through_at']);
        $this->assertStringContainsString('will not be deleted automatically', $data['message']);
        $this->assertStringContainsString('/admin/data-export', $data['export_url']);

        $this->assertDatabaseHas('subscription_audit_logs', [
            'subscription_id' => $this->subscriptionA->id,
            'event' => 'cancellation_scheduled',
        ]);
    }

    public function test_cancellation_request_is_not_sent_twice(): void
    {
        $this->actAsA();

        $this->post('/admin/billing/cancel-request', ['reason' => 'too_expensive'])->assertRedirect();
        $this->post('/admin/billing/cancel-request', ['reason' => 'no_longer_needed'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(1, SubscriptionCancellationRequest::where('subscription_id', $this->subscriptionA->id)->count());
        $this->assertSame(1, $this->notificationCount($this->adminA, SubscriptionCancellationScheduled::class));
    }

    public function test_cancellation_notification_respects_tenant_master_switch(): void
    {
        $this->actAsA();
        Tenant::setCurrent($this->tenantA);

        \App\Models\Setting::create(['key' => 'notifications_enabled', 'value' => 'false']);

        $this->post('/admin/billing/cancel-request', ['reason' => 'too_expensive'])->assertRedirect();

        $this->assertSame(1, SubscriptionCancellationRequest::where('subscription_id', $this->subscriptionA->id)->count());
        $this->assertSame(0, $this->notificationCount($this->adminA, SubscriptionCancellationScheduled::class));
    }

    public function test_cancellation_notification_is_tenant_isolated(): void
    {
        $this->actAsA();

        $this->post('/admin/billing/cancel-request', ['reason' => 'too_expensive'])->assertRedirect();

        $this->assertSame(1, $this->notificationCount($this->adminA, SubscriptionCancellationScheduled::class));
        $this->assertSame(0, $this->notificationCount($this->adminB, SubscriptionCancellationScheduled::class));
    }

    public function test_expiry_reminder_for_scheduled_cancellation_shows_ending_copy(): void
    {
        $this->subscriptionA->update(['expires_at' => now()->addDays(7)->startOfDay()]);
        $this->makeCancellation($this->subscriptionA);

        $this->assertNull(\App\Models\Tenant::getCurrent());
        $this->assertSame(1, \App\Auth\IdentityResolver::resolveTenantAdmins($this->tenantA->id)->count());
        $this->assertTrue(app(\App\Services\NotificationPreferenceService::class)->tenantAllows($this->tenantA->id));
        $this->assertNotNull($this->subscriptionA->fresh()->activeCancellationRequest()->first());

        $this->artisan('subscriptions:send-reminders')->assertSuccessful();

        $data = $this->notificationData($this->adminA, SubscriptionExpiringSoon::class);

        $this->assertSame('Subscription Ending Soon', $data['title']);
        $this->assertTrue($data['cancellation_scheduled']);
        $this->assertSame(7, $data['days_remaining']);
        $this->assertStringContainsString('scheduled to end', $data['message']);
        $this->assertStringContainsString('will not be deleted automatically', $data['message']);
        $this->assertStringContainsString('/admin/data-export', $data['export_url']);
    }

    public function test_renewal_reminder_email_is_skipped_for_scheduled_cancellation(): void
    {
        Mail::fake();

        PlatformSetting::query()->delete();
        PlatformSetting::create(['billing_renewal_reminder_days' => 7]);
        PlatformSetting::clearCache();

        $this->subscriptionA->update(['expires_at' => now()->addDays(7)->endOfDay()]);
        $this->makeCancellation($this->subscriptionA);

        $sent = app(BillingEmailService::class)->sendRenewalReminder($this->subscriptionA->fresh());

        $this->assertFalse($sent);
        Mail::assertNothingQueued();
    }

    public function test_actual_expiry_for_scheduled_cancellation_mentions_retained_data(): void
    {
        $this->subscriptionA->update([
            'status' => 'active',
            'expires_at' => now()->subDay(),
        ]);
        $this->makeCancellation($this->subscriptionA);

        app(SubscriptionExpiryService::class)->process();

        $data = $this->notificationData($this->adminA, SubscriptionPastDue::class);

        $this->assertSame('Subscription Ended', $data['title']);
        $this->assertTrue($data['cancellation_scheduled']);
        $this->assertStringContainsString('has not been deleted', $data['message']);
        $this->assertStringContainsString('/admin/data-export', $data['export_url']);
    }

    public function test_expired_notification_for_scheduled_cancellation_mentions_retained_data(): void
    {
        $this->subscriptionA->update([
            'status' => 'past_due',
            'expires_at' => now()->subDays(8),
        ]);
        $this->makeCancellation($this->subscriptionA);

        app(SubscriptionExpiryService::class)->process();

        $data = $this->notificationData($this->adminA, SubscriptionExpired::class);

        $this->assertSame('Subscription Ended', $data['title']);
        $this->assertTrue($data['cancellation_scheduled']);
        $this->assertStringContainsString('will not be deleted automatically', $data['message']);
        $this->assertStringContainsString('/admin/data-export', $data['export_url']);
    }

    public function test_lifecycle_processing_does_not_delete_tenant_data(): void
    {
        $this->subscriptionA->update([
            'status' => 'past_due',
            'expires_at' => now()->subDays(8),
        ]);
        $this->makeCancellation($this->subscriptionA);

        Tenant::setCurrent($this->tenantA);
        $product = Product::create([
            'name' => 'Retained Product ' . uniqid(),
            'price' => 10,
            'stock' => 1,
            'status' => 'active',
            'type' => 'single',
            'category_id' => \App\Models\Category::firstOrCreate([
                'tenant_id' => $this->tenantA->id,
                'name' => 'Retained Category ' . $this->tenantA->id,
            ])->id,
        ]);

        app(SubscriptionExpiryService::class)->process();

        $this->assertDatabaseHas('tenants', ['id' => $this->tenantA->id]);
        $this->assertDatabaseHas('products', ['id' => $product->id]);
        $this->assertSame(1, SubscriptionCancellationRequest::where('subscription_id', $this->subscriptionA->id)->count());
        $this->assertSame(0, DB::table('notifications')->where('type', 'like', '%Retention%')->count());
    }

    private function actAsA(): void
    {
        Tenant::setCurrent($this->tenantA);
        $this->actingAs($this->adminA);
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

        $tenant->update(['subscription_plan_id' => $plan->id]);

        return $subscription;
    }

    private function makeAdminRole(Tenant $tenant): Role
    {
        $role = Role::query()
            ->withoutGlobalScope(\App\Models\Scopes\TenantScope::class)
            ->firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
            'tenant_id' => $tenant->id,
        ]);
        $role->syncPermissions(['billing.view', 'billing.manage']);

        return $role;
    }

    private function makeAdmin(Tenant $tenant, string $email, Role $role): User
    {
        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Cancellation Admin',
            'email' => $email,
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $user->assignRole($role);
        $user->givePermissionTo(['billing.view', 'billing.manage']);

        return $user;
    }

    private function makeCancellation(Subscription $subscription): SubscriptionCancellationRequest
    {
        return SubscriptionCancellationRequest::create([
            'tenant_id' => $subscription->tenant_id,
            'subscription_id' => $subscription->id,
            'reason' => 'too_expensive',
            'status' => SubscriptionCancellationRequest::STATUS_SCHEDULED,
        ]);
    }

    private function notificationCount(User $user, string $type): int
    {
        return DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->where('type', $type)
            ->count();
    }

    private function notificationData(User $user, string $type): array
    {
        $row = DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->where('type', $type)
            ->latest('created_at')
            ->first();

        $this->assertNotNull($row, "Expected a {$type} notification.");

        return json_decode($row->data, true);
    }
}
