<?php

namespace App\Notifications;

use App\Contracts\HasDatabaseTenantId;
use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SubscriptionPastDue extends Notification implements HasDatabaseTenantId
{
    use Queueable;

    public function __construct(
        private readonly Subscription $subscription,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseTenantId(): ?int
    {
        return $this->subscription->tenant_id;
    }

    public function toArray(object $notifiable): array
    {
        $planName = $this->subscription->plan?->name ?? 'Current';

        return [
            'title' => 'Payment Past Due',
            'message' => "Your {$planName} plan subscription payment is past due. You have a 7-day grace period to renew before features are restricted. Please renew to avoid service interruption.",
            'subscription_id' => $this->subscription->id,
            'action_url' => route('admin.billing'),
            'action_label' => 'Renew Now',
        ];
    }
}
