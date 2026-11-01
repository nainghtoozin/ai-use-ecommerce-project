<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupStateStore;
use App\Services\Backup\Offsite\RemoteBackupStore;
use App\Services\Backup\SystemBackupAuditLogger;
use App\Services\Backup\SystemBackupService;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

class CreateSystemBackup extends Command
{
    protected $signature = 'backup:create {--label= : Optional operator label for the audit log (max 100 printable characters)}
        {--upload : Upload the finished archive to the configured off-site disk and verify it}';

    protected $description = 'Create a private system backup artifact (database snapshot + media + checksum manifest)';

    public function handle(
        SystemBackupService $service,
        SystemBackupAuditLogger $audit,
        RemoteBackupStore $offsite,
        BackupStateStore $state,
    ): int {
        $label = trim((string) $this->option('label'));
        $label = $label === '' ? null : $label;
        $startedAt = microtime(true);

        if ($label !== null && preg_match('/^[^\p{C}]{1,100}$/u', $label) !== 1) {
            $exception = new InvalidArgumentException('The --label option must be 1-100 printable characters.');

            $audit->failure($exception, $this->elapsedMilliseconds($startedAt), null);
            $state->recordFailure('invalid_label', $exception->getMessage());
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        try {
            $result = $service->create();
        } catch (Throwable $exception) {
            $audit->failure($exception, $this->elapsedMilliseconds($startedAt), $label);
            $state->recordFailure(
                $exception instanceof \App\Services\Backup\BackupOperationException ? $exception->reason : $exception::class,
                $audit->redact($exception->getMessage())
            );
            $this->error('System backup failed: ' . $audit->redact($exception->getMessage()));

            return self::FAILURE;
        }

        $audit->success($result, $this->elapsedMilliseconds($startedAt), $label);
        $state->recordSuccess($result, null);

        $this->info('System backup created.');
        $this->line('Disk: ' . $result['disk']);
        $this->line('Path: ' . $result['path']);
        $this->line('Bytes: ' . $result['bytes']);
        $this->line('SHA-256: ' . ($result['sha256'] ?? 'unavailable'));
        $this->line('Encryption: ' . ($result['encrypted']
            ? 'sbx container v' . $result['container_version'] . ', key id ' . $result['key_id']
            : 'none (plain zip)'));

        if (!$this->option('upload')) {
            $this->line('Off-site: not requested' . ($offsite->configured() ? ' (upload available)' : ' (no off-site disk configured)'));

            return self::SUCCESS;
        }

        if (!$offsite->configured()) {
            $audit->record('system_backup.upload_failed', 'failure', [
                'archive' => basename((string) $result['path']),
                'label' => $label,
                'error_class' => 'offsite_not_configured',
                'error_message' => 'Off-site backup storage is not configured or not enabled.',
            ], 'error');

            $state->recordFailure('offsite_not_configured', 'Off-site backup storage is not configured or not enabled.');

            $this->error('Off-site upload failed: off-site backup storage is not configured or not enabled.');
            $this->line('The local archive was kept and is usable.');

            return self::FAILURE;
        }

        try {
            $upload = $offsite->upload((string) $result['absolute'], (bool) $result['encrypted']);
        } catch (Throwable $exception) {
            $audit->record('system_backup.upload_failed', 'failure', [
                'archive' => basename((string) $result['path']),
                'label' => $label,
                'error_class' => $exception::class,
                'error_message' => $exception->getMessage(),
            ], 'error');

            $state->recordFailure('offsite_upload_failed', $audit->redact($exception->getMessage()));

            $this->error('Off-site upload failed: ' . $audit->redact($exception->getMessage()));
            $this->line('The local archive was kept and is usable. Off-site protection is not confirmed.');

            return self::FAILURE;
        }

        $audit->record('system_backup.uploaded', 'success', [
            'archive' => basename((string) $result['path']),
            'label' => $label,
            'offsite_disk' => $upload['disk'],
            'offsite_path' => $upload['path'],
            'bytes' => $upload['bytes'],
            'sha256' => $upload['sha256'],
            'remote_bytes' => $upload['remote_bytes'],
            'remote_sha256' => $upload['remote_sha256'],
        ]);

        $state->recordUpload((string) $result['path'], $upload);

        $this->line('Off-site: uploaded to ' . $upload['disk'] . ':' . $upload['path'] . ' (' . $upload['verified_by'] . ')');

        return self::SUCCESS;
    }

    private function elapsedMilliseconds(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
