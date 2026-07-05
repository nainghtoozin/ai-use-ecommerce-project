<?php

namespace App\Notifications;

use App\Models\PaymentIntent;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BillingPaymentRejectedMerchantNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly PaymentIntent $intent
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $planName = $this->intent->plan?->name ?? 'your plan';
        $reason = $this->intent->reviews?->first()?->reason;

        $message = "Your subscription payment for {$planName} was rejected.";
        if ($reason) {
            $message .= " Reason: {$reason}";
        }

        return [
            'title' => '❌ Payment Rejected',
            'message' => $message,
            'action_url' => url('/store/' . $this->intent->tenant?->slug . '/admin/billing'),
        ];
    }
}
