<?php

namespace App\Notifications;

use App\Models\PaymentIntent;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BillingPaymentApprovedMerchantNotification extends Notification
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

        return [
            'title' => '✅ Payment Approved',
            'message' => "Your subscription payment for {$planName} has been approved.",
            'action_url' => url('/store/' . $this->intent->tenant?->slug . '/admin/billing'),
        ];
    }
}
