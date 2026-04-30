<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserTyping implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $senderId,
        public int $receiverId,
        public string $senderName,
        public bool $isTyping = true
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('chat.' . $this->receiverId)];
    }

    public function broadcastAs(): string
    {
        return 'typing';
    }

    public function broadcastWith(): array
    {
        return [
            'sender_id' => $this->senderId,
            'sender_name' => $this->senderName,
            'is_typing' => $this->isTyping,
        ];
    }
}
