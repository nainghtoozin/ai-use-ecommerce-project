<?php

namespace App\Notifications;

use App\Contracts\HasDatabaseTenantId;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AfterSalesStatusNotification extends Notification implements HasDatabaseTenantId
{
    use Queueable;

    public function __construct(
        private readonly int $tenantId,
        private readonly string $title,
        private readonly string $message,
        private readonly ?string $actionUrl = null,
        private readonly array $meta = []
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseTenantId(): ?int
    {
        return $this->tenantId;
    }

    public function toArray(object $notifiable): array
    {
        return array_merge([
            'title' => $this->title,
            'message' => $this->message,
            'action_url' => $this->actionUrl,
        ], $this->meta);
    }
}
