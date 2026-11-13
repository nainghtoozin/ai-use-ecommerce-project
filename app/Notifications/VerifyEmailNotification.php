<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends BaseVerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        $platform = \App\Models\PlatformSetting::current();
        $platformName = $platform->site_name ?: config('app.name', 'Laravel');
        $logoUrl = $platform->site_logo_url;
        $supportEmail = $platform->support_email;
        $supportUrl = $platform->support_page_url;

        $recipientName = method_exists($notifiable, 'getDisplayName')
            ? $notifiable->getDisplayName()
            : ($notifiable->name ?? $notifiable->email);

        $message = (new MailMessage)
            ->subject('Verify your email address | ' . $platformName)
            ->view('emails.verify-email', [
                'platformName' => $platformName,
                'logoUrl' => $logoUrl,
                'recipientName' => $recipientName,
                'recipientEmail' => $notifiable->email,
                'verificationUrl' => $verificationUrl,
                'supportEmail' => $supportEmail,
                'supportUrl' => $supportUrl,
                'year' => date('Y'),
            ]);

        $fromAddress = config('mail.from.address');
        $message->from($fromAddress, $platformName);
        if ($supportEmail) {
            $message->replyTo($supportEmail, $platformName);
        }

        return $message;
    }
}
