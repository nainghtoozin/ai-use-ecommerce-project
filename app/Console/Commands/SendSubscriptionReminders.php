<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Notifications\SubscriptionExpiringSoon;
use App\Services\SubscriptionAuditService;
use Illuminate\Console\Command;

class SendSubscriptionReminders extends Command
{
    protected $signature = 'subscriptions:send-reminders';
    protected $description = 'Send renewal reminders and trial-ending notifications at configured intervals';

    public function handle(): int
    {
        $reminderDays = [14, 7, 3];
        $sent = ['renewal' => 0, 'trial' => 0];

        foreach ($reminderDays as $days) {
            $targetDate = now()->addDays($days);

            // Renewal reminders for active subscriptions
            $sent['renewal'] += $this->sendRenewalReminders($targetDate, $days);

            // Trial-ending reminders
            $sent['trial'] += $this->sendTrialReminders($targetDate, $days);
        }

        $total = $sent['renewal'] + $sent['trial'];
        $this->info("Sent {$total} reminder(s) ({$sent['renewal']} renewal, {$sent['trial']} trial).");

        return self::SUCCESS;
    }

    private function sendRenewalReminders(\Carbon\Carbon $targetDate, int $days): int
    {
        $count = 0;

        Subscription::where('status', 'active')
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', $targetDate->toDateString())
            ->chunk(100, function ($subscriptions) use ($days, &$count) {
                foreach ($subscriptions as $sub) {
                    $sub->tenant->notifyAdmins(new SubscriptionExpiringSoon($sub, $days));

                    SubscriptionAuditService::log($sub, 'renewal_reminder_sent', [
                        'days_remaining' => $days,
                        'reason' => "Renewal reminder sent ({$days} day(s) before expiry).",
                    ]);

                    $count++;
                }
            });

        return $count;
    }

    private function sendTrialReminders(\Carbon\Carbon $targetDate, int $days): int
    {
        $count = 0;

        Subscription::where('status', 'trialing')
            ->whereNotNull('trial_ends_at')
            ->whereDate('trial_ends_at', $targetDate->toDateString())
            ->chunk(100, function ($subscriptions) use ($days, &$count) {
                foreach ($subscriptions as $sub) {
                    $sub->tenant->notifyAdmins(new SubscriptionExpiringSoon($sub, $days));

                    SubscriptionAuditService::log($sub, 'trial_ending_reminder_sent', [
                        'days_remaining' => $days,
                        'reason' => "Trial ending reminder sent ({$days} day(s) before trial ends).",
                    ]);

                    $count++;
                }
            });

        return $count;
    }
}
