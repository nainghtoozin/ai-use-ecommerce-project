<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Product $product,
        private readonly int $threshold = 10
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $isOutOfStock = $this->product->stock === 0;

        return [
            'title' => $isOutOfStock ? '🚨 Out of Stock' : '⚠️ Low Stock Alert',
            'message' => $isOutOfStock
                ? "{$this->product->name} (#{$this->product->id}) is out of stock! Restock immediately."
                : "{$this->product->name} (#{$this->product->id}) has only {$this->product->stock} left (threshold: {$this->threshold}). Consider restocking.",
            'product_id' => $this->product->id,
            'action_url' => route('admin.products.edit', $this->product),
        ];
    }
}
