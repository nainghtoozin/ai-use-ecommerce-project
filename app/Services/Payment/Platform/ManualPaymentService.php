<?php

namespace App\Services\Payment\Platform;

use App\Data\Currency;
use App\Models\PaymentIntent;
use App\Models\Plan;
use App\Models\Tenant;
use InvalidArgumentException;

class ManualPaymentService
{
    public function __construct(
        private readonly CheckoutService $checkout,
        private readonly PaymentIntentService $intents,
        private readonly PaymentExecutionGuard $guard,
        private readonly PaymentAuditService $audit,
        private readonly PaymentTimelineService $timeline,
    ) {}

    public function initiate(
        Tenant $tenant,
        Plan $plan,
        string $billingCycle,
        float $amount,
        Currency $currency,
        array $metadata = [],
    ): PaymentIntent {
        return $this->checkout->initiateCheckout(
            tenant: $tenant,
            plan: $plan,
            billingCycle: $billingCycle,
            amount: $amount,
            currency: $currency,
            gateway: 'manual',
            metadata: $metadata,
        );
    }

    public function confirmPayment(PaymentIntent $intent): PaymentIntent
    {
        return $this->guard->executeOnce($intent, 'manual.confirm_payment', function () use ($intent) {
            return $this->intents->markWaitingReview($intent);
        });
    }

    public function approvePayment(PaymentIntent $intent): PaymentIntent
    {
        return $this->guard->executeOnce($intent, 'manual.approve_payment', function () use ($intent) {
            $this->intents->approve($intent);

            $fresh = $this->intents->markAsPaid($intent->fresh());

            $this->intents->complete($fresh);

            return $fresh->fresh();
        });
    }

    public function rejectPayment(PaymentIntent $intent, string $reason = ''): PaymentIntent
    {
        if ($intent->status !== 'waiting_review') {
            throw new InvalidArgumentException(sprintf(
                'Cannot reject PaymentIntent #%d: must be in waiting_review status, currently %s.',
                $intent->id,
                $intent->status,
            ));
        }

        $metadata = $intent->metadata ?? [];
        $metadata['rejection_reason'] = $reason;
        $intent->update(['metadata' => $metadata]);

        return $this->intents->reject($intent);
    }

    public function cancelPayment(PaymentIntent $intent): PaymentIntent
    {
        if (!$intent->isPending()) {
            throw new InvalidArgumentException(sprintf(
                'Cannot cancel PaymentIntent #%d: current status is %s.',
                $intent->id,
                $intent->status,
            ));
        }

        return $this->intents->cancel($intent);
    }

    public function resubmitPayment(PaymentIntent $intent): PaymentIntent
    {
        if (!$intent->isRejected()) {
            throw new InvalidArgumentException(sprintf(
                'Cannot resubmit PaymentIntent #%d: must be rejected, currently %s.',
                $intent->id,
                $intent->status,
            ));
        }

        $metadata = $intent->metadata ?? [];
        unset($metadata['rejection_reason']);
        $intent->update(['metadata' => $metadata]);

        $fresh = $this->intents->markWaitingPayment($intent);

        $this->timeline->record(
            intent: $fresh,
            type: 'resubmitted',
            description: 'Payment resubmitted after rejection',
        );

        return $fresh;
    }
}
