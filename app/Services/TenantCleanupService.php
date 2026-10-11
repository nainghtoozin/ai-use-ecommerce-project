<?php

namespace App\Services;

use App\Models\PaymentIntent;
use App\Models\Subscription;
use App\Models\SubscriptionCancellationRequest;
use App\Models\Tenant;
use App\Models\TenantCleanup;
use App\Notifications\TenantCleanupNotice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TenantCleanupService
{
    public function __construct(
        private readonly NotificationPreferenceService $preferenceService,
    ) {}

    public function graceDays(): int
    {
        return max(1, (int) config('billing.tenant_cleanup.grace_days', 15));
    }

    /** @return int[] */
    public function reminderDays(): array
    {
        $days = (array) config('billing.tenant_cleanup.reminder_days', [7, 3, 1]);
        $days = array_map('intval', $days);
        rsort($days);

        return $days;
    }

    public function pendingReviewExtensionDays(): int
    {
        return max(1, (int) config('billing.tenant_cleanup.pending_review_extension_days', 7));
    }

    /**
     * Run the staged, idempotent cleanup for expired tenants.
     *
     * @return array{candidates:int,reminded:int,restricted:int,flagged:int,cancelled:int,skipped:int,errors:int}
     */
    public function process(bool $dryRun = false): array
    {
        $result = [
            'candidates' => 0,
            'reminded' => 0,
            'restricted' => 0,
            'flagged' => 0,
            'cancelled' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        Subscription::withoutTenantScope()
            ->with(['tenant', 'plan'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->whereIn('status', ['past_due', 'expired', 'suspended', 'canceled'])
            ->chunkById(100, function ($subscriptions) use (&$result, $dryRun) {
                foreach ($subscriptions as $subscription) {
                    if (!$subscription->tenant || !$subscription->plan || $subscription->plan->isFree()) {
                        continue;
                    }

                    $result['candidates']++;

                    try {
                        $this->processCandidate($subscription, $dryRun, $result);
                    } catch (\Throwable $e) {
                        $result['errors']++;
                        Log::error('Tenant cleanup failed for subscription.', [
                            'subscription_id' => $subscription->id,
                            'tenant_id' => $subscription->tenant_id,
                            'error' => $e->getMessage(),
                        ]);

                        if (!$dryRun) {
                            TenantCleanup::where('tenant_id', $subscription->tenant_id)->update([
                                'last_error' => $e->getMessage(),
                                'attempts' => DB::raw('attempts + 1'),
                            ]);
                        }
                    }
                }
            });

        return $result;
    }

    private function processCandidate(Subscription $subscription, bool $dryRun, array &$result): void
    {
        if ($dryRun) {
            $this->evaluateDryRun($subscription, $result);
            return;
        }

        DB::transaction(function () use ($subscription, &$result) {
            $tenant = $subscription->tenant;

            $cleanup = TenantCleanup::where('tenant_id', $tenant->id)->lockForUpdate()->first();

            if (!$cleanup) {
                $cleanup = TenantCleanup::create([
                    'tenant_id' => $tenant->id,
                    'subscription_id' => $subscription->id,
                    'status' => TenantCleanup::STATUS_GRACE,
                    'expires_at' => $subscription->expires_at,
                    'grace_ends_at' => $subscription->expires_at->copy()->addDays($this->graceDays()),
                    'reminders_sent' => [],
                ]);
            }

            if ($cleanup->isTerminal()) {
                $result['skipped']++;
                return;
            }

            // Already restricted — safe to skip on repeated runs (no duplicate actions).
            if ($cleanup->status === TenantCleanup::STATUS_RESTRICTED && $cleanup->restricted_at !== null) {
                $result['skipped']++;
                return;
            }

            $cleanup->subscription_id = $subscription->id;
            $cleanup->expires_at = $subscription->expires_at;
            $cleanup->grace_ends_at = $subscription->expires_at->copy()->addDays($this->graceDays());

            // A successful renewal/upgrade makes the tenant active again → cancel cleanup.
            if ($this->hasActiveSubscription($tenant)) {
                $cleanup->status = TenantCleanup::STATUS_CANCELLED;
                $cleanup->reason = 'Active subscription present — cleanup cancelled.';
                $cleanup->save();
                $result['cancelled']++;
                return;
            }

            if (now()->lt($cleanup->grace_ends_at)) {
                $cleanup->status = TenantCleanup::STATUS_GRACE;
                if ($this->sendDueReminder($cleanup, $subscription)) {
                    $result['reminded']++;
                }
                $cleanup->save();
                return;
            }

            // Past the deadline — re-check eligibility before any restriction.
            if ($this->hasPreservingHold($tenant, $subscription)) {
                $cleanup->status = TenantCleanup::STATUS_FLAGGED;
                $cleanup->reason = 'Payment/cancellation review pending — cleanup paused.';
                $cleanup->grace_ends_at = now()->copy()->addDays($this->pendingReviewExtensionDays());
                $cleanup->save();
                $this->notify($tenant, $subscription, 'flagged');
                $result['flagged']++;
                return;
            }

            $this->restrictTenant($cleanup, $subscription);
            $result['restricted']++;
        });
    }

    private function evaluateDryRun(Subscription $subscription, array &$result): void
    {
        $tenant = $subscription->tenant;
        $cleanup = TenantCleanup::where('tenant_id', $tenant->id)->first();

        if ($cleanup && $cleanup->isTerminal()) {
            $result['skipped']++;
            return;
        }

        if ($cleanup && $cleanup->status === TenantCleanup::STATUS_RESTRICTED && $cleanup->restricted_at !== null) {
            $result['skipped']++;
            return;
        }

        $graceEndsAt = $subscription->expires_at->copy()->addDays($this->graceDays());

        if ($this->hasActiveSubscription($tenant)) {
            $result['cancelled']++;
            return;
        }

        if (now()->lt($graceEndsAt)) {
            if ($this->isReminderDue($cleanup, $graceEndsAt)) {
                $result['reminded']++;
            }
            return;
        }

        if ($this->hasPreservingHold($tenant, $subscription)) {
            $result['flagged']++;
            return;
        }

        $result['restricted']++;
    }

    private function restrictTenant(TenantCleanup $cleanup, Subscription $subscription): void
    {
        $tenant = $subscription->tenant;

        if ($cleanup->restricted_at === null) {
            $tenant->update(['status' => 'expired']);
            $tenant->lock();

            $cleanup->restricted_at = now();

            SubscriptionAuditService::log($subscription, 'tenant_cleanup_restricted', [
                'old_status' => $subscription->status,
                'reason' => 'Grace period ended without an approved renewal.',
                'metadata' => [
                    'grace_ends_at' => $cleanup->grace_ends_at?->toDateTimeString(),
                ],
            ]);

            $this->notify($tenant, $subscription, 'restricted');
        }

        $cleanup->status = TenantCleanup::STATUS_RESTRICTED;
        $cleanup->reason = 'Grace period ended without an approved renewal.';
        $cleanup->attempts++;
        $cleanup->save();
    }

    private function hasActiveSubscription(Tenant $tenant): bool
    {
        return $tenant->activeSubscription()->exists();
    }

    private function hasPreservingHold(Tenant $tenant, Subscription $subscription): bool
    {
        $settings = $tenant->settings ?? [];
        if (!empty($settings['cleanup_hold'])) {
            return true;
        }

        $hasPendingPayment = PaymentIntent::forTenant($tenant->id)
            ->whereIn('status', ['waiting_payment', 'waiting_review'])
            ->exists();

        if ($hasPendingPayment) {
            return true;
        }

        return SubscriptionCancellationRequest::where('subscription_id', $subscription->id)
            ->where('status', SubscriptionCancellationRequest::STATUS_PENDING)
            ->exists();
    }

    private function sendDueReminder(TenantCleanup $cleanup, Subscription $subscription): bool
    {
        if (!$this->isReminderDue($cleanup, $cleanup->grace_ends_at)) {
            return false;
        }

        $daysRemaining = $this->daysRemaining($cleanup->grace_ends_at);
        $sent = $this->nextReminderThreshold($cleanup, $cleanup->grace_ends_at);

        $sentinels = $cleanup->reminders_sent ?? [];
        $sentinels[] = $sent;
        $cleanup->reminders_sent = $sentinels;
        $cleanup->last_reminder_at = now();

        $this->notify($subscription->tenant, $subscription, 'grace', $daysRemaining);

        return true;
    }

    private function isReminderDue(?TenantCleanup $cleanup, $graceEndsAt): bool
    {
        return $this->nextReminderThreshold($cleanup, $graceEndsAt) !== null;
    }

    private function nextReminderThreshold(?TenantCleanup $cleanup, $graceEndsAt)
    {
        if (!$graceEndsAt) {
            return null;
        }

        $daysRemaining = $this->daysRemaining($graceEndsAt);
        $sent = $cleanup?->reminders_sent ?? [];

        foreach ($this->reminderDays() as $threshold) {
            if ($daysRemaining <= $threshold && !in_array($threshold, $sent, true)) {
                return $threshold;
            }
        }

        // Send an initial notice as soon as a cleanup record exists.
        if (empty($sent)) {
            return 'initial';
        }

        return null;
    }

    private function daysRemaining($graceEndsAt): int
    {
        return (int) ceil(max(0, $graceEndsAt->getTimestamp() - now()->getTimestamp()) / 86400);
    }

    private function notify(Tenant $tenant, ?Subscription $subscription, string $phase, ?int $daysRemaining = null): void
    {
        try {
            if (!$this->preferenceService->tenantAllows($tenant->id)) {
                return;
            }

            $tenant->notifyAdmins(new TenantCleanupNotice(
                tenant: $tenant,
                subscription: $subscription,
                phase: $phase,
                graceEndsAt: $subscription?->expires_at?->copy()->addDays($this->graceDays()),
                daysRemaining: $daysRemaining,
            ));
        } catch (\Throwable $e) {
            Log::warning('Tenant cleanup notification failed.', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Cancel any pending cleanup for a tenant (e.g. after an approved renewal).
     */
    public function cancelForTenant(int $tenantId, string $reason = 'Subscription renewed.'): bool
    {
        $cleanup = TenantCleanup::where('tenant_id', $tenantId)->first();

        if (!$cleanup || $cleanup->isTerminal()) {
            return false;
        }

        $cleanup->update([
            'status' => TenantCleanup::STATUS_CANCELLED,
            'reason' => $reason,
        ]);

        return true;
    }
}
