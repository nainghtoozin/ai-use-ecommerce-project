<?php

namespace App\Services;

    use App\Models\Subscription;
    use App\Notifications\SubscriptionExpired;
    use App\Notifications\SubscriptionSuspended;
    use App\Services\SubscriptionAuditService;
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Log;

class SubscriptionExpiryService
{
    public const GRACE_DAYS = 7;
    public const SUSPEND_DAYS_AFTER_EXPIRY = 1;

    public function process(): array
    {
        $result = [
            'active_to_past_due' => 0,
            'past_due_to_expired' => 0,
            'expired_to_suspended' => 0,
            'trial_ended' => 0,
            'total' => 0,
        ];

        // Step 1: Active subscriptions past expires_at → past_due (grace period starts)
        $result['active_to_past_due'] = $this->transitionActiveToPastDue();
        $result['total'] += $result['active_to_past_due'];

        // Step 2: Past-due subscriptions past grace period → expired
        $result['past_due_to_expired'] = $this->transitionPastDueToExpired();
        $result['total'] += $result['past_due_to_expired'];

        // Step 3: Expired subscriptions beyond suspension threshold → suspended
        $result['expired_to_suspended'] = $this->transitionExpiredToSuspended();
        $result['total'] += $result['expired_to_suspended'];

        // Step 4: Trialing subscriptions past trial_ends_at → expired
        $result['trial_ended'] = $this->transitionTrialToExpired();
        $result['total'] += $result['trial_ended'];

        if ($result['total'] > 0) {
            Log::info('Subscription lifecycle processed', $result);
        }

        return $result;
    }

    private function transitionActiveToPastDue(): int
    {
        return $this->processBatch(
            Subscription::where('status', 'active')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', now()),
            function ($sub) {
                $oldStatus = $sub->status;
                $sub->update([
                    'status' => 'past_due',
                    'notes' => $sub->notes
                        ? $sub->notes . "\n[" . now() . "] Expired — entered 7-day grace period (past_due)."
                        : "[" . now() . "] Expired — entered 7-day grace period (past_due).",
                ]);

                $sub->tenant->notifyAdmins(new SubscriptionExpired($sub));
                return ['old_status' => $oldStatus, 'event' => 'past_due'];
            }
        );
    }

    private function transitionPastDueToExpired(): int
    {
        $graceCutoff = now()->subDays(self::GRACE_DAYS);

        return $this->processBatch(
            Subscription::where('status', 'past_due')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', $graceCutoff),
            function ($sub) {
                $oldStatus = $sub->status;
                $sub->update([
                    'status' => 'expired',
                    'notes' => $sub->notes
                        ? $sub->notes . "\n[" . now() . "] Grace period ended — status set to expired."
                        : "[" . now() . "] Grace period ended — status set to expired.",
                ]);

                $sub->tenant->lock();

                return ['old_status' => $oldStatus, 'event' => 'expired'];
            }
        );
    }

    private function transitionExpiredToSuspended(): int
    {
        $suspendCutoff = now()->subDays(self::SUSPEND_DAYS_AFTER_EXPIRY);

        return $this->processBatch(
            Subscription::where('status', 'expired')
                ->where('updated_at', '<', $suspendCutoff),
            function ($sub) {
                $oldStatus = $sub->status;
                $sub->update([
                    'status' => 'suspended',
                    'suspended_at' => now(),
                    'notes' => $sub->notes
                        ? $sub->notes . "\n[" . now() . "] Auto-suspended — tenant deactivated."
                        : "[" . now() . "] Auto-suspended — tenant deactivated.",
                ]);

                $sub->tenant->update(['status' => 'suspended']);
                $sub->tenant->lock();
                $sub->tenant->notifyAdmins(new SubscriptionSuspended($sub));

                return ['old_status' => $oldStatus, 'event' => 'suspended'];
            }
        );
    }

    private function transitionTrialToExpired(): int
    {
        return $this->processBatch(
            Subscription::where('status', 'trialing')
                ->whereNotNull('trial_ends_at')
                ->where('trial_ends_at', '<', now()),
            function ($sub) {
                $oldStatus = $sub->status;
                $sub->update([
                    'status' => 'expired',
                    'notes' => $sub->notes
                        ? $sub->notes . "\n[" . now() . "] Trial ended — auto-expired."
                        : "[" . now() . "] Trial ended — auto-expired.",
                ]);

                $sub->tenant->lock();
                $sub->tenant->notifyAdmins(new SubscriptionExpired($sub));

                return ['old_status' => $oldStatus, 'event' => 'trial_ended'];
            }
        );
    }

    private function processBatch($query, callable $transition): int
    {
        $count = 0;

        $query->chunk(100, function ($subscriptions) use ($transition, &$count) {
            foreach ($subscriptions as $sub) {
                DB::transaction(function () use ($sub, $transition, &$count) {
                    $sub->refresh();

                    $result = $transition($sub);

                    SubscriptionAuditService::log($sub, $result['event'], [
                        'old_status' => $result['old_status'],
                    ]);

                    $count++;
                });
            }
        });

        return $count;
    }
}
