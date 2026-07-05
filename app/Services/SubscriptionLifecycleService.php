<?php

namespace App\Services;

use App\Models\PaymentIntent;
use App\Models\Subscription;
use App\Services\Payment\Platform\PaymentTimelineService;

class SubscriptionLifecycleService
{
    public function __construct(
        private readonly PaymentTimelineService $timeline,
        private readonly SubscriptionAuditService $audit,
    ) {}

    public function handleCompletedPayment(PaymentIntent $intent): void
    {
        $tenant = $intent->tenant;

        if (!$tenant) {
            return;
        }

        $subscription = $tenant->subscription;

        if (!$subscription) {
            return;
        }

        $plan = $intent->plan;

        if (!$plan) {
            return;
        }

        $previousStatus = $subscription->status;
        $previousPlanId = $subscription->plan_id;
        $isUpgrade = $plan->id !== $previousPlanId;

        if ($subscription->isTrialing()) {
            $this->activateFromTrial($subscription, $plan, $intent);
        } elseif ($subscription->isExpired() || $subscription->isPastDue() || $subscription->isCanceled()) {
            $this->renewSubscription($subscription, $plan, $intent);
        } elseif ($subscription->isActive() && $isUpgrade) {
            $this->upgradePlan($subscription, $plan, $intent);
        } elseif ($subscription->isActive() && !$isUpgrade) {
            $this->renewSubscription($subscription, $plan, $intent);
        }

        FeatureGate::clearCache($plan);

        $this->timeline->record(
            intent: $intent,
            type: 'subscription_activated',
            description: sprintf(
                'Subscription %s — Plan: %s (%s)',
                $isUpgrade ? 'upgraded' : 'activated',
                $plan->name,
                $intent->billing_cycle ?? 'monthly'
            ),
            metadata: [
                'previous_status' => $previousStatus,
                'previous_plan_id' => $previousPlanId,
                'new_plan_id' => $plan->id,
            ]
        );

        $this->audit::log($subscription, 'payment_completed', [
            'old_status' => $previousStatus,
            'new_plan_id' => $plan->id,
            'old_plan_id' => $previousPlanId,
            'payment_intent_id' => $intent->id,
            'reason' => sprintf(
                'Payment %s completed. Subscription lifecycle applied.',
                $intent->reference_number
            ),
        ]);
    }

    private function activateFromTrial(Subscription $subscription, $plan, PaymentIntent $intent): void
    {
        $interval = $intent->billing_cycle ?? $subscription->billing_interval ?? 'monthly';

        $expiresAt = $plan->calculateExpiryDate(now(), $interval);

        $subscription->update([
            'plan_id' => $plan->id,
            'billing_interval' => $interval,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => $expiresAt,
            'trial_ends_at' => now(),
            'trial_renewals_count' => 0,
        ]);

        $subscription->tenant->unlock();

        $this->timeline->record(
            intent: $intent,
            type: 'trial_ended',
            description: 'Trial period ended. Paid subscription activated.',
        );

        $this->audit::log($subscription, 'trial_converted', [
            'old_status' => 'trialing',
            'reason' => 'Trial converted to paid subscription via payment approval.',
        ]);
    }

    private function renewSubscription(Subscription $subscription, $plan, PaymentIntent $intent): void
    {
        $interval = $intent->billing_cycle ?? $subscription->billing_interval ?? 'monthly';

        $planForExpiry = $plan ?: $subscription->plan;

        if (!$planForExpiry) {
            return;
        }

        $baseDate = $subscription->expires_at?->isFuture()
            ? $subscription->expires_at
            : now();

        $newExpiry = $planForExpiry->calculateExpiryDate($baseDate, $interval);

        if (!$newExpiry) {
            return;
        }

        $note = sprintf(
            "[%s] Renewed via payment %s — %s → %s",
            now()->toDateTimeString(),
            $intent->reference_number,
            $baseDate->format('Y-m-d'),
            $newExpiry->format('Y-m-d')
        );

        $updateData = [
            'status' => 'active',
            'expires_at' => $newExpiry,
            'notes' => $subscription->notes ? $subscription->notes . "\n" . $note : $note,
        ];

        if ($subscription->plan_id !== $plan->id) {
            $updateData['plan_id'] = $plan->id;
            $updateData['billing_interval'] = $interval;
        }

        $subscription->update($updateData);

        $subscription->tenant->unlock();

        $this->timeline->record(
            intent: $intent,
            type: 'subscription_renewed',
            description: sprintf(
                'Subscription renewed. New expiry: %s',
                $newExpiry->format('Y-m-d')
            ),
        );
    }

    private function upgradePlan(Subscription $subscription, $plan, PaymentIntent $intent): void
    {
        $interval = $intent->billing_cycle ?? $subscription->billing_interval ?? 'monthly';

        $expiresAt = $plan->calculateExpiryDate(now(), $interval);

        $subscription->update([
            'plan_id' => $plan->id,
            'billing_interval' => $interval,
            'status' => 'active',
            'expires_at' => $expiresAt,
        ]);

        $this->timeline->record(
            intent: $intent,
            type: 'plan_changed',
            description: sprintf(
                'Plan upgraded to %s (%s)',
                $plan->name,
                $interval
            ),
        );
    }
}
