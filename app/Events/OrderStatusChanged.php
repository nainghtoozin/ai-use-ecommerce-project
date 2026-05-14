<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order,
        public string $oldStatus,
        public string $newStatus
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('notifications.user.'.$this->order->user_id)];
    }

    public function broadcastAs(): string
    {
        return 'order.status_changed';
    }

    public function broadcastWith(): array
    {
        $icons = [
            'confirmed' => '✅',
            'shipped' => '🚚',
            'delivered' => '📦',
            'cancelled' => '❌',
            'rejected' => '⚠️',
        ];

        $icon = $icons[$this->newStatus] ?? '📋';

        return [
            'id' => $this->order->id,
            'order_id' => $this->order->id,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'title' => "{$icon} Order Status Updated",
            'message' => "Order #{$this->order->id} status changed: {$this->oldStatus} → {$this->newStatus}",
            'updated_at' => now()->diffForHumans(),
        ];
    }
}
