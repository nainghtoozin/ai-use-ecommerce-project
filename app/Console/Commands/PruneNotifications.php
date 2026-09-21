<?php

namespace App\Console\Commands;

use App\Models\PlatformSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneNotifications extends Command
{
    protected $signature = 'notifications:prune {--dry-run : Show what would be deleted without actually deleting}';
    protected $description = 'Delete database notifications older than the retention period';

    public function handle(): int
    {
        $retentionDays = (int) (PlatformSetting::current()->notification_retention_days ?? 90);

        if ($retentionDays <= 0) {
            $this->info('Notification retention is set to "Never". No notifications will be deleted.');
            return self::SUCCESS;
        }

        $cutoffDate = now()->subDays($retentionDays);
        $count = DB::table('notifications')->where('created_at', '<', $cutoffDate)->count();

        if ($count === 0) {
            $this->info("No notifications older than {$retentionDays} days found.");
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("Would delete {$count} notification(s) older than {$cutoffDate->format('Y-m-d')} (retention: {$retentionDays} days).");
            return self::SUCCESS;
        }

        $this->info("Deleting {$count} notification(s) older than {$retentionDays} days...");

        $deleted = 0;
        do {
            $batch = DB::table('notifications')
                ->where('created_at', '<', $cutoffDate)
                ->limit(1000)
                ->delete();
            $deleted += $batch;
        } while ($batch > 0);

        $this->info("Successfully deleted {$deleted} notification(s).");

        return self::SUCCESS;
    }
}
