<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderPlaced implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order
    ) {}

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('notifications.user.'.$this->order->user_id)];

        $admins = \App\Models\User::where('role', 'admin')->pluck('id');
        foreach ($admins as $adminId) {
            $channels[] = new PrivateChannel('notifications.user.'.$adminId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'order.placed';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->order->id,
            'customer_name' => $this->order->customer_name ?? trim(($this->order->first_name ?? '').' '.($this->order->last_name ?? '')),
            'total_amount' => $this->order->total_amount,
            'created_at' => $this->order->created_at->diffForHumans(),
            'title' => '🛒 New Order Received',
            'message' => "Order #{$this->order->id} has been placed.",
        ];
    }
}
