<?php

namespace App\Events;

use App\Auth\IdentityResolver;
use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Message $message
    ) {}

    public function broadcastOn(): array
    {
        $channels = [];

        foreach (['receiver', 'sender'] as $role) {
            $party = $this->message->{$role};

            $name = $party
                ? IdentityResolver::chatChannelFor($party)
                : ($this->message->{$role.'_id'}
                    ? IdentityResolver::chatChannel((int) $this->message->{$role.'_id'}, $this->message->{$role.'_type'})
                    : null);

            if ($name) {
                $channels[$name] = new PrivateChannel($name);
            }
        }

        return array_values($channels);
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        $replyTo = null;
        if ($this->message->reply_to_id && $this->message->replyTo) {
            $replyTo = [
                'id' => $this->message->replyTo->id,
                'message' => $this->message->replyTo->message,
                'sender_id' => $this->message->replyTo->sender_id,
            ];
        }

        return [
            'id' => $this->message->id,
            'sender_id' => $this->message->sender_id,
            'receiver_id' => $this->message->receiver_id,
            'message' => $this->message->message,
            'created_at' => $this->message->created_at->toISOString(),
            'reply_to_id' => $this->message->reply_to_id,
            'reply_to' => $replyTo,
            'sender' => $this->message->sender ? [
                'id' => $this->message->sender->id,
                'name' => $this->message->sender->name,
            ] : null,
        ];
    }
}