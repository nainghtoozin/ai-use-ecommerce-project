<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Notifications\SubscriptionExpiringSoon;
use Illuminate\Console\Command;

class SendExpiryWarnings extends Command
{
    protected $signature = 'subscriptions:send-expiry-warnings
                            {--dry-run : List warnings that would be sent without dispatching}';

    protected $description = 'Send expiry warning notifications to tenants at 7, 3, and 1 day thresholds';

    private const WARNING_THRESHOLDS = [7, 3, 1];

    public function handle(): int
    {
        $totalSent = 0;

        foreach (self::WARNING_THRESHOLDS as $days) {
            $targetDate = now()->addDays($days)->startOfDay();

            $subscriptions = Subscription::where('status', 'active')
                ->whereNotNull('expires_at')
                ->whereDate('expires_at', $targetDate)
                ->get();

            foreach ($subscriptions as $sub) {
                if ($this->option('dry-run')) {
                    $this->line("  [{$days}d] Would notify tenant #{$sub->tenant_id} — expires {$sub->expires_at->format('Y-m-d')}");
                } else {
                    $sub->tenant->notifyAdmins(new SubscriptionExpiringSoon($sub, $days));
                    $this->line("  [{$days}d] Notified tenant #{$sub->tenant_id} — expires {$sub->expires_at->format('Y-m-d')}");
                }
                $totalSent++;
            }
        }

        if ($totalSent === 0) {
            $this->info('No subscriptions expiring within warning thresholds.');
        } else {
            $this->info("Done — {$totalSent} warning(s) " . ($this->option('dry-run') ? 'would be sent.' : 'sent.'));
        }

        return Command::SUCCESS;
    }
}
