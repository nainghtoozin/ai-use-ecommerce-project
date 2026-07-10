<?php

namespace App\Events;

use App\Auth\IdentityResolver;
use App\Models\PaymentIntent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BillingPaymentApproved implements ShouldBroadcast
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
        return 'billing.payment_approved';
    }

    public function broadcastWith(): array
    {
        $planName = $this->intent->plan?->name ?? 'your plan';

        return [
            'id' => $this->intent->id,
            'title' => '✅ Payment Approved',
            'message' => "Your subscription payment for {$planName} has been approved.",
            'action_url' => url('/store/' . $this->intent->tenant?->slug . '/admin/billing'),
        ];
    }
}
