<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentVerified implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('notifications.user.'.$this->order->user_id)];
    }

    public function broadcastAs(): string
    {
        return 'payment.verified';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->order->id,
            'order_id' => $this->order->id,
            'title' => '✅ Payment Verified',
            'message' => 'Your payment for Order #'.$this->order->id.' has been verified successfully.',
            'updated_at' => now()->diffForHumans(),
        ];
    }
}
