<?php

namespace Tests\Feature;

use App\Services\Backup\BackupValidationException;
use App\Services\Backup\DatabaseDumper;
use App\Services\Backup\SystemBackupService;
use App\Services\Backup\SystemBackupValidator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use PDO;
use Tests\TestCase;
use ZipArchive;

class SystemBackupTest extends TestCase
{
    use DatabaseTransactions;

    private SystemBackupService $service;
    private SystemBackupValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        Storage::disk('public')->put('catalog/hero.txt', 'synthetic system backup asset');
        Storage::disk('public')->put('tmp/ignored.txt', 'temporary file that must not be bundled');

        Config::set('backups.media.disks', ['public']);
        Config::set('backups.database.connection', config('database.default'));

        $this->service = app(SystemBackupService::class);
        $this->validator = app(SystemBackupValidator::class);
    }

    public function test_system_backup_bundles_database_snapshot_media_and_manifest_checksums(): void
    {
        $backup = $this->service->create();
        $manifest = $backup['manifest'];

        $this->assertFileExists($backup['absolute']);
        $this->assertTrue(Storage::disk('local')->exists($backup['path']));
        $this->assertStringStartsWith('backups/system-backup-', $backup['path']);
        $this->assertGreaterThan(0, $backup['bytes']);

        $this->assertSame('system_backup', $manifest['type']);
        $this->assertSame(1, $manifest['schema_version']);
        $this->assertSame(1, $manifest['archive_version']);
        $this->assertSame('database/database.sql', $manifest['database']['entry']);
        $this->assertSame('pdo_consistent_snapshot', $manifest['database']['method']);
        $this->assertSame('innodb_consistent_snapshot', $manifest['database']['consistency']);
        $this->assertGreaterThan(0, $manifest['database']['tables']);

        $mediaEntry = 'media/public/catalog/hero.txt';
        $this->assertArrayHasKey($mediaEntry, $manifest['files']);
        $this->assertSame(hash('sha256', 'synthetic system backup asset'), $manifest['files'][$mediaEntry]['sha256']);
        $this->assertSame(strlen('synthetic system backup asset'), $manifest['files'][$mediaEntry]['bytes']);
        $this->assertArrayNotHasKey('media/public/tmp/ignored.txt', $manifest['files']);
        $this->assertSame(1, $manifest['media']['files']);

        $expected = array_keys($manifest['files']);
        $expected[] = 'manifest.json';
        sort($expected);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($backup['absolute']) === true);
        $entries = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entries[] = $zip->getNameIndex($index);
        }

        $databaseSql = (string) $zip->getFromName('database/database.sql');
        $zip->close();

        sort($entries);
        $this->assertSame($expected, $entries);
        $this->assertStringContainsString('CREATE TABLE `orders`', $databaseSql);
        $this->assertStringContainsString('INSERT INTO `migrations`', $databaseSql);
        $this->assertStringNotContainsString('CREATE DATABASE', $databaseSql);
        $this->assertStringNotContainsString('USE `', $databaseSql);
    }

    public function test_preflight_accepts_a_valid_archive_and_keeps_media_paths_inside_the_target_directory(): void
    {
        $backup = $this->service->create();
        $sandbox = Storage::disk('local')->path('restore-sandbox');
        $this->assertTrue(mkdir($sandbox, 0775, true) || is_dir($sandbox));

        $result = $this->validator->validate($backup['absolute'], $sandbox);

        $this->assertSame($backup['manifest']['files'], $result['files']);
        $this->assertSame('database/database.sql', $result['database_entry']);
        $this->assertContains('media/public/catalog/hero.txt', $result['media_entries']);

        $resolved = $this->validator->mediaTargetPath('media/public/catalog/hero.txt', $sandbox);
        $normalizedSandbox = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $sandbox);
        $this->assertStringStartsWith($normalizedSandbox, $resolved);
        $this->assertStringEndsWith('hero.txt', $resolved);
    }

    public function test_sqlite_connection_uses_a_vacuum_snapshot(): void
    {
        $sqlitePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'system-backup-source-' . uniqid() . '.sqlite';
        $destination = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'system-backup-sqlite-' . uniqid() . '.sqlite';

        try {
            $pdo = new PDO('sqlite:' . $sqlitePath);
            $pdo->exec('CREATE TABLE drill_rows (id INTEGER PRIMARY KEY, name TEXT)');
            $pdo->exec("INSERT INTO drill_rows (name) VALUES ('synthetic')");
            $pdo = null;

            Config::set('database.connections.backup_sqlite_test', [
                'driver' => 'sqlite',
                'database' => $sqlitePath,
                'prefix' => '',
            ]);
            Config::set('backups.database.connection', 'backup_sqlite_test');

            $meta = app(DatabaseDumper::class)->dump($destination);

            $this->assertSame('sqlite', $meta['driver']);
            $this->assertSame('sqlite_vacuum', $meta['method']);
            $this->assertSame('consistent', $meta['consistency']);
            $this->assertSame('database/database.sqlite', $meta['entry']);
            $this->assertFileExists($destination);
            $this->assertStringStartsWith('SQLite format 3', (string) file_get_contents($destination, false, null, 0, 15));
        } finally {
            Config::set('backups.database.connection', config('database.default'));
            Config::set('database.connections.backup_sqlite_test', null);
            @unlink($sqlitePath);
            @unlink($destination);
        }
    }

    public function test_preflight_rejects_unsupported_versions(): void
    {
        $backup = $this->service->create();

        $this->rewriteManifest($backup['absolute'], function (array $manifest): array {
            $manifest['archive_version'] = 2;

            return $manifest;
        });

        $this->assertRejected($backup['absolute'], 'unsupported_archive_version');

        $this->rewriteManifest($backup['absolute'], function (array $manifest): array {
            $manifest['archive_version'] = 1;
            $manifest['schema_version'] = 0;

            return $manifest;
        });

        $this->assertRejected($backup['absolute'], 'unsupported_schema_version');
    }

    public function test_preflight_rejects_a_missing_listed_file(): void
    {
        $backup = $this->service->create();

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($backup['absolute']) === true);
        $zip->deleteName('media/public/catalog/hero.txt');
        $zip->close();

        $this->assertRejected($backup['absolute'], 'missing_entry');
    }

    public function test_preflight_rejects_a_tampered_file(): void
    {
        $backup = $this->service->create();

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($backup['absolute']) === true);
        $zip->deleteName('media/public/catalog/hero.txt');
        $zip->addFromString('media/public/catalog/hero.txt', 'tampered system backup asset');
        $zip->close();

        $this->assertRejected($backup['absolute'], 'checksum_mismatch');
    }

    public function test_preflight_rejects_unexpected_entries(): void
    {
        $backup = $this->service->create();

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($backup['absolute']) === true);
        $zip->addFromString('notes.txt', 'unexpected');
        $zip->close();

        $this->assertRejected($backup['absolute'], 'unexpected_entry');
    }

    public function test_preflight_rejects_a_malformed_manifest(): void
    {
        $backup = $this->service->create();

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($backup['absolute']) === true);
        $zip->deleteName('manifest.json');
        $zip->addFromString('manifest.json', '{"type":');
        $zip->close();

        $this->assertRejected($backup['absolute'], 'malformed_manifest');
    }

    public function test_preflight_rejects_path_traversal_entries(): void
    {
        $backup = $this->service->create();

        $this->rewriteManifest($backup['absolute'], function (array $manifest): array {
            $manifest['files']['media/../../escape.txt'] = ['bytes' => 0, 'sha256' => str_repeat('a', 64)];

            return $manifest;
        });

        $this->assertRejected($backup['absolute'], 'path_traversal');

        $this->rewriteManifest($backup['absolute'], function (array $manifest): array {
            unset($manifest['files']['media/../../escape.txt']);
            $manifest['files']['C:/escape.txt'] = ['bytes' => 0, 'sha256' => str_repeat('a', 64)];

            return $manifest;
        });

        $this->assertRejected($backup['absolute'], 'invalid_entry');

        $target = Storage::disk('local')->path('restore-sandbox');

        foreach (['media/../../escape.txt', '../escape.txt', 'C:/escape.txt', '/etc/passwd', 'media\\public\\escape.txt'] as $entry) {
            try {
                $this->validator->mediaTargetPath($entry, $target);
                $this->fail('Expected the media target resolver to reject: ' . $entry);
            } catch (BackupValidationException $exception) {
                $this->assertContains($exception->reason, ['path_traversal', 'invalid_entry']);
            }
        }
    }

    public function test_manifest_never_contains_database_credentials(): void
    {
        $backup = $this->service->create();
        $manifest = $backup['manifest'];

        $this->assertSame(
            ['consistency', 'driver', 'entry', 'method', 'server_version', 'tables'],
            collect(array_keys($manifest['database']))->sort()->values()->all()
        );

        foreach (['password', 'username', 'host', 'socket', 'dsn', 'url'] as $key) {
            $this->assertArrayNotHasKey($key, $manifest['database']);
        }

        $encoded = strtolower((string) json_encode($manifest));

        foreach (['db_password', 'db_username', 'defaults-extra-file', 'mysql:host='] as $needle) {
            $this->assertStringNotContainsString($needle, $encoded);
        }
    }

    private function rewriteManifest(string $archivePath, callable $mutate): void
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($archivePath) === true);
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        $manifest = $mutate($manifest);
        $zip->deleteName('manifest.json');
        $zip->addFromString('manifest.json', (string) json_encode($manifest, JSON_PRETTY_PRINT));
        $zip->close();
    }

    private function assertRejected(string $archivePath, string $reason): void
    {
        try {
            $this->validator->validate($archivePath);
            $this->fail('Expected the system backup preflight to reject the archive.');
        } catch (BackupValidationException $exception) {
            $this->assertSame($reason, $exception->reason, $exception->getMessage());
        }
    }
}
