<?php

namespace App\Console\Commands;

use App\Models\PlatformSetting;
use Illuminate\Console\Command;

class CleanupActivityLogs extends Command
{
    protected $signature = 'activity-logs:cleanup {--dry-run : Show what would be deleted without actually deleting}';
    protected $description = 'Clean up old activity logs based on retention policy';

    public function handle(): int
    {
        $settings = PlatformSetting::current();
        $retentionDays = $settings->audit_retention_days ?? 90;

        if ($retentionDays <= 0) {
            $this->info('Audit retention is set to "Never". No logs will be deleted.');
            return self::SUCCESS;
        }

        $cutoffDate = now()->subDays($retentionDays);
        $count = \App\Models\ActivityLog::where('created_at', '<', $cutoffDate)->count();

        if ($count === 0) {
            $this->info("No activity logs older than {$retentionDays} days found.");
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("Would delete {$count} activity logs older than {$cutoffDate->format('Y-m-d')} (retention: {$retentionDays} days).");
            return self::SUCCESS;
        }

        $this->info("Deleting {$count} activity logs older than {$retentionDays} days...");

        $deleted = \App\Models\ActivityLog::where('created_at', '<', $cutoffDate)->delete();

        $this->info("Successfully deleted {$deleted} activity logs.");

        return self::SUCCESS;
    }
}
