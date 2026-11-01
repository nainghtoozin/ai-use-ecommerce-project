<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Export\MerchantBackupSigner;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MerchantBackupDownloadTest extends TestCase
{
    use DatabaseTransactions;

    private const SIGNING_KEY = 'test-merchant-backup-signing-key-0123456789abcdef';

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $adminA;
    private User $adminB;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        config([
            'exports.backup.signing_key' => self::SIGNING_KEY,
            'exports.backup.key_id' => 'test-key',
            'exports.backup.per_hour' => 10,
        ]);

        Permission::firstOrCreate(['name' => 'billing.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'billing.manage', 'guard_name' => 'web']);

        foreach (['backup.view', 'backup.download', 'backup.verify', 'backup.recover'] as $backupPermission) {
            Permission::firstOrCreate(['name' => $backupPermission, 'guard_name' => 'web']);
        }

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions(['billing.view']);

        $plan = Plan::firstOrCreate(
            ['slug' => 'backup-test-plan'],
            ['name' => 'Backup Test Plan', 'monthly_price' => 0, 'yearly_price' => 0, 'status' => 'active']
        );

        $this->tenantA = Tenant::create(['name' => 'Backup Store A', 'slug' => 'backup-store-a-' . uniqid()]);
        $this->tenantB = Tenant::create(['name' => 'Backup Store B', 'slug' => 'backup-store-b-' . uniqid()]);

        $this->tenantA->subscription_plan_id = $plan->id;
        $this->tenantA->save();

        $this->adminA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Backup Admin A',
            'email' => 'backup-a-' . uniqid() . '@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $this->adminA->assignRole($adminRole);
        $this->adminA->givePermissionTo(['billing.view', 'backup.view', 'backup.download', 'backup.verify']);

        $this->adminB = User::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Backup Admin B',
            'email' => 'backup-b-' . uniqid() . '@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $this->adminB->assignRole($adminRole);
        $this->adminB->givePermissionTo('billing.view');
    }

    protected function tearDown(): void
    {
        Tenant::setCurrent(null);
        parent::tearDown();
    }

    private function actAsA(): void
    {
        $this->actingAs($this->adminA);
        Tenant::setCurrent($this->tenantA);
    }

    private function makeProduct(Tenant $tenant, string $name): Product
    {
        Tenant::setCurrent($tenant);

        $category = Category::firstOrCreate([
            'tenant_id' => $tenant->id,
            'name' => 'Backup Category ' . $tenant->id,
        ]);

        return Product::create([
            'name' => $name,
            'price' => 100,
            'stock' => 5,
            'status' => 'active',
            'type' => 'single',
            'category_id' => $category->id,
        ]);
    }

    private function readManifest(string $path): array
    {
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path) === true);
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        $this->assertIsArray($manifest);
        $zip->close();

        return $manifest;
    }

    private function zipEntry(string $path, string $entry): string
    {
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path) === true);
        $content = (string) $zip->getFromName($entry);
        $zip->close();

        return $content;
    }

    public function test_index_page_renders_with_security_notices(): void
    {
        $this->actAsA();

        $this->get('/admin/backups')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/MerchantBackup/Index')
                ->where('signingConfigured', true)
                ->where('schemaVersion', 2)
                ->where('mediaBundled', false)
                ->where('mediaRecoverable', false)
                ->where('encrypted', false)
                ->where('recoveryAvailable', false)
                ->where('totalCategories', 48)
                ->has('includedGroups')
                ->has('excludedAreas'));
    }

    public function test_backup_archive_is_signed_and_tenant_scoped(): void
    {
        $this->makeProduct($this->tenantA, 'BACKUP-ALPHA-PRODUCT');
        $this->makeProduct($this->tenantB, 'BACKUP-BETA-PRODUCT');

        $this->actAsA();

        $response = $this->get('/admin/backups/download');
        $response->assertOk();
        $this->assertStringContainsString('.zip', (string) $response->headers->get('content-disposition'));

        $path = $response->getFile()->getPathname();
        $manifest = $this->readManifest($path);
        $productsCsv = $this->zipEntry($path, 'products.csv');

        $this->assertSame(2, $manifest['schema_version']);
        $this->assertSame($this->tenantA->id, $manifest['tenant']['id']);
        $this->assertSame($this->tenantA->slug, $manifest['tenant']['slug']);
        $this->assertNotEmpty($manifest['archive_id']);
        $this->assertSame($this->adminA->id, $manifest['created_by']['id']);
        $this->assertSame('user', $manifest['created_by']['type']);
        $this->assertSame('merchant-backup', $manifest['purpose']);
        $this->assertArrayHasKey('version', $manifest['app']);
        $this->assertArrayHasKey('migrations_applied', $manifest['schema']);
        $this->assertSame($manifest['categories'], $manifest['record_counts']);
        $this->assertSame(array_sum($manifest['categories']), $manifest['total_records']);
        $this->assertGreaterThanOrEqual(1, $manifest['record_counts']['products']);
        $this->assertFalse($manifest['media']['bundled']);
        $this->assertFalse($manifest['media']['recoverable']);
        $this->assertFalse($manifest['encryption']['encrypted']);
        $this->assertSame('hmac-sha256', $manifest['signature']['algorithm']);
        $this->assertSame('test-key', $manifest['signature']['key_id']);
        $this->assertSame(64, strlen($manifest['signature']['value']));
        $this->assertNotEmpty($manifest['excluded']);

        $this->assertTrue(MerchantBackupSigner::fromConfig()->verify($manifest));

        $this->assertStringContainsString('BACKUP-ALPHA-PRODUCT', $productsCsv);
        $this->assertStringNotContainsString('BACKUP-BETA-PRODUCT', $productsCsv);

        $manifestJson = json_encode($manifest, JSON_UNESCAPED_SLASHES);
        $this->assertStringNotContainsString(self::SIGNING_KEY, $manifestJson);
        $this->assertStringNotContainsString('BACKUP-BETA-PRODUCT', $this->zipEntry($path, 'products.csv'));

        @unlink($path);
    }

    public function test_backup_manifest_excludes_non_allowlisted_settings(): void
    {
        Tenant::setCurrent($this->tenantA);
        Setting::create(['key' => 'notifications_enabled', 'value' => 'true']);
        Setting::create(['key' => 'cloudinary_url', 'value' => 'cloudinary://SK-LIVE-SECRET@demo']);
        Setting::create(['key' => 'smtp_password', 'value' => 'SMTP-SECRET-VALUE']);

        $this->actAsA();

        $response = $this->get('/admin/backups/download')->assertOk();
        $path = $response->getFile()->getPathname();

        $settingsCsv = $this->zipEntry($path, 'settings.csv');
        $manifest = $this->readManifest($path);

        $this->assertStringContainsString('notifications_enabled', $settingsCsv);
        $this->assertStringNotContainsString('cloudinary_url', $settingsCsv);
        $this->assertStringNotContainsString('smtp_password', $settingsCsv);
        $this->assertStringNotContainsString('SK-LIVE-SECRET', $settingsCsv);
        $this->assertStringNotContainsString('SMTP-SECRET-VALUE', $settingsCsv);

        $this->assertContains('cloudinary_url', $manifest['excluded_settings']['keys']);
        $this->assertContains('smtp_password', $manifest['excluded_settings']['keys']);

        @unlink($path);
    }

    public function test_backup_fails_closed_when_signing_key_is_missing_or_short(): void
    {
        $this->actAsA();

        config(['exports.backup.signing_key' => null]);
        $this->get('/admin/backups')->assertOk()->assertInertia(fn (Assert $page) => $page->where('signingConfigured', false));
        $this->get('/admin/backups/download')->assertStatus(503);

        config(['exports.backup.signing_key' => str_repeat('a', 31)]);
        $this->get('/admin/backups/download')->assertStatus(503);
    }

    public function test_backup_requires_billing_view_baseline(): void
    {
        $user = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'No Billing View',
            'email' => 'no-billing-backup-' . uniqid() . '@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $user->givePermissionTo('billing.manage');

        $this->actingAs($user);
        Tenant::setCurrent($this->tenantA);

        $this->get('/admin/backups')->assertForbidden();
        $this->get('/admin/backups/download')->assertForbidden();
    }

    public function test_backup_requires_dedicated_permission_when_defined(): void
    {
        Permission::firstOrCreate(['name' => 'backup.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'backup.download', 'guard_name' => 'web']);

        $this->actAsA();

        $this->adminA->revokePermissionTo(['backup.view', 'backup.download']);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->get('/admin/backups')->assertForbidden();
        $this->get('/admin/backups/download')->assertForbidden();

        $this->adminA->givePermissionTo(['backup.view', 'backup.download']);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->get('/admin/backups')->assertOk();
        $response = $this->get('/admin/backups/download')->assertOk();

        @unlink($response->getFile()->getPathname());
    }

    public function test_backup_is_throttled_per_tenant(): void
    {
        config(['exports.backup.per_hour' => 1]);

        $this->actAsA();

        $first = $this->get('/admin/backups/download')->assertOk();
        @unlink($first->getFile()->getPathname());

        $this->get('/admin/backups/download')->assertStatus(429);
    }

    public function test_storefront_prefix_serves_backup(): void
    {
        $this->makeProduct($this->tenantA, 'STOREFRONT-BACKUP-PRODUCT');

        $this->actAsA();

        $this->get("/store/{$this->tenantA->slug}/admin/backups")->assertOk();

        $response = $this->get("/store/{$this->tenantA->slug}/admin/backups/download")->assertOk();
        $path = $response->getFile()->getPathname();
        $manifest = $this->readManifest($path);

        $this->assertSame($this->tenantA->id, $manifest['tenant']['id']);
        $this->assertStringContainsString('STOREFRONT-BACKUP-PRODUCT', $this->zipEntry($path, 'products.csv'));

        @unlink($path);
    }

    public function test_backup_routes_are_registered_on_both_prefixes(): void
    {
        $routes = app('router')->getRoutes();

        $this->assertNotNull($routes->getByName('admin.backups.index'));
        $this->assertNotNull($routes->getByName('admin.backups.download'));
        $this->assertNotNull($routes->getByName('storefront.admin.backups.index'));
        $this->assertNotNull($routes->getByName('storefront.admin.backups.download'));

        $index = $routes->getByName('admin.backups.index')->gatherMiddleware();
        $download = $routes->getByName('admin.backups.download')->gatherMiddleware();

        $this->assertContains('tenant.access', $index);
        $this->assertNotContains('tenant.active', $index);
        $this->assertContains('throttle:merchant-backup', $download);
        $this->assertNotContains('tenant.active', $download);
    }

    public function test_legacy_data_export_manifest_remains_version_one_and_unsigned(): void
    {
        $this->makeProduct($this->tenantA, 'LEGACY-EXPORT-PRODUCT');

        $this->actAsA();

        $path = $this->get('/admin/data-export/all')->assertOk()->getFile()->getPathname();
        $manifest = $this->readManifest($path);

        $this->assertSame(1, $manifest['schema_version']);
        $this->assertArrayNotHasKey('signature', $manifest);
        $this->assertArrayNotHasKey('archive_id', $manifest);
        $this->assertStringContainsString('LEGACY-EXPORT-PRODUCT', $this->zipEntry($path, 'products.csv'));

        @unlink($path);
    }
}
