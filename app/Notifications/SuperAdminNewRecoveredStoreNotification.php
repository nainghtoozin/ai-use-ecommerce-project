<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SuperAdminNewRecoveredStoreNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly array $data
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return array_merge([
            'title' => 'New Store Requires Activation',
            'message' => 'A merchant has restored a backup into a new store. Please review the store\'s subscription and activate it if eligible.',
            'priority' => 'high',
            'event' => 'new_recovered_store_activation',
        ], $this->data);
    }
}
