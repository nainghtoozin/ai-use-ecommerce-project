<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SubscriptionExpired extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Subscription $subscription,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $planName = $this->subscription->plan?->name ?? 'Current';

        return [
            'title' => 'Subscription Expired',
            'message' => "Your {$planName} plan subscription has expired. Product management, order processing, and other operational features are now restricted. Please renew to restore full access.",
            'subscription_id' => $this->subscription->id,
            'action_url' => route('admin.billing'),
            'action_label' => 'Renew Now',
        ];
    }
}
