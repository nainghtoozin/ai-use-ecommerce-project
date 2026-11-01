<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\MerchantRecoveryClaim;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use ZipArchive;

class MerchantBackupVerificationTest extends TestCase
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
            'exports.backup.verify_per_hour' => 5,
            'exports.backup.upload_ttl_hours' => 1,
            'exports.backup.max_upload_kb' => 51200,
            'exports.backup.max_entries' => 200,
            'exports.backup.max_entry_bytes' => 67108864,
            'exports.backup.max_total_bytes' => 268435456,
        ]);

        Permission::firstOrCreate(['name' => 'billing.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'billing.manage', 'guard_name' => 'web']);

        foreach (['backup.view', 'backup.download', 'backup.verify', 'backup.recover'] as $backupPermission) {
            Permission::firstOrCreate(['name' => $backupPermission, 'guard_name' => 'web']);
        }

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions(['billing.view']);

        $plan = Plan::firstOrCreate(
            ['slug' => 'backup-verify-test-plan'],
            ['name' => 'Backup Verify Test Plan', 'monthly_price' => 0, 'yearly_price' => 0, 'status' => 'active']
        );

        $this->tenantA = Tenant::create(['name' => 'Verify Store A', 'slug' => 'verify-store-a-' . uniqid()]);
        $this->tenantB = Tenant::create(['name' => 'Verify Store B', 'slug' => 'verify-store-b-' . uniqid()]);

        $this->tenantA->subscription_plan_id = $plan->id;
        $this->tenantA->save();

        $this->adminA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Verify Admin A',
            'email' => 'verify-a-' . uniqid() . '@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $this->adminA->assignRole($adminRole);
        $this->adminA->givePermissionTo(['billing.view', 'backup.view', 'backup.download', 'backup.verify']);

        $this->adminB = User::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Verify Admin B',
            'email' => 'verify-b-' . uniqid() . '@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $this->adminB->assignRole($adminRole);
        $this->adminB->givePermissionTo(['billing.view', 'backup.view', 'backup.download', 'backup.verify']);
    }

    protected function tearDown(): void
    {
        Tenant::setCurrent(null);

        foreach (array_filter([$this->tenantA ?? null, $this->tenantB ?? null]) as $tenant) {
            $root = Storage::disk('local')->path('exports/' . $tenant->id);

            foreach (glob($root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'upload-*.zip') ?: [] as $file) {
                @unlink($file);
            }

            foreach (glob($root . DIRECTORY_SEPARATOR . 'backup-*.zip') ?: [] as $file) {
                @unlink($file);
            }
        }

        File::deleteDirectory(storage_path('framework/testing/merchant-backup'));

        parent::tearDown();
    }

    private function actAsA(): void
    {
        $this->actingAs($this->adminA);
        Tenant::setCurrent($this->tenantA);
    }

    private function actAsB(): void
    {
        $this->actingAs($this->adminB);
        Tenant::setCurrent($this->tenantB);
    }

    public function test_cleanup_command_deletes_only_expired_valid_uploads(): void
    {
        $expired = $this->makeUploadFile($this->tenantA, 'upload-' . Str::uuid() . '.zip', 7200);
        $fresh = $this->makeUploadFile($this->tenantA, 'upload-' . Str::uuid() . '.zip', 60);
        $malformed = $this->makeUploadFile($this->tenantA, 'notes.txt', 7200);
        $badName = $this->makeUploadFile($this->tenantA, 'upload-not-a-uuid.zip', 7200);

        $outside = Storage::disk('local')->path('exports/' . $this->tenantA->id . '/backup-keep.zip');
        File::put($outside, 'keep');
        touch($outside, time() - 7200);

        $legacyDir = Storage::disk('local')->path('exports/legacy/uploads');
        File::ensureDirectoryExists($legacyDir);
        $legacy = $legacyDir . DIRECTORY_SEPARATOR . 'upload-' . Str::uuid() . '.zip';
        File::put($legacy, 'keep');
        touch($legacy, time() - 7200);

        $this->artisan('backup:cleanup-uploads')->assertExitCode(0);

        $this->assertFileDoesNotExist($expired);
        $this->assertFileExists($fresh);
        $this->assertFileExists($malformed);
        $this->assertFileExists($badName);
        $this->assertFileExists($outside);
        $this->assertFileExists($legacy);

        @unlink($outside);
        File::deleteDirectory($legacyDir);
    }

    public function test_cleanup_command_and_verifier_keep_uploads_used_by_an_active_recovery(): void
    {
        $name = 'upload-' . Str::uuid() . '.zip';
        $path = $this->makeUploadFile($this->tenantA, $name, 7200);

        $claim = MerchantRecoveryClaim::create([
            'archive_id' => 'cleanup-claim-' . uniqid(),
            'source_tenant_id' => $this->tenantA->id,
            'target_slug' => 'cleanup-target-' . strtolower(uniqid()),
            'archive_name' => $name,
            'status' => MerchantRecoveryClaim::STATUS_CLAIMED,
            'expires_at' => now()->addMinutes(30),
        ]);

        app(\App\Services\Export\MerchantBackupVerifier::class)->cleanupUploads($this->tenantA->id);
        $this->assertFileExists($path);

        $this->artisan('backup:cleanup-uploads')->assertExitCode(0);
        $this->assertFileExists($path);

        $claim->update(['status' => MerchantRecoveryClaim::STATUS_FAILED, 'expires_at' => null]);

        $this->artisan('backup:cleanup-uploads')->assertExitCode(0);
        $this->assertFileDoesNotExist($path);
    }

    public function test_cleanup_command_dry_run_tenant_scope_and_validation(): void
    {
        $expiredA = $this->makeUploadFile($this->tenantA, 'upload-' . Str::uuid() . '.zip', 7200);
        $expiredB = $this->makeUploadFile($this->tenantB, 'upload-' . Str::uuid() . '.zip', 7200);

        $this->artisan('backup:cleanup-uploads', ['--dry-run' => true])->assertExitCode(0);
        $this->assertFileExists($expiredA);
        $this->assertFileExists($expiredB);

        $this->artisan('backup:cleanup-uploads', ['--tenant' => (string) $this->tenantA->id])->assertExitCode(0);
        $this->assertFileDoesNotExist($expiredA);
        $this->assertFileExists($expiredB);

        $this->artisan('backup:cleanup-uploads', ['--tenant' => 'not-a-tenant'])->assertExitCode(1);
        $this->assertFileExists($expiredB);
    }

    public function test_cleanup_command_skips_when_another_node_holds_the_lock(): void
    {
        $expired = $this->makeUploadFile($this->tenantA, 'upload-' . Str::uuid() . '.zip', 7200);

        $lock = Cache::lock('merchant-backup-upload-cleanup', 300);
        $this->assertTrue($lock->get());

        $this->artisan('backup:cleanup-uploads')->assertExitCode(0);
        $this->assertFileExists($expired);

        $lock->release();

        $this->artisan('backup:cleanup-uploads')->assertExitCode(0);
        $this->assertFileDoesNotExist($expired);
    }

    private function makeUploadFile(Tenant $tenant, string $name, int $ageSeconds): string
    {
        $dir = Storage::disk('local')->path('exports/' . $tenant->id . '/uploads');
        File::ensureDirectoryExists($dir);

        $path = $dir . DIRECTORY_SEPARATOR . $name;
        File::put($path, 'zip-bytes');
        touch($path, time() - $ageSeconds);

        return $path;
    }

    private function makeProduct(Tenant $tenant, string $name): Product
    {
        Tenant::setCurrent($tenant);

        $category = Category::firstOrCreate([
            'tenant_id' => $tenant->id,
            'name' => 'Verify Category ' . $tenant->id,
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

    private function tempArchivePath(): string
    {
        $dir = storage_path('framework/testing/merchant-backup');
        File::ensureDirectoryExists($dir);

        return $dir . DIRECTORY_SEPARATOR . 'archive-' . bin2hex(random_bytes(8)) . '.zip';
    }

    private function downloadArchive(string $url = '/admin/backups/download'): string
    {
        $response = $this->get($url)->assertOk();
        $source = $response->getFile()->getPathname();
        $copy = $this->tempArchivePath();

        $this->assertTrue(copy($source, $copy), 'Unable to copy the downloaded archive.');
        @unlink($source);

        return $copy;
    }

    private function uploadArchive(string $path, string $url = '/admin/backups/verify')
    {
        $copy = $this->tempArchivePath();
        $this->assertTrue(copy($path, $copy), 'Unable to copy the archive for upload.');

        return $this->post($url, [
            'archive' => new UploadedFile($copy, 'store-backup.zip', 'application/zip', null, true),
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

    private function rewriteManifest(string $path, callable $mutate): array
    {
        $manifest = $this->manifestFrom($path);
        $mutate($manifest);

        $this->rewriteEntry(
            $path,
            'manifest.json',
            (string) json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        return $manifest;
    }

    private function addEntry(string $path, string $entry, string $contents): void
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path) === true);
        $this->assertTrue($zip->addFromString($entry, $contents));
        $zip->close();
    }

    private function markEntryAsSymlink(string $path, string $entry): void
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path) === true);
        $this->assertTrue($zip->setExternalAttributesName($entry, ZipArchive::OPSYS_UNIX, 0120000 << 16));
        $zip->close();
    }

    private function upsertEntry(string $path, string $entry, string $contents): void
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path) === true);
        $existing = $zip->locateName($entry, ZipArchive::FL_NOCASE) !== false;
        if ($existing) {
            $zip->deleteName($entry);
        }
        $this->assertTrue($zip->addFromString($entry, $contents));
        $zip->close();
    }

    private function uploadFiles(Tenant $tenant): array
    {
        $dir = Storage::disk('local')->path('exports/' . $tenant->id . '/uploads');

        return array_values(array_map(
            fn (string $file) => basename($file),
            glob($dir . DIRECTORY_SEPARATOR . 'upload-*.zip') ?: []
        ));
    }

    private function assertRejected($response, string $reason): void
    {
        $response->assertRedirect();
        $response->assertSessionHasErrors('archive');

        $log = ActivityLog::where('event', 'merchant_backup_rejected')->latest('id')->first();

        $this->assertNotNull($log, 'A rejection must be audited.');
        $this->assertSame($reason, $log->properties['reason'] ?? null);
    }

    public function test_valid_signed_archive_is_verified_and_previewed_read_only(): void
    {
        $this->makeProduct($this->tenantA, 'VERIFY-ALPHA-PRODUCT');

        $this->actAsA();

        $archive = $this->downloadArchive();
        $manifest = $this->manifestFrom($archive);

        $response = $this->uploadArchive($archive);
        $response->assertRedirect('/admin/backups/preview');
        $response->assertSessionHasNoErrors();

        $this->get('/admin/backups/preview')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/MerchantBackup/Preview')
                ->where('recoveryEnabled', true)
                ->where('preview.archive_id', $manifest['archive_id'])
                ->where('preview.source_store.id', $this->tenantA->id)
                ->where('preview.recovery_enabled', true)
                ->where('preview.media.bundled', false)
                ->where('preview.media.recoverable', false)
                ->where('preview.target.mode', 'new_inactive_store')
                ->has('preview.categories', 48)
                ->has('preview.data_losses', 11)
                ->where('preview.summary.total_records', $manifest['total_records'])
                ->where('preview.summary.media_assets', $manifest['asset_summary']['total']));

        $this->assertGreaterThanOrEqual(1, $manifest['record_counts']['products']);
        $this->assertSame($manifest['archive_id'], session('merchant_backup_preview.archive_id'));
        $this->assertSame($manifest['archive_id'], session('merchant_backup_pending.archive_id'));

        $log = ActivityLog::where('event', 'merchant_backup_verified')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($manifest['archive_id'], $log->properties['archive_id'] ?? null);
        $this->assertArrayNotHasKey('manifest', $log->properties);
        $this->assertArrayNotHasKey('csv', $log->properties);

        $this->assertCount(1, $this->uploadFiles($this->tenantA));
    }

    public function test_legacy_unsigned_version_one_export_is_rejected(): void
    {
        $this->makeProduct($this->tenantA, 'LEGACY-UNSIGNED-PRODUCT');

        $this->actAsA();

        $archive = $this->downloadArchive('/admin/data-export/all');
        $manifest = $this->manifestFrom($archive);

        $this->assertSame(1, $manifest['schema_version']);
        $this->assertArrayNotHasKey('signature', $manifest);

        $this->assertRejected($this->uploadArchive($archive), 'unsupported_version');
    }

    public function test_archive_without_a_signature_is_rejected(): void
    {
        $this->actAsA();
        $archive = $this->downloadArchive();

        $this->rewriteManifest($archive, function (array &$manifest) {
            unset($manifest['signature']);
        });

        $this->assertRejected($this->uploadArchive($archive), 'missing_signature');
    }

    public function test_tampered_manifest_is_rejected(): void
    {
        $this->actAsA();
        $archive = $this->downloadArchive();

        $this->rewriteManifest($archive, function (array &$manifest) {
            $manifest['store']['name'] = 'Tampered Store Name';
            $manifest['total_records'] = (int) $manifest['total_records'] + 500;
            $manifest['record_counts'] = $manifest['categories'];
        });

        $this->assertRejected($this->uploadArchive($archive), 'invalid_signature');
    }

    public function test_tampered_data_file_is_rejected(): void
    {
        $this->makeProduct($this->tenantA, 'TAMPER-DATA-PRODUCT');

        $this->actAsA();
        $archive = $this->downloadArchive();

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($archive) === true);
        $csv = (string) $zip->getFromName('products.csv');
        $zip->close();

        $this->upsertEntry($archive, 'products.csv', $csv . "\n999,TAMPERED-ROW,,,,,,,,,,,,,,,,,,,,,,,,,,\n");

        $this->assertRejected($this->uploadArchive($archive), 'integrity_failed');
    }

    public function test_archive_signed_with_a_different_key_is_rejected(): void
    {
        $this->actAsA();
        $archive = $this->downloadArchive();

        config(['exports.backup.signing_key' => str_repeat('b', 48)]);

        $this->assertRejected($this->uploadArchive($archive), 'invalid_signature');
    }

    public function test_archive_signed_with_an_unsupported_key_id_is_rejected(): void
    {
        $this->actAsA();
        $archive = $this->downloadArchive();

        config(['exports.backup.key_id' => 'rotated-key']);

        $this->assertRejected($this->uploadArchive($archive), 'unsupported_key_id');
    }

    public function test_archive_from_another_tenant_is_rejected(): void
    {
        $this->makeProduct($this->tenantA, 'TENANT-A-PRODUCT');

        $this->actAsA();
        $archive = $this->downloadArchive();
        $manifest = $this->manifestFrom($archive);

        $this->assertSame($this->tenantA->id, $manifest['tenant']['id']);

        $this->actAsB();

        $this->assertRejected($this->uploadArchive($archive), 'tenant_mismatch');
        $this->assertSame([], $this->uploadFiles($this->tenantB));
    }

    public function test_unexpected_zip_entry_is_rejected(): void
    {
        $this->actAsA();
        $archive = $this->downloadArchive();

        $this->addEntry($archive, 'evil.php', '<?php echo "pwned";');

        $this->assertRejected($this->uploadArchive($archive), 'unexpected_entry');
    }

    public function test_path_traversal_entry_is_rejected(): void
    {
        $this->actAsA();
        $archive = $this->downloadArchive();

        $this->addEntry($archive, '../evil.csv', 'traversal');

        $this->assertRejected($this->uploadArchive($archive), 'path_traversal');
    }

    public function test_absolute_path_entry_is_rejected(): void
    {
        $this->actAsA();
        $archive = $this->downloadArchive();

        $this->addEntry($archive, '/abs.csv', 'absolute');

        $this->assertRejected($this->uploadArchive($archive), 'path_traversal');
    }

    public function test_symlink_entry_is_rejected(): void
    {
        $this->actAsA();
        $archive = $this->downloadArchive();

        $this->markEntryAsSymlink($archive, 'products.csv');

        $this->assertRejected($this->uploadArchive($archive), 'symlink_entry');
    }

    public function test_truncated_archive_is_rejected(): void
    {
        $this->actAsA();
        $archive = $this->downloadArchive();

        $bytes = (string) file_get_contents($archive);
        $this->assertGreaterThan(10, strlen($bytes));

        file_put_contents($archive, substr($bytes, 0, (int) floor(strlen($bytes) * 0.6)));

        $this->assertRejected($this->uploadArchive($archive), 'corrupt_archive');
    }

    public function test_oversized_entry_is_rejected(): void
    {
        $this->actAsA();
        $archive = $this->downloadArchive();

        config(['exports.backup.max_entry_bytes' => 10]);

        $this->assertRejected($this->uploadArchive($archive), 'oversized_entry');
    }

    public function test_oversized_upload_fails_validation(): void
    {
        $this->actAsA();
        $archive = $this->downloadArchive();

        config(['exports.backup.max_upload_kb' => 1]);

        $this->assertRejected($this->uploadArchive($archive), 'upload_validation');
    }

    public function test_guest_cannot_verify_or_preview(): void
    {
        $this->get('/admin/backups/preview')->assertRedirect('/login');

        $this->post('/admin/backups/verify')->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_verification_requires_billing_view_baseline(): void
    {
        $this->actAsA();
        $archive = $this->downloadArchive();

        Permission::firstOrCreate(['name' => 'backup.verify', 'guard_name' => 'web']);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $user = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Backup Verify Only',
            'email' => 'verify-only-' . uniqid() . '@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $user->givePermissionTo(['billing.manage', 'backup.verify']);

        $this->actingAs($user);
        Tenant::setCurrent($this->tenantA);

        $this->uploadArchive($archive)->assertForbidden();
    }

    public function test_verification_requires_dedicated_permission_when_defined(): void
    {
        Permission::firstOrCreate(['name' => 'backup.verify', 'guard_name' => 'web']);

        $this->actAsA();

        $archive = $this->downloadArchive();

        $this->adminA->revokePermissionTo('backup.verify');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->uploadArchive($archive)->assertForbidden();

        $this->adminA->givePermissionTo('backup.verify');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->uploadArchive($archive)->assertRedirect('/admin/backups/preview');
    }

    public function test_storefront_prefix_verifies_and_redirects_to_storefront_preview(): void
    {
        $this->makeProduct($this->tenantA, 'STOREFRONT-VERIFY-PRODUCT');

        $this->actAsA();

        $archive = $this->downloadArchive("/store/{$this->tenantA->slug}/admin/backups/download");

        $this->uploadArchive($archive, "/store/{$this->tenantA->slug}/admin/backups/verify")
            ->assertRedirect("/store/{$this->tenantA->slug}/admin/backups/preview");

        $this->get("/store/{$this->tenantA->slug}/admin/backups/preview")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/MerchantBackup/Preview'));
    }

    public function test_verification_is_throttled_per_tenant(): void
    {
        config(['exports.backup.verify_per_hour' => 1]);

        $this->actAsA();
        $archive = $this->downloadArchive();

        $this->uploadArchive($archive)->assertRedirect('/admin/backups/preview');
        $this->uploadArchive($archive)->assertStatus(429);
    }

    public function test_preview_without_a_verified_archive_redirects_to_index(): void
    {
        $this->actAsA();

        $this->get('/admin/backups/preview')
            ->assertRedirect('/admin/backups')
            ->assertSessionHas('info');
    }

    public function test_upload_is_retained_after_accept_and_deleted_after_reject_and_stale_uploads_are_purged(): void
    {
        $this->actAsA();

        $archive = $this->downloadArchive();

        $uploadDir = Storage::disk('local')->path('exports/' . $this->tenantA->id . '/uploads');
        File::ensureDirectoryExists($uploadDir);

        $stale = $uploadDir . DIRECTORY_SEPARATOR . 'upload-stale.zip';
        file_put_contents($stale, 'stale');
        touch($stale, time() - 7200);

        $this->uploadArchive($archive)->assertRedirect('/admin/backups/preview');
        $this->assertFileDoesNotExist($stale);
        $this->assertCount(1, $this->uploadFiles($this->tenantA));

        $tampered = $this->downloadArchive();
        $this->rewriteManifest($tampered, fn (array &$manifest) => $manifest['store']['name'] = 'Cleanup Tamper');
        $this->assertRejected($this->uploadArchive($tampered), 'invalid_signature');

        $this->assertCount(1, $this->uploadFiles($this->tenantA));

        $cleanupLog = ActivityLog::where('event', 'merchant_backup_upload_cleaned')->latest('id')->first();
        $this->assertNotNull($cleanupLog);
        $this->assertArrayNotHasKey('path', $cleanupLog->properties);
    }

    public function test_verification_and_preview_perform_no_application_database_writes(): void
    {
        $this->makeProduct($this->tenantA, 'ZERO-WRITE-PRODUCT');

        $this->actAsA();

        $archive = $this->downloadArchive();

        $before = $this->tableCounts();

        $this->uploadArchive($archive)->assertRedirect('/admin/backups/preview');
        $this->get('/admin/backups/preview')->assertOk();

        $after = $this->tableCounts();

        $this->assertSame($before, $after, 'Verification and preview must not write to application tables.');
    }

    public function test_only_audited_events_are_written_during_verification(): void
    {
        $this->actAsA();
        $archive = $this->downloadArchive();

        $before = ActivityLog::max('id') ?? 0;

        $this->uploadArchive($archive)->assertRedirect('/admin/backups/preview');

        $events = ActivityLog::where('id', '>', $before)->orderBy('id')->pluck('event')->all();

        $this->assertContains('merchant_backup_verified', $events);
        $this->assertNotContains('merchant_backup_upload_cleaned', $events);
        $this->assertNotContains('merchant_backup_downloaded', $events);
    }

    private function tableCounts(): array
    {
        $tables = DB::select(
            'SELECT table_name AS name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = ?',
            ['BASE TABLE']
        );

        $counts = [];

        foreach ($tables as $table) {
            if ($table->name === 'activity_logs') {
                continue;
            }

            $counts[$table->name] = DB::table($table->name)->count();
        }

        ksort($counts);

        return $counts;
    }
}
