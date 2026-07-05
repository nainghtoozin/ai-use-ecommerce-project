<?php

namespace App\Notifications;

use App\Models\PaymentIntent;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BillingPaymentSubmittedAdminNotification extends Notification
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
        $merchantName = $this->intent->tenant?->name ?? 'A merchant';
        $planName = $this->intent->plan?->name ?? 'a plan';

        return [
            'title' => '💰 New Payment Submitted',
            'message' => "{$merchantName} submitted a payment for {$planName}.",
            'action_url' => url('/superadmin/billing'),
        ];
    }
}
