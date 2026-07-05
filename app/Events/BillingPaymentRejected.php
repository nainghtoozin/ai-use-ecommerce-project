<?php

namespace App\Events;

use App\Models\PaymentIntent;
use App\Models\User;
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
        return User::where('tenant_id', $this->intent->tenant_id)
            ->where(function ($q) {
                $q->where('is_owner', true)->orWhereHas('roles', fn($r) => $r->where('name', 'admin'));
            })
            ->pluck('id')
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
