<?php

namespace App\Console\Commands;

use App\Jobs\SendTelegramMessageJob;
use App\Models\Subscription;
use App\Models\SubscriptionAuditLog;
use App\Notifications\SubscriptionExpiringSoon;
use App\Services\BillingEmailService;
use App\Services\NotificationPreferenceService;
use App\Services\SubscriptionAuditService;
use App\Services\TelegramSystemAlertMessageBuilder;
use Illuminate\Console\Command;

class SendSubscriptionReminders extends Command
{
    protected $signature = 'subscriptions:send-reminders
                            {--dry-run : List reminders that would be sent without dispatching}';

    protected $description = 'Send renewal reminders and trial-ending notifications at configured intervals';

    private const REMINDER_DAYS = [14, 7, 3, 1];

    private const RENEWAL_EVENT = 'renewal_reminder_sent';

    private const TRIAL_EVENT = 'trial_ending_reminder_sent';

    public function __construct(
        private readonly BillingEmailService $billingEmails,
        private readonly TelegramSystemAlertMessageBuilder $telegramBuilder,
        private readonly NotificationPreferenceService $preferenceService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $sent = ['renewal' => 0, 'trial' => 0];

        foreach (self::REMINDER_DAYS as $days) {
            $targetDate = now()->addDays($days);

            $sent['renewal'] += $this->sendRenewalReminders($targetDate, $days);
            $sent['trial'] += $this->sendTrialReminders($targetDate, $days);
        }

        $total = $sent['renewal'] + $sent['trial'];
        $dryRun = $this->option('dry-run') ? ' (dry run)' : '';
        $this->info("Sent {$total} reminder(s){$dryRun} ({$sent['renewal']} renewal, {$sent['trial']} trial).");

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        $emails = $this->sendRenewalReminderEmails();
        $this->info("Sent {$emails} renewal reminder email(s).");

        return self::SUCCESS;
    }

    private function sendRenewalReminderEmails(): int
    {
        $count = 0;
        $targetDate = now()->addDays($this->billingEmails->renewalReminderDays())->toDateString();

        Subscription::where('status', 'active')
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', $targetDate)
            ->chunk(100, function ($subscriptions) use (&$count) {
                foreach ($subscriptions as $sub) {
                    if ($this->billingEmails->sendRenewalReminder($sub)) {
                        $count++;
                    }
                }
            });

        return $count;
    }

    private function sendRenewalReminders(\Carbon\Carbon $targetDate, int $days): int
    {
        $count = 0;

        Subscription::where('status', 'active')
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', $targetDate->toDateString())
            ->chunk(100, function ($subscriptions) use ($days, &$count) {
                foreach ($subscriptions as $sub) {
                    $anchor = $sub->expires_at->toDateTimeString();

                    if ($this->option('dry-run')) {
                        $this->line("  [{$days}d] Would notify tenant #{$sub->tenant_id} — expires {$sub->expires_at->format('Y-m-d')}");
                        $count++;
                        continue;
                    }

                    if ($this->alreadyReminded($sub->id, self::RENEWAL_EVENT, $anchor)
                        || !$this->preferenceService->tenantAllows($sub->tenant_id)) {
                        continue;
                    }

                    $sub->tenant->notifyAdmins(new SubscriptionExpiringSoon($sub, $days));

                    SubscriptionAuditService::log($sub, self::RENEWAL_EVENT, [
                        'reason' => "Renewal reminder sent ({$days} day(s) before expiry).",
                        'metadata' => ['expires_at' => $anchor, 'days_remaining' => $days],
                    ]);

                    SendTelegramMessageJob::dispatchForTenant(
                        $sub->tenant_id,
                        $this->telegramBuilder->billingSubscriptionExpiring($sub, $days),
                    );

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
                    $anchor = $sub->trial_ends_at->toDateTimeString();

                    if ($this->option('dry-run')) {
                        $this->line("  [{$days}d] Would notify tenant #{$sub->tenant_id} — trial ends {$sub->trial_ends_at->format('Y-m-d')}");
                        $count++;
                        continue;
                    }

                    if ($this->alreadyReminded($sub->id, self::TRIAL_EVENT, $anchor)
                        || !$this->preferenceService->tenantAllows($sub->tenant_id)) {
                        continue;
                    }

                    $sub->tenant->notifyAdmins(new SubscriptionExpiringSoon($sub, $days));

                    SubscriptionAuditService::log($sub, self::TRIAL_EVENT, [
                        'reason' => "Trial ending reminder sent ({$days} day(s) before trial ends).",
                        'metadata' => ['trial_ends_at' => $anchor, 'days_remaining' => $days],
                    ]);

                    SendTelegramMessageJob::dispatchForTenant(
                        $sub->tenant_id,
                        $this->telegramBuilder->billingSubscriptionExpiring($sub, $days),
                    );

                    $count++;
                }
            });

        return $count;
    }

    private function alreadyReminded(int $subscriptionId, string $event, string $anchor): bool
    {
        return SubscriptionAuditLog::where('subscription_id', $subscriptionId)
            ->where('event', $event)
            ->get()
            ->contains(fn ($log) => in_array($anchor, array_values($log->metadata ?? []), true));
    }
}
