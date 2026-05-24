<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TestBroadcastEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $userId,
        public string $message = 'This is a test broadcast notification.'
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('notifications.user.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'order.placed';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => 999999,
            'title' => '🔔 Test Broadcast',
            'message' => $this->message,
            'created_at' => now()->diffForHumans(),
        ];
    }
}
