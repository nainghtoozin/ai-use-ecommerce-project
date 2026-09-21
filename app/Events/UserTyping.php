<?php

namespace App\Events;

use App\Auth\IdentityResolver;
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
        public bool $isTyping = true,
        public ?string $senderType = null,
        public ?string $receiverType = null
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel(IdentityResolver::chatChannel($this->receiverId, $this->receiverType))];
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
