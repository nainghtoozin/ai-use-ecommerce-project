<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupRetention;
use App\Services\Backup\SystemBackupAuditLogger;
use Illuminate\Console\Command;

class PruneSystemBackups extends Command
{
    protected $signature = 'backup:prune
        {--dry-run : List retention candidates without deleting anything}
        {--confirm : Delete the listed archives (requires retention to be enabled)}
        {--keep-last= : Override the configured number of newest archives to keep}
        {--max-age-days= : Override the configured maximum age in days}';

    protected $description = 'Preview or apply system backup retention (never touches non-backup data)';

    public function handle(BackupRetention $retention, SystemBackupAuditLogger $audit): int
    {
        $confirm = (bool) $this->option('confirm');
        $dryRun = (bool) $this->option('dry-run') || !$confirm;

        if ($confirm && !(bool) config('backups.retention.enabled', false)) {
            $this->error('Backup retention is not enabled; refusing to delete any archive.');
            $this->line('Enable BACKUP_RETENTION_ENABLED explicitly before running backup:prune --confirm.');

            return self::FAILURE;
        }

        $keepLast = $this->option('keep-last') !== null
            ? (int) $this->option('keep-last')
            : (int) config('backups.retention.keep_last', 7);
        $maxAgeDays = $this->option('max-age-days') !== null
            ? (int) $this->option('max-age-days')
            : (int) config('backups.retention.max_age_days', 30);
        $requireOffsite = (bool) config('backups.retention.require_offsite', false);

        $plan = $retention->plan($keepLast, $maxAgeDays, $requireOffsite);

        if ($dryRun) {
            $audit->record('system_backup.prune_preview', 'preview', [
                'dry_run' => true,
                'candidates' => count($plan['candidates']),
                'kept' => count($plan['kept']),
                'message' => 'retention preview; no files were deleted',
            ]);

            $this->info('Backup retention preview (nothing deleted).');
            $this->line('Keep last: ' . max(1, $keepLast) . ', max age: ' . $maxAgeDays . ' day(s), require off-site: ' . ($requireOffsite ? 'yes' : 'no'));

            if ($plan['candidates'] === []) {
                $this->line('No archives are eligible for deletion.');
            } else {
                $this->line('Eligible for deletion:');
                $this->table(
                    ['Path', 'Age (days)', 'Bytes', 'Reason'],
                    array_map(static fn (array $file): array => [
                        $file['path'],
                        $file['age_days'],
                        $file['bytes'],
                        $file['reason'],
                    ], $plan['candidates'])
                );
            }

            if ($plan['skipped'] !== []) {
                $this->warn('Skipped because no verified off-site copy: ' . implode(', ', array_column($plan['skipped'], 'path')));
            }

            if (!$confirm) {
                $this->line('Re-run with --confirm to delete these archives.');
            }

            return self::SUCCESS;
        }

        if ($plan['candidates'] === []) {
            $this->info('No archives are eligible for deletion.');

            return self::SUCCESS;
        }

        $result = $retention->delete($plan['candidates']);

        foreach ($result['deleted'] as $path) {
            $this->line('Deleted: ' . $path);
        }

        $audit->record('system_backup.pruned', $result['failed'] === [] ? 'success' : 'partial_failure', [
            'deleted' => count($result['deleted']),
            'bytes' => $result['bytes'],
            'candidates' => count($plan['candidates']),
            'message' => $result['failed'] === [] ? null : 'failed to delete: ' . implode(', ', $result['failed']),
        ], $result['failed'] === [] ? 'info' : 'error');

        if ($result['failed'] !== []) {
            $this->error('Some archives could not be deleted: ' . implode(', ', $result['failed']));

            return self::FAILURE;
        }

        $this->info('Deleted ' . count($result['deleted']) . ' archive(s), ' . $result['bytes'] . ' bytes.');

        return self::SUCCESS;
    }
}
