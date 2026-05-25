<?php

namespace App\Events;

use App\Models\Product;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LowStockAlert implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Product $product,
        public int $threshold = 10
    ) {}

    public function broadcastOn(): array
    {
        $channels = [];
        $admins = \App\Models\User::role('admin')->pluck('id');
        foreach ($admins as $adminId) {
            $channels[] = new PrivateChannel('notifications.user.'.$adminId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'stock.low';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->product->id,
            'product_name' => $this->product->name,
            'current_stock' => $this->product->stock,
            'threshold' => $this->threshold,
            'title' => $this->product->stock === 0 ? '🚨 Out of Stock' : '⚠️ Low Stock Alert',
            'message' => $this->product->stock === 0
                ? "{$this->product->name} is out of stock!"
                : "{$this->product->name} has only {$this->product->stock} left (threshold: {$this->threshold})",
            'created_at' => now()->diffForHumans(),
        ];
    }
}
