<?php

namespace App\Console\Commands;

use App\Models\PlatformSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneNotifications extends Command
{
    protected $signature = 'notifications:prune {--dry-run : Show what would be deleted without actually deleting}';

    protected $description = 'Delete read database notifications older than the retention period';

    public function handle(): int
    {
        $retentionDays = (int) (PlatformSetting::current()->notification_retention_days ?? 90);

        if ($retentionDays <= 0) {
            $this->info('Notification retention is disabled (retention days = 0). No notifications will be deleted.');
            return self::SUCCESS;
        }

        $cutoffDate = now()->subDays($retentionDays);

        $query = DB::table('notifications')
            ->whereNotNull('read_at')
            ->where('read_at', '<', $cutoffDate);

        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info("No read notifications older than {$retentionDays} days found.");
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("Dry run: would delete {$count} read notification(s) with read_at older than {$cutoffDate->format('Y-m-d H:i:s')} (retention: {$retentionDays} days).");
            return self::SUCCESS;
        }

        $this->info("Deleting {$count} read notification(s) older than {$retentionDays} days...");

        $deleted = 0;
        do {
            $batch = (clone $query)->limit(1000)->delete();
            $deleted += $batch;
        } while ($batch > 0);

        $this->info("Successfully deleted {$deleted} notification(s).");

        return self::SUCCESS;
    }
}
