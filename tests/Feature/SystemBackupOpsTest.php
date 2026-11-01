<?php

namespace Tests\Feature;

use App\Services\Backup\BackupRetention;
use App\Services\Backup\BackupStateStore;
use App\Services\Backup\Encryption\EncryptedContainer;
use App\Services\Backup\Offsite\RemoteBackupStore;
use App\Services\Backup\SystemBackupAuditLogger;
use App\Services\Backup\SystemBackupService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Backup\CollectingLogger;
use Tests\TestCase;

class SystemBackupOpsTest extends TestCase
{
    use DatabaseTransactions;

    private CollectingLogger $logger;

    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        Storage::disk('public')->put('catalog/asset.txt', 'ops asset');

        Config::set('backups.media.disks', ['public']);
        Config::set('backups.database.connection', config('database.default'));
        Config::set('backups.database.dump_binary', null);
        Config::set('backups.encryption.enabled', false);
        Config::set('backups.encryption.key_id', null);
        Config::set('backups.encryption.key_file', null);
        Config::set('backups.encryption.keys', []);
        Config::set('backups.encryption.chunk_size', 65536);
        Config::set('backups.offsite.enabled', false);
        Config::set('backups.offsite.disk', null);
        Config::set('backups.offsite.path', 'system-backups');
        Config::set('backups.offsite.require_encryption', true);
        Config::set('backups.offsite.verify_readback', true);
        Config::set('backups.retention.enabled', false);
        Config::set('backups.retention.keep_last', 7);
        Config::set('backups.retention.max_age_days', 30);
        Config::set('backups.retention.scheduled', false);
        Config::set('backups.retention.require_offsite', false);
        Config::set('backups.schedule.max_age_hours', 26);

        $this->logger = new CollectingLogger();
        $this->app->instance(SystemBackupAuditLogger::class, new SystemBackupAuditLogger($this->logger));

        $this->temporaryDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'backup-ops-' . bin2hex(random_bytes(6));

        if (!mkdir($this->temporaryDirectory, 0700, true) && !is_dir($this->temporaryDirectory)) {
            $this->fail('Unable to create the backup ops test directory.');
        }
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->temporaryDirectory);

        parent::tearDown();
    }

    public function test_offsite_is_unavailable_until_enabled_and_configured(): void
    {
        $store = app(RemoteBackupStore::class);

        $this->assertFalse($store->configured());

        try {
            $store->upload($this->writeTemporaryFile('remote.bin', 'x'), true);
            $this->fail('Expected an unconfigured off-site store to refuse the upload.');
        } catch (\App\Services\Backup\BackupOperationException $exception) {
            $this->assertSame('offsite_not_configured', $exception->reason);
        }

        Config::set('backups.offsite.enabled', true);
        $this->assertFalse($store->configured());

        Config::set('backups.offsite.disk', 'missing-disk');

        try {
            $store->upload($this->writeTemporaryFile('remote-2.bin', 'x'), true);
            $this->fail('Expected a missing off-site disk to be reported.');
        } catch (\App\Services\Backup\BackupOperationException $exception) {
            $this->assertSame('offsite_not_configured', $exception->reason);
        }
    }

    public function test_offsite_upload_requires_encryption_and_verifies_size_and_checksum(): void
    {
        Storage::fake('offsite');
        Config::set('backups.offsite.enabled', true);
        Config::set('backups.offsite.disk', 'offsite');

        $path = $this->writeTemporaryFile('archive.sbx', random_bytes(4096));
        $store = app(RemoteBackupStore::class);

        try {
            $store->upload($path, false);
            $this->fail('Expected a plaintext upload to be refused.');
        } catch (\App\Services\Backup\BackupOperationException $exception) {
            $this->assertSame('offsite_encryption_required', $exception->reason);
        }

        $this->assertFalse(Storage::disk('offsite')->exists('system-backups/archive.sbx'));

        $result = $store->upload($path, true);

        $this->assertSame('offsite', $result['disk']);
        $this->assertSame('system-backups/archive.sbx', $result['path']);
        $this->assertSame(filesize($path), $result['bytes']);
        $this->assertSame(hash_file('sha256', $path), $result['sha256']);
        $this->assertSame($result['sha256'], $result['remote_sha256']);
        $this->assertSame('size_and_sha256', $result['verified_by']);
        $this->assertTrue(Storage::disk('offsite')->exists('system-backups/archive.sbx'));

        try {
            $store->upload($path, true);
            $this->fail('Expected an existing remote object to be refused.');
        } catch (\App\Services\Backup\BackupOperationException $exception) {
            $this->assertSame('offsite_target_exists', $exception->reason);
            $this->assertSame(hash_file('sha256', $path), hash('sha256', (string) Storage::disk('offsite')->get('system-backups/archive.sbx')));
        }
    }

    public function test_offsite_upload_fails_closed_when_remote_size_or_checksum_differs(): void
    {
        Config::set('backups.offsite.enabled', true);
        Config::set('backups.offsite.disk', 'offsite');

        $path = $this->writeTemporaryFile('archive.sbx', 'payload-bytes');
        $bytes = filesize($path);

        $sizeDisk = $this->fakeFilesystem(['size' => (int) $bytes + 7]);
        Storage::set('offsite', $sizeDisk);

        try {
            app(RemoteBackupStore::class)->upload($path, true);
            $this->fail('Expected a remote size mismatch to fail the upload.');
        } catch (\App\Services\Backup\BackupOperationException $exception) {
            $this->assertSame('offsite_verification_failed', $exception->reason);
        }

        $checksumDisk = $this->fakeFilesystem(['size' => (int) $bytes, 'contents' => 'different-bytes']);
        Storage::set('offsite', $checksumDisk);

        try {
            app(RemoteBackupStore::class)->upload($path, true);
            $this->fail('Expected a remote checksum mismatch to fail the upload.');
        } catch (\App\Services\Backup\BackupOperationException $exception) {
            $this->assertSame('offsite_verification_failed', $exception->reason);
        }
    }

    public function test_upload_command_reports_failure_without_claiming_offsite_protection(): void
    {
        $backup = app(SystemBackupService::class)->create();

        $exitCode = Artisan::call('backup:upload', ['archive' => $backup['absolute']]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Off-site backup storage is not configured', $output);
        $this->assertFileExists($backup['absolute']);
        $this->assertStringContainsString('upload_failed', (string) $this->logger->lastContext()['event']);
    }

    public function test_upload_command_records_a_plaintext_refusal_as_a_failure(): void
    {
        Storage::fake('offsite');
        Config::set('backups.offsite.enabled', true);
        Config::set('backups.offsite.disk', 'offsite');

        $backup = app(SystemBackupService::class)->create();

        $exitCode = Artisan::call('backup:upload', ['archive' => $backup['absolute']]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('requires an encrypted', $output);
        $this->assertStringContainsString('upload_failed', (string) $this->logger->lastContext()['event']);
        $this->assertSame('offsite_encryption_required', app(BackupStateStore::class)->read()['last_failure_reason'] ?? null);
        $this->assertSame([], Storage::disk('offsite')->files('system-backups'));
    }

    public function test_create_upload_reports_local_success_and_offsite_failure_separately(): void
    {
        $exitCode = Artisan::call('backup:create', ['--upload' => true]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('System backup created.', $output);
        $this->assertStringContainsString('Off-site upload failed', $output);
        $this->assertStringContainsString('local archive was kept', $output);
        $this->assertStringNotContainsString('Off-site: uploaded', $output);

        $this->assertCount(1, Storage::disk('local')->files('backups'));
    }

    public function test_create_upload_verifies_and_records_a_remote_copy(): void
    {
        Storage::fake('offsite');
        Config::set('backups.encryption.enabled', true);
        Config::set('backups.offsite.enabled', true);
        Config::set('backups.offsite.disk', 'offsite');
        $this->configureKey(1, random_bytes(32));

        $exitCode = Artisan::call('backup:create', ['--upload' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString('Off-site: uploaded to offsite:system-backups/', $output);

        $remote = Storage::disk('offsite')->files('system-backups');
        $this->assertCount(1, $remote);
        $this->assertStringEndsWith('.sbx', $remote[0]);

        $state = app(BackupStateStore::class)->read();
        $this->assertSame('size_and_sha256', $state['last_offsite']['verified_by'] ?? null);
        $this->assertArrayHasKey(basename($remote[0]), $state['uploads']);
    }

    public function test_prune_dry_run_lists_candidates_without_deleting_anything(): void
    {
        $this->seedArchives();

        $exitCode = Artisan::call('backup:prune', ['--keep-last' => 2, '--max-age-days' => 30]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('retention preview', strtolower($output));
        $this->assertStringContainsString('system-backup-', $output);
        $this->assertCount(7, Storage::disk('local')->files('backups'));
    }

    public function test_prune_confirm_requires_retention_to_be_enabled(): void
    {
        $this->seedArchives();

        $exitCode = Artisan::call('backup:prune', ['--confirm' => true, '--keep-last' => 1, '--max-age-days' => 1]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('retention is not enabled', strtolower($output));
        $this->assertCount(7, Storage::disk('local')->files('backups'));
    }

    public function test_prune_confirm_deletes_only_expired_backup_archives(): void
    {
        Config::set('backups.retention.enabled', true);
        $this->seedArchives();

        $exitCode = Artisan::call('backup:prune', ['--confirm' => true, '--keep-last' => 2, '--max-age-days' => 30]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Deleted 3 archive(s)', $output);

        $remaining = collect(Storage::disk('local')->files('backups'))
            ->map(static fn (string $path): string => basename($path))
            ->sort()
            ->values()
            ->all();

        $this->assertSame([
            'notes.txt',
            'random-export.zip',
            'system-backup-20260104-010101-dddddddd.zip',
            'system-backup-20260105-010101-eeeeeeee.zip',
        ], $remaining);
    }

    public function test_retention_skips_archives_without_a_verified_offsite_copy(): void
    {
        $this->seedArchives();

        $retention = app(BackupRetention::class);
        $plan = $retention->plan(1, 1, true);

        $this->assertSame([], $plan['candidates']);
        $this->assertCount(4, $plan['skipped']);
        $this->assertSame('no_verified_offsite_copy', $plan['skipped'][0]['reason']);

        app(BackupStateStore::class)->recordUpload('backups/system-backup-20260103-010101-cccccccc.zip', [
            'disk' => 'offsite',
            'path' => 'system-backups/system-backup-20260103-010101-cccccccc.zip',
            'sha256' => str_repeat('a', 64),
            'verified_by' => 'size_and_sha256',
        ]);

        $plan = $retention->plan(1, 1, true);

        $this->assertCount(1, $plan['candidates']);
        $this->assertStringContainsString('cccccccc', $plan['candidates'][0]['path']);
    }

    public function test_health_reports_missing_archives_and_configuration_warnings(): void
    {
        $exitCode = Artisan::call('backup:health', ['--json' => true]);
        $report = json_decode(Artisan::output(), true);

        $this->assertSame(1, $exitCode);
        $this->assertFalse($report['healthy']);
        $this->assertContains('no_archive', $report['reasons']);
        $this->assertContains('offsite_not_configured', $report['warnings']);
        $this->assertContains('encryption_disabled', $report['warnings']);
        $this->assertContains('retention_disabled', $report['warnings']);
        $this->assertSame(0, $report['archive_count']);
        $this->assertNull($report['newest']);
    }

    public function test_health_detects_stale_archives_and_recent_failures(): void
    {
        $this->putArchive('system-backup-20260101-010101-aaaaaaaa.zip', 'payload', time() - 40 * 3600);

        $state = app(BackupStateStore::class);
        $state->recordSuccess([
            'path' => 'backups/system-backup-20260101-010101-aaaaaaaa.zip',
            'bytes' => 7,
            'sha256' => str_repeat('b', 64),
            'encrypted' => false,
        ], null);
        $this->travel(5)->seconds();
        $state->recordFailure('insufficient_disk_space', 'not enough space');

        $exitCode = Artisan::call('backup:health', ['--json' => true]);
        $report = json_decode(Artisan::output(), true);

        $this->assertSame(1, $exitCode);
        $this->assertContains('stale_archive', $report['reasons']);
        $this->assertContains('recent_failure', $report['reasons']);
        $this->assertSame('insufficient_disk_space', $report['last_failure_reason']);
        $this->assertSame(40, $report['newest']['age_hours']);
    }

    public function test_health_reports_a_missing_key_for_encrypted_archives(): void
    {
        Config::set('backups.encryption.enabled', true);
        $this->configureKey(1, random_bytes(32));

        $backup = app(SystemBackupService::class)->create();
        $this->assertTrue($backup['encrypted']);

        Config::set('backups.encryption.key_id', null);
        Config::set('backups.encryption.key_file', null);
        Config::set('backups.encryption.keys', []);

        $report = app(\App\Services\Backup\BackupHealthService::class)->report();

        $this->assertContains('key_unavailable', $report['reasons']);
        $this->assertContains('active_key_unavailable', $report['reasons']);
        $this->assertFalse($report['healthy']);
        $this->assertFalse($report['newest']['key_available']);
    }

    public function test_health_verifies_the_newest_archive_on_request(): void
    {
        app(SystemBackupService::class)->create();

        $exitCode = Artisan::call('backup:health', ['--json' => true, '--verify' => true]);
        $report = json_decode(Artisan::output(), true);

        $this->assertSame(0, $exitCode);
        $this->assertTrue($report['healthy']);
        $this->assertSame('verified', $report['newest']['verification']);
        $this->assertSame([], $report['reasons']);
        $this->assertFileDoesNotExist(Storage::disk('local')->path('backups/.tmp'));
    }

    public function test_inspect_command_reports_plain_and_encrypted_archives(): void
    {
        $plain = app(SystemBackupService::class)->create();

        $exitCode = Artisan::call('backup:inspect', ['archive' => $plain['absolute']]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('unencrypted zip', $output);
        $this->assertStringContainsString('Encryption: none', $output);

        Config::set('backups.encryption.enabled', true);
        $this->configureKey(1, random_bytes(32));
        $encrypted = app(SystemBackupService::class)->create();

        $exitCode = Artisan::call('backup:inspect', ['archive' => $encrypted['absolute'], '--verify' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('encrypted SBX1 container', $output);
        $this->assertStringContainsString('Key available locally: yes', $output);
        $this->assertStringContainsString('Envelope verified: yes', $output);
        $this->assertStringContainsString('Payload verified:', $output);
    }

    public function test_inspect_command_reports_an_unavailable_key_without_failing_the_inspection(): void
    {
        Config::set('backups.encryption.enabled', true);
        $this->configureKey(1, random_bytes(32));
        $encrypted = app(SystemBackupService::class)->create();

        Config::set('backups.encryption.key_id', null);
        Config::set('backups.encryption.key_file', null);
        Config::set('backups.encryption.keys', []);

        $exitCode = Artisan::call('backup:inspect', ['archive' => $encrypted['absolute']]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Key available locally: no', $output);
        $this->assertStringContainsString('Envelope verified: no', $output);
        $this->assertStringContainsString('is not configured on this host', $output);

        $exitCode = Artisan::call('backup:inspect', ['archive' => $encrypted['absolute'], '--verify' => true]);
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Cannot verify the payload without the archive key', Artisan::output());
    }

    public function test_decrypt_command_requires_confirm_and_refuses_to_overwrite(): void
    {
        Config::set('backups.encryption.enabled', true);
        $this->configureKey(1, random_bytes(32));
        $encrypted = app(SystemBackupService::class)->create();

        $output = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'decrypted.zip';

        $exitCode = Artisan::call('backup:decrypt', ['archive' => $encrypted['absolute'], '--output' => $output]);
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('without --confirm', Artisan::output());
        $this->assertFileDoesNotExist($output);

        file_put_contents($output, 'existing');
        $exitCode = Artisan::call('backup:decrypt', ['archive' => $encrypted['absolute'], '--output' => $output, '--confirm' => true]);
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('already exists', Artisan::output());
        $this->assertSame('existing', file_get_contents($output));
        @unlink($output);

        $exitCode = Artisan::call('backup:decrypt', ['archive' => $encrypted['absolute'], '--output' => $output, '--confirm' => true]);
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Archive decrypted.', Artisan::output());
        $this->assertFileExists($output);
        $this->assertSame($encrypted['payload_sha256'], hash_file('sha256', $output));

        Config::set('backups.encryption.enabled', false);
        $plain = app(SystemBackupService::class)->create();
        $exitCode = Artisan::call('backup:decrypt', ['archive' => $plain['absolute'], '--output' => $this->temporaryDirectory . '/second.zip', '--confirm' => true]);
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('not an encrypted SBX1 container', Artisan::output());
    }

    public function test_decrypt_command_refuses_plaintext_output_in_the_public_root_and_backup_directory(): void
    {
        Config::set('backups.encryption.enabled', true);
        $this->configureKey(1, random_bytes(32));
        $encrypted = app(SystemBackupService::class)->create();

        $exposed = [
            public_path('leaked-system-backup.zip'),
            Storage::disk('local')->path('backups/leaked-system-backup.zip'),
        ];

        foreach ($exposed as $path) {
            $exitCode = Artisan::call('backup:decrypt', [
                'archive' => $encrypted['absolute'],
                '--output' => $path,
                '--confirm' => true,
            ]);

            $this->assertSame(1, $exitCode);
            $this->assertStringContainsString('must not be inside the public web root or the system backup directory', Artisan::output());
            $this->assertFileDoesNotExist($path);
        }
    }

    public function test_rewrap_command_moves_the_envelope_and_rejects_unknown_keys(): void
    {
        $this->configureKey(1, random_bytes(32));
        $this->configureKey(2, random_bytes(32));
        Config::set('backups.encryption.key_id', 1);
        Config::set('backups.encryption.key_file', (string) Config::get('backups.encryption.keys')[1]);

        Config::set('backups.encryption.enabled', true);
        $encrypted = app(SystemBackupService::class)->create();

        $exitCode = Artisan::call('backup:rewrap', ['archive' => $encrypted['absolute'], '--to-key' => 2]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString('Previous key id: 1', $output);
        $this->assertStringContainsString('New key id: 2', $output);
        $this->assertSame(2, app(EncryptedContainer::class)->inspect($encrypted['absolute'])['key_id']);
        app(EncryptedContainer::class)->verify($encrypted['absolute']);

        $exitCode = Artisan::call('backup:rewrap', ['archive' => $encrypted['absolute'], '--to-key' => 9]);
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('not configured', Artisan::output());

        Config::set('backups.encryption.enabled', false);
        $plain = app(SystemBackupService::class)->create();
        $exitCode = Artisan::call('backup:rewrap', ['archive' => $plain['absolute'], '--to-key' => 2]);
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('not an encrypted SBX1 container', Artisan::output());
    }

    private function seedArchives(): void
    {
        $this->putArchive('system-backup-20260101-010101-aaaaaaaa.zip', 'oldest', time() - 90 * 86400);
        $this->putArchive('system-backup-20260102-010101-bbbbbbbb.zip', 'older', time() - 80 * 86400);
        $this->putArchive('system-backup-20260103-010101-cccccccc.zip', 'middle', time() - 70 * 86400);
        $this->putArchive('system-backup-20260104-010101-dddddddd.zip', 'newer', time() - 60 * 86400);
        $this->putArchive('system-backup-20260105-010101-eeeeeeee.zip', 'newest', time() - 50 * 86400);

        Storage::disk('local')->put('backups/notes.txt', 'operator notes');
        Storage::disk('local')->put('backups/random-export.zip', 'not a system backup');
    }

    private function putArchive(string $name, string $contents, int $modifiedAt): void
    {
        Storage::disk('local')->put('backups/' . $name, $contents);
        touch(Storage::disk('local')->path('backups/' . $name), $modifiedAt);
    }

    private function configureKey(int $id, string $material): void
    {
        $directory = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'keys';
        if (!is_dir($directory)) {
            mkdir($directory, 0700, true);
        }

        $path = $directory . DIRECTORY_SEPARATOR . $id . '.key';
        file_put_contents($path, $material);

        Config::set('backups.encryption.key_id', $id);
        Config::set('backups.encryption.key_file', $path);
        Config::set('backups.encryption.keys', [$id => $path] + (array) Config::get('backups.encryption.keys', []));
    }

    private function writeTemporaryFile(string $name, string $contents): string
    {
        $path = $this->temporaryDirectory . DIRECTORY_SEPARATOR . $name;

        if (file_put_contents($path, $contents) === false) {
            $this->fail('Unable to write the temporary upload fixture.');
        }

        return $path;
    }

    /**
     * @param array{size?: int, contents?: string} $options
     */
    private function fakeFilesystem(array $options): Filesystem
    {
        $disk = $this->createMock(Filesystem::class);
        $disk->method('exists')->willReturn(false);
        $disk->method('makeDirectory')->willReturn(true);
        $disk->method('writeStream')->willReturn(true);
        $disk->method('delete')->willReturn(true);
        $disk->method('size')->willReturn((int) ($options['size'] ?? 0));
        $disk->method('readStream')->willReturnCallback(static function () use ($options) {
            $stream = fopen('php://temp', 'r+b');
            fwrite($stream, (string) ($options['contents'] ?? ''));
            rewind($stream);

            return $stream;
        });

        return $disk;
    }

    private function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($directory);
    }
}
