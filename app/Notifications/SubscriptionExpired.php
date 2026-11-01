<?php

namespace App\Notifications;

use App\Contracts\HasDatabaseTenantId;
use App\Models\Subscription;
use App\Models\SubscriptionCancellationRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SubscriptionExpired extends Notification implements HasDatabaseTenantId
{
    use Queueable;

    public function __construct(
        private readonly Subscription $subscription,
        private readonly ?SubscriptionCancellationRequest $cancellation = null,
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

        if ($this->cancellation) {
            $expiresAt = $this->subscription->expires_at?->format('Y-m-d') ?? 'N/A';

            return [
                'title' => 'Subscription Ended',
                'message' => "Your {$planName} plan ended on {$expiresAt} and the 7-day grace period has passed. Store operations are now restricted. Your data has not been deleted and will not be deleted automatically; you can export it at any time or renew to restore full access.",
                'subscription_id' => $this->subscription->id,
                'expires_at' => $expiresAt,
                'cancellation_scheduled' => true,
                'cancellation_request_id' => $this->cancellation->id,
                'action_url' => route('admin.billing'),
                'action_label' => 'View Billing',
                'export_url' => route('admin.data-export.index'),
                'export_label' => 'Export My Data',
            ];
        }

        return [
            'title' => 'Subscription Expired',
            'message' => "Your {$planName} plan subscription has expired. Product management, order processing, and other operational features are now restricted. Please renew to restore full access.",
            'subscription_id' => $this->subscription->id,
            'action_url' => route('admin.billing'),
            'action_label' => 'Renew Now',
        ];
    }
}
