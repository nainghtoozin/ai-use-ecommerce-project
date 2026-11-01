<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupHealthService;
use App\Services\Backup\SystemBackupAuditLogger;
use Illuminate\Console\Command;

class BackupHealth extends Command
{
    protected $signature = 'backup:health
        {--json : Emit the health report as JSON}
        {--verify : Fully verify the newest archive (streams and hashes it)}';

    protected $description = 'Report the health and freshness of system backups without changing anything';

    public function handle(BackupHealthService $health, SystemBackupAuditLogger $audit): int
    {
        $report = $health->report((bool) $this->option('verify'));

        $audit->record('system_backup.health', $report['healthy'] ? 'healthy' : 'unhealthy', [
            'healthy' => $report['healthy'],
            'archive' => $report['newest']['path'] ?? null,
            'bytes' => $report['newest']['bytes'] ?? null,
            'last_backup_at' => $report['newest']['modified_at'] ?? null,
            'last_success_at' => $report['last_success_at'],
            'reason' => $report['reasons'] === [] ? null : implode(',', $report['reasons']),
            'message' => $report['warnings'] === [] ? null : 'warnings: ' . implode(',', $report['warnings']),
        ], $report['healthy'] ? 'info' : 'error');

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $report['healthy'] ? self::SUCCESS : self::FAILURE;
        }

        $this->line('System backup health: ' . ($report['healthy'] ? 'HEALTHY' : 'UNHEALTHY'));
        $this->line('Disk: ' . $report['disk'] . ' (' . $report['directory'] . ')');
        $this->line('Archives: ' . $report['archive_count']);

        if ($report['newest'] !== null) {
            $newest = $report['newest'];
            $this->line('Newest: ' . $newest['path']);
            $this->line('Newest age: ' . $newest['age_hours'] . ' hour(s) (limit ' . $report['max_age_hours'] . ')');
            $this->line('Newest format: ' . ($newest['format'] ?? 'unreadable'));
            $this->line('Newest bytes: ' . $newest['bytes']);

            if ($newest['key_available'] !== null) {
                $this->line('Encryption key available: ' . ($newest['key_available'] ? 'yes' : 'no'));
            }

            if ($newest['verification'] !== null) {
                $this->line('Verification: ' . $newest['verification']);
            }
        }

        $this->line('Last success: ' . ($report['last_success_at'] ?? 'never'));
        $this->line('Last failure: ' . ($report['last_failure_at'] ?? 'never'));

        if ($report['last_failure_reason'] !== null) {
            $this->line('Last failure reason: ' . $report['last_failure_reason']);
        }

        $this->line('Off-site configured: ' . ($report['offsite_configured'] ? 'yes' : 'no'));
        $this->line('Last off-site verification: ' . ($report['last_offsite']['verified_at'] ?? 'never'));

        if ($report['reasons'] !== []) {
            $this->error('Problems: ' . implode(', ', $report['reasons']));
        }

        if ($report['warnings'] !== []) {
            $this->warn('Warnings: ' . implode(', ', $report['warnings']));
        }

        return $report['healthy'] ? self::SUCCESS : self::FAILURE;
    }
}
