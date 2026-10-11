<?php

namespace App\Notifications;

use App\Contracts\HasDatabaseTenantId;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TenantCleanupNotice extends Notification implements HasDatabaseTenantId
{
    public function __construct(
        private readonly Tenant $tenant,
        private readonly ?Subscription $subscription,
        private readonly string $phase,
        private readonly ?\Carbon\Carbon $graceEndsAt = null,
        private readonly ?int $daysRemaining = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseTenantId(): ?int
    {
        return $this->tenant->id;
    }

    public function toArray(object $notifiable): array
    {
        $planName = $this->subscription?->plan?->name ?? 'your paid';
        $deadline = $this->graceEndsAt?->format('Y-m-d');

        [$title, $message] = match ($this->phase) {
            'restricted' => [
                'Subscription Expired — Store Restricted',
                "Your {$planName} subscription has expired and the cleanup grace period has ended. "
                    . 'Your store is now restricted. Renew with an approved payment to restore full access.',
            ],
            'flagged' => [
                'Payment Under Review — Cleanup Paused',
                'Your payment is still awaiting review, so your store has not been cleaned up. '
                    . "We will continue the review; renewal will restore full access once approved.",
            ],
            default => [
                'Renewal Required — Cleanup Deadline',
                "Your {$planName} subscription has expired. Renew to keep your store active. "
                    . ($deadline ? "If no approved renewal is received, your store will be restricted on {$deadline}. " : '')
                    . ($this->daysRemaining !== null ? "{$this->daysRemaining} day(s) remaining." : ''),
            ],
        };

        return [
            'title' => $title,
            'message' => $message,
            'phase' => $this->phase,
            'subscription_id' => $this->subscription?->id,
            'grace_ends_at' => $deadline,
            'days_remaining' => $this->daysRemaining,
            'action_url' => route('admin.billing'),
            'action_label' => 'Renew Now',
        ];
    }
}
