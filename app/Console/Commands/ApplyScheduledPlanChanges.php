<?php

namespace App\Console\Commands;

use App\Services\SubscriptionPlanChangeService;
use Illuminate\Console\Command;

class ApplyScheduledPlanChanges extends Command
{
    protected $signature = 'subscriptions:apply-scheduled-changes';
    protected $description = 'Apply scheduled plan changes (downgrades) that are due';

    public function handle(SubscriptionPlanChangeService $planChange): int
    {
        $count = $planChange->applyScheduledChanges();

        $this->info("Applied {$count} scheduled plan change(s).");

        return self::SUCCESS;
    }
}
