<?php

namespace App\Notifications;

use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewChatMessageNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Message $message
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => '💬 New Chat Message',
            'message' => 'You have a new message from support chat.',
            'sender_id' => $this->message->sender_id,
            'receiver_id' => $this->message->receiver_id,
            'chat_message_id' => $this->message->id,
            'action_url' => route('chat.index'),
        ];
    }
}