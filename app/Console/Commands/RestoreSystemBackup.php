<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupOperationException;
use App\Services\Backup\SystemBackupAuditLogger;
use App\Services\Backup\SystemRestoreService;
use Illuminate\Console\Command;
use Throwable;

class RestoreSystemBackup extends Command
{
    protected $signature = 'backup:restore {archive : Path to a system backup archive (.zip or .sbx)}
        {--target-database= : New disposable database to create (must start with restore_ or drill_)}
        {--confirm-database= : Must repeat the target database name exactly}
        {--media-target= : Absolute path that does not exist yet; restored media is written there}
        {--confirm-media-target= : Must repeat the media target path exactly}
        {--dry-run : Validate the archive and target without creating anything}';

    protected $description = 'Restore a system backup into a new disposable database and an isolated media directory (operator only)';

    public function handle(SystemRestoreService $service, SystemBackupAuditLogger $audit): int
    {
        $startedAt = microtime(true);

        $target = trim((string) $this->option('target-database'));
        $confirmation = trim((string) $this->option('confirm-database'));
        $media = trim((string) $this->option('media-target'));
        $mediaConfirmation = trim((string) $this->option('confirm-media-target'));
        $dryRun = (bool) $this->option('dry-run');

        if ($target === '') {
            $this->error('The --target-database option is required.');
            $this->line('Restore targets must be new databases whose names start with restore_ or drill_.');

            return self::FAILURE;
        }

        if ($confirmation === '') {
            $this->error('The --confirm-database option is required and must repeat the target database name.');

            return self::FAILURE;
        }

        if ($media === '' && $mediaConfirmation !== '') {
            $this->error('The --confirm-media-target option was provided without --media-target.');

            return self::FAILURE;
        }

        if ($media !== '' && $mediaConfirmation === '') {
            $this->error('The --confirm-media-target option is required when --media-target is set.');

            return self::FAILURE;
        }

        $archive = $this->resolveArchive((string) $this->argument('archive'));

        if ($archive === null) {
            $this->error('The archive path does not exist.');

            return self::FAILURE;
        }

        $audit->record('system_backup.restore_started', $dryRun ? 'preview' : 'started', [
            'archive' => basename($archive),
            'target_database' => $target,
            'media_target' => $media === '' ? null : $media,
            'dry_run' => $dryRun,
        ]);

        try {
            $result = $service->restore([
                'archive' => $archive,
                'target_database' => $target,
                'confirm_database' => $confirmation,
                'media_target' => $media === '' ? null : $media,
                'confirm_media_target' => $mediaConfirmation === '' ? null : $mediaConfirmation,
                'dry_run' => $dryRun,
            ]);
        } catch (Throwable $exception) {
            $context = $exception instanceof BackupOperationException ? $exception->context : [];

            $audit->record('system_backup.restore_failed', 'failure', array_merge($context, [
                'archive' => basename($archive),
                'target_database' => $target,
                'duration_ms' => $this->elapsedMilliseconds($startedAt),
                'error_class' => $exception::class,
                'error_message' => $exception->getMessage(),
            ]), 'error');

            $this->error('System restore failed: ' . $audit->redact($exception->getMessage()));

            if ($context !== []) {
                $this->line('Phase: ' . ($context['phase'] ?? 'unknown'));
                $this->line('Cleanup: ' . (($context['cleanup'] ?? '') === '' ? 'nothing created' : (string) $context['cleanup']));
            }

            return self::FAILURE;
        }

        $audit->record('system_backup.restore_completed', $result['dry_run'] ? 'preview' : 'success', [
            'archive' => $result['archive'],
            'target_database' => $result['target_database'],
            'media_target' => $result['media_target'],
            'dry_run' => $result['dry_run'],
            'encrypted' => $result['encrypted'],
            'key_id' => $result['key_id'],
            'container_version' => $result['container_version'],
            'tables_restored' => $result['tables_restored'] ?? $result['database_tables'] ?? null,
            'media_files' => $result['media_files'],
            'media_bytes' => $result['media_bytes'],
            'duration_ms' => $this->elapsedMilliseconds($startedAt),
        ]);

        if ($result['dry_run']) {
            $this->info('Restore preflight passed (dry run: nothing was created).');
            $this->line('Archive: ' . $result['archive'] . ' (' . ($result['encrypted'] ? 'encrypted sbx' : 'plain zip') . ')');
            $this->line('Archive generated at: ' . ($result['generated_at'] ?? 'unknown'));
            $this->line('Target database: ' . $result['target_database'] . ' (does not exist)');
            $this->line('Media target: ' . ($result['media_target'] ?? 'not requested'));
            $this->line('Tables in archive: ' . $result['database_tables']);
            $this->line('Media in archive: ' . $result['media_files'] . ' file(s), ' . $result['media_bytes'] . ' bytes');
            $this->line('Phases checked: ' . implode(', ', $result['phases']));

            return self::SUCCESS;
        }

        $this->info('System restore completed.');
        $this->line('Archive: ' . $result['archive']);
        $this->line('Target database: ' . $result['target_database']);
        $this->line('Tables restored: ' . $result['tables_restored']);
        $this->line('Migration records: ' . $result['migrations']);
        $this->line('Media restored: ' . $result['media_files'] . ' file(s), ' . $result['media_bytes'] . ' bytes');
        $this->line('Media target: ' . ($result['media_target'] ?? 'not requested'));

        $this->warn('The restored database is a disposable copy. Review it, then drop it manually when finished.');
        $this->warn('The application was not pointed at the restored database and no live media was overwritten.');

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
