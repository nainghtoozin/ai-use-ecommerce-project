<?php

namespace App\Console\Commands;

use App\Services\Backup\Encryption\BackupKeyRing;
use App\Services\Backup\Encryption\EncryptedContainer;
use App\Services\Backup\SystemBackupArchive;
use App\Services\Backup\SystemBackupAuditLogger;
use Illuminate\Console\Command;
use Throwable;

class RewrapSystemBackup extends Command
{
    protected $signature = 'backup:rewrap {archive : Path to an encrypted .sbx system backup archive}
        {--to-key= : Numeric id of the configured archive key that should wrap the data key}';

    protected $description = 'Re-wrap the data key of an encrypted archive under another configured key without rewriting the payload';

    public function handle(
        EncryptedContainer $container,
        BackupKeyRing $keys,
        SystemBackupAuditLogger $audit,
    ): int {
        $startedAt = microtime(true);
        $archive = $this->resolveArchive((string) $this->argument('archive'));

        if ($archive === null) {
            $this->error('The archive path does not exist.');

            return self::FAILURE;
        }

        $target = $keys->tryNormalizeId($this->option('to-key'));

        if ($target === null) {
            $this->error('The --to-key option must be a numeric key id between 1 and 65535.');

            return self::FAILURE;
        }

        if (!$keys->hasKey($target)) {
            $this->error('The requested key id is not configured or its key file is unavailable.');

            return self::FAILURE;
        }

        try {
            if (SystemBackupArchive::detectFormat($archive) !== SystemBackupArchive::FORMAT_ENCRYPTED) {
                $this->error('The archive is not an encrypted SBX1 container.');

                return self::FAILURE;
            }

            $result = $container->rewrap($archive, $target);
        } catch (Throwable $exception) {
            $audit->record('system_backup.rewrapped', 'failure', [
                'archive' => basename($archive),
                'key_id' => $target,
                'duration_ms' => $this->elapsedMilliseconds($startedAt),
                'error_class' => $exception::class,
                'error_message' => $exception->getMessage(),
            ], 'error');

            $this->error('Rewrap failed: ' . $audit->redact($exception->getMessage()));

            return self::FAILURE;
        }

        $audit->record('system_backup.rewrapped', 'success', [
            'archive' => basename($archive),
            'key_id' => $result['key_id'],
            'duration_ms' => $this->elapsedMilliseconds($startedAt),
        ]);

        $this->info('Archive key envelope rewrapped.');
        $this->line('Archive: ' . $archive);
        $this->line('Previous key id: ' . $result['previous_key_id']);
        $this->line('New key id: ' . $result['key_id']);
        $this->line('Payload untouched.');

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

    private function elapsedMilliseconds(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
