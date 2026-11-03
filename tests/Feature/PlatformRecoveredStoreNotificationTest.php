<?php

namespace Tests\Feature;

use App\Mail\Platform\NewRecoveredStoreActivationMail;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PlatformNotificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PlatformRecoveredStoreNotificationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['identity.use_accounts' => false]);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'billing.view', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);

        Mail::fake();
    }

    public function test_successful_recovery_notification_created_for_superadmin(): void
    {
        $superadmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin-' . uniqid() . '@example.test',
            'password' => 'secret',
        ]);
        $superadmin->assignRole('superadmin');

        $source = Tenant::create([
            'slug' => 'source-' . uniqid(),
            'name' => 'Source Store',
            'status' => 'active',
        ]);

        $newTenant = Tenant::create([
            'slug' => 'recovered-' . uniqid(),
            'name' => 'Recovered Store',
            'status' => 'pending',
        ]);

        $service = app(PlatformNotificationService::class);
        $service->notifyNewRecoveredStore($newTenant, $source, [
            'plan_name' => 'Pro Plan',
            'owner_email' => 'owner@example.test',
        ], 'archive-' . uniqid());

        $this->assertSame(1, DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $superadmin->id)
            ->where('type', 'App\\Notifications\\SuperAdminNewRecoveredStoreNotification')
            ->count());

        $notification = DB::table('notifications')
            ->where('notifiable_id', $superadmin->id)
            ->first();
        $data = json_decode($notification->data, true);
        $this->assertSame('New Store Requires Activation', $data['title']);
        $this->assertSame($newTenant->id, $data['tenant_id']);
        $this->assertSame($newTenant->slug, $data['store_slug']);

        Mail::assertQueued(NewRecoveredStoreActivationMail::class, 1);
    }

    public function test_duplicate_archive_does_not_create_duplicate_notification(): void
    {
        $superadmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin2-' . uniqid() . '@example.test',
            'password' => 'secret',
        ]);
        $superadmin->assignRole('superadmin');

        $source = Tenant::create(['slug' => 'source2-' . uniqid(), 'name' => 'Source Store', 'status' => 'active']);
        $newTenant = Tenant::create(['slug' => 'recovered2-' . uniqid(), 'name' => 'Recovered Store', 'status' => 'pending']);

        $archiveId = 'archive-dup-' . uniqid();

        $service = app(PlatformNotificationService::class);
        $service->notifyNewRecoveredStore($newTenant, $source, ['plan_name' => 'Pro Plan', 'owner_email' => 'owner@example.test'], $archiveId);
        $service->notifyNewRecoveredStore($newTenant, $source, ['plan_name' => 'Pro Plan', 'owner_email' => 'owner@example.test'], $archiveId);

        $this->assertSame(1, DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $superadmin->id)
            ->count());
    }

    public function test_merchant_cannot_access_superadmin_notification_page(): void
    {
        $merchant = User::create([
            'name' => 'Merchant',
            'email' => 'merchant-' . uniqid() . '@example.test',
            'password' => 'secret',
            'tenant_id' => null,
        ]);

        $this->actingAs($merchant)->get('/admin/notifications')->assertStatus(403);
    }
}
