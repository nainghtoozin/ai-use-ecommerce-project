<?php

namespace App\Notifications;

use App\Contracts\HasDatabaseTenantId;
use App\Models\Subscription;
use App\Models\SubscriptionCancellationRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SubscriptionCancellationScheduled extends Notification implements HasDatabaseTenantId
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
        $paidThrough = $this->subscription->expires_at?->format('Y-m-d')
            ?? 'the end of the paid period';

        return [
            'title' => 'Cancellation Scheduled',
            'message' => "Your {$planName} plan cancellation is scheduled. Your store stays available until {$paidThrough}. Your data will not be deleted automatically; you can export it at any time.",
            'subscription_id' => $this->subscription->id,
            'cancellation_request_id' => $this->cancellation?->id,
            'cancellation_scheduled' => true,
            'effective_at' => $paidThrough,
            'paid_through_at' => $paidThrough,
            'action_url' => route('admin.billing'),
            'action_label' => 'View Billing',
            'export_url' => route('admin.data-export.index'),
            'export_label' => 'Export My Data',
        ];
    }
}
