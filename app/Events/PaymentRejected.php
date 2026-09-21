<?php

namespace App\Events;

use App\Auth\IdentityResolver;
use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentRejected implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order
    ) {}

    public function broadcastOn(): array
    {
        $channel = IdentityResolver::notificationChannelForOrderUser($this->order);

        return $channel ? [new PrivateChannel($channel)] : [];
    }

    public function broadcastAs(): string
    {
        return 'payment.rejected';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->order->id,
            'order_id' => $this->order->id,
            'title' => '❌ Payment Rejected',
            'message' => 'Your payment for Order #'.$this->order->id.' could not be verified. Reason: '.($this->order->rejection_reason ?? 'Please contact support.'),
            'updated_at' => now()->diffForHumans(),
        ];
    }
}
