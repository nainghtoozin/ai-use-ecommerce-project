<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\MerchantRecoveryClaim;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Export\MerchantBackupSigner;
use App\Services\Recovery\MerchantRecoveryImporter;
use App\Services\TenantBootstrapService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\Support\MerchantRestoreDrill\DrillFixture;
use Tests\TestCase;
use ZipArchive;

class MerchantRecoveryTest extends TestCase
{
    use DatabaseTransactions;

    private const SIGNING_KEY = 'test-merchant-recovery-signing-key-0123456789abcdef';

    private DrillFixture $fixture;
    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $adminA;
    private User $adminB;
    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'identity.use_accounts' => false,
            'exports.backup.signing_key' => self::SIGNING_KEY,
            'exports.backup.key_id' => 'test-key',
            'exports.backup.per_hour' => 100,
            'exports.backup.verify_per_hour' => 100,
            'exports.backup.recover_per_hour' => 100,
            'exports.backup.upload_ttl_hours' => 1,
            'exports.backup.max_upload_kb' => 51200,
            'exports.backup.max_entries' => 500,
            'exports.backup.max_entry_bytes' => 67108864,
            'exports.backup.max_total_bytes' => 268435456,
            'exports.backup.recovery_lock_seconds' => 60,
        ]);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['billing.view', 'backup.view', 'backup.download', 'backup.verify', 'backup.recover'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions(['billing.view']);

        $this->plan = Plan::create([
            'name' => 'Recovery Test Plan ' . uniqid(),
            'slug' => 'recovery-test-plan-' . uniqid(),
            'monthly_price' => 0,
            'yearly_price' => 0,
            'product_limit' => 50,
            'status' => 'active',
        ]);

        $this->fixture = new DrillFixture();
        $this->fixture->create();

        $this->tenantA = Tenant::findOrFail($this->fixture->tenantA);
        $this->tenantB = Tenant::findOrFail($this->fixture->tenantB);

        $this->tenantA->subscription_plan_id = $this->plan->id;
        $this->tenantA->save();

        Subscription::forceCreate([
            'tenant_id' => $this->tenantA->id,
            'plan_id' => $this->plan->id,
            'billing_interval' => 'monthly',
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
        ]);

        $this->adminA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Recovery Admin A',
            'email' => 'recovery-a-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
            'status' => 'active',
            'is_owner' => true,
        ]);
        $this->adminA->assignRole($adminRole);
        $this->adminA->givePermissionTo(['billing.view', 'backup.view', 'backup.download', 'backup.verify', 'backup.recover']);

        $this->adminB = User::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Recovery Admin B',
            'email' => 'recovery-b-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $this->adminB->assignRole($adminRole);
        $this->adminB->givePermissionTo('billing.view');

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        Tenant::setCurrent(null);

        $this->fixture?->cleanupAsset();
        $this->fixture?->purge();

        foreach ([$this->tenantA ?? null, $this->tenantB ?? null] as $tenant) {
            if (!$tenant) {
                continue;
            }

            $root = Storage::disk('local')->path('exports/' . $tenant->id);

            foreach (glob($root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'upload-*.zip') ?: [] as $file) {
                @unlink($file);
            }

            foreach (glob($root . DIRECTORY_SEPARATOR . 'backup-*.zip') ?: [] as $file) {
                @unlink($file);
            }
        }

        File::deleteDirectory(storage_path('framework/testing/merchant-recovery'));

        parent::tearDown();
    }

    public function test_recovery_imports_into_a_new_inactive_tenant_with_remapped_ids(): void
    {
        $this->actAsA();

        $archive = $this->downloadArchive();
        $manifest = $this->manifestFrom($archive);

        $this->uploadArchive($archive)->assertRedirect('/admin/backups/preview')->assertSessionHasNoErrors();

        $this->get('/admin/backups/preview')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/MerchantBackup/Preview')
                ->where('recoveryEnabled', true)
                ->where('preview.recovery.enabled', true)
                ->where('preview.recovery.source_slug', $this->tenantA->slug));

        $targetSlug = 'recovered-store-' . strtolower(uniqid());
        $sourceSnapshot = $this->sourceSnapshot();

        $response = $this->recover($targetSlug);

        $response->assertRedirect('/admin/backups/recovered');
        $response->assertSessionHasNoErrors();

        $recovered = Tenant::where('slug', $targetSlug)->firstOrFail();

        $this->assertSame('pending', $recovered->status);
        $this->assertNull($recovered->activated_at);
        $this->assertSame($this->plan->id, $recovered->subscription_plan_id);
        $this->assertSame($manifest['archive_id'], $recovered->settings['recovery']['archive_id'] ?? null);
        $this->assertSame($this->tenantA->id, $recovered->settings['recovery']['source_tenant_id'] ?? null);

        $subscription = Subscription::withoutTenantScope()
            ->where('tenant_id', $recovered->id)
            ->firstOrFail();
        $this->assertSame('pending', $subscription->status);
        $this->assertSame($this->plan->id, $subscription->plan_id);

        $this->assertSame($sourceSnapshot, $this->sourceSnapshot());
        $this->assertSame(0, Tenant::where('slug', $this->tenantA->slug)->where('id', '!=', $this->tenantA->id)->count());

        $this->assertRecoveredCounts($recovered, $manifest);
        $this->assertIdsWereRemapped($recovered);
        $this->assertInventoryAndIdentities($recovered);

        $this->get('/admin/backups/recovered')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/MerchantBackup/Recovered')
                ->where('result.tenant_id', $recovered->id)
                ->where('result.tenant_slug', $targetSlug)
                ->where('result.status', 'pending'));

        $this->assertAuditEvent('merchant_recovery_started');
        $this->assertAuditEvent('merchant_recovery_completed');

        $started = ActivityLog::where('event', 'merchant_recovery_started')->latest('id')->firstOrFail();
        $this->assertSame($manifest['archive_id'], $started->properties['archive_id'] ?? null);
        $this->assertArrayNotHasKey('manifest', $started->properties);
        $this->assertArrayNotHasKey('csv', $started->properties);

        $this->assertSame([], $this->uploadFiles($this->tenantA));
    }

    public function test_tampered_retained_archive_is_rejected_before_any_import(): void
    {
        $this->actAsA();

        $archive = $this->downloadArchive();
        $manifest = $this->manifestFrom($archive);

        $this->uploadArchive($archive)->assertRedirect('/admin/backups/preview')->assertSessionHasNoErrors();

        $this->tamperRetainedUpload();

        $sourceSnapshot = $this->sourceSnapshot();
        $targetSlug = 'tampered-store-' . strtolower(uniqid());

        $response = $this->recover($targetSlug);

        $response->assertRedirect();
        $response->assertSessionHasErrors('recovery');

        $this->assertNull(Tenant::where('slug', $targetSlug)->first());
        $this->assertSame($sourceSnapshot, $this->sourceSnapshot());
        $this->assertSame([], $this->uploadFiles($this->tenantA));

        $this->assertAuditEvent('merchant_recovery_failed', 'reverification_failed');
        $this->assertNull(ActivityLog::where('event', 'merchant_recovery_started')
            ->where('properties->archive_id', $manifest['archive_id'])
            ->first());
    }

    public function test_failed_recovery_rolls_back_only_the_new_tenant(): void
    {
        $this->actAsA();

        $archive = $this->downloadArchive();
        $manifest = $this->manifestFrom($archive);

        $this->makeInventoryLevelInconsistent($archive);
        $manifest = $this->manifestFrom($archive);

        $this->uploadArchive($archive)->assertRedirect('/admin/backups/preview')->assertSessionHasNoErrors();

        $sourceSnapshot = $this->sourceSnapshot();
        $targetSlug = 'rollback-store-' . strtolower(uniqid());

        $response = $this->recover($targetSlug);

        $response->assertRedirect();
        $response->assertSessionHasErrors('recovery');

        $this->assertNull(Tenant::where('slug', $targetSlug)->first());
        $this->assertSame($sourceSnapshot, $this->sourceSnapshot());

        $this->assertAuditEvent('merchant_recovery_failed', 'inventory_mismatch');
        $this->assertAuditEvent('merchant_recovery_rolled_back');

        $this->assertCount(1, $this->uploadFiles($this->tenantA));
    }

    public function test_duplicate_recovery_of_the_same_archive_is_blocked(): void
    {
        $this->actAsA();

        $archive = $this->downloadArchive();

        $this->uploadArchive($archive)->assertRedirect('/admin/backups/preview')->assertSessionHasNoErrors();

        $firstSlug = 'first-store-' . strtolower(uniqid());
        $this->recover($firstSlug)->assertRedirect('/admin/backups/recovered');

        $this->uploadArchive($archive)->assertRedirect('/admin/backups/preview')->assertSessionHasNoErrors();

        $secondSlug = 'second-store-' . strtolower(uniqid());
        $response = $this->recover($secondSlug);

        $response->assertRedirect();
        $response->assertSessionHasErrors('recovery');

        $this->assertNull(Tenant::where('slug', $secondSlug)->first());
        $this->assertNotNull(Tenant::where('slug', $firstSlug)->first());
        $this->assertAuditEvent('merchant_recovery_failed', 'duplicate_recovery');
    }

    public function test_recovery_is_rejected_when_the_plan_product_limit_is_exceeded(): void
    {
        $this->actAsA();

        $this->plan->update(['product_limit' => 2]);

        $archive = $this->downloadArchive();
        $this->uploadArchive($archive)->assertRedirect('/admin/backups/preview')->assertSessionHasNoErrors();

        $sourceSnapshot = $this->sourceSnapshot();
        $targetSlug = 'limited-store-' . strtolower(uniqid());

        $response = $this->recover($targetSlug);

        $response->assertRedirect();
        $response->assertSessionHasErrors('recovery');

        $this->assertNull(Tenant::where('slug', $targetSlug)->first());
        $this->assertSame($sourceSnapshot, $this->sourceSnapshot());
        $this->assertAuditEvent('merchant_recovery_failed', 'subscription_limit');
    }

    public function test_recovery_is_rejected_when_the_plan_coupon_limit_is_exceeded(): void
    {
        $this->assertRecoveryRejectedByPlanLimit('coupon_limit');
    }

    public function test_recovery_is_rejected_when_the_plan_promotion_limit_is_exceeded(): void
    {
        $this->assertRecoveryRejectedByPlanLimit('promotion_limit');
    }

    public function test_recovery_is_rejected_when_the_plan_flash_sale_limit_is_exceeded(): void
    {
        $this->assertRecoveryRejectedByPlanLimit('flash_sale_limit');
    }

    public function test_staff_with_recovery_permission_cannot_recover_a_store_they_do_not_own(): void
    {
        $staff = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Recovery Staff A',
            'email' => 'recovery-staff-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
            'status' => 'active',
            'is_owner' => false,
        ]);
        $staff->givePermissionTo(['billing.view', 'backup.view', 'backup.download', 'backup.verify', 'backup.recover']);

        $this->assertTrue($staff->can('backup.recover'));
        $this->assertFalse($this->tenantA->isOwnedBy($staff));

        $this->actingAs($staff);
        Tenant::setCurrent($this->tenantA);

        $archive = $this->downloadArchive();
        $this->uploadArchive($archive)->assertRedirect('/admin/backups/preview')->assertSessionHasNoErrors();

        $targetSlug = 'staff-owned-' . strtolower(uniqid());

        $this->recover($targetSlug)->assertForbidden();

        $this->assertNull(Tenant::where('slug', $targetSlug)->first());
        $this->assertNull(ActivityLog::where('event', 'merchant_recovery_started')->latest('id')->first());
    }

    public function test_superadmin_recovery_assigns_the_source_owner_and_never_the_superadmin(): void
    {
        Tenant::setCurrent($this->tenantA);

        $superadminRole = Role::create([
            'name' => 'superadmin',
            'guard_name' => 'web',
            'tenant_id' => $this->tenantA->id,
        ]);
        $superadminRole->syncPermissions(Permission::all());

        $superadmin = User::create([
            'name' => 'Recovery SuperAdmin',
            'email' => 'recovery-superadmin-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $superadmin->assignRole($superadminRole);

        $this->actingAs($superadmin);
        Tenant::setCurrent($this->tenantA);

        $this->assertTrue($superadmin->isSuperAdmin());
        $this->assertFalse($this->tenantA->isOwnedBy($superadmin));

        $archive = $this->downloadArchive();
        $this->uploadArchive($archive)->assertRedirect('/admin/backups/preview')->assertSessionHasNoErrors();

        $targetSlug = 'superadmin-recovery-' . strtolower(uniqid());

        $this->recover($targetSlug)->assertRedirect('/admin/backups/recovered')->assertSessionHasNoErrors();

        $recovered = Tenant::where('slug', $targetSlug)->firstOrFail();
        $this->assertSame('pending', $recovered->status);
        $this->assertFalse($recovered->canServePublicStorefront());

        $this->assertNull(User::where('tenant_id', $recovered->id)->where('id', $superadmin->id)->first());
        $this->assertFalse($recovered->isOwnedBy($superadmin));

        $owner = User::where('tenant_id', $recovered->id)->where('is_owner', true)->firstOrFail();
        $this->assertNotSame($superadmin->id, $owner->id);
        $this->assertStringContainsString('recovery-a-', $owner->email);
        $this->assertStringContainsString('+owner-' . $recovered->id, $owner->email);
        $this->assertTrue($owner->hasPermissionTo('backup.recover'));

        $this->get('/admin/backups/recovered')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/MerchantBackup/Recovered')
                ->where('result.owner_handoff_required', true)
                ->where('result.owner_mode', 'user'));
    }

    public function test_backup_routes_fail_closed_when_the_dedicated_permission_record_is_missing(): void
    {
        $this->actAsA();

        $this->get('/admin/backups')->assertOk();
        $this->get('/store/' . $this->tenantA->slug . '/admin/backups')->assertOk();

        Permission::whereIn('name', ['backup.view', 'backup.download', 'backup.verify', 'backup.recover'])->delete();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->get('/admin/backups')->assertForbidden();
        $this->get('/admin/backups/download')->assertForbidden();
        $this->get('/store/' . $this->tenantA->slug . '/admin/backups')->assertForbidden();
        $this->post('/admin/backups/verify', [])->assertForbidden();
        $this->post('/admin/backups/recover', [
            'confirmation' => $this->tenantA->slug,
            'target_slug' => 'missing-permission-store',
            'accept' => true,
        ])->assertForbidden();

        $this->assertNull(Tenant::where('slug', 'missing-permission-store')->first());
    }

    public function test_backup_routes_reject_users_holding_billing_view_only(): void
    {
        $this->actingAs($this->adminB);
        Tenant::setCurrent($this->tenantB);

        $this->assertTrue($this->adminB->can('billing.view'));
        $this->assertFalse($this->adminB->can('backup.view'));

        $this->get('/admin/backups')->assertForbidden();
        $this->get('/store/' . $this->tenantB->slug . '/admin/backups')->assertForbidden();
    }

    public function test_pending_store_is_hidden_from_the_public_storefront_and_transactions(): void
    {
        $this->tenantB->update(['status' => 'pending']);

        $slug = $this->tenantB->slug;
        $usersBefore = User::where('tenant_id', $this->tenantB->id)->count();

        $this->assertFalse($this->tenantB->fresh()->canServePublicStorefront());

        $this->get('/store/' . $slug)->assertNotFound();
        $this->get('/store/' . $slug . '/products')->assertNotFound();
        $this->get('/store/' . $slug . '/cart')->assertNotFound();
        $this->get('/store/' . $slug . '/register')->assertNotFound();
        $this->post('/store/' . $slug . '/register', [])->assertNotFound();
        $this->post('/store/' . $slug . '/checkout', [])->assertNotFound();
        $this->post('/store/' . $slug . '/checkout/quote', [])->assertNotFound();

        $this->assertSame($usersBefore, User::where('tenant_id', $this->tenantB->id)->count());
    }

    public function test_suspended_store_hides_operations_but_keeps_the_locked_page(): void
    {
        $this->tenantB->update(['status' => 'suspended']);

        $slug = $this->tenantB->slug;

        $this->assertNotNull($this->tenantB->fresh()->locked_at);
        $this->assertFalse($this->tenantB->fresh()->canServePublicStorefront());

        $this->get('/store/' . $slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Storefront/Locked'));

        $this->get('/store/' . $slug . '/products')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Storefront/Locked'));

        $this->post('/store/' . $slug . '/register', [])->assertNotFound();
        $this->post('/store/' . $slug . '/checkout', [])->assertNotFound();
    }

    public function test_active_and_trialing_stores_keep_serving_the_public_storefront(): void
    {
        $this->tenantA->update(['status' => 'active']);
        $this->assertTrue($this->tenantA->fresh()->canServePublicStorefront());
        $this->get('/store/' . $this->tenantA->slug)->assertOk();
        $this->get('/store/' . $this->tenantA->slug . '/products')->assertOk();

        $this->tenantA->update(['status' => 'trialing']);
        $this->assertTrue($this->tenantA->fresh()->canServePublicStorefront());
        $this->get('/store/' . $this->tenantA->slug)->assertOk();
    }

    public function test_a_recovered_store_stays_unreachable_until_it_is_activated(): void
    {
        $this->actAsA();

        $archive = $this->downloadArchive();
        $this->uploadArchive($archive)->assertRedirect('/admin/backups/preview')->assertSessionHasNoErrors();

        $targetSlug = 'pending-public-' . strtolower(uniqid());
        $this->recover($targetSlug)->assertRedirect('/admin/backups/recovered');

        $recovered = Tenant::where('slug', $targetSlug)->firstOrFail();
        $this->assertSame('pending', $recovered->status);

        $ordersBefore = \App\Models\Order::withoutTenantScope()->where('tenant_id', $recovered->id)->count();
        $this->assertGreaterThan(0, $ordersBefore);
        $usersBefore = User::where('tenant_id', $recovered->id)->count();

        auth()->logout();
        Tenant::setCurrent(null);

        $this->get('/store/' . $targetSlug)->assertNotFound();
        $this->get('/store/' . $targetSlug . '/products')->assertNotFound();
        $this->post('/store/' . $targetSlug . '/register', [])->assertNotFound();
        $this->post('/store/' . $targetSlug . '/checkout', [])->assertNotFound();

        $this->assertSame(
            $ordersBefore,
            \App\Models\Order::withoutTenantScope()->where('tenant_id', $recovered->id)->count()
        );
        $this->assertSame($usersBefore, User::where('tenant_id', $recovered->id)->count());
    }

    public function test_pending_paid_subscription_grants_no_service_and_activation_derives_a_bounded_period(): void
    {
        $paidPlan = $this->createPaidPlan();

        $tenant = Tenant::create([
            'name' => 'Entitlement Store ' . uniqid(),
            'slug' => 'entitlement-store-' . strtolower(uniqid()),
            'status' => 'active',
        ]);

        $subscription = Subscription::forceCreate([
            'tenant_id' => $tenant->id,
            'plan_id' => $paidPlan->id,
            'billing_interval' => 'monthly',
            'status' => 'pending',
            'starts_at' => null,
            'expires_at' => null,
        ]);

        $tenant->refresh();

        $this->assertTrue($tenant->isPubliclyVisible());
        $this->assertFalse($subscription->grantsService());
        $this->assertFalse($tenant->canServePublicStorefront());
        $this->get('/store/' . $tenant->slug)->assertNotFound();

        $subscription->activate();
        $subscription->refresh();

        $this->assertSame('active', $subscription->status);
        $this->assertNotNull($subscription->starts_at);
        $this->assertNotNull($subscription->expires_at);
        $this->assertTrue($subscription->expires_at->isFuture());
        $this->assertTrue($subscription->expires_at->lessThanOrEqualTo(now()->addMonth()->endOfDay()->addMinute()));
        $this->assertTrue($subscription->grantsService());
        $this->assertTrue($tenant->fresh()->canServePublicStorefront());

        $subscription->update(['expires_at' => now()->subDay()]);
        $this->assertFalse($subscription->fresh()->grantsService());
        $this->assertFalse($tenant->fresh()->canServePublicStorefront());
    }

    public function test_reactivating_a_suspended_undated_paid_subscription_derives_a_period(): void
    {
        $paidPlan = $this->createPaidPlan();

        $tenant = Tenant::create([
            'name' => 'Suspended Entitlement Store ' . uniqid(),
            'slug' => 'suspended-entitlement-' . strtolower(uniqid()),
            'status' => 'active',
        ]);

        $subscription = Subscription::forceCreate([
            'tenant_id' => $tenant->id,
            'plan_id' => $paidPlan->id,
            'billing_interval' => 'monthly',
            'status' => 'suspended',
            'starts_at' => null,
            'expires_at' => null,
            'suspended_at' => now()->subDay(),
        ]);

        $this->assertFalse($subscription->grantsService());

        $subscription->activate();
        $subscription->refresh();

        $this->assertSame('active', $subscription->status);
        $this->assertNull($subscription->suspended_at);
        $this->assertNotNull($subscription->expires_at);
        $this->assertTrue($subscription->expires_at->isFuture());
        $this->assertTrue($subscription->grantsService());
        $this->assertTrue($tenant->fresh()->canServePublicStorefront());
    }

    public function test_free_plan_subscription_stays_entitled_without_billing_dates(): void
    {
        $subscription = Subscription::forceCreate([
            'tenant_id' => $this->tenantB->id,
            'plan_id' => $this->plan->id,
            'billing_interval' => 'monthly',
            'status' => 'active',
            'starts_at' => null,
            'expires_at' => null,
        ]);

        $this->assertTrue($subscription->plan->isFree());
        $this->assertTrue($subscription->grantsService());

        $subscription->activate();
        $subscription->refresh();

        $this->assertSame('active', $subscription->status);
        $this->assertNull($subscription->expires_at);
        $this->assertTrue($subscription->grantsService());
    }

    public function test_recovery_rejects_users_without_permission_and_guests(): void
    {
        $targetSlug = 'forbidden-store-' . strtolower(uniqid());

        $this->post('/admin/backups/recover', [
            'confirmation' => $this->tenantA->slug,
            'target_slug' => $targetSlug,
            'accept' => true,
        ])->assertRedirect('/login');

        $this->assertNull(Tenant::where('slug', $targetSlug)->first());

        $this->actingAs($this->adminB);
        Tenant::setCurrent($this->tenantB);

        $this->post('/admin/backups/recover', [
            'confirmation' => $this->tenantB->slug,
            'target_slug' => $targetSlug,
            'accept' => true,
        ])->assertForbidden();

        $this->assertNull(Tenant::where('slug', $targetSlug)->first());
    }

    public function test_recovery_is_rejected_while_the_store_lock_is_held(): void
    {
        $this->actAsA();

        $archive = $this->downloadArchive();
        $this->uploadArchive($archive)->assertRedirect('/admin/backups/preview')->assertSessionHasNoErrors();

        $lock = \Illuminate\Support\Facades\Cache::lock('merchant-recovery:' . $this->tenantA->id, 60);
        $this->assertTrue($lock->get());

        $targetSlug = 'locked-store-' . strtolower(uniqid());
        $response = $this->recover($targetSlug);

        $response->assertRedirect();
        $response->assertSessionHasErrors('recovery');
        $this->assertNull(Tenant::where('slug', $targetSlug)->first());
        $this->assertAuditEvent('merchant_recovery_failed', 'recovery_in_progress');

        $lock->release();
    }

    public function test_preview_rejects_a_session_upload_from_another_tenant_directory(): void
    {
        $this->actAsA();

        $archive = $this->downloadArchive();
        $manifest = $this->manifestFrom($archive);
        $this->uploadArchive($archive)->assertRedirect('/admin/backups/preview')->assertSessionHasNoErrors();

        $files = $this->uploadFiles($this->tenantA);
        $this->assertCount(1, $files);

        $source = Storage::disk('local')->path('exports/' . $this->tenantA->id . '/uploads/' . $files[0]);
        $foreignDir = Storage::disk('local')->path('exports/' . $this->tenantB->id . '/uploads');
        File::ensureDirectoryExists($foreignDir);
        $foreign = $foreignDir . DIRECTORY_SEPARATOR . 'upload-foreign.zip';
        $this->assertTrue(copy($source, $foreign));

        $this->session([
            'merchant_backup_pending' => [
                'path' => $foreign,
                'name' => 'foreign.zip',
                'archive_id' => $manifest['archive_id'],
                'expires_at' => now()->addHour()->toIso8601String(),
            ],
        ]);

        $this->get('/admin/backups/preview')->assertRedirect('/admin/backups');
    }

    public function test_active_tenant_without_a_subscription_row_fails_closed_for_public_traffic(): void
    {
        $tenant = Tenant::create([
            'name' => 'No Subscription Store ' . uniqid(),
            'slug' => 'no-subscription-' . strtolower(uniqid()),
            'status' => 'active',
        ]);

        $tenant->refresh();

        Tenant::setCurrent($tenant);

        $this->assertNull($tenant->subscription);
        $this->assertTrue($tenant->isPubliclyVisible());
        $this->assertFalse($tenant->canServePublicStorefront());

        $slug = $tenant->slug;

        $this->get('/store/' . $slug)->assertNotFound();
        $this->get('/store/' . $slug . '/products')->assertNotFound();
        $this->get('/store/' . $slug . '/cart')->assertNotFound();
        $this->get('/store/' . $slug . '/register')->assertNotFound();
        $this->post('/store/' . $slug . '/register', [])->assertNotFound();
        $this->post('/store/' . $slug . '/checkout', [])->assertNotFound();
        $this->post('/store/' . $slug . '/checkout/quote', [])->assertNotFound();
    }

    public function test_expired_paid_subscription_cannot_serve_the_public_storefront(): void
    {
        $paidPlan = $this->createPaidPlan();

        $tenant = Tenant::create([
            'name' => 'Expired Entitlement Store ' . uniqid(),
            'slug' => 'expired-entitlement-' . strtolower(uniqid()),
            'status' => 'active',
        ]);

        Subscription::forceCreate([
            'tenant_id' => $tenant->id,
            'plan_id' => $paidPlan->id,
            'billing_interval' => 'monthly',
            'status' => 'active',
            'starts_at' => now()->subMonths(2),
            'expires_at' => now()->subDays(8),
        ]);

        $tenant->refresh();

        Tenant::setCurrent($tenant);

        $this->assertTrue($tenant->isPubliclyVisible());
        $this->assertFalse($tenant->canServePublicStorefront());
        $this->get('/store/' . $tenant->slug)->assertNotFound();
        $this->get('/store/' . $tenant->slug . '/products')->assertNotFound();
        $this->post('/store/' . $tenant->slug . '/checkout', [])->assertNotFound();
    }

    public function test_active_paid_and_free_plan_tenants_still_serve_the_public_storefront(): void
    {
        $paidPlan = $this->createPaidPlan();

        $paidTenant = Tenant::create([
            'name' => 'Paid Entitlement Store ' . uniqid(),
            'slug' => 'paid-entitlement-' . strtolower(uniqid()),
            'status' => 'active',
        ]);

        Subscription::forceCreate([
            'tenant_id' => $paidTenant->id,
            'plan_id' => $paidPlan->id,
            'billing_interval' => 'monthly',
            'status' => 'active',
            'starts_at' => now()->subWeek(),
            'expires_at' => now()->addWeeks(3),
        ]);

        Tenant::setCurrent($paidTenant);
        $this->assertTrue($paidTenant->fresh()->canServePublicStorefront());
        $this->get('/store/' . $paidTenant->slug)->assertOk();

        $freeTenant = Tenant::create([
            'name' => 'Free Entitlement Store ' . uniqid(),
            'slug' => 'free-entitlement-' . strtolower(uniqid()),
            'status' => 'active',
        ]);

        Subscription::forceCreate([
            'tenant_id' => $freeTenant->id,
            'plan_id' => $this->plan->id,
            'billing_interval' => 'monthly',
            'status' => 'active',
            'starts_at' => null,
            'expires_at' => null,
        ]);

        Tenant::setCurrent($freeTenant);
        $this->assertTrue($freeTenant->fresh()->canServePublicStorefront());
        $this->get('/store/' . $freeTenant->slug)->assertOk();
    }

    public function test_completed_recovery_claim_is_durable_across_cache_flush_and_restart(): void
    {
        $this->actAsA();

        $archive = $this->downloadArchive();
        $manifest = $this->manifestFrom($archive);

        $this->uploadArchive($archive)->assertRedirect('/admin/backups/preview')->assertSessionHasNoErrors();

        $firstSlug = 'durable-first-' . strtolower(uniqid());
        $this->recover($firstSlug)->assertRedirect('/admin/backups/recovered');

        $claim = MerchantRecoveryClaim::where('archive_id', $manifest['archive_id'])->first();
        $this->assertNotNull($claim);
        $this->assertSame(MerchantRecoveryClaim::STATUS_COMPLETED, $claim->status);
        $this->assertSame(
            Tenant::where('slug', $firstSlug)->value('id'),
            $claim->target_tenant_id
        );

        // A cache flush simulates a lock TTL expiry and an application restart:
        // the durable claim must still block a duplicate recovery.
        Cache::flush();

        $this->uploadArchive($archive)->assertRedirect('/admin/backups/preview')->assertSessionHasNoErrors();

        $secondSlug = 'durable-second-' . strtolower(uniqid());
        $response = $this->recover($secondSlug);

        $response->assertRedirect();
        $response->assertSessionHasErrors('recovery');
        $this->assertNull(Tenant::where('slug', $secondSlug)->first());
        $this->assertSame(1, MerchantRecoveryClaim::where('archive_id', $manifest['archive_id'])->count());
    }

    public function test_active_claim_lease_blocks_a_duplicate_recovery_without_the_cache_lock(): void
    {
        $this->actAsA();

        $archive = $this->downloadArchive();
        $manifest = $this->manifestFrom($archive);

        $this->uploadArchive($archive)->assertRedirect('/admin/backups/preview')->assertSessionHasNoErrors();

        MerchantRecoveryClaim::create([
            'archive_id' => $manifest['archive_id'],
            'source_tenant_id' => $this->tenantA->id,
            'target_slug' => 'lease-holder-' . strtolower(uniqid()),
            'archive_name' => null,
            'actor_type' => User::class,
            'actor_id' => $this->adminA->id,
            'status' => MerchantRecoveryClaim::STATUS_CLAIMED,
            'expires_at' => now()->addHour(),
        ]);

        $targetSlug = 'lease-blocked-' . strtolower(uniqid());
        $response = $this->recover($targetSlug);

        $response->assertRedirect();
        $response->assertSessionHasErrors('recovery');
        $this->assertNull(Tenant::where('slug', $targetSlug)->first());
        $this->assertAuditEvent('merchant_recovery_failed', 'recovery_in_progress');
    }

    public function test_expired_claim_lease_is_taken_over_and_the_recovery_completes(): void
    {
        $this->actAsA();

        $archive = $this->downloadArchive();
        $manifest = $this->manifestFrom($archive);

        $this->uploadArchive($archive)->assertRedirect('/admin/backups/preview')->assertSessionHasNoErrors();

        $stale = MerchantRecoveryClaim::create([
            'archive_id' => $manifest['archive_id'],
            'source_tenant_id' => $this->tenantA->id,
            'target_slug' => 'stale-lease-' . strtolower(uniqid()),
            'archive_name' => null,
            'actor_type' => User::class,
            'actor_id' => $this->adminA->id,
            'status' => MerchantRecoveryClaim::STATUS_CLAIMED,
            'expires_at' => now()->subHour(),
        ]);

        $targetSlug = 'lease-takeover-' . strtolower(uniqid());
        $this->recover($targetSlug)->assertRedirect('/admin/backups/recovered');

        $recovered = Tenant::where('slug', $targetSlug)->firstOrFail();

        $stale->refresh();
        $this->assertSame(MerchantRecoveryClaim::STATUS_COMPLETED, $stale->status);
        $this->assertSame($recovered->id, $stale->target_tenant_id);
        $this->assertSame($targetSlug, $stale->target_slug);
        $this->assertSame(1, MerchantRecoveryClaim::where('archive_id', $manifest['archive_id'])->count());
    }

    public function test_failed_recovery_marks_the_claim_failed_and_allows_a_retry(): void
    {
        $this->actAsA();

        $brokenArchive = $this->downloadArchive();
        $this->makeInventoryLevelInconsistent($brokenArchive);
        $brokenManifest = $this->manifestFrom($brokenArchive);

        $this->uploadArchive($brokenArchive)->assertRedirect('/admin/backups/preview')->assertSessionHasNoErrors();

        $failedSlug = 'claim-failed-' . strtolower(uniqid());
        $this->recover($failedSlug)->assertSessionHasErrors('recovery');

        $this->assertNull(Tenant::where('slug', $failedSlug)->first());

        $failedClaim = MerchantRecoveryClaim::where('archive_id', $brokenManifest['archive_id'])->first();
        $this->assertNotNull($failedClaim);
        $this->assertSame(MerchantRecoveryClaim::STATUS_FAILED, $failedClaim->status);
        $this->assertNull($failedClaim->target_tenant_id);

        $freshArchive = $this->downloadArchive();
        $this->uploadArchive($freshArchive)->assertRedirect('/admin/backups/preview')->assertSessionHasNoErrors();

        $retrySlug = 'claim-retry-' . strtolower(uniqid());
        $this->recover($retrySlug)->assertRedirect('/admin/backups/recovered');

        $this->assertSame(MerchantRecoveryClaim::STATUS_FAILED, $failedClaim->fresh()->status);
        $this->assertNotNull(Tenant::where('slug', $retrySlug)->first());
    }

    public function test_importer_resets_state_between_runs_and_rejects_stale_counts(): void
    {
        $this->actAsA();

        $archive = $this->downloadArchive();
        $manifest = $this->manifestFrom($archive);

        $bootstrap = app(TenantBootstrapService::class);
        $importer = app(MerchantRecoveryImporter::class);

        $first = Tenant::create([
            'name' => 'Importer Run One ' . uniqid(),
            'slug' => 'importer-run-one-' . strtolower(uniqid()),
            'status' => 'pending',
        ]);
        $bootstrap->ensureCustomerRole($first);

        $firstResult = $importer->import($archive, $first->id, $manifest);
        $importer->verify($manifest);

        $counter = new \ReflectionProperty($importer, 'emailCounter');
        $counter->setAccessible(true);
        $afterFirstRun = (int) $counter->getValue($importer);

        $second = Tenant::create([
            'name' => 'Importer Run Two ' . uniqid(),
            'slug' => 'importer-run-two-' . strtolower(uniqid()),
            'status' => 'pending',
        ]);
        $bootstrap->ensureCustomerRole($second);

        $secondResult = $importer->import($archive, $second->id, $manifest);

        $this->assertSame($firstResult['counts'], $secondResult['counts']);
        $this->assertNotSame($firstResult['placeholder_identity_id'], $secondResult['placeholder_identity_id']);
        $this->assertSame($afterFirstRun, (int) $counter->getValue($importer));

        $secondEmails = DB::table('users')
            ->where('tenant_id', $second->id)
            ->where('email', 'like', '%+customer-%')
            ->pluck('email')
            ->all();

        $this->assertNotEmpty($secondEmails);

        foreach ($secondEmails as $email) {
            $this->assertStringContainsString('-t' . $second->id . '-', $email);
            $this->assertStringNotContainsString('-t' . $first->id . '-', $email);
        }

        $importer->verify($manifest);
    }

    public function test_two_recoveries_of_the_same_source_produce_distinct_customer_aliases(): void
    {
        $this->actAsA();

        $firstArchive = $this->downloadArchive();
        $this->uploadArchive($firstArchive)->assertRedirect('/admin/backups/preview')->assertSessionHasNoErrors();

        $firstSlug = 'alias-first-' . strtolower(uniqid());
        $this->recover($firstSlug)->assertRedirect('/admin/backups/recovered');

        $secondArchive = $this->downloadArchive();
        $this->uploadArchive($secondArchive)->assertRedirect('/admin/backups/preview')->assertSessionHasNoErrors();

        $secondSlug = 'alias-second-' . strtolower(uniqid());
        $this->recover($secondSlug)->assertRedirect('/admin/backups/recovered');

        $first = Tenant::where('slug', $firstSlug)->firstOrFail();
        $second = Tenant::where('slug', $secondSlug)->firstOrFail();

        $firstEmails = DB::table('users')->where('tenant_id', $first->id)
            ->where('email', 'like', '%+customer-%')->pluck('email')->all();
        $secondEmails = DB::table('users')->where('tenant_id', $second->id)
            ->where('email', 'like', '%+customer-%')->pluck('email')->all();

        $this->assertNotEmpty($firstEmails);
        $this->assertNotEmpty($secondEmails);
        $this->assertSame([], array_values(array_intersect($firstEmails, $secondEmails)));

        foreach ($secondEmails as $email) {
            $this->assertStringContainsString('-t' . $second->id . '-', $email);
        }

        foreach ($firstEmails as $email) {
            $this->assertStringContainsString('-t' . $first->id . '-', $email);
        }
    }

    public function test_recovery_result_is_scoped_to_the_actor_and_store(): void
    {
        $this->actAsA();

        $archive = $this->downloadArchive();
        $this->uploadArchive($archive)->assertRedirect('/admin/backups/preview')->assertSessionHasNoErrors();

        $targetSlug = 'scoped-result-' . strtolower(uniqid());
        $this->recover($targetSlug)->assertRedirect('/admin/backups/recovered');

        $payload = session('merchant_backup_recovery_result');
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('scope', $payload);
        $this->assertSame($this->tenantA->id, $payload['scope']['tenant_id']);
        $this->assertSame($this->adminA->id, (int) $payload['scope']['actor_id']);
        $this->assertArrayNotHasKey('password', $payload['result']);
        $this->assertArrayNotHasKey('credentials', $payload['result']);

        $this->get('/admin/backups/recovered')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/MerchantBackup/Recovered')
                ->where('result.tenant_slug', $targetSlug));

        $staff = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Scoped Result Staff',
            'email' => 'scoped-result-staff-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
            'status' => 'active',
            'is_owner' => false,
        ]);
        $staff->givePermissionTo(['billing.view', 'backup.view']);

        $this->actingAs($staff);
        Tenant::setCurrent($this->tenantA);
        $this->get('/admin/backups/recovered')->assertRedirect('/admin/backups');

        $this->actingAs($this->adminA);
        Tenant::setCurrent($this->tenantB);
        $this->get('/admin/backups/recovered')->assertRedirect('/admin/backups');
    }

    public function test_account_mode_recovery_hands_ownership_to_the_source_owner_account(): void
    {
        config(['identity.use_accounts' => true]);

        $source = Tenant::create([
            'name' => 'Account Owner Source ' . uniqid(),
            'slug' => 'account-owner-source-' . strtolower(uniqid()),
            'status' => 'active',
        ]);

        $account = Account::create([
            'name' => 'Account Owner',
            'email' => 'account-owner-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
            'status' => Account::STATUS_ACTIVE,
        ]);

        $sourceAdminRole = Role::withoutTenantScope()->firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
            'tenant_id' => $source->id,
        ]);

        TenantMembership::create([
            'account_id' => $account->id,
            'tenant_id' => $source->id,
            'role_id' => $sourceAdminRole->id,
            'is_owner' => true,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $recovered = Tenant::create([
            'name' => 'Account Owner Recovered ' . uniqid(),
            'slug' => 'account-owner-recovered-' . strtolower(uniqid()),
            'status' => 'pending',
        ]);

        $result = app(TenantBootstrapService::class)->bootstrapRecovery($recovered, $source, null, $this->plan);

        $this->assertInstanceOf(Account::class, $result['owner']);
        $this->assertSame($account->id, $result['owner']->id);
        $this->assertFalse($result['handoff_required']);

        $membership = TenantMembership::where('tenant_id', $recovered->id)
            ->where('account_id', $account->id)
            ->first();

        $this->assertNotNull($membership);
        $this->assertTrue($membership->is_owner);
        $this->assertSame('active', $membership->status);
    }

    private function actAsA(): void
    {
        $this->actingAs($this->adminA);
        Tenant::setCurrent($this->tenantA);
    }

    private function createPaidPlan(): Plan
    {
        return Plan::create([
            'name' => 'Recovery Paid Plan ' . uniqid(),
            'slug' => 'recovery-paid-plan-' . uniqid(),
            'monthly_price' => 49000,
            'yearly_price' => 490000,
            'product_limit' => 100,
            'status' => 'active',
        ]);
    }

    private function assertRecoveryRejectedByPlanLimit(string $column): void
    {
        $this->actAsA();

        $this->plan->update([$column => 0]);

        $archive = $this->downloadArchive();
        $this->uploadArchive($archive)->assertRedirect('/admin/backups/preview')->assertSessionHasNoErrors();

        $sourceSnapshot = $this->sourceSnapshot();
        $targetSlug = str_replace('_', '-', str_replace('_limit', '', $column)) . '-limited-' . strtolower(uniqid());

        $response = $this->recover($targetSlug);

        $response->assertRedirect();
        $response->assertSessionHasErrors('recovery');

        $this->assertNull(Tenant::where('slug', $targetSlug)->first());
        $this->assertSame($sourceSnapshot, $this->sourceSnapshot());
        $this->assertAuditEvent('merchant_recovery_failed', 'subscription_limit');
    }

    private function archiveDirectory(): string
    {
        $dir = storage_path('framework/testing/merchant-recovery');
        File::ensureDirectoryExists($dir);

        return $dir;
    }

    private function tempArchivePath(): string
    {
        return $this->archiveDirectory() . DIRECTORY_SEPARATOR . 'archive-' . bin2hex(random_bytes(8)) . '.zip';
    }

    private function downloadArchive(): string
    {
        $response = $this->get('/admin/backups/download')->assertOk();
        $source = $response->getFile()->getPathname();
        $copy = $this->tempArchivePath();

        $this->assertTrue(copy($source, $copy), 'Unable to copy the backup archive.');
        @unlink($source);

        return $copy;
    }

    private function uploadArchive(string $path)
    {
        $copy = $this->tempArchivePath();
        $this->assertTrue(copy($path, $copy), 'Unable to copy the archive for upload.');

        return $this->post('/admin/backups/verify', [
            'archive' => new UploadedFile($copy, 'store-backup.zip', 'application/zip', null, true),
        ]);
    }

    private function recover(string $targetSlug)
    {
        return $this->post('/admin/backups/recover', [
            'confirmation' => Tenant::getCurrent()?->slug ?? $this->tenantA->slug,
            'target_slug' => $targetSlug,
            'accept' => true,
        ]);
    }

    private function manifestFrom(string $path): array
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path) === true);
        $json = $zip->getFromName('manifest.json');
        $zip->close();

        $manifest = json_decode((string) $json, true);
        $this->assertIsArray($manifest);

        return $manifest;
    }

    private function rewriteEntry(string $path, string $entry, string $contents): void
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path) === true);
        $zip->deleteName($entry);
        $this->assertTrue($zip->addFromString($entry, $contents));
        $zip->close();
    }

    private function rewriteSignedEntry(string $path, string $entry): void
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path) === true);
        $contents = (string) $zip->getFromName($entry);
        $zip->close();

        $manifest = $this->manifestFrom($path);
        $manifest['files'][$entry]['sha256'] = hash('sha256', $contents);
        $manifest['files'][$entry]['bytes'] = strlen($contents);
        $manifest['signature'] = MerchantBackupSigner::fromConfig()->sign($manifest);

        $this->rewriteEntry(
            $path,
            'manifest.json',
            (string) json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    private function mutateCsvEntry(string $path, string $entry, callable $mutate): void
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path) === true);
        $contents = (string) $zip->getFromName($entry);
        $zip->close();

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $contents);
        rewind($stream);

        $rows = [];

        while (($row = fgetcsv($stream, 0, ',', '"', '')) !== false) {
            $rows[] = $row;
        }

        fclose($stream);

        $this->assertNotEmpty($rows);
        $mutate($rows);

        $stream = fopen('php://temp', 'r+');

        foreach ($rows as $row) {
            fputcsv($stream, $row, ',', '"', '');
        }

        rewind($stream);
        $updated = (string) stream_get_contents($stream);
        fclose($stream);

        $this->rewriteEntry($path, $entry, $updated);
    }

    private function makeInventoryLevelInconsistent(string $path): void
    {
        $this->mutateCsvEntry($path, 'inventory_levels.csv', function (array &$rows): void {
            $this->assertArrayHasKey(1, $rows);
            $rows[1][7] = (string) ((float) $rows[1][7] + 5);
        });

        $this->rewriteSignedEntry($path, 'inventory_levels.csv');
    }

    private function tamperRetainedUpload(): void
    {
        $files = $this->uploadFiles($this->tenantA);
        $this->assertCount(1, $files);

        $path = Storage::disk('local')->path('exports/' . $this->tenantA->id . '/uploads/' . $files[0]);

        $this->mutateCsvEntry($path, 'inventory_levels.csv', function (array &$rows): void {
            $rows[1][7] = (string) ((float) $rows[1][7] + 9);
        });
    }

    private function uploadFiles(Tenant $tenant): array
    {
        $dir = Storage::disk('local')->path('exports/' . $tenant->id . '/uploads');

        return array_values(array_map(
            fn (string $file) => basename($file),
            glob($dir . DIRECTORY_SEPARATOR . 'upload-*.zip') ?: []
        ));
    }

    private function sourceSnapshot(): array
    {
        $tables = [
            'products',
            'product_variants',
            'product_combos',
            'categories',
            'brands',
            'units',
            'stock_movements',
            'orders',
            'order_items',
            'order_coupon',
            'customer_addresses',
            'wishlists',
            'coupons',
            'promotions',
            'promotion_usages',
            'flash_sales',
            'delivery_services',
            'warehouses',
            'payment_methods',
            'cities',
            'townships',
            'storefronts',
            'storefront_media',
            'website_faqs',
            'website_infos',
            'settings',
        ];

        $snapshot = [
            'users' => DB::table('users')->where('tenant_id', $this->tenantA->id)->count(),
        ];

        foreach ($tables as $table) {
            $snapshot[$table] = DB::table($table)->where('tenant_id', $this->tenantA->id)->count();
        }

        return $snapshot;
    }

    private function assertRecoveredCounts(Tenant $recovered, array $manifest): void
    {
        $expected = $manifest['record_counts'];

        $mapping = [
            'products' => 'products',
            'product_variants' => 'product_variants',
            'categories' => 'categories',
            'brands' => 'brands',
            'orders' => 'orders',
            'order_items' => 'order_items',
            'customer_addresses' => 'customer_addresses',
            'wishlists' => 'wishlists',
            'coupons' => 'coupons',
            'promotions' => 'promotions',
            'flash_sales' => 'flash_sales',
            'storefronts' => 'storefronts',
            'storefront_media' => 'storefront_media',
            'website_faqs' => 'website_faqs',
            'settings' => 'settings',
            'import_histories' => 'import_histories',
        ];

        foreach ($mapping as $category => $table) {
            $this->assertSame(
                (int) $expected[$category],
                DB::table($table)->where('tenant_id', $recovered->id)->count(),
                'Unexpected recovered row count for ' . $table
            );
        }

        $this->assertSame(
            (int) $expected['customers'],
            DB::table('users')
                ->join('model_has_roles', 'model_has_roles.model_id', '=', 'users.id')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('users.tenant_id', $recovered->id)
                ->where('roles.tenant_id', $recovered->id)
                ->where('roles.name', 'customer')
                ->where('users.name', '!=', 'Recovered Order Identity')
                ->count()
        );
    }

    private function assertIdsWereRemapped(Tenant $recovered): void
    {
        $sourceProductIds = DB::table('products')->where('tenant_id', $this->tenantA->id)->pluck('id')->all();
        $recoveredProducts = DB::table('products')->where('tenant_id', $recovered->id)->get();

        $this->assertCount(3, $recoveredProducts);
        $this->assertSame([], array_intersect($sourceProductIds, $recoveredProducts->pluck('id')->all()));

        $sourceCategoryIds = DB::table('categories')->where('tenant_id', $this->tenantA->id)->pluck('id')->all();
        $recoveredCategories = DB::table('categories')->where('tenant_id', $recovered->id)->get();

        $this->assertCount(2, $recoveredCategories);
        $this->assertSame([], array_intersect($sourceCategoryIds, $recoveredCategories->pluck('id')->all()));

        $parent = $recoveredCategories->firstWhere('name', 'Drill Parent Category ' . $this->fixture->marker);
        $child = $recoveredCategories->firstWhere('name', 'Drill Child Category ' . $this->fixture->marker);

        $this->assertNotNull($parent);
        $this->assertNotNull($child);
        $this->assertSame($parent->id, $child->parent_id);

        $simple = $recoveredProducts->firstWhere('name', 'Drill Simple Product ' . $this->fixture->marker);
        $this->assertNotNull($simple);
        $this->assertSame($child->id, $simple->category_id);

        $recoveredOrder = DB::table('orders')->where('tenant_id', $recovered->id)->first();
        $this->assertNotNull($recoveredOrder);
        $this->assertNotSame($this->fixture->order, $recoveredOrder->id);

        $itemProductIds = DB::table('order_items')->where('tenant_id', $recovered->id)->pluck('product_id')->all();
        $this->assertCount(2, $itemProductIds);

        foreach ($itemProductIds as $productId) {
            $this->assertContains($productId, $recoveredProducts->pluck('id')->all());
            $this->assertNotContains($productId, $sourceProductIds);
        }

        $recoveredMovementNumbers = DB::table('stock_movements')->where('tenant_id', $recovered->id)->pluck('adjustment_number');
        foreach ($recoveredMovementNumbers as $number) {
            $this->assertStringStartsWith('R' . $recovered->id . '-', $number);
        }
    }

    private function assertInventoryAndIdentities(Tenant $recovered): void
    {
        $simpleId = DB::table('products')
            ->where('tenant_id', $recovered->id)
            ->where('name', 'Drill Simple Product ' . $this->fixture->marker)
            ->value('id');

        $variantId = DB::table('product_variants')
            ->where('tenant_id', $recovered->id)
            ->where('sku', DB::table('product_variants')->where('id', $this->fixture->variant)->value('sku'))
            ->value('id');

        $this->assertNotNull($simpleId);
        $this->assertNotNull($variantId);

        $simpleMovementSum = (float) DB::table('stock_movements')
            ->where('tenant_id', $recovered->id)
            ->where('product_id', $simpleId)
            ->sum('quantity');

        $this->assertSame(7.0, $simpleMovementSum);

        $variantMovementSum = (float) DB::table('stock_movements')
            ->where('tenant_id', $recovered->id)
            ->where('product_variant_id', $variantId)
            ->sum('quantity');

        $this->assertSame(4.0, $variantMovementSum);

        $order = DB::table('orders')->where('tenant_id', $recovered->id)->first();
        $placeholder = DB::table('users')
            ->where('tenant_id', $recovered->id)
            ->where('name', 'Recovered Order Identity')
            ->first();

        $this->assertNotNull($placeholder);
        $this->assertSame($placeholder->id, $order->user_id);

        $owner = DB::table('users')
            ->where('tenant_id', $recovered->id)
            ->where('is_owner', 1)
            ->first();

        $this->assertNotNull($owner);
        $this->assertStringContainsString('+owner-' . $recovered->id, $owner->email);

        $aliasedCustomer = DB::table('users')
            ->where('tenant_id', $recovered->id)
            ->where('email', 'like', 'customer-a-%+customer-%@example.test')
            ->exists();

        $this->assertTrue($aliasedCustomer);

        $this->assertSame(
            0,
            DB::table('products')->where('tenant_id', $recovered->id)
                ->whereIn('id', DB::table('products')->where('tenant_id', $this->tenantA->id)->pluck('id'))
                ->count()
        );
    }

    private function assertAuditEvent(string $event, ?string $reason = null): void
    {
        $log = ActivityLog::where('event', $event)->latest('id')->first();

        $this->assertNotNull($log, 'Missing audit event: ' . $event);

        if ($reason !== null) {
            $this->assertSame($reason, $log->properties['reason'] ?? null);
        }
    }
}
