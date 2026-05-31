<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SubscriptionRenewed extends Notification
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
        $expiresAt = $this->subscription->expires_at?->format('Y-m-d') ?? 'N/A';

        return [
            'title' => 'Subscription Renewed',
            'message' => "Your {$planName} plan subscription has been renewed and is now active. Next renewal date: {$expiresAt}.",
            'subscription_id' => $this->subscription->id,
            'expires_at' => $expiresAt,
            'action_url' => route('admin.billing'),
            'action_label' => 'View Billing',
        ];
    }
}
