<?php

namespace App\Notifications;

use App\Contracts\HasDatabaseTenantId;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderDeliveredNotification extends Notification implements HasDatabaseTenantId
{
    use Queueable;

    public function __construct(
        private readonly Order $order
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseTenantId(): ?int
    {
        return $this->order->tenant_id;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => '📦 Order Delivered',
            'message' => 'Your order #'.$this->order->id.' has been delivered. Thank you for shopping with us!',
            'order_id' => $this->order->id,
            'order_number' => $this->order->id,
            'action_url' => route('client.orders.show', $this->order),
        ];
    }
}
