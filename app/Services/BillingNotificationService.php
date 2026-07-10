<?php

namespace App\Services;

use App\Auth\IdentityResolver;
use App\Events\BillingPaymentApproved;
use App\Events\BillingPaymentRejected;
use App\Events\BillingPaymentSubmitted;
use App\Models\PaymentIntent;
use App\Notifications\BillingPaymentApprovedMerchantNotification;
use App\Notifications\BillingPaymentRejectedMerchantNotification;
use App\Notifications\BillingPaymentSubmittedAdminNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class BillingNotificationService
{
    public function notifyPaymentSubmitted(PaymentIntent $intent): void
    {
        try {
            $superAdmins = IdentityResolver::resolveSuperAdmins();

            if ($superAdmins->isNotEmpty()) {
                Notification::send($superAdmins, new BillingPaymentSubmittedAdminNotification($intent));
            }

            BroadcastService::fire(new BillingPaymentSubmitted($intent), [
                'intent_id' => $intent->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Billing payment submitted notification failed.', [
                'intent_id' => $intent->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function notifyPaymentApproved(PaymentIntent $intent): void
    {
        try {
            $tenant = $intent->tenant;

            if ($tenant) {
                $tenant->notifyAdmins(new BillingPaymentApprovedMerchantNotification($intent));
            }

            BroadcastService::fire(new BillingPaymentApproved($intent), [
                'intent_id' => $intent->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Billing payment approved notification failed.', [
                'intent_id' => $intent->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function notifyPaymentRejected(PaymentIntent $intent): void
    {
        try {
            $intent->loadMissing('reviews');

            $tenant = $intent->tenant;

            if ($tenant) {
                $tenant->notifyAdmins(new BillingPaymentRejectedMerchantNotification($intent));
            }

            BroadcastService::fire(new BillingPaymentRejected($intent), [
                'intent_id' => $intent->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Billing payment rejected notification failed.', [
                'intent_id' => $intent->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
