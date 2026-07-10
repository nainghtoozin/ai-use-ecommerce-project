<?php

namespace App\Events;

use App\Auth\IdentityResolver;
use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentProofUploaded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order
    ) {}

    public function broadcastOn(): array
    {
        $channels = [];
        $admins = IdentityResolver::resolveTenantAdmins($this->order->tenant_id);
        foreach ($admins as $admin) {
            $channels[] = new PrivateChannel('notifications.user.'.$admin->id);
        }
        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'payment.proof_uploaded';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->order->id,
            'order_id' => $this->order->id,
            'customer_name' => $this->order->customer_name ?? trim(($this->order->first_name ?? '').' '.($this->order->last_name ?? '')),
            'title' => '💳 Payment Proof Uploaded',
            'message' => 'Order #'.$this->order->id.' by '.($this->order->customer_name ?? 'Customer').' is pending payment review.',
            'created_at' => now()->diffForHumans(),
        ];
    }
}
