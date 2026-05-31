<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\SubscriptionExpiryService;
use Illuminate\Console\Command;

class ProcessExpiredSubscriptions extends Command
{
    protected $signature = 'subscriptions:process-expired
                            {--dry-run : Check for subscriptions needing lifecycle transitions without applying}';

    protected $description = 'Process full subscription lifecycle: grace period, expiry, suspension';

    public function handle(SubscriptionExpiryService $service): int
    {
        if ($this->option('dry-run')) {
            return $this->dryRun();
        }

        $this->info('Processing subscription lifecycle...');

        $result = $service->process();

        $this->line("  Active → Past Due (grace period): {$result['active_to_past_due']}");
        $this->line("  Past Due → Expired (grace ended):  {$result['past_due_to_expired']}");
        $this->line("  Expired → Suspended:               {$result['expired_to_suspended']}");
        $this->line("  Trial → Expired:                   {$result['trial_ended']}");
        $this->line("  Total processed:                   {$result['total']}");

        if ($result['total'] === 0) {
            $this->info('No subscriptions require lifecycle transitions.');
        } else {
            $this->info("Done — {$result['total']} subscription(s) processed.");
        }

        return Command::SUCCESS;
    }

    protected function dryRun(): int
    {
        $this->info('Dry-run: checking subscriptions that would transition...');

        $activeToPastDue = Subscription::where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->count();

        $graceCutoff = now()->subDays(SubscriptionExpiryService::GRACE_DAYS);
        $pastDueToExpired = Subscription::where('status', 'past_due')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', $graceCutoff)
            ->count();

        $suspendCutoff = now()->subDays(SubscriptionExpiryService::SUSPEND_DAYS_AFTER_EXPIRY);
        $expiredToSuspended = Subscription::where('status', 'expired')
            ->where('updated_at', '<', $suspendCutoff)
            ->count();

        $trialToExpired = Subscription::where('status', 'trialing')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', now())
            ->count();

        $total = $activeToPastDue + $pastDueToExpired + $expiredToSuspended + $trialToExpired;

        $this->line("  Active → Past Due (grace period):     {$activeToPastDue}");
        $this->line("  Past Due → Expired (grace ended):      {$pastDueToExpired}");
        $this->line("  Expired → Suspended:                   {$expiredToSuspended}");
        $this->line("  Trial → Expired:                       {$trialToExpired}");
        $this->line("  Total would be affected:               {$total}");

        if ($total === 0) {
            $this->info('No subscriptions would be affected.');
        } else {
            $this->warn('Run without --dry-run to apply changes.');
        }

        return Command::SUCCESS;
    }
}
