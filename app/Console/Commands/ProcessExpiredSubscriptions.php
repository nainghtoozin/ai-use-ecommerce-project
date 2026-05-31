<?php

namespace App\Console\Commands;

use App\Services\SubscriptionExpiryService;
use Illuminate\Console\Command;

class ProcessExpiredSubscriptions extends Command
{
    protected $signature = 'subscriptions:process-expired
                            {--dry-run : Check for expired subscriptions without updating}';

    protected $description = 'Detect and update expired subscriptions';

    public function handle(SubscriptionExpiryService $service): int
    {
        if ($this->option('dry-run')) {
            return $this->dryRun();
        }

        $this->info('Processing expired subscriptions...');

        $result = $service->process();

        $this->line("  Active → Expired:  {$result['active_expired']}");
        $this->line("  Trial → Expired:   {$result['trial_ended']}");
        $this->line("  Total processed:   {$result['total']}");

        if ($result['total'] === 0) {
            $this->info('No expired subscriptions found.');
        } else {
            $this->info("Done — {$result['total']} subscription(s) marked as expired.");
        }

        return Command::SUCCESS;
    }

    protected function dryRun(): int
    {
        $this->info('Dry-run: checking for subscriptions that would expire...');

        $activeCount = \App\Models\Subscription::where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->count();

        $trialCount = \App\Models\Subscription::where('status', 'trialing')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', now())
            ->count();

        $this->line("  Active subscriptions past expires_at:    {$activeCount}");
        $this->line("  Trialing subscriptions past trial_ends_at: {$trialCount}");
        $this->line("  Total would be expired:                   " . ($activeCount + $trialCount));

        if ($activeCount + $trialCount === 0) {
            $this->info('No subscriptions would be affected.');
        } else {
            $this->warn('Run without --dry-run to apply changes.');
        }

        return Command::SUCCESS;
    }
}
