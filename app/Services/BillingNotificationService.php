<?php

namespace App\Services;

use App\Auth\IdentityResolver;
use App\Events\BillingPaymentApproved;
use App\Events\BillingPaymentRejected;
use App\Events\BillingPaymentSubmitted;
use App\Models\Account;
use App\Models\PaymentIntent;
use App\Models\User;
use App\Jobs\SendTelegramMessageJob;
use App\Notifications\BillingPaymentApprovedMerchantNotification;
use App\Notifications\BillingPaymentRejectedMerchantNotification;
use App\Notifications\BillingPaymentSubmittedAdminNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class BillingNotificationService
{
    public function __construct(
        private readonly BillingEmailService $billingEmails,
        private readonly TelegramSystemAlertMessageBuilder $telegramBuilder,
        private readonly NotificationPreferenceService $preferenceService,
    ) {}

    public function notifyPaymentSubmitted(PaymentIntent $intent): void
    {
        try {
            $superAdminIds = IdentityResolver::resolveSuperAdmins();

            if ($superAdminIds->isNotEmpty()) {
                $superAdmins = config('identity.use_accounts')
                    ? Account::whereIn('id', $superAdminIds)->get()
                    : User::whereIn('id', $superAdminIds)->get();

                if ($superAdmins->isNotEmpty()) {
                    Notification::send($superAdmins, new BillingPaymentSubmittedAdminNotification($intent));
                }
            }

            $this->billingEmails->sendSubmittedEmail($intent);
            $this->billingEmails->sendReviewEmail($intent);

            if ($this->preferenceService->tenantAllows($intent->tenant_id)) {
                SendTelegramMessageJob::dispatchForTenant(
                    $intent->tenant_id,
                    $this->telegramBuilder->billingPaymentSubmitted($intent),
                );
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
            if (!$this->preferenceService->tenantAllows($intent->tenant_id)) {
                return;
            }

            $tenant = $intent->tenant;

            if ($tenant) {
                $tenant->notifyAdmins(new BillingPaymentApprovedMerchantNotification($intent));
            }

            BroadcastService::fire(new BillingPaymentApproved($intent), [
                'intent_id' => $intent->id,
            ]);

            SendTelegramMessageJob::dispatchForTenant(
                $intent->tenant_id,
                $this->telegramBuilder->billingPaymentApproved($intent),
            );
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

            $merchantAlerts = $this->preferenceService->tenantAllows($intent->tenant_id);

            $tenant = $intent->tenant;

            if ($tenant && $merchantAlerts) {
                $tenant->notifyAdmins(new BillingPaymentRejectedMerchantNotification($intent));
            }

            $this->billingEmails->sendRejectedEmail($intent);

            if ($merchantAlerts) {
                SendTelegramMessageJob::dispatchForTenant(
                    $intent->tenant_id,
                    $this->telegramBuilder->billingPaymentRejected(
                        $intent,
                        $intent->reviews->first()?->reason
                    ),
                );

                BroadcastService::fire(new BillingPaymentRejected($intent), [
                    'intent_id' => $intent->id,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Billing payment rejected notification failed.', [
                'intent_id' => $intent->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
