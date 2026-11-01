<?php

namespace App\Console\Commands;

use App\Services\Backup\Encryption\EncryptedContainer;
use App\Services\Backup\SystemBackupArchive;
use App\Services\Backup\SystemBackupAuditLogger;
use App\Services\Backup\SystemBackupValidator;
use Illuminate\Console\Command;
use Throwable;

class InspectSystemBackup extends Command
{
    protected $signature = 'backup:inspect {archive : Path to a system backup archive (.zip or .sbx)}
        {--verify : Stream-verify the encrypted payload (decrypts and hashes, writes nothing)}';

    protected $description = 'Inspect a system backup archive without restoring or decrypting it to disk';

    public function handle(
        EncryptedContainer $container,
        SystemBackupValidator $validator,
        SystemBackupAuditLogger $audit,
    ): int {
        $path = $this->resolveArchive((string) $this->argument('archive'));

        if ($path === null) {
            $this->error('The archive path does not exist.');

            return self::FAILURE;
        }

        try {
            $format = SystemBackupArchive::detectFormat($path);
        } catch (Throwable $exception) {
            $this->error('Unable to inspect the archive: ' . $audit->redact($exception->getMessage()));

            return self::FAILURE;
        }

        if ($format === SystemBackupArchive::FORMAT_PLAIN) {
            try {
                $validation = $validator->validate($path);
            } catch (Throwable $exception) {
                $this->error('The archive failed validation: ' . $audit->redact($exception->getMessage()));

                return self::FAILURE;
            }

            $manifest = $validation['manifest'];

            $this->info('System backup archive (unencrypted zip).');
            $this->line('Schema version: ' . $manifest['schema_version']);
            $this->line('Archive version: ' . $manifest['archive_version']);
            $this->line('Generated at: ' . $manifest['generated_at']);
            $this->line('Database driver: ' . $manifest['database']['driver'] . ' (' . $manifest['database']['method'] . ')');
            $this->line('Database tables: ' . $manifest['database']['tables']);
            $this->line('Media files: ' . $manifest['media']['files'] . ' (' . $manifest['media']['bytes'] . ' bytes)');
            $this->line('Archive bytes: ' . $validation['archive_bytes']);
            $this->line('Encryption: none');

            return self::SUCCESS;
        }

        try {
            $info = $container->inspect($path);
        } catch (Throwable $exception) {
            $this->error('Unable to inspect the encrypted archive: ' . $audit->redact($exception->getMessage()));

            return self::FAILURE;
        }

        $this->info('System backup archive (encrypted SBX1 container).');
        $this->line('Container version: ' . $info['container_version']);
        $this->line('Cipher suite: ' . $info['cipher_suite'] . ' (AES-256-GCM + HKDF-SHA256)');
        $this->line('Key id: ' . $info['key_id']);
        $this->line('Key available locally: ' . ($info['key_available'] ? 'yes' : 'no'));
        $this->line('Envelope verified: ' . ($info['envelope_verified'] ? 'yes' : 'no'));
        $this->line('Payload bytes: ' . $info['payload_bytes']);
        $this->line('Payload SHA-256: ' . $info['payload_sha256']);
        $this->line('Created at: ' . gmdate('c', (int) $info['created_at']));
        $this->line('Archive bytes: ' . $info['bytes']);

        if (!$info['key_available']) {
            $this->warn('The key for this archive is not configured on this host; it cannot be decrypted or verified here.');
        }

        if ((bool) $this->option('verify')) {
            if (!$info['key_available']) {
                $this->error('Cannot verify the payload without the archive key.');

                return self::FAILURE;
            }

            try {
                $verification = $container->verify($path);
            } catch (Throwable $exception) {
                $this->error('Payload verification failed: ' . $audit->redact($exception->getMessage()));

                return self::FAILURE;
            }

            $this->info('Payload verified: ' . $verification['records'] . ' records, SHA-256 matched.');
        }

        return self::SUCCESS;
    }

    private function resolveArchive(string $path): ?string
    {
        foreach ([$path, base_path($path)] as $candidate) {
            if ($candidate !== '' && is_file($candidate)) {
                return realpath($candidate) ?: $candidate;
            }
        }

        return null;
    }
}
