<?php

namespace App\Services;

use App\Enums\Payment\TransactionStatus;
use App\Models\PaymentIntent;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionAuditLog;
use App\Models\SubscriptionCancellationRequest;
use App\Models\Tenant;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class TenantRetentionEligibilityService
{
    public const GRACE_DAYS = 7;
    public const RETENTION_DAYS = 15;
    public const REVIEW_AFTER_DAYS = 22;

    public const DECISION_ELIGIBLE = 'eligible';
    public const DECISION_NOT_ELIGIBLE = 'not_eligible';
    public const DECISION_MANUAL_REVIEW = 'manual_review_required';

    public const SOURCE_PAID = 'paid_subscription';
    public const SOURCE_TRIAL = 'trial_subscription';

    public const HOLD_ACTIVE = 'active';
    public const HOLD_VERIFIED_NONE = 'verified_none';
    public const HOLD_UNVERIFIED = 'unverified';
    public const HOLD_NOT_EVALUATED = 'not_evaluated';

    public const REASON_PROTECTED_TENANT = 'protected_tenant';
    public const REASON_DEFAULT_TENANT = self::REASON_PROTECTED_TENANT;
    public const REASON_TENANT_NOT_FOUND = 'tenant_not_found';
    public const REASON_TENANT_DELETED = 'tenant_already_deleted';
    public const REASON_UNKNOWN_TENANT_STATUS = 'unknown_tenant_status';
    public const REASON_TENANT_STATUS_CONFLICT = 'tenant_status_conflict';
    public const REASON_TENANT_EXPIRY_STATUS_MISMATCH = 'tenant_expiry_status_mismatch';
    public const REASON_TENANT_SUBSCRIPTION_PLAN_MISMATCH = 'tenant_subscription_plan_mismatch';
    public const REASON_MISSING_SUBSCRIPTION = 'missing_subscription';
    public const REASON_MISSING_PLAN = 'missing_plan';
    public const REASON_FREE_PLAN = 'free_plan';
    public const REASON_SUBSCRIPTION_NOT_ENDED = 'subscription_not_ended';
    public const REASON_STALE_OPEN_SUBSCRIPTION_EXPIRED = 'stale_open_subscription_expired';
    public const REASON_AMBIGUOUS_SUBSCRIPTION_STATUS = 'ambiguous_subscription_status';
    public const REASON_SUSPENSION_ORIGIN_UNVERIFIED = 'suspension_origin_unverified';
    public const REASON_UNKNOWN_SUBSCRIPTION_STATUS = 'unknown_subscription_status';
    public const REASON_CONFLICTING_SUBSCRIPTION_STATE = 'conflicting_subscription_state';
    public const REASON_MISSING_EXPIRY_DATE = 'missing_expiry_date';
    public const REASON_MISSING_TRIAL_EXPIRY_DATE = 'missing_trial_expiry_date';
    public const REASON_INCONSISTENT_EXPIRY_DATE = 'inconsistent_expiry_date';
    public const REASON_RETENTION_PERIOD_NOT_ELAPSED = 'retention_period_not_elapsed';
    public const REASON_PAYMENT_PENDING = 'payment_pending';
    public const REASON_PAYMENT_REVIEW_UNRESOLVED = 'payment_review_unresolved';
    public const REASON_RENEWAL_PAYMENT_PENDING = 'renewal_payment_pending';
    public const REASON_PLAN_CHANGE_PAYMENT_PENDING = 'plan_change_payment_pending';
    public const REASON_COMPLETED_PAYMENT_UNAPPLIED = 'completed_payment_unapplied';
    public const REASON_CANCELLATION_PENDING = 'cancellation_pending';
    public const REASON_PLAN_CHANGE_PENDING = 'plan_change_pending';
    public const REASON_STALE_PLAN_CHANGE = 'stale_plan_change';
    public const REASON_ACTIVE_HOLD = 'active_hold';
    public const REASON_HOLD_STATUS_UNVERIFIED = 'hold_status_unverified';

    private const OPEN_SUBSCRIPTION_STATUSES = [
        'pending',
        'active',
        'trialing',
        'past_due',
    ];

    private const ENDED_SUBSCRIPTION_STATUSES = [
        'expired',
        'canceled',
    ];

    private const KNOWN_SUBSCRIPTION_STATUSES = [
        'pending',
        'active',
        'trialing',
        'past_due',
        'expired',
        'canceled',
        'suspended',
    ];

    private const PENDING_PAYMENT_STATUSES = [
        TransactionStatus::DRAFT->value,
        TransactionStatus::PENDING->value,
        TransactionStatus::WAITING_PAYMENT->value,
        TransactionStatus::WAITING_REVIEW->value,
        TransactionStatus::APPROVED->value,
        TransactionStatus::PAID->value,
    ];

    private const EXPIRING_PENDING_PAYMENT_STATUSES = [
        TransactionStatus::DRAFT->value,
        TransactionStatus::PENDING->value,
        TransactionStatus::WAITING_PAYMENT->value,
    ];

    public function evaluate(
        Tenant $tenant,
        ?bool $hasActiveHold = null,
        ?CarbonInterface $asOf = null,
    ): array {
        $asOf = $this->toLocal($asOf ?? now());

        $tenant = Tenant::query()->find($tenant->getKey());

        if (!$tenant) {
            return $this->result(
                tenantId: null,
                decision: self::DECISION_MANUAL_REVIEW,
                reasonCodes: [self::REASON_TENANT_NOT_FOUND],
                asOf: $asOf,
            );
        }

        if ($this->isProtectedTenant($tenant)) {
            return $this->result(
                tenantId: $tenant->id,
                decision: self::DECISION_MANUAL_REVIEW,
                reasonCodes: [self::REASON_PROTECTED_TENANT],
                asOf: $asOf,
            );
        }

        if ($tenant->status === 'deleted') {
            return $this->result(
                tenantId: $tenant->id,
                decision: self::DECISION_MANUAL_REVIEW,
                reasonCodes: [self::REASON_TENANT_DELETED],
                asOf: $asOf,
            );
        }

        if (!in_array($tenant->status, ['active', 'trialing', 'pending', 'suspended'], true)) {
            return $this->result(
                tenantId: $tenant->id,
                decision: self::DECISION_MANUAL_REVIEW,
                reasonCodes: [self::REASON_UNKNOWN_TENANT_STATUS],
                asOf: $asOf,
            );
        }

        $subscriptions = Subscription::withoutTenantScope()
            ->with('plan')
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('id')
            ->get();

        if ($subscriptions->isEmpty()) {
            return $this->result(
                tenantId: $tenant->id,
                decision: self::DECISION_MANUAL_REVIEW,
                reasonCodes: [self::REASON_MISSING_SUBSCRIPTION],
                asOf: $asOf,
            );
        }

        if ($subscriptions->contains(fn (Subscription $item) => !in_array(
            (string) $item->status,
            self::KNOWN_SUBSCRIPTION_STATUSES,
            true,
        ))) {
            return $this->result(
                tenantId: $tenant->id,
                decision: self::DECISION_MANUAL_REVIEW,
                reasonCodes: [self::REASON_UNKNOWN_SUBSCRIPTION_STATUS],
                asOf: $asOf,
            );
        }

        $subscription = $subscriptions->first();

        $openSubscriptions = $subscriptions->filter(
            fn (Subscription $item) => in_array((string) $item->status, self::OPEN_SUBSCRIPTION_STATUSES, true),
        );

        if ($openSubscriptions->count() > 1
            || ($openSubscriptions->count() === 1
                && !in_array((string) $subscription->status, self::OPEN_SUBSCRIPTION_STATUSES, true))) {
            return $this->result(
                tenantId: $tenant->id,
                decision: self::DECISION_MANUAL_REVIEW,
                reasonCodes: [self::REASON_CONFLICTING_SUBSCRIPTION_STATE],
                asOf: $asOf,
            );
        }

        $plan = $subscription->plan;

        if (!$plan instanceof Plan) {
            return $this->result(
                tenantId: $tenant->id,
                decision: self::DECISION_MANUAL_REVIEW,
                reasonCodes: [self::REASON_MISSING_PLAN],
                asOf: $asOf,
            );
        }

        $status = (string) $subscription->status;

        if (in_array($status, self::OPEN_SUBSCRIPTION_STATUSES, true)) {
            return $this->evaluateOpen($tenant, $subscription, $plan, $asOf, $hasActiveHold);
        }

        if ($status === 'suspended') {
            return $this->evaluateSuspended($tenant, $subscription, $plan, $asOf, $hasActiveHold);
        }

        return $this->evaluateEnded($tenant, $subscription, $plan, $asOf, $hasActiveHold);
    }

    private function evaluateOpen(
        Tenant $tenant,
        Subscription $subscription,
        Plan $plan,
        CarbonInterface $asOf,
        ?bool $hasActiveHold,
    ): array {
        $source = $subscription->status === 'trialing' ? self::SOURCE_TRIAL : self::SOURCE_PAID;
        $applicableExpiry = $this->openApplicableExpiry($subscription);
        $applicableExpiry = $applicableExpiry ? $this->toLocal($applicableExpiry) : null;
        $graceEndsAt = $this->graceEndsAt($applicableExpiry);
        $retentionReviewAt = $this->retentionReviewAt($applicableExpiry);

        $reasonCodes = $this->consistencyReasons(
            $tenant,
            $subscription,
            $asOf,
            true,
            $applicableExpiry,
        );

        if ($this->openSubscriptionExpired($subscription, $asOf)) {
            $reasonCodes[] = self::REASON_STALE_OPEN_SUBSCRIPTION_EXPIRED;
        }

        if ($this->tenantStatusConflicts($tenant, $subscription)) {
            $reasonCodes[] = self::REASON_TENANT_STATUS_CONFLICT;
        }

        if ($plan->isFree()) {
            if ($reasonCodes !== []) {
                return $this->result(
                    tenantId: $tenant->id,
                    decision: self::DECISION_MANUAL_REVIEW,
                    reasonCodes: $reasonCodes,
                    asOf: $asOf,
                    source: $source,
                    applicableExpiry: $applicableExpiry,
                    retentionReviewAt: $retentionReviewAt,
                    graceEndsAt: $graceEndsAt,
                );
            }

            return $this->result(
                tenantId: $tenant->id,
                decision: self::DECISION_NOT_ELIGIBLE,
                reasonCodes: [self::REASON_FREE_PLAN],
                asOf: $asOf,
                source: $source,
                applicableExpiry: $applicableExpiry,
                retentionReviewAt: $retentionReviewAt,
                graceEndsAt: $graceEndsAt,
            );
        }

        if ($reasonCodes !== []) {
            return $this->result(
                tenantId: $tenant->id,
                decision: self::DECISION_MANUAL_REVIEW,
                reasonCodes: $reasonCodes,
                asOf: $asOf,
                source: $source,
                applicableExpiry: $applicableExpiry,
                retentionReviewAt: $retentionReviewAt,
                graceEndsAt: $graceEndsAt,
            );
        }

        return $this->result(
            tenantId: $tenant->id,
            decision: self::DECISION_NOT_ELIGIBLE,
            reasonCodes: [self::REASON_SUBSCRIPTION_NOT_ENDED],
            asOf: $asOf,
            source: $source,
            applicableExpiry: $applicableExpiry,
            retentionReviewAt: $retentionReviewAt,
            graceEndsAt: $graceEndsAt,
        );
    }

    private function evaluateSuspended(
        Tenant $tenant,
        Subscription $subscription,
        Plan $plan,
        CarbonInterface $asOf,
        ?bool $hasActiveHold,
    ): array {
        $isTrial = $subscription->isTrialSubscription();
        $source = $isTrial ? self::SOURCE_TRIAL : self::SOURCE_PAID;
        $applicableExpiry = $this->endedApplicableExpiry($subscription, $isTrial);
        $applicableExpiry = $applicableExpiry ? $this->toLocal($applicableExpiry) : null;
        $graceEndsAt = $this->graceEndsAt($applicableExpiry);
        $retentionReviewAt = $this->retentionReviewAt($applicableExpiry);

        $reasonCodes = $this->consistencyReasons(
            $tenant,
            $subscription,
            $asOf,
            false,
            $applicableExpiry,
        );

        if ($applicableExpiry === null) {
            $reasonCodes[] = $isTrial
                ? self::REASON_MISSING_TRIAL_EXPIRY_DATE
                : self::REASON_MISSING_EXPIRY_DATE;
        }

        if (!$this->isVerifiedLifecycleSuspension($subscription, $asOf, $applicableExpiry)) {
            $reasonCodes[] = self::REASON_AMBIGUOUS_SUBSCRIPTION_STATUS;
            $reasonCodes[] = self::REASON_SUSPENSION_ORIGIN_UNVERIFIED;

            return $this->result(
                tenantId: $tenant->id,
                decision: self::DECISION_MANUAL_REVIEW,
                reasonCodes: $reasonCodes,
                asOf: $asOf,
                source: $source,
                applicableExpiry: $applicableExpiry,
                retentionReviewAt: $retentionReviewAt,
                graceEndsAt: $graceEndsAt,
            );
        }

        if ($plan->isFree()) {
            if ($reasonCodes !== []) {
                return $this->result(
                    tenantId: $tenant->id,
                    decision: self::DECISION_MANUAL_REVIEW,
                    reasonCodes: $reasonCodes,
                    asOf: $asOf,
                    source: $source,
                    applicableExpiry: $applicableExpiry,
                    retentionReviewAt: $retentionReviewAt,
                    graceEndsAt: $graceEndsAt,
                );
            }

            return $this->result(
                tenantId: $tenant->id,
                decision: self::DECISION_NOT_ELIGIBLE,
                reasonCodes: [self::REASON_FREE_PLAN],
                asOf: $asOf,
                source: $source,
                applicableExpiry: $applicableExpiry,
                retentionReviewAt: $retentionReviewAt,
                graceEndsAt: $graceEndsAt,
            );
        }

        if ($reasonCodes !== []) {
            return $this->result(
                tenantId: $tenant->id,
                decision: self::DECISION_MANUAL_REVIEW,
                reasonCodes: $reasonCodes,
                asOf: $asOf,
                source: $source,
                applicableExpiry: $applicableExpiry,
                retentionReviewAt: $retentionReviewAt,
                graceEndsAt: $graceEndsAt,
            );
        }

        if ($asOf->copy()->startOfDay()->lt($retentionReviewAt)) {
            return $this->result(
                tenantId: $tenant->id,
                decision: self::DECISION_NOT_ELIGIBLE,
                reasonCodes: [self::REASON_RETENTION_PERIOD_NOT_ELAPSED],
                asOf: $asOf,
                source: $source,
                applicableExpiry: $applicableExpiry,
                retentionReviewAt: $retentionReviewAt,
                graceEndsAt: $graceEndsAt,
            );
        }

        if ($hasActiveHold === true) {
            return $this->result(
                tenantId: $tenant->id,
                decision: self::DECISION_MANUAL_REVIEW,
                reasonCodes: [self::REASON_ACTIVE_HOLD],
                asOf: $asOf,
                source: $source,
                applicableExpiry: $applicableExpiry,
                retentionReviewAt: $retentionReviewAt,
                holdStatus: self::HOLD_ACTIVE,
                graceEndsAt: $graceEndsAt,
            );
        }

        if ($hasActiveHold === null) {
            return $this->result(
                tenantId: $tenant->id,
                decision: self::DECISION_MANUAL_REVIEW,
                reasonCodes: [self::REASON_HOLD_STATUS_UNVERIFIED],
                asOf: $asOf,
                source: $source,
                applicableExpiry: $applicableExpiry,
                retentionReviewAt: $retentionReviewAt,
                holdStatus: self::HOLD_UNVERIFIED,
                graceEndsAt: $graceEndsAt,
            );
        }

        return $this->result(
            tenantId: $tenant->id,
            decision: self::DECISION_ELIGIBLE,
            reasonCodes: [],
            asOf: $asOf,
            source: $source,
            applicableExpiry: $applicableExpiry,
            retentionReviewAt: $retentionReviewAt,
            holdStatus: self::HOLD_VERIFIED_NONE,
            graceEndsAt: $graceEndsAt,
        );
    }

    private function evaluateEnded(
        Tenant $tenant,
        Subscription $subscription,
        Plan $plan,
        CarbonInterface $asOf,
        ?bool $hasActiveHold,
    ): array {
        $isTrial = $subscription->isTrialSubscription();
        $source = $isTrial ? self::SOURCE_TRIAL : self::SOURCE_PAID;
        $applicableExpiry = $this->endedApplicableExpiry($subscription, $isTrial);

        if (!$applicableExpiry) {
            return $this->result(
                tenantId: $tenant->id,
                decision: self::DECISION_MANUAL_REVIEW,
                reasonCodes: [
                    $isTrial
                        ? self::REASON_MISSING_TRIAL_EXPIRY_DATE
                        : self::REASON_MISSING_EXPIRY_DATE,
                ],
                asOf: $asOf,
                source: $source,
            );
        }

        $applicableExpiry = $this->toLocal($applicableExpiry);
        $graceEndsAt = $this->graceEndsAt($applicableExpiry);
        $retentionReviewAt = $this->retentionReviewAt($applicableExpiry);

        $reasonCodes = $this->consistencyReasons(
            $tenant,
            $subscription,
            $asOf,
            false,
            $applicableExpiry,
        );

        if ($subscription->status === 'expired' && $applicableExpiry->gt($asOf)) {
            $reasonCodes[] = self::REASON_INCONSISTENT_EXPIRY_DATE;

            return $this->result(
                tenantId: $tenant->id,
                decision: self::DECISION_MANUAL_REVIEW,
                reasonCodes: $reasonCodes,
                asOf: $asOf,
                source: $source,
                applicableExpiry: $applicableExpiry,
                retentionReviewAt: $retentionReviewAt,
                graceEndsAt: $graceEndsAt,
            );
        }

        if ($plan->isFree()) {
            if ($reasonCodes !== []) {
                return $this->result(
                    tenantId: $tenant->id,
                    decision: self::DECISION_MANUAL_REVIEW,
                    reasonCodes: $reasonCodes,
                    asOf: $asOf,
                    source: $source,
                    applicableExpiry: $applicableExpiry,
                    retentionReviewAt: $retentionReviewAt,
                    graceEndsAt: $graceEndsAt,
                );
            }

            return $this->result(
                tenantId: $tenant->id,
                decision: self::DECISION_NOT_ELIGIBLE,
                reasonCodes: [self::REASON_FREE_PLAN],
                asOf: $asOf,
                source: $source,
                applicableExpiry: $applicableExpiry,
                retentionReviewAt: $retentionReviewAt,
                graceEndsAt: $graceEndsAt,
            );
        }

        if ($reasonCodes !== []) {
            return $this->result(
                tenantId: $tenant->id,
                decision: self::DECISION_MANUAL_REVIEW,
                reasonCodes: $reasonCodes,
                asOf: $asOf,
                source: $source,
                applicableExpiry: $applicableExpiry,
                retentionReviewAt: $retentionReviewAt,
                graceEndsAt: $graceEndsAt,
            );
        }

        if ($asOf->copy()->startOfDay()->lt($retentionReviewAt)) {
            return $this->result(
                tenantId: $tenant->id,
                decision: self::DECISION_NOT_ELIGIBLE,
                reasonCodes: [self::REASON_RETENTION_PERIOD_NOT_ELAPSED],
                asOf: $asOf,
                source: $source,
                applicableExpiry: $applicableExpiry,
                retentionReviewAt: $retentionReviewAt,
                graceEndsAt: $graceEndsAt,
            );
        }

        if ($hasActiveHold === true) {
            return $this->result(
                tenantId: $tenant->id,
                decision: self::DECISION_MANUAL_REVIEW,
                reasonCodes: [self::REASON_ACTIVE_HOLD],
                asOf: $asOf,
                source: $source,
                applicableExpiry: $applicableExpiry,
                retentionReviewAt: $retentionReviewAt,
                holdStatus: self::HOLD_ACTIVE,
                graceEndsAt: $graceEndsAt,
            );
        }

        if ($hasActiveHold === null) {
            return $this->result(
                tenantId: $tenant->id,
                decision: self::DECISION_MANUAL_REVIEW,
                reasonCodes: [self::REASON_HOLD_STATUS_UNVERIFIED],
                asOf: $asOf,
                source: $source,
                applicableExpiry: $applicableExpiry,
                retentionReviewAt: $retentionReviewAt,
                holdStatus: self::HOLD_UNVERIFIED,
                graceEndsAt: $graceEndsAt,
            );
        }

        return $this->result(
            tenantId: $tenant->id,
            decision: self::DECISION_ELIGIBLE,
            reasonCodes: [],
            asOf: $asOf,
            source: $source,
            applicableExpiry: $applicableExpiry,
            retentionReviewAt: $retentionReviewAt,
            holdStatus: self::HOLD_VERIFIED_NONE,
            graceEndsAt: $graceEndsAt,
        );
    }

    private function consistencyReasons(
        Tenant $tenant,
        Subscription $subscription,
        CarbonInterface $asOf,
        bool $isOpen,
        ?CarbonInterface $applicableExpiry,
    ): array {
        $reasons = [];

        if ($this->tenantSubscriptionPlanMismatches($tenant, $subscription)) {
            $reasons[] = self::REASON_TENANT_SUBSCRIPTION_PLAN_MISMATCH;
        }

        if ($isOpen && $this->tenantExpiryConflictsWithStatus($tenant, $asOf)) {
            $reasons[] = self::REASON_TENANT_EXPIRY_STATUS_MISMATCH;
        }

        $reasons = array_merge($reasons, $this->planChangeReasons($subscription, $asOf));
        $reasons = array_merge($reasons, $this->pendingPaymentReasons($tenant->id, $asOf));
        $reasons = array_merge(
            $reasons,
            $this->cancellationReasons($tenant->id, $subscription, $asOf, $applicableExpiry),
        );

        if (!$isOpen) {
            $reasons = array_merge($reasons, $this->completedPaymentReasons($subscription));
        }

        return array_values(array_unique($reasons));
    }

    private function tenantSubscriptionPlanMismatches(
        Tenant $tenant,
        Subscription $subscription,
    ): bool {
        return (int) $tenant->subscription_plan_id !== (int) $subscription->plan_id;
    }

    private function tenantExpiryConflictsWithStatus(
        Tenant $tenant,
        CarbonInterface $asOf,
    ): bool {
        if ($tenant->expires_at === null) {
            return false;
        }

        if (!in_array($tenant->status, ['active', 'trialing', 'pending'], true)) {
            return false;
        }

        return $tenant->expires_at->lt($asOf);
    }

    private function tenantStatusConflicts(Tenant $tenant, Subscription $subscription): bool
    {
        return $tenant->status === 'suspended'
            && in_array((string) $subscription->status, self::OPEN_SUBSCRIPTION_STATUSES, true);
    }

    private function openSubscriptionExpired(
        Subscription $subscription,
        CarbonInterface $asOf,
    ): bool {
        return match ((string) $subscription->status) {
            'trialing' => ($subscription->trial_ends_at ?? $subscription->expires_at)?->lt($asOf) ?? false,
            'active' => $subscription->expires_at?->lt($asOf) ?? false,
            'past_due' => $subscription->expires_at
                ? $subscription->expires_at->copy()->addDays(self::GRACE_DAYS)->lt($asOf)
                : false,
            default => false,
        };
    }

    private function planChangeReasons(
        Subscription $subscription,
        CarbonInterface $asOf,
    ): array {
        if ($subscription->pending_plan_id === null
            && $subscription->pending_plan_effective_at === null) {
            return [];
        }

        $reasons = [self::REASON_PLAN_CHANGE_PENDING];

        if ($subscription->pending_plan_effective_at !== null
            && $this->toLocal($subscription->pending_plan_effective_at)->lte($asOf)) {
            $reasons[] = self::REASON_STALE_PLAN_CHANGE;
        }

        return $reasons;
    }

    private function pendingPaymentReasons(
        int $tenantId,
        CarbonInterface $asOf,
    ): array {
        $intents = PaymentIntent::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', self::PENDING_PAYMENT_STATUSES)
            ->get()
            ->filter(fn (PaymentIntent $intent) => $this->isActionablePaymentIntent($intent, $asOf));

        if ($intents->isEmpty()) {
            return [];
        }

        $reasons = [self::REASON_PAYMENT_PENDING];

        foreach ($intents as $intent) {
            if ($intent->status === TransactionStatus::WAITING_REVIEW->value
                && !$this->hasResolvedPaymentReview($intent)) {
                $reasons[] = self::REASON_PAYMENT_REVIEW_UNRESOLVED;
            }

            $paymentType = $intent->paymentType();

            if ($paymentType === PaymentIntent::TYPE_RENEWAL) {
                $reasons[] = self::REASON_RENEWAL_PAYMENT_PENDING;
            }

            if (in_array($paymentType, [PaymentIntent::TYPE_UPGRADE, PaymentIntent::TYPE_PLAN_CHANGE], true)) {
                $reasons[] = self::REASON_PLAN_CHANGE_PAYMENT_PENDING;
            }
        }

        return array_values(array_unique($reasons));
    }

    private function isActionablePaymentIntent(
        PaymentIntent $intent,
        CarbonInterface $asOf,
    ): bool {
        if (!in_array($intent->status, self::EXPIRING_PENDING_PAYMENT_STATUSES, true)) {
            return true;
        }

        if ($intent->expires_at === null) {
            return true;
        }

        return $this->toLocal($intent->expires_at)->gte($asOf);
    }

    private function hasResolvedPaymentReview(PaymentIntent $intent): bool
    {
        return $intent->reviews()
            ->whereIn('action', ['approved', 'rejected'])
            ->exists();
    }

    private function completedPaymentReasons(Subscription $subscription): array
    {
        $intents = PaymentIntent::withoutTenantScope()
            ->where('tenant_id', $subscription->tenant_id)
            ->where('status', TransactionStatus::COMPLETED->value)
            ->get();

        foreach ($intents as $intent) {
            if (!empty(($intent->metadata ?? [])['subscription_applied'])) {
                continue;
            }

            if ($intent->subscription_id !== null
                && (int) $intent->subscription_id !== (int) $subscription->id) {
                continue;
            }

            $paymentType = $intent->paymentType();

            $relevant = $intent->subscription_id !== null
                || (int) $intent->plan_id === (int) $subscription->plan_id
                || in_array($paymentType, [
                    PaymentIntent::TYPE_RENEWAL,
                    PaymentIntent::TYPE_UPGRADE,
                    PaymentIntent::TYPE_PLAN_CHANGE,
                ], true);

            if ($relevant) {
                return [self::REASON_COMPLETED_PAYMENT_UNAPPLIED];
            }
        }

        return [];
    }

    private function cancellationReasons(
        int $tenantId,
        Subscription $subscription,
        CarbonInterface $asOf,
        ?CarbonInterface $applicableExpiry,
    ): array {
        $requests = SubscriptionCancellationRequest::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', SubscriptionCancellationRequest::EFFECTIVE_STATUSES)
            ->get();

        foreach ($requests as $request) {
            if ($request->subscription_id !== null
                && (int) $request->subscription_id !== (int) $subscription->id) {
                continue;
            }

            if ($applicableExpiry !== null && $this->toLocal($applicableExpiry)->lt($asOf)) {
                continue;
            }

            return [self::REASON_CANCELLATION_PENDING];
        }

        return [];
    }

    private function isVerifiedLifecycleSuspension(
        Subscription $subscription,
        CarbonInterface $asOf,
        ?CarbonInterface $applicableExpiry,
    ): bool {
        if ($subscription->status !== 'suspended' || $subscription->suspended_at === null) {
            return false;
        }

        if ($applicableExpiry === null || $applicableExpiry->gt($asOf)) {
            return false;
        }

        if ($this->toLocal($subscription->suspended_at)->lt($applicableExpiry)) {
            return false;
        }

        $log = SubscriptionAuditLog::withoutTenantScope()
            ->where('subscription_id', $subscription->id)
            ->where('event', 'suspended')
            ->orderByDesc('id')
            ->first();

        if (!$log) {
            return false;
        }

        if ((string) $log->old_status !== 'expired') {
            return false;
        }

        return in_array($log->actor_type, ['system', null], true);
    }

    private function openApplicableExpiry(Subscription $subscription): ?CarbonInterface
    {
        return $subscription->status === 'trialing'
            ? ($subscription->trial_ends_at ?? $subscription->expires_at)
            : $subscription->expires_at;
    }

    private function endedApplicableExpiry(
        Subscription $subscription,
        bool $isTrial,
    ): ?CarbonInterface {
        return $isTrial
            ? $subscription->trial_ends_at
            : $subscription->expires_at;
    }

    private function graceEndsAt(?CarbonInterface $applicableExpiry): ?Carbon
    {
        return $applicableExpiry?->copy()->startOfDay()->addDays(self::GRACE_DAYS);
    }

    private function retentionReviewAt(?CarbonInterface $applicableExpiry): ?Carbon
    {
        return $applicableExpiry?->copy()->startOfDay()->addDays(self::REVIEW_AFTER_DAYS);
    }

    private function isProtectedTenant(Tenant $tenant): bool
    {
        $protectedIds = array_map('intval', (array) config('retention.protected_tenant_ids', []));
        $protectedSlugs = array_map('strval', (array) config('retention.protected_tenant_slugs', []));
        $settingKey = (string) config('retention.protected_tenant_setting_key', 'retention_protected');

        if (in_array((int) $tenant->id, $protectedIds, true)) {
            return true;
        }

        if (in_array((string) $tenant->slug, $protectedSlugs, true)) {
            return true;
        }

        return !empty(($tenant->settings ?? [])[$settingKey]);
    }

    private function toLocal(CarbonInterface $date): Carbon
    {
        return Carbon::instance($date)->setTimezone($this->timezone());
    }

    private function timezone(): string
    {
        $timezone = config('retention.timezone', config('app.timezone', 'UTC'));

        return is_string($timezone) && $timezone !== '' ? $timezone : 'UTC';
    }

    private function result(
        ?int $tenantId,
        string $decision,
        array $reasonCodes,
        CarbonInterface $asOf,
        ?string $source = null,
        ?CarbonInterface $applicableExpiry = null,
        ?CarbonInterface $retentionReviewAt = null,
        string $holdStatus = self::HOLD_NOT_EVALUATED,
        ?CarbonInterface $graceEndsAt = null,
    ): array {
        return [
            'tenant_id' => $tenantId,
            'decision' => $decision,
            'eligible' => $decision === self::DECISION_ELIGIBLE,
            'reason_codes' => array_values(array_unique($reasonCodes)),
            'source' => $source,
            'subscription_ended_at' => $applicableExpiry,
            'applicable_expiry_at' => $applicableExpiry,
            'grace_ends_at' => $graceEndsAt,
            'retention_review_at' => $retentionReviewAt,
            'grace_days' => self::GRACE_DAYS,
            'retention_days' => self::RETENTION_DAYS,
            'review_after_days' => self::REVIEW_AFTER_DAYS,
            'hold_status' => $holdStatus,
            'evaluated_at' => $asOf,
        ];
    }
}
