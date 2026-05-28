<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class OrderPlaced implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order
    ) {}

    public function broadcastOn(): array
    {
        $customerChannel = 'notifications.user.'.$this->order->user_id;
        $channels = [new PrivateChannel($customerChannel)];

        $admins = \App\Models\User::role('admin')
            ->where('users.tenant_id', $this->order->tenant_id)
            ->pluck('id');
        foreach ($admins as $adminId) {
            $channels[] = new PrivateChannel('notifications.user.'.$adminId);
        }

        Log::debug('[OrderPlaced] Broadcasting to channels:', [
            'customer_channel' => $customerChannel,
            'admin_channels' => $admins->toArray(),
        ]);

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
