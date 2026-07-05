<?php

namespace App\Observers;

use App\Enums\Payment\TransactionStatus;
use App\Models\PaymentIntent;
use App\Services\BillingNotificationService;

class PaymentIntentObserver
{
    public function __construct(
        private readonly BillingNotificationService $notificationService,
    ) {}

    public function updated(PaymentIntent $intent): void
    {
        if (!$intent->wasChanged('status')) {
            return;
        }

        $newStatus = $intent->status;

        match ($newStatus) {
            TransactionStatus::WAITING_REVIEW->value => $this->notificationService->notifyPaymentSubmitted($intent),
            TransactionStatus::REJECTED->value => $this->notificationService->notifyPaymentRejected($intent),
            TransactionStatus::COMPLETED->value => $this->notificationService->notifyPaymentApproved($intent),
            default => null,
        };
    }
}
