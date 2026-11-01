<?php

namespace App\Console\Commands;

use App\Services\Backup\Encryption\EncryptedContainer;
use App\Services\Backup\SystemBackupArchive;
use App\Services\Backup\SystemBackupAuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DecryptSystemBackup extends Command
{
    protected $signature = 'backup:decrypt {archive : Path to an encrypted .sbx system backup archive}
        {--output= : Destination path for the decrypted zip (must not exist)}
        {--confirm : Confirm writing a plaintext archive to disk}';

    protected $description = 'Decrypt an encrypted system backup archive to a plaintext zip for inspection or restore';

    public function handle(EncryptedContainer $container, SystemBackupAuditLogger $audit): int
    {
        $startedAt = microtime(true);
        $archive = $this->resolveArchive((string) $this->argument('archive'));

        if ($archive === null) {
            $this->error('The archive path does not exist.');

            return self::FAILURE;
        }

        $output = trim((string) $this->option('output'));

        if ($output === '') {
            $this->error('The --output path is required.');

            return self::FAILURE;
        }

        $output = $this->absolutePath($output);

        if ($this->isExposedPath($output)) {
            $this->error('The output path must not be inside the public web root or the system backup directory.');

            return self::FAILURE;
        }

        if (is_file($output) || is_dir($output)) {
            $this->error('The output path already exists; refusing to overwrite it.');

            return self::FAILURE;
        }

        if (!$this->option('confirm')) {
            $this->error('Refusing to write a plaintext archive without --confirm.');

            return self::FAILURE;
        }

        try {
            if (SystemBackupArchive::detectFormat($archive) !== SystemBackupArchive::FORMAT_ENCRYPTED) {
                $this->error('The archive is not an encrypted SBX1 container.');

                return self::FAILURE;
            }

            $result = $container->decryptToFile($archive, $output);
        } catch (Throwable $exception) {
            $audit->record('system_backup.decrypted', 'failure', [
                'archive' => basename($archive),
                'duration_ms' => $this->elapsedMilliseconds($startedAt),
                'error_class' => $exception::class,
                'error_message' => $exception->getMessage(),
            ], 'error');

            $this->error('Decryption failed: ' . $audit->redact($exception->getMessage()));

            return self::FAILURE;
        }

        @chmod($output, 0600);

        $audit->record('system_backup.decrypted', 'success', [
            'archive' => basename($archive),
            'path' => basename($output),
            'bytes' => $result['payload_bytes'],
            'payload_sha256' => $result['payload_sha256'],
            'key_id' => $result['key_id'],
            'container_version' => $result['container_version'],
            'duration_ms' => $this->elapsedMilliseconds($startedAt),
        ]);

        $this->info('Archive decrypted.');
        $this->line('Output: ' . $output);
        $this->line('Payload bytes: ' . $result['payload_bytes']);
        $this->line('Payload SHA-256: ' . $result['payload_sha256']);
        $this->warn('The decrypted zip is plaintext. Delete it as soon as you are done.');

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

    private function absolutePath(string $path): string
    {
        if (preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1 || str_starts_with($path, '/') || str_starts_with($path, '\\\\')) {
            return $path;
        }

        return base_path($path);
    }

    private function elapsedMilliseconds(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function isExposedPath(string $path): bool
    {
        $roots = [public_path()];

        try {
            $roots[] = Storage::disk((string) config('backups.disk', 'local'))
                ->path(trim((string) config('backups.path', 'backups'), '/'));
        } catch (\Throwable) {
            // An unusable backup disk is reported by the backup commands; the public root still applies.
        }

        $candidate = $this->normalizePath($path);

        foreach ($roots as $root) {
            $normalizedRoot = $this->normalizePath($root);

            if ($normalizedRoot !== '' && ($candidate === $normalizedRoot || str_starts_with($candidate, $normalizedRoot . DIRECTORY_SEPARATOR))) {
                return true;
            }
        }

        return false;
    }

    private function normalizePath(string $path): string
    {
        $parent = realpath(dirname($path));
        $resolved = $parent === false ? $path : $parent . DIRECTORY_SEPARATOR . basename($path);
        $resolved = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $resolved), DIRECTORY_SEPARATOR);

        return DIRECTORY_SEPARATOR === '\\' ? strtolower($resolved) : $resolved;
    }
}
