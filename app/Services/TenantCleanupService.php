<?php

namespace App\Services;

use App\Models\PaymentIntent;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\SubscriptionCancellationRequest;
use App\Models\Tenant;
use App\Models\TenantCleanup;
use App\Notifications\TenantCleanupNotice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class TenantCleanupService
{
    private static ?array $cleanupColumns = null;

    public function __construct(
        private readonly NotificationPreferenceService $preferenceService,
    ) {}

    public function graceDays(): int
    {
        return max(1, (int) config('billing.tenant_cleanup.grace_days', 15));
    }

    public function retentionDays(): int
    {
        try {
            $days = PlatformSetting::current()->tenant_data_retention_days ?? 15;
        } catch (\Throwable $e) {
            $days = 15;
        }

        return max(1, min(365, (int) $days));
    }

    public function autoDeleteEnabled(): bool
    {
        try {
            return (bool) (PlatformSetting::current()->tenant_auto_delete_enabled ?? false);
        } catch (\Throwable $e) {
            return false;
        }
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
     * @return array{candidates:int,reminded:int,retention_reminded:int,restricted:int,pending_deletion:int,flagged:int,cancelled:int,skipped:int,errors:int}
     */
    public function process(bool $dryRun = false, ?int $limit = null): array
    {
        $result = [
            'candidates' => 0,
            'reminded' => 0,
            'retention_reminded' => 0,
            'restricted' => 0,
            'pending_deletion' => 0,
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
            ->orderBy('id')
            ->chunkById(100, function ($subscriptions) use (&$result, $dryRun, $limit) {
                foreach ($subscriptions as $subscription) {
                    if ($limit !== null && $result['candidates'] >= $limit) {
                        return false;
                    }

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
                $cleanup = TenantCleanup::create($this->cleanupPayload([
                    'tenant_id' => $tenant->id,
                    'subscription_id' => $subscription->id,
                    'status' => TenantCleanup::STATUS_GRACE,
                    'expires_at' => $subscription->expires_at,
                    'grace_ends_at' => $subscription->expires_at->copy()->addDays($this->graceDays()),
                    'deletion_status' => TenantCleanup::DELETION_NONE,
                    'reminders_sent' => [],
                ]));
            }

            if ($cleanup->isTerminal()) {
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
                $this->saveCleanup($cleanup, ['status', 'reason', 'subscription_id', 'expires_at', 'grace_ends_at']);
                $result['cancelled']++;
                return;
            }

            if (now()->lt($cleanup->grace_ends_at)) {
                $cleanup->status = TenantCleanup::STATUS_GRACE;
                if ($this->sendDueReminder($cleanup, $subscription)) {
                    $result['reminded']++;
                }
                $this->saveCleanup($cleanup);
                return;
            }

            // Past the deadline — re-check eligibility before any restriction.
            if ($this->hasPreservingHold($tenant, $subscription)) {
                $cleanup->status = TenantCleanup::STATUS_FLAGGED;
                $cleanup->reason = 'Payment/cancellation review pending — cleanup paused.';
                $cleanup->grace_ends_at = now()->copy()->addDays($this->pendingReviewExtensionDays());
                $this->saveCleanup($cleanup);
                $this->notify($tenant, $subscription, 'flagged');
                $result['flagged']++;
                return;
            }

            if ($cleanup->restricted_at !== null) {
                $this->processRestricted($cleanup, $subscription, $result);
                return;
            }

            $this->restrictTenant($cleanup, $subscription);
            $result['restricted']++;
        });
    }

    private function processRestricted(TenantCleanup $cleanup, Subscription $subscription, array &$result): void
    {
        $tenant = $subscription->tenant;

        if ($cleanup->status !== TenantCleanup::STATUS_RESTRICTED
            && $cleanup->status !== TenantCleanup::STATUS_PENDING_DELETION
        ) {
            $cleanup->status = TenantCleanup::STATUS_RESTRICTED;
        }

        if ($cleanup->retention_ends_at === null && $this->hasRetentionColumn('retention_ends_at')) {
            $base = $cleanup->restricted_at ?? now();
            $cleanup->retention_ends_at = $base->copy()->addDays($this->retentionDays());
        }

        if ($cleanup->retention_ends_at !== null && now()->lt($cleanup->retention_ends_at)) {
            $cleanup->status = TenantCleanup::STATUS_RESTRICTED;
            if ($this->sendRetentionReminder($cleanup, $subscription)) {
                $result['retention_reminded']++;
            } else {
                $result['skipped']++;
            }
            $this->saveCleanup($cleanup);
            return;
        }

        if (!$this->autoDeleteEnabled()) {
            if ($this->sendRetentionExpiredNotice($cleanup, $subscription)) {
                $result['retention_reminded']++;
            } else {
                $result['skipped']++;
            }
            $this->saveCleanup($cleanup);
            return;
        }

        if ($cleanup->status === TenantCleanup::STATUS_PENDING_DELETION
            && $cleanup->deletion_status === TenantCleanup::DELETION_PENDING
        ) {
            $result['skipped']++;
            return;
        }

        $cleanup->status = TenantCleanup::STATUS_PENDING_DELETION;
        if ($this->hasRetentionColumn('deletion_status')) {
            $cleanup->deletion_status = TenantCleanup::DELETION_PENDING;
        }
        $cleanup->reason = 'Retention period ended. Tenant queued for permanent deletion — export data before the purge runs.';
        $cleanup->attempts++;
        $this->saveCleanup($cleanup);
        $this->notify($tenant, $subscription, 'pending_deletion', 0, $cleanup->retention_ends_at);
        $result['pending_deletion']++;
    }

    private function evaluateDryRun(Subscription $subscription, array &$result): void
    {
        $tenant = $subscription->tenant;
        $cleanup = TenantCleanup::where('tenant_id', $tenant->id)->first();

        if ($cleanup && $cleanup->isTerminal()) {
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

        if ($cleanup && $cleanup->restricted_at !== null) {
            $retentionEndsAt = $cleanup->retention_ends_at
                ?? $cleanup->restricted_at->copy()->addDays($this->retentionDays());

            if (now()->lt($retentionEndsAt)) {
                if ($this->isRetentionReminderDue($cleanup, $retentionEndsAt)) {
                    $result['retention_reminded']++;
                } else {
                    $result['skipped']++;
                }
                return;
            }

            if (!$this->autoDeleteEnabled()) {
                $result['skipped']++;
                return;
            }

            if ($cleanup->status === TenantCleanup::STATUS_PENDING_DELETION
                && $cleanup->deletion_status === TenantCleanup::DELETION_PENDING
            ) {
                $result['skipped']++;
                return;
            }

            $result['pending_deletion']++;
            return;
        }

        $result['restricted']++;
    }

    private function restrictTenant(TenantCleanup $cleanup, Subscription $subscription): void
    {
        $tenant = $subscription->tenant;

        $wasRestricted = $cleanup->restricted_at !== null;

        if (!$wasRestricted) {
            $tenant->update(['status' => 'expired']);
            $tenant->lock();

            $cleanup->restricted_at = now();
        }

        $cleanup->status = TenantCleanup::STATUS_RESTRICTED;
        $cleanup->reason = 'Grace period ended without an approved renewal.';
        if ($this->hasRetentionColumn('retention_ends_at') && $cleanup->retention_ends_at === null) {
            $cleanup->retention_ends_at = now()->copy()->addDays($this->retentionDays());
        }
        if ($this->hasRetentionColumn('deletion_status') && !$cleanup->deletion_status) {
            $cleanup->deletion_status = TenantCleanup::DELETION_NONE;
        }
        $cleanup->attempts++;
        $this->saveCleanup($cleanup);

        if (!$wasRestricted) {
            SubscriptionAuditService::log($subscription, 'tenant_cleanup_restricted', [
                'old_status' => $subscription->status,
                'reason' => 'Grace period ended without an approved renewal.',
                'metadata' => [
                    'grace_ends_at' => $cleanup->grace_ends_at?->toDateTimeString(),
                    'retention_ends_at' => $cleanup->retention_ends_at?->toDateTimeString(),
                ],
            ]);

            $this->notify($tenant, $subscription, 'restricted', 0, $cleanup->retention_ends_at);
        }
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

        $this->notify(
            $subscription->tenant,
            $subscription,
            'grace',
            $daysRemaining,
            $cleanup->grace_ends_at?->copy()->addDays($this->retentionDays()),
        );

        return true;
    }

    private function sendRetentionReminder(TenantCleanup $cleanup, Subscription $subscription): bool
    {
        if (!$this->isRetentionReminderDue($cleanup, $cleanup->retention_ends_at)) {
            return false;
        }

        $daysRemaining = $this->daysRemaining($cleanup->retention_ends_at);
        $sentinel = 'retention:' . $this->nextRetentionThreshold($cleanup, $cleanup->retention_ends_at);

        $sentinels = $cleanup->reminders_sent ?? [];
        $sentinels[] = $sentinel;
        $cleanup->reminders_sent = $sentinels;
        $cleanup->last_reminder_at = now();

        $this->notify($subscription->tenant, $subscription, 'retention', $daysRemaining, $cleanup->retention_ends_at);

        return true;
    }

    private function sendRetentionExpiredNotice(TenantCleanup $cleanup, Subscription $subscription): bool
    {
        $sentinels = $cleanup->reminders_sent ?? [];

        if (in_array('retention_expired', $sentinels, true)) {
            return false;
        }

        $sentinels[] = 'retention_expired';
        $cleanup->reminders_sent = $sentinels;
        $cleanup->last_reminder_at = now();

        $this->notify($subscription->tenant, $subscription, 'retention_expired', 0, $cleanup->retention_ends_at);

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

    private function isRetentionReminderDue(?TenantCleanup $cleanup, $retentionEndsAt): bool
    {
        return $this->nextRetentionThreshold($cleanup, $retentionEndsAt) !== null;
    }

    private function nextRetentionThreshold(?TenantCleanup $cleanup, $retentionEndsAt)
    {
        if (!$retentionEndsAt) {
            return null;
        }

        $daysRemaining = $this->daysRemaining($retentionEndsAt);
        $sent = $cleanup?->reminders_sent ?? [];

        foreach ($this->reminderDays() as $threshold) {
            $sentinel = 'retention:' . $threshold;
            if ($daysRemaining <= $threshold && !in_array($sentinel, $sent, true) && !in_array($threshold, $sent, true)) {
                return $threshold;
            }
        }

        return null;
    }

    private function daysRemaining($endsAt): int
    {
        return (int) ceil(max(0, $endsAt->getTimestamp() - now()->getTimestamp()) / 86400);
    }

    private function notify(Tenant $tenant, ?Subscription $subscription, string $phase, ?int $daysRemaining = null, $retentionEndsAt = null): void
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
                retentionEndsAt: $retentionEndsAt?->copy(),
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

        $cleanup->update($this->cleanupPayload([
            'status' => TenantCleanup::STATUS_CANCELLED,
            'reason' => $reason,
        ]));

        return true;
    }

    private function saveCleanup(TenantCleanup $cleanup, ?array $only = null): void
    {
        if ($only !== null) {
            $columns = $this->cleanupColumns();
            $only = array_values(array_intersect($only, $columns));
            if ($only === []) {
                return;
            }
            $dirty = array_intersect(array_keys($cleanup->getDirty()), $only);
            if ($dirty === []) {
                return;
            }
            $cleanup->save();
            return;
        }

        $columns = $this->cleanupColumns();
        foreach (array_keys($cleanup->getDirty()) as $attribute) {
            if (!in_array($attribute, $columns, true)) {
                unset($cleanup->{$attribute});
            }
        }

        if ($cleanup->isDirty()) {
            $cleanup->save();
        }
    }

    private function cleanupPayload(array $payload): array
    {
        $columns = $this->cleanupColumns();

        return array_intersect_key($payload, array_flip($columns));
    }

    private function hasRetentionColumn(string $column): bool
    {
        return in_array($column, $this->cleanupColumns(), true);
    }

    private function cleanupColumns(): array
    {
        if (self::$cleanupColumns === null) {
            try {
                self::$cleanupColumns = Schema::getColumnListing('tenant_cleanups');
            } catch (\Throwable $e) {
                self::$cleanupColumns = [];
            }
        }

        if (self::$cleanupColumns === []) {
            return [
                'tenant_id', 'subscription_id', 'status', 'expires_at', 'grace_ends_at',
                'retention_ends_at', 'restricted_at', 'deleted_at', 'deletion_status',
                'archived_at', 'last_reminder_at', 'reminders_sent', 'attempts',
                'reason', 'last_error', 'metadata', 'created_at', 'updated_at',
            ];
        }

        return self::$cleanupColumns;
    }
}
