<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Subscription;

class SubscriptionPlanChangeService
{
    public const TYPE_PLAN_UPGRADE = 'plan_upgrade';

    public function __construct(
        private readonly SubscriptionAuditService $audit,
    ) {}

    public function calculateProration(Subscription $subscription, Plan $targetPlan, ?string $billingInterval = null): array
    {
        $interval = $billingInterval ?? $subscription->billing_interval ?? 'monthly';

        $currentPrice = (float) ($subscription->plan?->getPriceForInterval($interval) ?? 0);
        $targetPrice = (float) ($targetPlan->getPriceForInterval($interval) ?? 0);
        $priceDiff = $targetPrice - $currentPrice;

        $isUpgrade = $priceDiff >= 0;
        $isDowngrade = $priceDiff < 0;

        $periodDays = $this->daysInInterval($interval);
        $periodSeconds = $periodDays * 86400;

        $remainingSeconds = 0;
        if ($subscription->expires_at) {
            $remainingSeconds = max(0, $subscription->expires_at->getTimestamp() - now()->getTimestamp());
        }

        $remainingFraction = $periodSeconds > 0
            ? min(1.0, $remainingSeconds / $periodSeconds)
            : 0.0;

        $daysRemaining = (int) ceil($remainingSeconds / 86400);

        // A trial has no paid value yet: never grant a paid-plan unused-value credit.
        // Converting to a paid plan charges the selected plan's price via the paid workflow.
        if ($subscription->isTrialing()) {
            $fullTarget = round($targetPrice, 2);

            return [
                'current_price' => $currentPrice,
                'target_price' => $targetPrice,
                'price_difference' => round($priceDiff, 2),
                'is_upgrade' => true,
                'is_downgrade' => false,
                'is_trial_conversion' => true,
                'interval' => $interval,
                'period_days' => $periodDays,
                'days_remaining' => $daysRemaining,
                'remaining_days' => round($remainingSeconds / 86400, 2),
                'remaining_fraction' => round($remainingFraction, 4),
                'credit_amount' => 0.0,
                'target_prorated' => $fullTarget,
                'prorated_amount' => $fullTarget,
                'amount_due' => $fullTarget,
                'total_due' => $fullTarget,
                'current_expires_at' => $subscription->expires_at?->toDateString(),
                'trial_ends_at' => $subscription->trial_ends_at?->toDateString(),
                'effective_date' => now()->toDateString(),
            ];
        }

        $creditAmount = round($currentPrice * $remainingFraction, 2);
        $targetProrated = round($targetPrice * $remainingFraction, 2);
        $amountDue = round(max(0.0, $priceDiff * $remainingFraction), 2);

        if ($isDowngrade) {
            $amountDue = 0.0;
        }

        return [
            'current_price' => $currentPrice,
            'target_price' => $targetPrice,
            'price_difference' => round($priceDiff, 2),
            'is_upgrade' => $isUpgrade,
            'is_downgrade' => $isDowngrade,
            'is_trial_conversion' => false,
            'interval' => $interval,
            'period_days' => $periodDays,
            'days_remaining' => $daysRemaining,
            'remaining_days' => round($remainingSeconds / 86400, 2),
            'remaining_fraction' => round($remainingFraction, 4),
            'credit_amount' => $creditAmount,
            'target_prorated' => $targetProrated,
            'prorated_amount' => $targetProrated,
            'amount_due' => $amountDue,
            'total_due' => $amountDue,
            'current_expires_at' => $subscription->expires_at?->toDateString(),
            'trial_ends_at' => $subscription->trial_ends_at?->toDateString(),
            'effective_date' => now()->toDateString(),
        ];
    }

    public function executeUpgrade(Subscription $subscription, Plan $targetPlan, ?string $billingInterval = null): Subscription
    {
        $oldPlanId = $subscription->plan_id;
        $interval = $billingInterval ?? $subscription->billing_interval ?? 'monthly';

        $subscription->changePlan($targetPlan, $interval);

        $this->audit::log($subscription, 'plan_upgraded', [
            'old_plan_id' => $oldPlanId,
            'new_plan_id' => $targetPlan->id,
            'reason' => "Plan upgraded to {$targetPlan->name} ({$interval})",
        ]);

        return $subscription->fresh();
    }

    public function executeDowngrade(Subscription $subscription, Plan $targetPlan, ?string $billingInterval = null): Subscription
    {
        $oldPlanId = $subscription->plan_id;
        $interval = $billingInterval ?? $subscription->billing_interval ?? 'monthly';

        $hasFutureExpiry = $subscription->expires_at && $subscription->expires_at->isFuture();

        if ($hasFutureExpiry) {
            $subscription->scheduleDowngrade($targetPlan);

            $this->audit::log($subscription, 'downgrade_scheduled', [
                'old_plan_id' => $oldPlanId,
                'new_plan_id' => $targetPlan->id,
                'effective_at' => $subscription->expires_at->toDateTimeString(),
                'reason' => "Downgrade to {$targetPlan->name} scheduled for {$subscription->expires_at->toDateString()}",
            ]);
        } else {
            $subscription->changePlan($targetPlan, $interval);

            $this->audit::log($subscription, 'plan_downgraded', [
                'old_plan_id' => $oldPlanId,
                'new_plan_id' => $targetPlan->id,
                'reason' => "Plan changed to {$targetPlan->name} ({$interval})",
            ]);
        }

        return $subscription->fresh();
    }

    public function cancelScheduledChange(Subscription $subscription): Subscription
    {
        $pendingPlan = $subscription->pendingPlan;

        $subscription->cancelScheduledDowngrade();

        if ($pendingPlan) {
            $this->audit::log($subscription, 'downgrade_cancelled', [
                'old_plan_id' => $pendingPlan->id,
                'reason' => "Scheduled downgrade to {$pendingPlan->name} cancelled",
            ]);
        }

        return $subscription->fresh();
    }

    public function applyScheduledChanges(): int
    {
        $count = 0;

        Subscription::whereNotNull('pending_plan_id')
            ->where('pending_plan_effective_at', '<=', now())
            ->chunk(50, function ($subscriptions) use (&$count) {
                foreach ($subscriptions as $subscription) {
                    $targetPlan = $subscription->pendingPlan;
                    if (!$targetPlan) {
                        $subscription->update([
                            'pending_plan_id' => null,
                            'pending_plan_effective_at' => null,
                        ]);
                        continue;
                    }

                    $oldPlanId = $subscription->plan_id;
                    $subscription->changePlan($targetPlan);

                    $this->audit::log($subscription, 'downgrade_applied', [
                        'old_plan_id' => $oldPlanId,
                        'new_plan_id' => $targetPlan->id,
                        'reason' => "Scheduled downgrade to {$targetPlan->name} applied",
                    ]);

                    $count++;
                }
            });

        return $count;
    }

    private function daysInInterval(string $interval): int
    {
        return match ($interval) {
            'yearly' => 365,
            default => 30,
        };
    }
}
