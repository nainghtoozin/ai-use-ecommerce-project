<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupStateStore;
use App\Services\Backup\Offsite\RemoteBackupStore;
use App\Services\Backup\SystemBackupArchive;
use App\Services\Backup\SystemBackupAuditLogger;
use Illuminate\Console\Command;
use Throwable;

class UploadSystemBackup extends Command
{
    protected $signature = 'backup:upload {archive : Path to a system backup archive (.sbx or .zip)}
        {--label= : Optional operator label for the audit log}';

    protected $description = 'Copy a finished system backup archive to the configured off-site disk and verify it';

    public function handle(RemoteBackupStore $store, SystemBackupAuditLogger $audit, BackupStateStore $state): int
    {
        $startedAt = microtime(true);
        $archive = $this->resolveArchive((string) $this->argument('archive'));

        if ($archive === null) {
            $this->error('The archive path does not exist.');

            return self::FAILURE;
        }

        $label = trim((string) $this->option('label'));
        $label = $label === '' ? null : $label;

        if (!$store->configured()) {
            $audit->record('system_backup.upload_failed', 'failure', [
                'archive' => basename($archive),
                'label' => $label,
                'duration_ms' => $this->elapsedMilliseconds($startedAt),
                'error_class' => 'offsite_not_configured',
                'error_message' => 'Off-site backup storage is not configured or not enabled.',
            ], 'error');

            $state->recordFailure('offsite_not_configured', 'Off-site backup storage is not configured or not enabled.');

            $this->error('Off-site backup storage is not configured or not enabled.');
            $this->line('Configure an off-site disk and enable BACKUP_OFFSITE_ENABLED before uploading.');

            return self::FAILURE;
        }

        $encrypted = SystemBackupArchive::detectFormat($archive) === SystemBackupArchive::FORMAT_ENCRYPTED;

        if (!$encrypted && (bool) config('backups.offsite.require_encryption', true)) {
            $audit->record('system_backup.upload_failed', 'failure', [
                'archive' => basename($archive),
                'label' => $label,
                'duration_ms' => $this->elapsedMilliseconds($startedAt),
                'error_class' => 'offsite_encryption_required',
                'error_message' => 'Off-site upload requires an encrypted .sbx archive; refusing to copy a plaintext backup.',
            ], 'error');

            $state->recordFailure('offsite_encryption_required', 'Off-site upload requires an encrypted .sbx archive.');

            $this->error('Off-site upload requires an encrypted .sbx archive; refusing to copy a plaintext backup.');

            return self::FAILURE;
        }

        try {
            $result = $store->upload($archive, $encrypted);
        } catch (Throwable $exception) {
            $audit->record('system_backup.upload_failed', 'failure', [
                'archive' => basename($archive),
                'label' => $label,
                'duration_ms' => $this->elapsedMilliseconds($startedAt),
                'error_class' => $exception::class,
                'error_message' => $exception->getMessage(),
            ], 'error');

            $state->recordFailure('offsite_upload_failed', $audit->redact($exception->getMessage()));

            $this->error('Off-site upload failed: ' . $audit->redact($exception->getMessage()));
            $this->line('The local archive was not deleted.');

            return self::FAILURE;
        }

        $audit->record('system_backup.uploaded', 'success', [
            'archive' => basename($archive),
            'label' => $label,
            'offsite_disk' => $result['disk'],
            'offsite_path' => $result['path'],
            'bytes' => $result['bytes'],
            'sha256' => $result['sha256'],
            'remote_bytes' => $result['remote_bytes'],
            'remote_sha256' => $result['remote_sha256'],
            'duration_ms' => $this->elapsedMilliseconds($startedAt),
        ]);

        $state->recordUpload($archive, $result);

        $this->info('Off-site backup uploaded and verified.');
        $this->line('Disk: ' . $result['disk']);
        $this->line('Path: ' . $result['path']);
        $this->line('Bytes: ' . $result['bytes']);
        $this->line('SHA-256: ' . $result['sha256']);
        $this->line('Verification: ' . $result['verified_by']);

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
