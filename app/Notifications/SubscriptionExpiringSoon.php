<?php

namespace App\Notifications;

use App\Contracts\HasDatabaseTenantId;
use App\Models\Subscription;
use App\Models\SubscriptionCancellationRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SubscriptionExpiringSoon extends Notification implements HasDatabaseTenantId
{
    use Queueable;

    public function __construct(
        private readonly Subscription $subscription,
        private readonly int $daysRemaining,
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
        $expiresAt = $this->subscription->expires_at?->format('Y-m-d') ?? 'N/A';

        if ($this->cancellation) {
            return [
                'title' => 'Subscription Ending Soon',
                'message' => "Your {$planName} plan is scheduled to end in {$this->daysRemaining} day(s) on {$expiresAt}. Your store stays available until then. Your data will not be deleted automatically; you can export it at any time.",
                'subscription_id' => $this->subscription->id,
                'days_remaining' => $this->daysRemaining,
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
