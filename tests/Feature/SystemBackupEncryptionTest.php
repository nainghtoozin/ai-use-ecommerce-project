<?php

namespace Tests\Feature;

use App\Services\Backup\BackupOperationException;
use App\Services\Backup\Encryption\BackupKeyRing;
use App\Services\Backup\Encryption\EncryptedContainer;
use App\Services\Backup\SystemBackupArchive;
use App\Services\Backup\SystemBackupPreflight;
use App\Services\Backup\SystemBackupService;
use App\Services\Backup\SystemBackupValidator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SystemBackupEncryptionTest extends TestCase
{
    use DatabaseTransactions;

    private string $keyDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        Storage::disk('public')->put('catalog/hero.txt', 'synthetic encrypted backup asset');
        Storage::disk('public')->put('tmp/ignored.txt', 'temporary encrypted file that must not be bundled');

        Config::set('backups.media.disks', ['public']);
        Config::set('backups.database.connection', config('database.default'));
        Config::set('backups.database.dump_binary', null);
        Config::set('backups.encryption.chunk_size', 65536);

        $this->keyDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sbx-keys-' . bin2hex(random_bytes(6));

        if (!mkdir($this->keyDirectory, 0700, true) && !is_dir($this->keyDirectory)) {
            $this->fail('Unable to create the encryption key test directory.');
        }
    }

    protected function tearDown(): void
    {
        foreach (glob($this->keyDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->keyDirectory);

        parent::tearDown();
    }

    public function test_container_round_trip_preserves_the_exact_payload_and_reports_metadata(): void
    {
        $this->configureKey(1, random_bytes(32));

        $plaintext = $this->plaintextFixture(2 * 65536 + 17);
        $containerPath = $this->temporaryPath('round-trip.sbx');
        $container = app(EncryptedContainer::class);

        $result = $container->encrypt($plaintext, $containerPath, 1);

        $this->assertFileExists($containerPath);
        $this->assertSame(1, $result['container_version']);
        $this->assertSame(1, $result['key_id']);
        $this->assertSame(filesize($plaintext), $result['payload_bytes']);
        $this->assertSame(hash_file('sha256', $plaintext), $result['payload_sha256']);
        $this->assertSame(EncryptedContainer::MAGIC, file_get_contents($containerPath, false, null, 0, 4));

        $inspection = $container->inspect($containerPath);

        $this->assertSame(1, $inspection['container_version']);
        $this->assertTrue($inspection['key_available']);
        $this->assertTrue($inspection['envelope_verified']);
        $this->assertSame($result['payload_sha256'], $inspection['payload_sha256']);

        $verification = $container->verify($containerPath);
        $this->assertGreaterThanOrEqual(2, $verification['records']);

        $decrypted = $this->temporaryPath('round-trip.zip');
        $container->decryptToFile($containerPath, $decrypted);

        $this->assertSame(hash_file('sha256', $plaintext), hash_file('sha256', $decrypted));
        $this->assertSame(file_get_contents($plaintext), file_get_contents($decrypted));

        @unlink($containerPath);
        @unlink($decrypted);
        @unlink($plaintext);
    }

    public function test_container_detects_tampering_truncation_trailing_data_and_header_changes(): void
    {
        $this->configureKey(1, random_bytes(32));

        $container = app(EncryptedContainer::class);
        $plaintext = $this->plaintextFixture(200000);

        $cases = [
            'tamper' => function (string $path): void {
                $handle = fopen($path, 'r+b');
                fseek($handle, EncryptedContainer::HEADER_LENGTH + EncryptedContainer::ENVELOPE_LENGTH + 8);
                $byte = fread($handle, 1);
                fseek($handle, -1, SEEK_CUR);
                fwrite($handle, chr(ord($byte) ^ 0xff));
                fclose($handle);
            },
            'header' => function (string $path): void {
                $handle = fopen($path, 'r+b');
                fseek($handle, 40);
                $byte = fread($handle, 1);
                fseek($handle, -1, SEEK_CUR);
                fwrite($handle, chr(ord($byte) ^ 0x01));
                fclose($handle);
            },
            'truncate' => function (string $path): void {
                $handle = fopen($path, 'r+b');
                ftruncate($handle, filesize($path) - 10);
                fclose($handle);
            },
            'trailing' => function (string $path): void {
                file_put_contents($path, 'unexpected', FILE_APPEND);
            },
        ];

        $expected = [
            'tamper' => 'container_authentication_failed',
            'header' => 'container_authentication_failed',
            'truncate' => 'container_truncated',
            'trailing' => 'container_trailing_data',
        ];

        foreach ($cases as $name => $mutate) {
            $path = $this->temporaryPath('tamper-' . $name . '.sbx');
            $container->encrypt($plaintext, $path, 1);
            $mutate($path);

            try {
                $container->verify($path);
                $this->fail('Expected the container to reject the ' . $name . ' variant.');
            } catch (BackupOperationException $exception) {
                $this->assertSame($expected[$name], $exception->reason, 'Unexpected reason for ' . $name);
                $this->assertStringNotContainsString($this->keyDirectory, $exception->getMessage());
            }

            @unlink($path);
        }

        @unlink($plaintext);
    }

    public function test_container_rejects_a_wrong_key_and_reports_a_missing_key(): void
    {
        $this->configureKey(1, random_bytes(32));

        $plaintext = $this->plaintextFixture(70000);
        $path = $this->temporaryPath('wrong-key.sbx');
        app(EncryptedContainer::class)->encrypt($plaintext, $path, 1);

        $this->configureKey(1, random_bytes(32));

        try {
            app(EncryptedContainer::class)->verify($path);
            $this->fail('Expected a wrong key to be rejected.');
        } catch (BackupOperationException $exception) {
            $this->assertSame('container_authentication_failed', $exception->reason);
        }

        Config::set('backups.encryption.keys', []);
        Config::set('backups.encryption.key_file', null);
        Config::set('backups.encryption.key_id', 1);

        try {
            app(EncryptedContainer::class)->verify($path);
            $this->fail('Expected a missing key to be reported.');
        } catch (BackupOperationException $exception) {
            $this->assertSame('encryption_key_unavailable', $exception->reason);
        }

        $inspection = app(EncryptedContainer::class)->inspect($path);
        $this->assertFalse($inspection['key_available']);
        $this->assertFalse($inspection['envelope_verified']);
        $this->assertSame(1, $inspection['key_id']);

        @unlink($path);
        @unlink($plaintext);
    }

    public function test_rewrap_moves_the_envelope_to_another_key_without_changing_the_payload(): void
    {
        $this->configureKey(1, random_bytes(32));
        $this->configureKey(2, random_bytes(32));

        $plaintext = $this->plaintextFixture(150000);
        $path = $this->temporaryPath('rewrap.sbx');
        $container = app(EncryptedContainer::class);
        $container->encrypt($plaintext, $path, 1);

        $before = hash_file('sha256', $path);
        $payloadBefore = $container->inspect($path)['payload_sha256'];

        $result = $container->rewrap($path, 2);

        $this->assertSame(1, $result['previous_key_id']);
        $this->assertSame(2, $result['key_id']);
        $this->assertSame($payloadBefore, $container->inspect($path)['payload_sha256']);
        $this->assertNotSame($before, hash_file('sha256', $path));
        $container->verify($path);

        $decrypted = $this->temporaryPath('rewrap.zip');
        $container->decryptToFile($path, $decrypted);
        $this->assertSame(hash_file('sha256', $plaintext), hash_file('sha256', $decrypted));

        @unlink($path);
        @unlink($decrypted);
        @unlink($plaintext);
    }

    public function test_decryption_never_deletes_or_overwrites_a_preexisting_destination_file(): void
    {
        $this->configureKey(1, random_bytes(32));

        $plaintext = $this->plaintextFixture(70000);
        $containerPath = $this->temporaryPath('preexisting-target.sbx');
        $container = app(EncryptedContainer::class);
        $container->encrypt($plaintext, $containerPath, 1);

        $destination = $this->temporaryPath('preexisting-target.zip');
        file_put_contents($destination, 'operator file that must survive');

        try {
            $container->decryptToFile($containerPath, $destination);
            $this->fail('Expected decryption to refuse an existing destination.');
        } catch (BackupOperationException $exception) {
            $this->assertSame('encryption_failed', $exception->reason);
        }

        $this->assertFileExists($destination);
        $this->assertSame('operator file that must survive', file_get_contents($destination));

        $handle = fopen($containerPath, 'r+b');
        fseek($handle, EncryptedContainer::HEADER_LENGTH + EncryptedContainer::ENVELOPE_LENGTH + 8);
        $byte = fread($handle, 1);
        fseek($handle, -1, SEEK_CUR);
        fwrite($handle, chr(ord($byte) ^ 0xff));
        fclose($handle);

        try {
            $container->decryptToFile($containerPath, $destination);
            $this->fail('Expected the existing destination to be refused even for a tampered container.');
        } catch (BackupOperationException $exception) {
            $this->assertSame('encryption_failed', $exception->reason);
        }

        $this->assertFileExists($destination);
        $this->assertSame('operator file that must survive', file_get_contents($destination));

        $freshDestination = $this->temporaryPath('fresh-target.zip');

        try {
            $container->decryptToFile($containerPath, $freshDestination);
            $this->fail('Expected a tampered container to be rejected.');
        } catch (BackupOperationException $exception) {
            $this->assertSame('container_authentication_failed', $exception->reason);
        }

        $this->assertFileDoesNotExist($freshDestination);

        @unlink($containerPath);
        @unlink($destination);
        @unlink($plaintext);
    }

    public function test_key_material_formats_are_accepted_and_unsafe_locations_are_refused(): void
    {
        $material = random_bytes(32);
        $rawPath = $this->writeKeyFile('raw.key', $material);
        $hexPath = $this->writeKeyFile('hex.key', bin2hex($material));
        $base64Path = $this->writeKeyFile('base64.key', base64_encode($material));

        foreach ([$rawPath, $hexPath, $base64Path] as $path) {
            Config::set('backups.encryption.keys', [1 => $path]);
            Config::set('backups.encryption.key_file', $path);
            Config::set('backups.encryption.key_id', 1);

            $this->assertSame($material, app(BackupKeyRing::class)->key(1));
        }

        Config::set('backups.encryption.keys', [1 => $this->writeKeyFile('short.key', 'too-short')]);

        try {
            app(BackupKeyRing::class)->key(1);
            $this->fail('Expected short key material to be rejected.');
        } catch (BackupOperationException $exception) {
            $this->assertSame('encryption_key_invalid', $exception->reason);
        }

        $insideBackups = Storage::disk('local')->path('backups/keys/1.key');
        @mkdir(dirname($insideBackups), 0775, true);
        file_put_contents($insideBackups, $material);
        Config::set('backups.encryption.keys', [1 => $insideBackups]);
        Config::set('backups.encryption.key_file', $insideBackups);

        try {
            app(BackupKeyRing::class)->key(1);
            $this->fail('Expected a key inside the backup directory to be refused.');
        } catch (BackupOperationException $exception) {
            $this->assertSame('encryption_key_invalid', $exception->reason);
            $this->assertStringNotContainsString('backups/keys', $exception->getMessage());
        }

        Config::set('backups.encryption.key_id', 'not-a-number');

        try {
            app(BackupKeyRing::class)->activeKeyId();
            $this->fail('Expected a non-numeric key id to be rejected.');
        } catch (BackupOperationException $exception) {
            $this->assertSame('encryption_not_configured', $exception->reason);
        }
    }

    public function test_service_creates_an_encrypted_archive_and_removes_the_plaintext_zip(): void
    {
        $this->configureKey(1, random_bytes(32));
        Config::set('backups.encryption.enabled', true);

        $result = app(SystemBackupService::class)->create();

        $this->assertTrue($result['encrypted']);
        $this->assertSame(1, $result['container_version']);
        $this->assertSame(1, $result['key_id']);
        $this->assertSame(64, strlen((string) $result['payload_sha256']));
        $this->assertStringEndsWith('.sbx', $result['path']);
        $this->assertFileExists($result['absolute']);
        $this->assertSame(hash_file('sha256', $result['absolute']), $result['sha256']);

        $topLevel = collect(Storage::disk('local')->files('backups'))
            ->filter(static fn (string $path): bool => str_ends_with($path, '.zip') || str_ends_with($path, '.sbx'))
            ->values()
            ->all();

        $this->assertSame([$result['path']], $topLevel);
        $this->assertSame([], Storage::disk('local')->files('backups/.work'));

        $container = app(EncryptedContainer::class);
        $inspection = $container->inspect($result['absolute']);
        $this->assertTrue($inspection['envelope_verified']);
        $this->assertSame($result['payload_sha256'], $inspection['payload_sha256']);
        $this->assertSame($result['bytes'], $inspection['bytes']);

        $archive = SystemBackupArchive::open(
            $result['absolute'],
            $container,
            Storage::disk('local')->path('backups/.tmp')
        );

        try {
            $this->assertTrue($archive->isEncrypted());
            $this->assertSame(SystemBackupArchive::FORMAT_ENCRYPTED, $archive->format());
            $this->assertFileExists($archive->plaintextPath());

            $validation = app(SystemBackupValidator::class)->validate($archive->plaintextPath());
            $this->assertSame($result['manifest']['files'], $validation['files']);
            $this->assertSame($result['manifest']['database']['entry'], $validation['database_entry']);
            $this->assertSame(1, $archive->container()['key_id']);
        } finally {
            $temporary = $archive->plaintextPath();
            $archive->close();
            $this->assertFileDoesNotExist($temporary);
        }
    }

    public function test_encryption_enabled_without_a_key_never_falls_back_to_plaintext(): void
    {
        Config::set('backups.encryption.enabled', true);
        Config::set('backups.encryption.key_id', 1);
        Config::set('backups.encryption.key_file', $this->keyDirectory . DIRECTORY_SEPARATOR . 'missing.key');
        Config::set('backups.encryption.keys', [1 => $this->keyDirectory . DIRECTORY_SEPARATOR . 'missing.key']);

        try {
            app(SystemBackupService::class)->create();
            $this->fail('Expected the backup to be refused without an available key.');
        } catch (BackupOperationException $exception) {
            $this->assertSame('encryption_key_unavailable', $exception->reason);
        }

        $this->assertSame([], Storage::disk('local')->files('backups'));
        $this->assertSame([], Storage::disk('local')->files('backups/.work'));
    }

    public function test_encryption_enabled_without_a_configured_key_id_is_refused_before_writing(): void
    {
        Config::set('backups.encryption.enabled', true);
        Config::set('backups.encryption.key_id', null);
        Config::set('backups.encryption.key_file', null);
        Config::set('backups.encryption.keys', []);

        try {
            app(SystemBackupService::class)->create();
            $this->fail('Expected the backup to be refused without a key id.');
        } catch (BackupOperationException $exception) {
            $this->assertSame('encryption_not_configured', $exception->reason);
        }

        $this->assertSame([], Storage::disk('local')->files('backups'));
    }

    public function test_encryption_multiplies_the_space_preflight_requirement(): void
    {
        $preflight = app(SystemBackupPreflight::class);
        $plain = $preflight->check(Storage::disk('local')->path('backups'), 1048576, false);
        $encrypted = $preflight->check(Storage::disk('local')->path('backups'), 1048576, true);

        $this->assertSame(1, $plain['copies']);
        $this->assertSame(2, $encrypted['copies']);
        $this->assertGreaterThan($plain['required_bytes'], $encrypted['required_bytes']);
    }

    private function configureKey(int $id, string $material): string
    {
        $path = $this->writeKeyFile('key-' . $id . '.key', $material);

        Config::set('backups.encryption.key_id', $id);
        Config::set('backups.encryption.key_file', $path);
        Config::set('backups.encryption.keys', [$id => $path] + (array) Config::get('backups.encryption.keys', []));

        return $path;
    }

    private function writeKeyFile(string $name, string $contents): string
    {
        $path = $this->keyDirectory . DIRECTORY_SEPARATOR . $name;

        if (file_put_contents($path, $contents) === false) {
            $this->fail('Unable to write a test encryption key.');
        }

        return $path;
    }

    private function plaintextFixture(int $bytes): string
    {
        $path = $this->temporaryPath('fixture.bin');
        $handle = fopen($path, 'wb');

        for ($written = 0; $written < $bytes; $written += 4096) {
            fwrite($handle, substr(random_bytes(4096), 0, min(4096, $bytes - $written)));
        }

        fclose($handle);

        return $path;
    }

    private function temporaryPath(string $name): string
    {
        return $this->keyDirectory . DIRECTORY_SEPARATOR . 'work-' . $name;
    }
}
