<?php

namespace App\Events;

use App\Auth\IdentityResolver;
use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AfterSalesStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order,
        public string $title,
        public string $message,
        public string $category = 'after_sales',
        public ?string $reference = null,
    ) {}

    public function broadcastOn(): array
    {
        $channel = IdentityResolver::notificationChannelForOrderUser($this->order);

        return $channel ? [new PrivateChannel($channel)] : [];
    }

    public function broadcastAs(): string
    {
        return 'after_sales.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'category' => $this->category,
            'reference' => $this->reference,
            'title' => $this->title,
            'message' => $this->message,
            'updated_at' => now()->diffForHumans(),
        ];
    }
}
