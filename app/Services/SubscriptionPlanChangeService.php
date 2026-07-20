<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Subscription;

class SubscriptionPlanChangeService
{
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
        $daysRemaining = 0;
        $creditAmount = 0.0;
        $proratedAmount = 0.0;

        if ($subscription->expires_at && $subscription->expires_at->isFuture() && $currentPrice > 0) {
            $totalDays = $this->daysInInterval($interval);
            $daysRemaining = max(0, now()->diffInDays($subscription->expires_at, false));
            $dailyRate = $currentPrice / max($totalDays, 1);
            $creditAmount = round($dailyRate * $daysRemaining, 2);

            if ($isUpgrade && $priceDiff > 0) {
                $dailyDiffRate = $targetPrice / max($totalDays, 1) - $dailyRate;
                $proratedAmount = round($dailyDiffRate * $daysRemaining, 2);
            }
        }

        $totalDue = $isUpgrade
            ? round(max($proratedAmount, $priceDiff), 2)
            : 0.0;

        return [
            'current_price' => $currentPrice,
            'target_price' => $targetPrice,
            'price_difference' => round($priceDiff, 2),
            'is_upgrade' => $isUpgrade,
            'is_downgrade' => !$isUpgrade,
            'days_remaining' => $daysRemaining,
            'credit_amount' => $creditAmount,
            'prorated_amount' => $proratedAmount,
            'total_due' => $totalDue,
            'interval' => $interval,
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
