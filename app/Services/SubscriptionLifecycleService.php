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
        // Idempotent: a given payment can only apply its subscription change once.
        // Protects against duplicate approval/event dispatch extending twice.
        if (!empty(($intent->metadata ?? [])['subscription_applied'])) {
            return;
        }

        $tenant = $intent->tenant;

        if (!$tenant) {
            return;
        }

        if ($tenant->isDeleted()) {
            $blockedSubscription = $tenant->subscription ?? $intent->subscription;

            if ($blockedSubscription) {
                $this->audit::log($blockedSubscription, 'renewal_blocked_tenant_deleted', [
                    'reason' => 'Payment completed for a permanently deleted store; subscription left unchanged. Contact support to restore access.',
                    'metadata' => ['payment_intent_id' => $intent->id],
                ]);
            }

            $this->markSubscriptionApplied($intent);

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

        if (($intent->metadata['type'] ?? null) === SubscriptionPlanChangeService::TYPE_PLAN_UPGRADE) {
            $this->applyProratedUpgrade($subscription, $plan, $intent);
            $this->markSubscriptionApplied($intent);
            return;
        }

        $previousStatus = $subscription->status;
        $previousPlanId = $subscription->plan_id;
        $isUpgrade = $plan->id !== $previousPlanId;

        if ($subscription->isTrialing()) {
            $this->activateFromTrial($subscription, $plan, $intent);
        } elseif ($subscription->isExpired() || $subscription->isPastDue() || $subscription->isCanceled() || $subscription->isSuspended()) {
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

        $this->markSubscriptionApplied($intent);
    }

    private function markSubscriptionApplied(PaymentIntent $intent): void
    {
        $metadata = $intent->metadata ?? [];

        if (!empty($metadata['subscription_applied'])) {
            return;
        }

        $metadata['subscription_applied'] = now()->toDateTimeString();
        $intent->update(['metadata' => $metadata]);
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

        if ($subscription->tenant->status === 'suspended') {
            $subscription->tenant->update(['status' => 'active']);
        }

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

    private function applyProratedUpgrade(Subscription $subscription, $plan, PaymentIntent $intent): void
    {
        if (!$plan) {
            return;
        }

        $interval = $intent->billing_cycle ?? $subscription->billing_interval ?? 'monthly';
        $oldPlanId = $subscription->plan_id;
        $previousExpiry = $subscription->expires_at;

        $scheduledAction = $intent->metadata['scheduled_change_action'] ?? 'keep';
        $pendingPlanId = $subscription->pending_plan_id;
        $pendingEffectiveAt = $subscription->pending_plan_effective_at;

        $subscription->changePlan($plan, $interval);
        $subscription->refresh();

        if ($scheduledAction === 'keep' && $pendingPlanId && (int) $pendingPlanId !== (int) $plan->id) {
            $subscription->update([
                'pending_plan_id' => $pendingPlanId,
                'pending_plan_effective_at' => $pendingEffectiveAt,
            ]);
            $subscription->refresh();
        }

        $proration = $intent->metadata['proration'] ?? [];

        $this->timeline->record(
            intent: $intent,
            type: 'plan_upgraded',
            description: sprintf(
                'Plan upgraded to %s (%s). Charged %s %s.',
                $plan->name,
                $interval,
                $intent->amount,
                $intent->currency
            ),
            metadata: [
                'previous_plan_id' => $oldPlanId,
                'new_plan_id' => $plan->id,
                'proration' => $proration,
            ],
        );

        $this->audit::log($subscription, 'plan_upgraded', [
            'old_plan_id' => $oldPlanId,
            'new_plan_id' => $plan->id,
            'reason' => sprintf(
                'Prorated upgrade approved via payment %s. Charged %s %s.',
                $intent->reference_number,
                $intent->amount,
                $intent->currency
            ),
            'metadata' => [
                'payment_intent_id' => $intent->id,
                'requested_amount' => $proration['amount_due'] ?? null,
                'approved_amount' => (float) $intent->amount,
                'proration' => $proration,
                'previous_expires_at' => $previousExpiry?->toDateTimeString(),
                'new_expires_at' => $subscription->expires_at?->toDateTimeString(),
            ],
        ]);
    }
}
