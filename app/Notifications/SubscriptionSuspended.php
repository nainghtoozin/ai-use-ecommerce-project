<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SubscriptionSuspended extends Notification
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
            'title' => 'Store Suspended',
            'message' => "Your store has been suspended because your {$planName} plan subscription expired and the grace period has passed. All store features are disabled. Please contact support to restore your store.",
            'subscription_id' => $this->subscription->id,
            'action_url' => route('admin.suspended'),
            'action_label' => 'Learn More',
        ];
    }
}
