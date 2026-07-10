<?php

namespace App\Events;

use App\Auth\IdentityResolver;
use App\Models\PaymentIntent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BillingPaymentRejected implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public PaymentIntent $intent
    ) {}

    public function broadcastOn(): array
    {
        return IdentityResolver::resolveTenantOwnersAndAdmins($this->intent->tenant_id)
            ->map(fn($id) => new PrivateChannel('notifications.user.' . $id))
            ->toArray();
    }

    public function broadcastAs(): string
    {
        return 'billing.payment_rejected';
    }

    public function broadcastWith(): array
    {
        $planName = $this->intent->plan?->name ?? 'your plan';
        $reason = $this->intent->reviews?->first()?->reason;

        $message = "Your subscription payment for {$planName} was rejected.";
        if ($reason) {
            $message .= " Reason: {$reason}";
        }

        return [
            'id' => $this->intent->id,
            'title' => '❌ Payment Rejected',
            'message' => $message,
            'action_url' => url('/store/' . $this->intent->tenant?->slug . '/admin/billing'),
        ];
    }
}
