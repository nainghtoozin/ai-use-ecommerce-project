<?php

namespace Tests\Feature;

use App\Exceptions\TenantFileCollectionException;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantCleanup;
use App\Models\User;
use App\Services\ImageService;
use App\Services\SubscriptionExpiryService;
use App\Services\TenantDataPurgeService;
use App\Services\TenantDeletionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TenantRetentionSafetyTest extends TestCase
{
    use DatabaseTransactions;

    private Plan $paidPlan;
    private Tenant $tenant;
    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSafetySchema();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['billing.view', 'billing.manage', 'billing.renew'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }
        $role = \App\Models\Role::withoutTenantScope()->firstOrCreate([
            'name' => 'admin', 'guard_name' => 'web', 'tenant_id' => null,
        ]);
        $role->syncPermissions(['billing.view', 'billing.manage', 'billing.renew']);

        $this->paidPlan = $this->makePlan('safeplan', 10, 100);

        $this->tenant = Tenant::create([
            'slug' => 'safe-' . uniqid(),
            'name' => 'Safety Shop',
            'status' => 'active',
        ]);

        $this->subscription = $this->makeSubscription($this->tenant, $this->paidPlan, 'active', now()->addDays(30));

        config(['billing.tenant_cleanup.purge_enabled' => false]);
    }

    protected function tearDown(): void
    {
        try {
            $ids = Tenant::where('slug', 'like', 'safe-%')->pluck('id')->map(fn ($id) => (int) $id)->all();

            if ($ids !== []) {
                foreach (['products', 'product_variants', 'categories', 'brands', 'orders', 'order_items', 'customer_addresses', 'cities', 'townships', 'delivery_services', 'delivery_pricing', 'subscriptions', 'subscription_cancellation_requests', 'tenant_cleanups', 'invoices', 'subscription_audit_logs', 'payment_intents', 'subscription_payments', 'users', 'website_infos', 'storefront_media', 'import_histories', 'team_invitations', 'wishlists', 'messages', 'settings', 'coupon_category', 'coupon_product', 'promotion_category', 'promotion_product', 'coupons', 'promotions'] as $table) {
                    try {
                        if (Schema::hasTable($table) && Schema::hasColumn($table, 'tenant_id')) {
                            DB::table($table)->whereIn('tenant_id', $ids)->delete();
                        }
                    } catch (\Throwable $e) {
                    }
                }

                try {
                    if (Schema::hasTable('products') && Schema::hasColumn('products', 'photo1')) {
                        DB::table('products')->where('photo1', 'like', '%res.cloudinary.com/demo%')->delete();
                    }
                } catch (\Throwable $e) {
                }

                Tenant::whereIn('id', $ids)->delete();

                try {
                    if (Schema::hasTable('plan_features') && Schema::hasTable('plans')) {
                        $planIds = DB::table('plans')->where('slug', 'like', 'safeplan-%')->pluck('id');
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

    public function test_deleted_tenant_cannot_start_checkout(): void
    {
        $this->tenant->update(['status' => 'deleted']);

        $this->expectException(\InvalidArgumentException::class);

        app(\App\Services\Payment\Platform\CheckoutService::class)->initiateCheckout(
            tenant: $this->tenant->fresh(),
            plan: $this->paidPlan,
            billingCycle: 'monthly',
            amount: 10.0,
            currency: \App\Data\Currency::fromCode('MMK'),
            gateway: 'manual',
        );
    }

    public function test_completed_payment_for_deleted_store_leaves_subscription_unchanged(): void
    {
        $this->tenant->update(['status' => 'deleted']);
        $this->subscription->update(['status' => 'expired', 'expires_at' => now()->subDays(40)]);

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

        app(\App\Services\SubscriptionLifecycleService::class)->handleCompletedPayment($intent->fresh());

        $this->assertSame('expired', $this->subscription->fresh()->status);
        $this->assertSame('deleted', $this->tenant->fresh()->status);
        $this->assertDatabaseHas('subscription_audit_logs', [
            'subscription_id' => $this->subscription->id,
            'event' => 'renewal_blocked_tenant_deleted',
        ]);
        $this->assertNotEmpty($intent->fresh()->metadata['subscription_applied'] ?? null);
    }

    public function test_expiry_service_skips_deleted_tenants(): void
    {
        $this->tenant->update(['status' => 'deleted']);
        $this->subscription->update(['status' => 'expired', 'expires_at' => now()->subDays(40)]);
        DB::table('subscriptions')->where('id', $this->subscription->id)->update(['updated_at' => now()->subDays(40)]);

        $live = Tenant::create(['slug' => 'safe-live-' . uniqid(), 'name' => 'Live', 'status' => 'active']);
        $liveSub = $this->makeSubscription($live, $this->paidPlan, 'expired', now()->subDays(40));
        DB::table('subscriptions')->where('id', $liveSub->id)->update(['updated_at' => now()->subDays(40)]);

        $result = app(SubscriptionExpiryService::class)->process();

        $this->assertSame('expired', $this->subscription->fresh()->status);
        $this->assertSame('deleted', $this->tenant->fresh()->status);
        $this->assertSame('suspended', $liveSub->fresh()->status);
        $this->assertSame('suspended', $live->fresh()->status);
        $this->assertSame(1, $result['expired_to_suspended']);
    }

    public function test_superadmin_renewal_refused_for_deleted_tenant(): void
    {
        $this->actingAs($this->makeSuperadmin());

        $this->tenant->update(['status' => 'deleted']);
        $this->subscription->update(['status' => 'expired', 'expires_at' => now()->subDays(40)]);

        $this->post("/superadmin/subscriptions/{$this->subscription->id}/renew-from-interval", [])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('expired', $this->subscription->fresh()->status);
    }

    public function test_cloudinary_public_id_extraction(): void
    {
        $this->assertSame(
            'products/abc123',
            ImageService::cloudinaryPublicId('https://res.cloudinary.com/demo/image/upload/v123/products/abc123.jpg')
        );
        $this->assertNull(ImageService::cloudinaryPublicId('products/local.jpg'));
        $this->assertNull(ImageService::cloudinaryPublicId('https://example.com/no-upload/photo.jpg'));
        $this->assertNull(ImageService::cloudinaryPublicId(null));

        config()->set('filesystems.disks.cloudinary.cloud_name', null);
        config()->set('filesystems.disks.cloudinary.url', null);
        $this->assertFalse(ImageService::cloudinaryConfigured());
    }

    public function test_unconfigured_cloudinary_asset_blocks_finalization(): void
    {
        config(['billing.tenant_cleanup.purge_enabled' => true]);
        config()->set('filesystems.disks.cloudinary.cloud_name', null);
        config()->set('filesystems.disks.cloudinary.url', null);
        $this->setPlatformSetting('tenant_auto_delete_enabled', true);

        $this->makePendingDeletion($this->tenant, $this->subscription);

        $url = 'https://res.cloudinary.com/demo/image/upload/v1/products/tenant-only-' . uniqid() . '.jpg';
        DB::table('products')->insert([
            'tenant_id' => $this->tenant->id, 'category_id' => $this->makeCategory($this->tenant),
            'type' => 'single', 'name' => 'Cloud', 'sku' => 'C-1', 'price' => 1, 'stock' => 1,
            'status' => 'active', 'photo1' => $url, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $result = app(TenantDataPurgeService::class)->process(false);

        $this->assertSame(0, $result['purged']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(0, $result['errors']);

        $cleanup = TenantCleanup::where('tenant_id', $this->tenant->id)->first();
        $this->assertSame(TenantCleanup::STATUS_PENDING_DELETION, $cleanup->status);
        $this->assertSame(TenantCleanup::DELETION_FAILED, $cleanup->deletion_status);
        $this->assertSame(1, $cleanup->attempts);
        $this->assertContains($url, $cleanup->metadata['cloudinary_skipped'] ?? []);
        $this->assertContains($url, $cleanup->metadata['failed_files'] ?? []);
        $this->assertContains($url, $cleanup->metadata['pending_files'] ?? []);
        $this->assertTrue($cleanup->metadata['cloudinary_unconfigured'] ?? false);
        $this->assertNotEmpty($cleanup->last_error);
        $this->assertNotSame('deleted', $this->tenant->fresh()->status);
        $this->assertDatabaseMissing('products', ['tenant_id' => $this->tenant->id, 'photo1' => $url]);
    }

    public function test_local_only_tenant_purges_when_cloudinary_unconfigured(): void
    {
        config(['filesystems.default' => 'public']);
        Storage::fake('public');

        config(['billing.tenant_cleanup.purge_enabled' => true]);
        config()->set('filesystems.disks.cloudinary.cloud_name', null);
        config()->set('filesystems.disks.cloudinary.url', null);
        $this->setPlatformSetting('tenant_auto_delete_enabled', true);

        $this->makePendingDeletion($this->tenant, $this->subscription);

        Storage::disk('public')->put('tenant-uploads/local/only.jpg', 'x');

        DB::table('products')->insert([
            'tenant_id' => $this->tenant->id, 'category_id' => $this->makeCategory($this->tenant),
            'type' => 'single', 'name' => 'Local', 'sku' => 'LO-1', 'price' => 1, 'stock' => 1,
            'status' => 'active', 'photo1' => 'tenant-uploads/local/only.jpg',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertTrue(app(TenantDataPurgeService::class)->purgeTenant($this->tenant->id, false));

        Storage::disk('public')->assertMissing('tenant-uploads/local/only.jpg');

        $cleanup = TenantCleanup::where('tenant_id', $this->tenant->id)->first();
        $this->assertSame(TenantCleanup::STATUS_DELETED, $cleanup->status);
        $this->assertSame(TenantCleanup::DELETION_DELETED, $cleanup->deletion_status);
        $this->assertArrayNotHasKey('cloudinary_skipped', $cleanup->metadata ?? []);
        $this->assertArrayNotHasKey('cloudinary_unconfigured', $cleanup->metadata ?? []);
        $this->assertArrayNotHasKey('failed_files', $cleanup->metadata ?? []);
    }

    public function test_successful_remote_deletion_finalizes_with_clean_metadata(): void
    {
        config(['billing.tenant_cleanup.purge_enabled' => true]);
        config()->set('filesystems.disks.cloudinary.cloud_name', 'demo');
        config()->set('filesystems.disks.cloudinary.url', null);
        $this->setPlatformSetting('tenant_auto_delete_enabled', true);

        $this->makePendingDeletion($this->tenant, $this->subscription);

        $url = 'https://res.cloudinary.com/demo/image/upload/v1/products/ok-' . uniqid() . '.jpg';
        DB::table('products')->insert([
            'tenant_id' => $this->tenant->id, 'category_id' => $this->makeCategory($this->tenant),
            'type' => 'single', 'name' => 'Ok', 'sku' => 'OK-1', 'price' => 1, 'stock' => 1,
            'status' => 'active', 'photo1' => $url, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $publicId = ImageService::cloudinaryPublicId($url);
        $admin = new \Tests\Unit\FakeCloudinaryAdminApi();
        $admin->existing[$publicId] = 'image';
        $upload = new \Tests\Unit\FakeCloudinaryUploadApi($admin);
        $client = new \Tests\Unit\FakeCloudinaryClient($admin, $upload);

        ImageService::$cloudinaryFactory = fn () => $client;

        try {
            $this->assertTrue(app(TenantDataPurgeService::class)->purgeTenant($this->tenant->id, false));
        } finally {
            ImageService::$cloudinaryFactory = null;
        }

        $this->assertArrayNotHasKey($publicId, $admin->existing);
        $this->assertSame(1, $upload->destroyCalls);

        $cleanup = TenantCleanup::where('tenant_id', $this->tenant->id)->first();
        $this->assertSame(TenantCleanup::STATUS_DELETED, $cleanup->status);
        $this->assertSame(TenantCleanup::DELETION_DELETED, $cleanup->deletion_status);
        $this->assertArrayNotHasKey('failed_files', $cleanup->metadata ?? []);
        $this->assertArrayNotHasKey('pending_files', $cleanup->metadata ?? []);
        $this->assertArrayNotHasKey('cloudinary_skipped', $cleanup->metadata ?? []);
        $this->assertArrayNotHasKey('cloudinary_unconfigured', $cleanup->metadata ?? []);
    }

    public function test_already_absent_remote_asset_does_not_block_finalization(): void
    {
        config(['billing.tenant_cleanup.purge_enabled' => true]);
        config()->set('filesystems.disks.cloudinary.cloud_name', 'demo');
        config()->set('filesystems.disks.cloudinary.url', null);
        $this->setPlatformSetting('tenant_auto_delete_enabled', true);

        $this->makePendingDeletion($this->tenant, $this->subscription);

        $url = 'https://res.cloudinary.com/demo/image/upload/v1/products/gone-' . uniqid() . '.jpg';
        DB::table('products')->insert([
            'tenant_id' => $this->tenant->id, 'category_id' => $this->makeCategory($this->tenant),
            'type' => 'single', 'name' => 'Gone', 'sku' => 'GN-1', 'price' => 1, 'stock' => 1,
            'status' => 'active', 'photo1' => $url, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $admin = new \Tests\Unit\FakeCloudinaryAdminApi();
        $upload = new \Tests\Unit\FakeCloudinaryUploadApi($admin);
        $client = new \Tests\Unit\FakeCloudinaryClient($admin, $upload);

        ImageService::$cloudinaryFactory = fn () => $client;

        try {
            $this->assertTrue(app(TenantDataPurgeService::class)->purgeTenant($this->tenant->id, false));
        } finally {
            ImageService::$cloudinaryFactory = null;
        }

        $this->assertSame(0, $upload->destroyCalls);

        $cleanup = TenantCleanup::where('tenant_id', $this->tenant->id)->first();
        $this->assertSame(TenantCleanup::DELETION_DELETED, $cleanup->deletion_status);
        $this->assertArrayNotHasKey('failed_files', $cleanup->metadata ?? []);
    }

    public function test_shared_cloudinary_assets_are_protected_from_deletion(): void
    {
        config(['billing.tenant_cleanup.purge_enabled' => true]);
        config()->set('filesystems.disks.cloudinary.cloud_name', 'demo');
        config()->set('filesystems.disks.cloudinary.url', null);
        $this->setPlatformSetting('tenant_auto_delete_enabled', true);

        $this->makePendingDeletion($this->tenant, $this->subscription);

        $url = 'https://res.cloudinary.com/demo/image/upload/v1/defaults/logo-' . uniqid() . '.png';
        DB::table('products')->insert([
            'tenant_id' => $this->tenant->id, 'category_id' => $this->makeCategory($this->tenant),
            'type' => 'single', 'name' => 'Shared', 'sku' => 'SH-1', 'price' => 1, 'stock' => 1,
            'status' => 'active', 'photo1' => $url, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $publicId = ImageService::cloudinaryPublicId($url);
        $admin = new \Tests\Unit\FakeCloudinaryAdminApi();
        $admin->existing[$publicId] = 'image';
        $upload = new \Tests\Unit\FakeCloudinaryUploadApi($admin);
        $client = new \Tests\Unit\FakeCloudinaryClient($admin, $upload);

        ImageService::$cloudinaryFactory = fn () => $client;

        try {
            $this->assertTrue(app(TenantDataPurgeService::class)->purgeTenant($this->tenant->id, false));
        } finally {
            ImageService::$cloudinaryFactory = null;
        }

        $this->assertSame(0, $upload->destroyCalls);
        $this->assertArrayHasKey($publicId, $admin->existing);

        $cleanup = TenantCleanup::where('tenant_id', $this->tenant->id)->first();
        $this->assertSame(TenantCleanup::DELETION_DELETED, $cleanup->deletion_status);
        $this->assertArrayNotHasKey('cloudinary_skipped', $cleanup->metadata ?? []);
    }

    public function test_unverified_cloudinary_deletion_blocks_purge_success(): void
    {
        config(['billing.tenant_cleanup.purge_enabled' => true]);
        $this->setPlatformSetting('tenant_auto_delete_enabled', true);

        $this->makePendingDeletion($this->tenant, $this->subscription);

        $url = 'https://res.cloudinary.com/demo/image/upload/v1/products/verify-' . uniqid() . '.jpg';
        DB::table('products')->insert([
            'tenant_id' => $this->tenant->id, 'category_id' => $this->makeCategory($this->tenant),
            'type' => 'single', 'name' => 'Verify', 'sku' => 'VF-1', 'price' => 1, 'stock' => 1,
            'status' => 'active', 'photo1' => $url, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $publicId = ImageService::cloudinaryPublicId($url);
        $admin = new \Tests\Unit\FakeCloudinaryAdminApi();
        $admin->existing[$publicId] = 'image';
        $upload = new class($admin) extends \Tests\Unit\FakeCloudinaryUploadApi {
            public function destroy($publicId, $options = [])
            {
                $this->destroyCalls++;

                return ['result' => 'ok'];
            }
        };
        $client = new \Tests\Unit\FakeCloudinaryClient($admin, $upload);

        ImageService::$cloudinaryFactory = fn () => $client;

        try {
            $this->assertFalse(app(TenantDataPurgeService::class)->purgeTenant($this->tenant->id, false));
        } finally {
            ImageService::$cloudinaryFactory = null;
        }

        $cleanup = TenantCleanup::where('tenant_id', $this->tenant->id)->first();
        $this->assertSame(TenantCleanup::STATUS_PENDING_DELETION, $cleanup->status);
        $this->assertSame(TenantCleanup::DELETION_FAILED, $cleanup->deletion_status);
        $this->assertContains($url, $cleanup->metadata['failed_files'] ?? []);
        $this->assertDatabaseMissing('products', ['tenant_id' => $this->tenant->id, 'sku' => 'VF-1']);

        ImageService::$cloudinaryFactory = fn () => $client;

        try {
            $retryOk = app(TenantDataPurgeService::class)->purgeTenant($this->tenant->id, false);
        } finally {
            ImageService::$cloudinaryFactory = null;
        }

        $this->assertFalse($retryOk);
        $retry = TenantCleanup::where('tenant_id', $this->tenant->id)->first();
        $this->assertSame(TenantCleanup::STATUS_PENDING_DELETION, $retry->status);
        $this->assertContains($url, $retry->metadata['failed_files'] ?? []);
    }

    public function test_failed_file_purge_retains_durable_inventory_and_retry_completes(): void
    {
        config(['billing.tenant_cleanup.purge_enabled' => true]);
        $this->setPlatformSetting('tenant_auto_delete_enabled', true);

        $this->makePendingDeletion($this->tenant, $this->subscription);

        $url = 'https://res.cloudinary.com/demo/image/upload/v1/products/durable-' . uniqid() . '.jpg';
        DB::table('products')->insert([
            'tenant_id' => $this->tenant->id, 'category_id' => $this->makeCategory($this->tenant),
            'type' => 'single', 'name' => 'Durable', 'sku' => 'DR-1', 'price' => 1, 'stock' => 1,
            'status' => 'active', 'photo1' => $url, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $publicId = ImageService::cloudinaryPublicId($url);
        $admin = new \Tests\Unit\FakeCloudinaryAdminApi();
        $admin->existing[$publicId] = 'image';
        $upload = new \Tests\Unit\FakeCloudinaryUploadApi($admin);
        $upload->destroyQueue[] = new \RuntimeException('Invalid credentials (401)');
        $client = new \Tests\Unit\FakeCloudinaryClient($admin, $upload);

        ImageService::$cloudinaryFactory = fn () => $client;

        try {
            $this->assertFalse(app(TenantDataPurgeService::class)->purgeTenant($this->tenant->id, false));
        } finally {
            ImageService::$cloudinaryFactory = null;
        }

        $cleanup = TenantCleanup::where('tenant_id', $this->tenant->id)->first();
        $this->assertSame(TenantCleanup::STATUS_PENDING_DELETION, $cleanup->status);
        $this->assertSame(TenantCleanup::DELETION_FAILED, $cleanup->deletion_status);
        $this->assertContains($url, $cleanup->metadata['failed_files'] ?? []);
        $this->assertContains($url, $cleanup->metadata['pending_files'] ?? []);
        $this->assertDatabaseMissing('products', ['tenant_id' => $this->tenant->id, 'photo1' => $url]);

        $upload->destroyQueue[] = ['result' => 'ok'];

        ImageService::$cloudinaryFactory = fn () => $client;

        try {
            $this->assertTrue(app(TenantDataPurgeService::class)->purgeTenant($this->tenant->id, false));
        } finally {
            ImageService::$cloudinaryFactory = null;
        }

        $this->assertArrayNotHasKey($publicId, $admin->existing);

        $retry = TenantCleanup::where('tenant_id', $this->tenant->id)->first();
        $this->assertSame(TenantCleanup::STATUS_DELETED, $retry->status);
        $this->assertSame(TenantCleanup::DELETION_DELETED, $retry->deletion_status);
        $this->assertArrayNotHasKey('failed_files', $retry->metadata ?? []);
        $this->assertArrayNotHasKey('pending_files', $retry->metadata ?? []);
    }

    public function test_retry_completes_using_pending_files_when_failed_files_never_persisted(): void
    {
        config(['billing.tenant_cleanup.purge_enabled' => true]);
        $this->setPlatformSetting('tenant_auto_delete_enabled', true);

        $cleanup = $this->makePendingDeletion($this->tenant, $this->subscription);

        $url = 'https://res.cloudinary.com/demo/image/upload/v1/products/orphan-' . uniqid() . '.jpg';
        $publicId = ImageService::cloudinaryPublicId($url);

        $cleanup->update([
            'status' => TenantCleanup::STATUS_PENDING_DELETION,
            'deletion_status' => TenantCleanup::DELETION_PENDING,
            'deleted_at' => now(),
            'metadata' => [
                'purged_at' => now()->toDateTimeString(),
                'purged_tables' => ['products' => 1],
                'pending_files' => [$url],
            ],
        ]);

        $admin = new \Tests\Unit\FakeCloudinaryAdminApi();
        $admin->existing[$publicId] = 'image';
        $upload = new \Tests\Unit\FakeCloudinaryUploadApi($admin);
        $client = new \Tests\Unit\FakeCloudinaryClient($admin, $upload);

        ImageService::$cloudinaryFactory = fn () => $client;

        try {
            $this->assertTrue(app(TenantDataPurgeService::class)->purgeTenant($this->tenant->id, false));
        } finally {
            ImageService::$cloudinaryFactory = null;
        }

        $this->assertArrayNotHasKey($publicId, $admin->existing);
        $this->assertSame(1, $upload->destroyCalls);

        $fresh = TenantCleanup::where('tenant_id', $this->tenant->id)->first();
        $this->assertSame(TenantCleanup::STATUS_DELETED, $fresh->status);
        $this->assertSame(TenantCleanup::DELETION_DELETED, $fresh->deletion_status);
        $this->assertArrayNotHasKey('pending_files', $fresh->metadata ?? []);
    }

    public function test_completed_purge_is_idempotent_and_does_not_redelete_files(): void
    {
        config(['billing.tenant_cleanup.purge_enabled' => true]);
        $this->setPlatformSetting('tenant_auto_delete_enabled', true);

        $this->makePendingDeletion($this->tenant, $this->subscription);

        $url = 'https://res.cloudinary.com/demo/image/upload/v1/products/once-' . uniqid() . '.jpg';
        DB::table('products')->insert([
            'tenant_id' => $this->tenant->id, 'category_id' => $this->makeCategory($this->tenant),
            'type' => 'single', 'name' => 'Once', 'sku' => 'ON-1', 'price' => 1, 'stock' => 1,
            'status' => 'active', 'photo1' => $url, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $publicId = ImageService::cloudinaryPublicId($url);
        $admin = new \Tests\Unit\FakeCloudinaryAdminApi();
        $admin->existing[$publicId] = 'image';
        $upload = new \Tests\Unit\FakeCloudinaryUploadApi($admin);
        $client = new \Tests\Unit\FakeCloudinaryClient($admin, $upload);

        ImageService::$cloudinaryFactory = fn () => $client;

        try {
            $this->assertTrue(app(TenantDataPurgeService::class)->purgeTenant($this->tenant->id, false));
            $this->assertSame(1, $upload->destroyCalls);

            $this->assertFalse(app(TenantDataPurgeService::class)->purgeTenant($this->tenant->id, false));
            $this->assertSame(1, $upload->destroyCalls);
        } finally {
            ImageService::$cloudinaryFactory = null;
        }

        $cleanup = TenantCleanup::where('tenant_id', $this->tenant->id)->first();
        $this->assertTrue($cleanup->isDeleted());
    }

    public function test_shared_file_is_preserved_during_retry(): void
    {
        config(['billing.tenant_cleanup.purge_enabled' => true]);
        $this->setPlatformSetting('tenant_auto_delete_enabled', true);

        $cleanup = $this->makePendingDeletion($this->tenant, $this->subscription);

        $url = 'https://res.cloudinary.com/demo/image/upload/v1/products/shared-retry-' . uniqid() . '.jpg';
        $publicId = ImageService::cloudinaryPublicId($url);

        $cleanup->update([
            'metadata' => [
                'purged_at' => now()->toDateTimeString(),
                'purged_tables' => ['products' => 1],
                'pending_files' => [$url],
            ],
        ]);

        $other = Tenant::create(['slug' => 'safe-shared-retry-' . uniqid(), 'name' => 'Other', 'status' => 'active']);
        $this->makeSubscription($other, $this->paidPlan, 'active', now()->addDays(30));
        DB::table('products')->insert([
            'tenant_id' => $other->id, 'category_id' => $this->makeCategory($other),
            'type' => 'single', 'name' => 'Neighbor', 'sku' => 'NS-1', 'price' => 1, 'stock' => 1,
            'status' => 'active', 'photo1' => $url, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $admin = new \Tests\Unit\FakeCloudinaryAdminApi();
        $admin->existing[$publicId] = 'image';
        $upload = new \Tests\Unit\FakeCloudinaryUploadApi($admin);
        $client = new \Tests\Unit\FakeCloudinaryClient($admin, $upload);

        ImageService::$cloudinaryFactory = fn () => $client;

        try {
            $this->assertTrue(app(TenantDataPurgeService::class)->purgeTenant($this->tenant->id, false));
        } finally {
            ImageService::$cloudinaryFactory = null;
        }

        $this->assertArrayHasKey($publicId, $admin->existing);
        $this->assertSame(0, $upload->destroyCalls);
        $this->assertDatabaseHas('products', ['tenant_id' => $other->id, 'sku' => 'NS-1']);
    }

    public function test_financial_payment_history_is_preserved_by_purge(): void
    {
        config(['billing.tenant_cleanup.purge_enabled' => true]);
        $this->setPlatformSetting('tenant_auto_delete_enabled', true);

        $this->makePendingDeletion($this->tenant, $this->subscription);

        DB::table('subscription_payments')->insert([
            'tenant_id' => $this->tenant->id,
            'subscription_id' => $this->subscription->id,
            'gateway' => 'manual',
            'status' => 'paid',
            'amount' => 25,
            'currency' => 'MMK',
            'billing_interval' => 'monthly',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertTrue(app(TenantDataPurgeService::class)->purgeTenant($this->tenant->id, false));

        $this->assertDatabaseHas('subscription_payments', [
            'tenant_id' => $this->tenant->id, 'subscription_id' => $this->subscription->id, 'status' => 'paid',
        ]);

        $cleanup = TenantCleanup::where('tenant_id', $this->tenant->id)->first();
        $this->assertTrue($cleanup->isDeleted());
    }

    public function test_pivot_rows_are_removed_for_target_tenant_only(): void
    {
        config(['billing.tenant_cleanup.purge_enabled' => true]);
        $this->setPlatformSetting('tenant_auto_delete_enabled', true);

        $this->makePendingDeletion($this->tenant, $this->subscription);

        $other = Tenant::create(['slug' => 'safe-b-' . uniqid(), 'name' => 'Neighbour', 'status' => 'active']);
        $catA = $this->makeCategory($this->tenant);
        $catB = $this->makeCategory($other);

        $prodA = DB::table('products')->insertGetId([
            'tenant_id' => $this->tenant->id, 'category_id' => $catA, 'type' => 'single', 'name' => 'Pivot A',
            'sku' => 'PV-A', 'price' => 1, 'stock' => 1, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $prodB = DB::table('products')->insertGetId([
            'tenant_id' => $other->id, 'category_id' => $catB, 'type' => 'single', 'name' => 'Pivot B',
            'sku' => 'PV-B', 'price' => 1, 'stock' => 1, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $couponA = DB::table('coupons')->insertGetId(['tenant_id' => $this->tenant->id, 'name' => 'Coupon A', 'code' => 'CA', 'created_at' => now(), 'updated_at' => now()]);
        $couponB = DB::table('coupons')->insertGetId(['tenant_id' => $other->id, 'name' => 'Coupon B', 'code' => 'CB', 'created_at' => now(), 'updated_at' => now()]);
        $promoA = DB::table('promotions')->insertGetId(['tenant_id' => $this->tenant->id, 'name' => 'PA', 'created_at' => now(), 'updated_at' => now()]);
        $promoB = DB::table('promotions')->insertGetId(['tenant_id' => $other->id, 'name' => 'PB', 'created_at' => now(), 'updated_at' => now()]);

        DB::table('coupon_category')->insert([
            ['tenant_id' => $this->tenant->id, 'coupon_id' => $couponA, 'category_id' => $catA],
            ['tenant_id' => $other->id, 'coupon_id' => $couponB, 'category_id' => $catB],
        ]);
        DB::table('coupon_product')->insert([
            ['tenant_id' => $this->tenant->id, 'coupon_id' => $couponA, 'product_id' => $prodA],
            ['tenant_id' => $other->id, 'coupon_id' => $couponB, 'product_id' => $prodB],
        ]);
        DB::table('promotion_category')->insert([
            ['tenant_id' => $this->tenant->id, 'promotion_id' => $promoA, 'category_id' => $catA],
            ['tenant_id' => $other->id, 'promotion_id' => $promoB, 'category_id' => $catB],
        ]);
        DB::table('promotion_product')->insert([
            ['tenant_id' => $this->tenant->id, 'promotion_id' => $promoA, 'product_id' => $prodA],
            ['tenant_id' => $other->id, 'promotion_id' => $promoB, 'product_id' => $prodB],
        ]);

        $this->assertTrue(app(TenantDataPurgeService::class)->purgeTenant($this->tenant->id, false));

        foreach (['coupon_category', 'coupon_product', 'promotion_category', 'promotion_product'] as $pivot) {
            $this->assertSame(0, DB::table($pivot)->where('tenant_id', $this->tenant->id)->count(), $pivot);
            $this->assertSame(1, DB::table($pivot)->where('tenant_id', $other->id)->count(), $pivot);
        }
    }

    public function test_collection_failure_aborts_purge_and_preserves_data_and_inventory(): void
    {
        config(['billing.tenant_cleanup.purge_enabled' => true]);
        $this->setPlatformSetting('tenant_auto_delete_enabled', true);

        $cleanup = $this->makePendingDeletion($this->tenant, $this->subscription);
        $cleanup->update(['metadata' => [
            'pending_files' => ['products/prior.jpg'],
            'failed_files' => ['products/fail.jpg'],
        ]]);

        DB::table('products')->insert([
            'tenant_id' => $this->tenant->id, 'category_id' => $this->makeCategory($this->tenant),
            'type' => 'single', 'name' => 'Keep', 'sku' => 'CF-1', 'price' => 1, 'stock' => 1,
            'status' => 'active', 'photo1' => 'products/keep.jpg',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->app->instance(TenantDataPurgeService::class, new FailingCloudinaryCollectionPurgeService(app(\App\Services\TenantCleanupService::class)));

        try {
            $this->assertFalse(app(TenantDataPurgeService::class)->purgeTenant($this->tenant->id, false));
        } finally {
            $this->app->forgetInstance(TenantDataPurgeService::class);
        }

        $this->assertDatabaseHas('products', ['tenant_id' => $this->tenant->id, 'sku' => 'CF-1']);

        $fresh = TenantCleanup::where('tenant_id', $this->tenant->id)->first();
        $this->assertSame(TenantCleanup::STATUS_PENDING_DELETION, $fresh->status);
        $this->assertSame(TenantCleanup::DELETION_FAILED, $fresh->deletion_status);
        $this->assertSame(['products/prior.jpg'], $fresh->metadata['pending_files']);
        $this->assertSame(['products/fail.jpg'], $fresh->metadata['failed_files']);
        $this->assertNotEmpty($fresh->metadata['collection_error'] ?? null);
        $this->assertNotEmpty($fresh->last_error);
    }

    public function test_local_collection_failure_rolls_back_partial_deletions(): void
    {
        config(['billing.tenant_cleanup.purge_enabled' => true]);
        $this->setPlatformSetting('tenant_auto_delete_enabled', true);

        $this->makePendingDeletion($this->tenant, $this->subscription);

        $productId = DB::table('products')->insertGetId([
            'tenant_id' => $this->tenant->id, 'category_id' => $this->makeCategory($this->tenant),
            'type' => 'variable', 'name' => 'Local', 'sku' => 'LC-1', 'price' => 1, 'stock' => 1,
            'status' => 'active', 'photo1' => 'products/local-keep.jpg',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('product_variants')->insert([
            'tenant_id' => $this->tenant->id, 'product_id' => $productId, 'sku' => 'LC-1-RED',
            'price' => 1, 'stock' => 1, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->app->instance(TenantDataPurgeService::class, new FailingLocalCollectionPurgeService(app(\App\Services\TenantCleanupService::class)));

        try {
            $this->assertFalse(app(TenantDataPurgeService::class)->purgeTenant($this->tenant->id, false));
        } finally {
            $this->app->forgetInstance(TenantDataPurgeService::class);
        }

        $this->assertDatabaseHas('product_variants', ['tenant_id' => $this->tenant->id, 'sku' => 'LC-1-RED']);
        $this->assertDatabaseHas('products', ['tenant_id' => $this->tenant->id, 'sku' => 'LC-1']);

        $fresh = TenantCleanup::where('tenant_id', $this->tenant->id)->first();
        $this->assertSame(TenantCleanup::STATUS_PENDING_DELETION, $fresh->status);
        $this->assertSame(TenantCleanup::DELETION_FAILED, $fresh->deletion_status);
    }

    public function test_retry_after_collection_failure_recovers_and_purges(): void
    {
        config(['billing.tenant_cleanup.purge_enabled' => true]);
        $this->setPlatformSetting('tenant_auto_delete_enabled', true);

        $this->makePendingDeletion($this->tenant, $this->subscription);

        DB::table('products')->insert([
            'tenant_id' => $this->tenant->id, 'category_id' => $this->makeCategory($this->tenant),
            'type' => 'single', 'name' => 'Recover', 'sku' => 'RC-1', 'price' => 1, 'stock' => 1,
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->app->instance(TenantDataPurgeService::class, new FailingCloudinaryCollectionPurgeService(app(\App\Services\TenantCleanupService::class)));

        try {
            $this->assertFalse(app(TenantDataPurgeService::class)->purgeTenant($this->tenant->id, false));
        } finally {
            $this->app->forgetInstance(TenantDataPurgeService::class);
        }

        $this->assertDatabaseHas('products', ['tenant_id' => $this->tenant->id, 'sku' => 'RC-1']);

        $this->assertTrue(app(TenantDataPurgeService::class)->purgeTenant($this->tenant->id, false));

        $this->assertDatabaseMissing('products', ['tenant_id' => $this->tenant->id, 'sku' => 'RC-1']);
        $fresh = TenantCleanup::where('tenant_id', $this->tenant->id)->first();
        $this->assertSame(TenantCleanup::STATUS_DELETED, $fresh->status);
        $this->assertSame(TenantCleanup::DELETION_DELETED, $fresh->deletion_status);
    }

    public function test_concurrent_purge_is_blocked_by_lock(): void
    {
        config(['billing.tenant_cleanup.purge_enabled' => true]);
        $this->setPlatformSetting('tenant_auto_delete_enabled', true);

        $this->makePendingDeletion($this->tenant, $this->subscription);

        DB::table('products')->insert([
            'tenant_id' => $this->tenant->id, 'category_id' => $this->makeCategory($this->tenant),
            'type' => 'single', 'name' => 'Locked', 'sku' => 'LK-1', 'price' => 1, 'stock' => 1,
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $lock = Cache::lock(TenantDataPurgeService::purgeLockKey($this->tenant->id), 600);
        $this->assertTrue($lock->get());

        try {
            $this->assertFalse(app(TenantDataPurgeService::class)->purgeTenant($this->tenant->id, false));
        } finally {
            $lock->release();
        }

        $this->assertDatabaseHas('products', ['tenant_id' => $this->tenant->id, 'sku' => 'LK-1']);
        $this->assertSame(
            TenantCleanup::STATUS_PENDING_DELETION,
            TenantCleanup::where('tenant_id', $this->tenant->id)->first()->status
        );

        $this->assertTrue(app(TenantDataPurgeService::class)->purgeTenant($this->tenant->id, false));
        $this->assertDatabaseMissing('products', ['tenant_id' => $this->tenant->id, 'sku' => 'LK-1']);
    }

    public function test_service_level_single_tenant_dry_run_is_non_mutating(): void
    {
        $this->assertFalse(app(TenantDataPurgeService::class)->purgeAllowed());

        $this->makePendingDeletion($this->tenant, $this->subscription);

        DB::table('products')->insert([
            'tenant_id' => $this->tenant->id, 'category_id' => $this->makeCategory($this->tenant),
            'type' => 'single', 'name' => 'Preview', 'sku' => 'PV-1', 'price' => 1, 'stock' => 1,
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $eligible = app(TenantDataPurgeService::class)->purgeTenant($this->tenant->id, true);

        $this->assertTrue($eligible);
        $this->assertDatabaseHas('products', ['tenant_id' => $this->tenant->id, 'sku' => 'PV-1']);
        $this->assertDatabaseHas('subscriptions', ['id' => $this->subscription->id]);
        $this->assertSame('active', $this->tenant->fresh()->status);
        $this->assertNull($this->tenant->fresh()->locked_at);

        $fresh = TenantCleanup::where('tenant_id', $this->tenant->id)->first();
        $this->assertSame(TenantCleanup::STATUS_PENDING_DELETION, $fresh->status);
        $this->assertSame(TenantCleanup::DELETION_PENDING, $fresh->deletion_status);
        $this->assertNull($fresh->deleted_at);
        $this->assertDatabaseMissing('subscription_audit_logs', [
            'subscription_id' => $this->subscription->id,
            'event' => 'tenant_data_purged',
        ]);
    }

    public function test_cloudinary_asset_shared_with_other_tenant_is_preserved(): void
    {
        config(['billing.tenant_cleanup.purge_enabled' => true]);
        $this->setPlatformSetting('tenant_auto_delete_enabled', true);

        $this->makePendingDeletion($this->tenant, $this->subscription);

        $url = 'https://res.cloudinary.com/demo/image/upload/v1/products/shared-' . uniqid() . '.jpg';
        DB::table('products')->insert([
            'tenant_id' => $this->tenant->id, 'category_id' => $this->makeCategory($this->tenant),
            'type' => 'single', 'name' => 'Victim', 'sku' => 'V-9', 'price' => 1, 'stock' => 1,
            'status' => 'active', 'photo1' => $url, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $other = Tenant::create(['slug' => 'safe-other-' . uniqid(), 'name' => 'Other', 'status' => 'active']);
        $this->makeSubscription($other, $this->paidPlan, 'active', now()->addDays(30));
        DB::table('products')->insert([
            'tenant_id' => $other->id, 'category_id' => $this->makeCategory($other),
            'type' => 'single', 'name' => 'Neighbor', 'sku' => 'N-9', 'price' => 1, 'stock' => 1,
            'status' => 'active', 'photo1' => $url, 'created_at' => now(), 'updated_at' => now(),
        ]);

        app(TenantDataPurgeService::class)->process(false);

        $this->assertDatabaseHas('products', ['tenant_id' => $other->id, 'sku' => 'N-9']);
        $this->assertDatabaseMissing('products', ['tenant_id' => $this->tenant->id, 'sku' => 'V-9']);
    }

    public function test_cross_tenant_city_reference_blocks_city_delete(): void
    {
        config(['billing.tenant_cleanup.purge_enabled' => true]);
        $this->setPlatformSetting('tenant_auto_delete_enabled', true);

        $this->makePendingDeletion($this->tenant, $this->subscription);

        if (!Schema::hasColumn('delivery_pricing', 'township_id')) {
            Schema::table('delivery_pricing', function ($t) {
                $t->unsignedBigInteger('township_id')->nullable();
            });
        }

        $cityId = DB::table('cities')->insertGetId([
            'tenant_id' => $this->tenant->id, 'name' => 'Victim City',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $townshipId = DB::table('townships')->insertGetId([
            'tenant_id' => $this->tenant->id, 'city_id' => $cityId, 'name' => 'Victim Township',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $other = Tenant::create(['slug' => 'safe-city-' . uniqid(), 'name' => 'Other', 'status' => 'active']);
        $this->makeSubscription($other, $this->paidPlan, 'active', now()->addDays(30));
        $serviceData = ['tenant_id' => $other->id, 'name' => 'Fast', 'created_at' => now(), 'updated_at' => now()];
        if (Schema::hasColumn('delivery_services', 'code')) {
            $serviceData['code'] = 'FAST-' . uniqid();
        }
        $serviceId = DB::table('delivery_services')->insertGetId($serviceData);
        $pricingId = DB::table('delivery_pricing')->insertGetId([
            'delivery_service_id' => $serviceId, 'township_id' => $townshipId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $result = app(TenantDataPurgeService::class)->process(false);

        $this->assertSame(1, $result['purged']);
        $this->assertDatabaseHas('townships', ['id' => $townshipId]);
        $this->assertDatabaseHas('delivery_pricing', ['id' => $pricingId]);

        $cleanup = TenantCleanup::where('tenant_id', $this->tenant->id)->first();
        $this->assertNotEmpty($cleanup->metadata['skipped_tables'] ?? []);
    }

    public function test_manual_deletion_dry_run_changes_nothing(): void
    {
        $this->actingAs($this->makeSuperadmin());

        $this->subscription->update(['status' => 'expired', 'expires_at' => now()->subDays(40)]);
        $cat = $this->makeCategory($this->tenant);
        DB::table('products')->insert([
            'tenant_id' => $this->tenant->id, 'category_id' => $cat,
            'type' => 'single', 'name' => 'Keep', 'sku' => 'K-1', 'price' => 1, 'stock' => 1,
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $summary = app(TenantDeletionService::class)->delete($this->tenant, true);

        $this->assertArrayHasKey('products', $summary);
        $this->assertDatabaseHas('tenants', ['id' => $this->tenant->id]);
        $this->assertDatabaseHas('products', ['tenant_id' => $this->tenant->id, 'sku' => 'K-1']);
        $this->assertDatabaseHas('subscriptions', ['id' => $this->subscription->id]);
    }

    public function test_manual_deletion_refuses_active_subscription(): void
    {
        $this->actingAs($this->makeSuperadmin());

        $response = $this->delete("/superadmin/tenants/{$this->tenant->id}");

        $response->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseHas('tenants', ['id' => $this->tenant->id]);
        $this->assertDatabaseHas('subscriptions', ['id' => $this->subscription->id]);
    }

    public function test_manual_deletion_requires_superadmin(): void
    {
        $this->actingAs($this->makeOwner($this->tenant));

        $this->subscription->update(['status' => 'expired', 'expires_at' => now()->subDays(40)]);

        $this->delete("/superadmin/tenants/{$this->tenant->id}")->assertForbidden();
        $this->assertDatabaseHas('tenants', ['id' => $this->tenant->id]);
    }

    public function test_manual_deletion_cleans_files_and_preserves_neighbors(): void
    {
        config(['filesystems.default' => 'public']);
        Storage::fake('public');
        $this->actingAs($this->makeSuperadmin());

        $this->subscription->update(['status' => 'expired', 'expires_at' => now()->subDays(40)]);
        Storage::disk('public')->put('products/manual.jpg', 'x');
        Storage::disk('public')->put('products/neighbor.jpg', 'y');

        DB::table('products')->insert([
            'tenant_id' => $this->tenant->id, 'category_id' => $this->makeCategory($this->tenant),
            'type' => 'single', 'name' => 'Gone', 'sku' => 'G-1', 'price' => 1, 'stock' => 1,
            'status' => 'active', 'photo1' => 'products/manual.jpg',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $other = Tenant::create(['slug' => 'safe-manual-' . uniqid(), 'name' => 'Other', 'status' => 'active']);
        $this->makeSubscription($other, $this->paidPlan, 'active', now()->addDays(30));
        DB::table('products')->insert([
            'tenant_id' => $other->id, 'category_id' => $this->makeCategory($other),
            'type' => 'single', 'name' => 'Neighbor', 'sku' => 'G-2', 'price' => 1, 'stock' => 1,
            'status' => 'active', 'photo1' => 'products/neighbor.jpg',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->delete("/superadmin/tenants/{$this->tenant->id}")->assertRedirect();

        $this->assertDatabaseMissing('tenants', ['id' => $this->tenant->id]);
        $this->assertDatabaseMissing('products', ['tenant_id' => $this->tenant->id, 'sku' => 'G-1']);
        $this->assertDatabaseHas('products', ['tenant_id' => $other->id, 'sku' => 'G-2']);
        Storage::disk('public')->assertMissing('products/manual.jpg');
        Storage::disk('public')->assertExists('products/neighbor.jpg');
        $this->assertDatabaseHas('subscription_audit_logs', [
            'tenant_id' => $this->tenant->id,
            'event' => 'tenant_deleted_manual',
        ]);
    }

    public function test_expanded_backup_exports_cover_essential_data(): void
    {
        $owner = $this->makeOwner($this->tenant);
        $this->actingAs($owner);

        $cat = $this->makeCategory($this->tenant);
        $productId = DB::table('products')->insertGetId([
            'tenant_id' => $this->tenant->id, 'category_id' => $cat,
            'type' => 'variable', 'name' => 'Shirt', 'sku' => 'S-1', 'price' => 20, 'stock' => 5,
            'status' => 'active', 'photo1' => 'products/shirt.jpg',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('product_variants')->insert([
            'tenant_id' => $this->tenant->id, 'product_id' => $productId, 'sku' => 'S-1-RED',
            'price' => 20, 'stock' => 5, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $orderId = DB::table('orders')->insertGetId([
            'tenant_id' => $this->tenant->id, 'user_id' => $owner->id, 'invoice_number' => 'ORD-1',
            'customer_name' => 'Aye', 'first_name' => 'Aye', 'last_name' => 'Customer',
            'email' => 'aye@test.com', 'phone' => '091', 'address' => 'No 1',
            'order_status' => 'delivered', 'payment_status' => 'paid', 'subtotal' => 20,
            'total_amount' => 20, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('order_items')->insert([
            'tenant_id' => $this->tenant->id, 'order_id' => $orderId, 'product_id' => $productId,
            'quantity' => 2, 'price' => 20, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $cityId = DB::table('cities')->insertGetId([
            'tenant_id' => $this->tenant->id, 'name' => 'Yangon',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('customer_addresses')->insert([
            'tenant_id' => $this->tenant->id, 'user_id' => $owner->id, 'first_name' => 'Aye',
            'last_name' => 'Customer', 'phone' => '091', 'address_line' => 'No 1',
            'city_id' => $cityId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->subscription->update(['status' => 'expired', 'expires_at' => now()->subDays(16)]);
        app(\App\Services\TenantCleanupService::class)->process();

        $base = "/store/{$this->tenant->slug}/admin/billing/backup";
        $this->get("{$base}/order-items")->assertOk();
        $this->get("{$base}/addresses")->assertOk();
        $this->get("{$base}/variants?format=csv")->assertOk();
        $this->get("{$base}/inventory?format=csv")->assertOk();
        $this->get("{$base}/media")->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_expanded_exports_denied_after_retention(): void
    {
        $this->actingAs($this->makeOwner($this->tenant));

        $this->subscription->update(['status' => 'expired', 'expires_at' => now()->subDays(40)]);
        app(\App\Services\TenantCleanupService::class)->process();
        \App\Models\TenantCleanup::where('tenant_id', $this->tenant->id)->update(['retention_ends_at' => now()->subDay()]);

        $base = "/store/{$this->tenant->slug}/admin/billing/backup";
        $this->get("{$base}/order-items")->assertForbidden();
        $this->get("{$base}/addresses")->assertForbidden();
        $this->get("{$base}/media")->assertForbidden();
    }

    public function test_repeated_purge_failures_are_parked_after_max_attempts(): void
    {
        config(['billing.tenant_cleanup.purge_enabled' => true]);
        config(['billing.tenant_cleanup.max_purge_attempts' => 2]);
        $this->setPlatformSetting('tenant_auto_delete_enabled', true);

        $this->makePendingDeletion($this->tenant, $this->subscription);

        DB::table('products')->insert([
            'tenant_id' => $this->tenant->id, 'category_id' => $this->makeCategory($this->tenant),
            'type' => 'single', 'name' => 'Parked', 'sku' => 'PK-1', 'price' => 1, 'stock' => 1,
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->app->instance(TenantDataPurgeService::class, new FailingLocalCollectionPurgeService(app(\App\Services\TenantCleanupService::class)));

        try {
            $service = app(TenantDataPurgeService::class);

            $first = $service->process(false);
            $second = $service->process(false);
            $third = $service->process(false);
        } finally {
            $this->app->forgetInstance(TenantDataPurgeService::class);
        }

        $this->assertSame(1, $first['candidates']);
        $this->assertSame(1, $second['candidates']);
        $this->assertSame(0, $third['candidates']);

        $cleanup = TenantCleanup::where('tenant_id', $this->tenant->id)->first();
        $this->assertSame(2, $cleanup->attempts);
        $this->assertSame(TenantCleanup::STATUS_PENDING_DELETION, $cleanup->status);
        $this->assertSame(TenantCleanup::DELETION_FAILED, $cleanup->deletion_status);
        $this->assertDatabaseHas('products', ['tenant_id' => $this->tenant->id, 'sku' => 'PK-1']);
    }

    public function test_empty_tenant_directories_are_pruned_but_referenced_dirs_are_kept(): void
    {
        config(['filesystems.default' => 'public']);
        Storage::fake('public');

        config(['billing.tenant_cleanup.purge_enabled' => true]);
        $this->setPlatformSetting('tenant_auto_delete_enabled', true);

        $this->makePendingDeletion($this->tenant, $this->subscription);

        Storage::disk('public')->put('tenant-uploads/abc/photo.jpg', 'x');
        Storage::disk('public')->put('tenant-uploads/other/keep.jpg', 'y');
        Storage::disk('public')->put('defaults/logo.png', 'z');

        DB::table('products')->insert([
            'tenant_id' => $this->tenant->id, 'category_id' => $this->makeCategory($this->tenant),
            'type' => 'single', 'name' => 'Prune', 'sku' => 'PR-1', 'price' => 1, 'stock' => 1,
            'status' => 'active', 'photo1' => 'tenant-uploads/abc/photo.jpg',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertTrue(app(TenantDataPurgeService::class)->purgeTenant($this->tenant->id, false));

        Storage::disk('public')->assertMissing('tenant-uploads/abc/photo.jpg');
        Storage::disk('public')->assertMissing('tenant-uploads/abc');
        Storage::disk('public')->assertExists('tenant-uploads');
        Storage::disk('public')->assertExists('tenant-uploads/other/keep.jpg');
        Storage::disk('public')->assertExists('defaults/logo.png');
    }

    public function test_service_level_dry_run_with_active_subscription_is_non_mutating(): void
    {
        $this->assertFalse(app(TenantDataPurgeService::class)->purgeAllowed());

        $cleanup = TenantCleanup::create([
            'tenant_id' => $this->tenant->id,
            'subscription_id' => $this->subscription->id,
            'status' => TenantCleanup::STATUS_PENDING_DELETION,
            'expires_at' => now()->subDay(),
            'grace_ends_at' => now()->addDays(14),
            'retention_ends_at' => now()->subDay(),
            'restricted_at' => now()->subDays(20),
            'deletion_status' => TenantCleanup::DELETION_PENDING,
            'reminders_sent' => [],
        ]);

        $this->subscription->update(['status' => 'active', 'expires_at' => now()->addDays(30)]);

        DB::table('products')->insert([
            'tenant_id' => $this->tenant->id, 'category_id' => $this->makeCategory($this->tenant),
            'type' => 'single', 'name' => 'Active', 'sku' => 'AS-1', 'price' => 1, 'stock' => 1,
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $auditBefore = DB::table('subscription_audit_logs')
            ->where('subscription_id', $this->subscription->id)->count();

        $eligible = app(TenantDataPurgeService::class)->purgeTenant($this->tenant->id, true);

        $this->assertFalse($eligible);

        $fresh = $cleanup->fresh();
        $this->assertSame(TenantCleanup::STATUS_PENDING_DELETION, $fresh->status);
        $this->assertSame(TenantCleanup::DELETION_PENDING, $fresh->deletion_status);
        $this->assertNull($fresh->deleted_at);
        $this->assertNull($fresh->last_error);

        $this->assertSame('active', $this->subscription->fresh()->status);
        $this->assertSame('active', $this->tenant->fresh()->status);
        $this->assertNull($this->tenant->fresh()->locked_at);
        $this->assertDatabaseHas('products', ['tenant_id' => $this->tenant->id, 'sku' => 'AS-1']);

        $auditAfter = DB::table('subscription_audit_logs')
            ->where('subscription_id', $this->subscription->id)->count();
        $this->assertSame($auditBefore, $auditAfter);
        $this->assertDatabaseMissing('subscription_audit_logs', [
            'subscription_id' => $this->subscription->id,
            'event' => 'tenant_data_purged',
        ]);
    }

    public function test_file_verification_failure_increments_attempts_and_preserves_retry_metadata(): void
    {
        config(['billing.tenant_cleanup.purge_enabled' => true]);
        $this->setPlatformSetting('tenant_auto_delete_enabled', true);

        $this->makePendingDeletion($this->tenant, $this->subscription);

        $url = 'https://res.cloudinary.com/demo/image/upload/v1/products/attempts-' . uniqid() . '.jpg';
        DB::table('products')->insert([
            'tenant_id' => $this->tenant->id, 'category_id' => $this->makeCategory($this->tenant),
            'type' => 'single', 'name' => 'Attempts', 'sku' => 'AT-1', 'price' => 1, 'stock' => 1,
            'status' => 'active', 'photo1' => $url, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $publicId = ImageService::cloudinaryPublicId($url);
        $admin = new \Tests\Unit\FakeCloudinaryAdminApi();
        $admin->existing[$publicId] = 'image';
        $upload = new \Tests\Unit\FakeCloudinaryUploadApi($admin);
        $upload->destroyQueue[] = new \RuntimeException('Invalid credentials (401)');
        $client = new \Tests\Unit\FakeCloudinaryClient($admin, $upload);

        ImageService::$cloudinaryFactory = fn () => $client;

        try {
            $this->assertFalse(app(TenantDataPurgeService::class)->purgeTenant($this->tenant->id, false));
        } finally {
            ImageService::$cloudinaryFactory = null;
        }

        $cleanup = TenantCleanup::where('tenant_id', $this->tenant->id)->first();
        $this->assertSame(1, $cleanup->attempts);
        $this->assertSame(TenantCleanup::STATUS_PENDING_DELETION, $cleanup->status);
        $this->assertSame(TenantCleanup::DELETION_FAILED, $cleanup->deletion_status);
        $this->assertContains($url, $cleanup->metadata['failed_files'] ?? []);
        $this->assertContains($url, $cleanup->metadata['pending_files'] ?? []);
        $this->assertNotEmpty($cleanup->last_error);
        $this->assertArrayHasKey('purged_at', $cleanup->metadata ?? []);
        $this->assertDatabaseMissing('products', ['tenant_id' => $this->tenant->id, 'photo1' => $url]);
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

    private function makeCategory(Tenant $tenant): int
    {
        return DB::table('categories')->insertGetId([
            'tenant_id' => $tenant->id, 'name' => 'Cat-' . uniqid(),
            'created_at' => now(), 'updated_at' => now(),
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

    private function makeSuperadmin(): User
    {
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $user = User::create([
            'tenant_id' => null,
            'name' => 'Super',
            'email' => 'super-' . uniqid() . '@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $user->assignRole($role);

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

    private function createSafetySchema(): void
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
            'subscription_audit_logs' => function ($t) { $t->id(); $t->unsignedBigInteger('subscription_id'); $t->unsignedBigInteger('tenant_id')->nullable(); $t->string('event'); $t->string('actor_type')->nullable(); $t->unsignedBigInteger('actor_id')->nullable(); $t->string('old_status')->nullable(); $t->string('new_status')->nullable(); $t->text('reason')->nullable(); $t->json('metadata')->nullable(); $t->timestamps(); },
            'subscription_cancellation_requests' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id'); $t->unsignedBigInteger('subscription_id'); $t->string('reason', 50)->default('other'); $t->text('comment')->nullable(); $t->string('status', 20)->default('pending'); $t->timestamps(); $t->index('tenant_id'); },
            'subscription_payments' => function ($t) { $t->id(); $t->unsignedBigInteger('subscription_id'); $t->unsignedBigInteger('tenant_id'); $t->string('gateway'); $t->string('gateway_transaction_id')->nullable(); $t->string('status'); $t->decimal('amount', 10, 2); $t->string('currency', 3); $t->string('billing_interval'); $t->longText('gateway_response')->nullable(); $t->longText('metadata')->nullable(); $t->timestamp('paid_at')->nullable(); $t->timestamp('failed_at')->nullable(); $t->timestamp('refunded_at')->nullable(); $t->timestamps(); $t->index('tenant_id'); },
            'invoices' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id'); $t->string('invoice_number')->unique(); $t->unsignedBigInteger('subscription_id')->nullable(); $t->unsignedBigInteger('plan_id')->nullable(); $t->decimal('total',10,2)->default(0); $t->string('currency',3)->default('MMK'); $t->string('status')->default('unpaid'); $t->timestamps(); },
            'platform_settings' => function ($t) { $t->id(); $t->string('site_name')->default('My Application'); $t->boolean('trial_enabled')->default(false); $t->integer('trial_days')->default(14); $t->boolean('allow_trial_renewal')->default(true); $t->integer('max_trial_renewals')->default(0); $t->integer('billing_renewal_reminder_days')->default(7); $t->unsignedInteger('tenant_data_retention_days')->default(15); $t->boolean('tenant_auto_delete_enabled')->default(false); $t->timestamps(); },
            'settings' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->string('key'); $t->text('value')->nullable(); $t->timestamps(); },
            'notifications' => function ($t) { $t->uuid('id')->primary(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->string('type'); $t->string('notifiable_type'); $t->unsignedBigInteger('notifiable_id'); $t->text('data'); $t->timestamp('read_at')->nullable(); $t->timestamps(); },
            'plan_features' => function ($t) { $t->id(); $t->unsignedBigInteger('plan_id'); $t->string('feature_key'); $t->boolean('is_enabled')->default(false); $t->string('display_label')->nullable(); $t->text('description')->nullable(); $t->timestamps(); },
            'categories' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->string('name'); $t->string('image')->nullable(); $t->timestamps(); },
            'brands' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->string('name'); $t->string('slug')->nullable(); $t->string('logo')->nullable(); $t->string('banner')->nullable(); $t->timestamps(); },
            'products' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id'); $t->unsignedBigInteger('category_id')->nullable(); $t->string('type')->default('single'); $t->string('name'); $t->string('sku')->nullable(); $t->decimal('price',10,2)->default(0); $t->integer('stock')->default(0); $t->string('status')->default('active'); $t->boolean('is_active')->default(true); $t->string('photo1')->nullable(); $t->string('photo2')->nullable(); $t->string('seo_image')->nullable(); $t->json('gallery_images')->nullable(); $t->timestamps(); $t->index('tenant_id'); },
            'product_variants' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id'); $t->unsignedBigInteger('product_id'); $t->string('sku')->nullable(); $t->decimal('price',10,2)->default(0); $t->integer('stock')->default(0); $t->string('status')->default('active'); $t->string('image')->nullable(); $t->timestamps(); $t->index('tenant_id'); },
            'orders' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id'); $t->unsignedBigInteger('user_id')->nullable(); $t->string('invoice_number')->nullable(); $t->string('customer_name')->nullable(); $t->string('first_name')->nullable(); $t->string('last_name')->nullable(); $t->string('email')->nullable(); $t->string('phone')->nullable(); $t->text('address')->nullable(); $t->string('order_status')->default('pending'); $t->string('payment_status')->default('pending'); $t->decimal('subtotal',10,2)->default(0); $t->decimal('total_amount',10,2)->default(0); $t->timestamps(); $t->index('tenant_id'); },
            'order_items' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id'); $t->unsignedBigInteger('order_id'); $t->unsignedBigInteger('product_id')->nullable(); $t->integer('quantity')->default(1); $t->decimal('price',10,2)->default(0); $t->timestamps(); $t->index('tenant_id'); },
            'customer_addresses' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id'); $t->unsignedBigInteger('user_id')->nullable(); $t->string('first_name')->nullable(); $t->string('last_name')->nullable(); $t->string('phone')->nullable(); $t->text('address')->nullable(); $t->string('address_line')->nullable(); $t->unsignedBigInteger('city_id')->nullable(); $t->unsignedBigInteger('township_id')->nullable(); $t->timestamps(); $t->index('tenant_id'); },
            'cities' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->string('name'); $t->timestamps(); },
            'townships' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->unsignedBigInteger('city_id')->nullable(); $t->string('name'); $t->timestamps(); },
            'delivery_services' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->string('name'); $t->string('code')->nullable(); $t->timestamps(); },
            'delivery_pricing' => function ($t) { $t->id(); $t->unsignedBigInteger('delivery_service_id'); $t->unsignedBigInteger('city_id')->nullable(); $t->unsignedBigInteger('township_id')->nullable(); $t->decimal('fee',10,2)->default(0); $t->timestamps(); },
            'website_infos' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->string('site_name')->nullable(); $t->string('site_tagline')->nullable(); $t->string('site_description')->nullable(); $t->string('site_keywords')->nullable(); $t->string('theme_color')->default('#3B82F6'); $t->string('default_language')->default('en'); $t->string('timezone')->default('Asia/Yangon'); $t->string('currency_code')->default('MMK'); $t->string('currency_symbol')->default('K'); $t->string('date_format')->default('Y-m-d'); $t->boolean('allow_registration')->default(true); $t->boolean('maintenance_mode')->default(false); $t->string('logo')->nullable(); $t->string('favicon')->nullable(); $t->string('og_image')->nullable(); $t->string('footer_logo')->nullable(); $t->string('contact_email')->nullable(); $t->string('support_email')->nullable(); $t->string('phone')->nullable(); $t->string('whatsapp_number')->nullable(); $t->text('address')->nullable(); $t->string('country')->nullable(); $t->string('google_maps_embed_url')->nullable(); $t->json('contact_address')->nullable(); $t->json('footer_settings')->nullable(); $t->json('hero_images')->nullable(); $t->boolean('enable_wishlist')->default(true); $t->boolean('enable_reviews')->default(true); $t->boolean('enable_compare')->default(true); $t->timestamps(); },
            'flash_sales' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->string('name')->nullable(); $t->boolean('is_active')->default(true); $t->timestamps(); },
            'storefront_media' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->string('path')->nullable(); $t->timestamps(); },
            'import_histories' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->string('file_name')->nullable(); $t->string('error_report_path')->nullable(); $t->timestamps(); },
            'team_invitations' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id'); $t->string('email'); $t->string('status')->default('pending'); $t->timestamps(); $t->index('tenant_id'); },
            'tenant_cleanups' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->unique(); $t->unsignedBigInteger('subscription_id')->nullable(); $t->string('status',20)->default('grace'); $t->timestamp('expires_at')->nullable(); $t->timestamp('grace_ends_at')->nullable(); $t->timestamp('retention_ends_at')->nullable(); $t->string('deletion_status',20)->default('none'); $t->timestamp('restricted_at')->nullable(); $t->timestamp('deleted_at')->nullable(); $t->timestamp('archived_at')->nullable(); $t->timestamp('last_reminder_at')->nullable(); $t->json('reminders_sent')->nullable(); $t->unsignedInteger('attempts')->default(0); $t->string('reason')->nullable(); $t->text('last_error')->nullable(); $t->json('metadata')->nullable(); $t->timestamps(); $t->index('status'); },
            'accounts' => function ($t) { $t->id(); $t->string('name')->nullable(); $t->string('email')->unique(); $t->string('password'); $t->string('status', 50)->default('active'); $t->json('notification_preferences')->nullable(); $t->timestamps(); $t->softDeletes(); },
            'tenant_memberships' => function ($t) { $t->id(); $t->unsignedBigInteger('account_id')->nullable(); $t->unsignedBigInteger('tenant_id'); $t->unsignedBigInteger('role_id')->nullable(); $t->boolean('is_owner')->default(false); $t->string('status')->default('active'); $t->timestamps(); $t->softDeletes(); },
            'wishlists' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->unsignedBigInteger('user_id')->nullable(); $t->string('user_type')->nullable(); $t->unsignedBigInteger('product_id')->nullable(); $t->timestamps(); },
            'messages' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->text('message')->nullable(); $t->timestamps(); },
            'coupons' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->string('code')->nullable(); $t->timestamps(); },
            'promotions' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->string('name')->nullable(); $t->string('code')->nullable(); $t->boolean('is_active')->default(true); $t->boolean('is_automatic')->default(false); $t->timestamps(); },
            'promotion_banners' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->string('image')->nullable(); $t->timestamps(); },
            'promotion_usages' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->timestamps(); },
            'coupon_product' => function ($t) { $t->unsignedBigInteger('coupon_id'); $t->unsignedBigInteger('product_id'); $t->unsignedBigInteger('tenant_id')->nullable(); },
            'coupon_category' => function ($t) { $t->unsignedBigInteger('coupon_id'); $t->unsignedBigInteger('category_id'); $t->unsignedBigInteger('tenant_id')->nullable(); },
            'promotion_product' => function ($t) { $t->unsignedBigInteger('promotion_id'); $t->unsignedBigInteger('product_id'); $t->unsignedBigInteger('tenant_id')->nullable(); },
            'promotion_category' => function ($t) { $t->unsignedBigInteger('promotion_id'); $t->unsignedBigInteger('category_id'); $t->unsignedBigInteger('tenant_id')->nullable(); },
            'order_coupon' => function ($t) { $t->unsignedBigInteger('order_id'); $t->unsignedBigInteger('coupon_id'); $t->unsignedBigInteger('tenant_id')->nullable(); },
            'product_combos' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->timestamps(); },
            'activity_logs' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->string('log_name')->nullable(); $t->string('category', 50)->nullable(); $t->string('severity', 20)->default('info'); $t->text('description')->nullable(); $t->string('subject_type')->nullable(); $t->unsignedBigInteger('subject_id')->nullable(); $t->string('causer_type')->nullable(); $t->unsignedBigInteger('causer_id')->nullable(); $t->unsignedBigInteger('impersonator_id')->nullable(); $t->unsignedBigInteger('impersonated_user_id')->nullable(); $t->json('properties')->nullable(); $t->string('event')->nullable(); $t->string('batch_uuid')->nullable(); $t->timestamps(); },
            'payment_methods' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->string('name')->nullable(); $t->timestamps(); },
            'telegram_integrations' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->timestamps(); },
            'payment_methods' => function ($t) { $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->string('name')->nullable(); $t->boolean('is_active')->default(true); $t->timestamps(); },
        ];

        foreach ($tables as $name => $callback) {
            if (!Schema::hasTable($name)) {
                Schema::create($name, $callback);
            }
        }

        foreach (['retention_ends_at', 'deleted_at'] as $column) {
            if (Schema::hasColumn('tenant_cleanups', $column)) {
                continue;
            }
            Schema::table('tenant_cleanups', function ($t) use ($column) {
                $t->timestamp($column)->nullable();
            });
        }

        if (!Schema::hasColumn('tenant_cleanups', 'deletion_status')) {
            Schema::table('tenant_cleanups', function ($t) {
                $t->string('deletion_status', 20)->default('none');
            });
        }

        foreach (['tenant_data_retention_days', 'tenant_auto_delete_enabled'] as $column) {
            if (Schema::hasColumn('platform_settings', $column)) {
                continue;
            }
            Schema::table('platform_settings', function ($t) use ($column) {
                $column === 'tenant_data_retention_days'
                    ? $t->unsignedInteger($column)->default(15)
                    : $t->boolean($column)->default(false);
            });
        }
    }
}

class FailingCloudinaryCollectionPurgeService extends TenantDataPurgeService
{
    protected function collectCloudinaryUrls(int $tenantId): array
    {
        throw new TenantFileCollectionException('Simulated Cloudinary inventory failure.');
    }
}

class FailingLocalCollectionPurgeService extends TenantDataPurgeService
{
    protected function collectFilePaths(string $modelClass, int $tenantId): array
    {
        if ($modelClass === \App\Models\Product::class) {
            throw new TenantFileCollectionException('Simulated local file inventory failure.');
        }

        return parent::collectFilePaths($modelClass, $tenantId);
    }
}
