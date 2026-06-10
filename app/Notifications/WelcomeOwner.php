<?php

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeOwner extends Notification
{
    use Queueable;

    public function __construct(public Tenant $tenant) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $loginUrl = route('storefront.admin.login', ['store_slug' => $this->tenant->slug]);

        return (new MailMessage)
            ->subject('Your Store is Active — ' . $this->tenant->name)
            ->greeting('Welcome to ' . $this->tenant->name . '!')
            ->line('Your store has been activated successfully. You can now log in and start managing your products, orders, and settings.')
            ->action('Go to Your Store Admin', $loginUrl)
            ->line('We\'re excited to have you on board!')
            ->salutation('Best regards, the team');
    }
}
