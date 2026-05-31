<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SubscriptionExpiringSoon extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Subscription $subscription,
        private readonly int $daysRemaining,
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
            'title' => 'Subscription Expiring Soon',
            'message' => "Your {$planName} plan subscription will expire in {$this->daysRemaining} day(s) on {$expiresAt}. Renew now to avoid service interruption.",
            'subscription_id' => $this->subscription->id,
            'days_remaining' => $this->daysRemaining,
            'expires_at' => $expiresAt,
            'action_url' => route('admin.billing'),
            'action_label' => 'Renew Now',
        ];
    }
}
