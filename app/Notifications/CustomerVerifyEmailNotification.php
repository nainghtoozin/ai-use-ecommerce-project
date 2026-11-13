<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class CustomerVerifyEmailNotification extends BaseVerifyEmail
{
    public function __construct(public $tenant = null) {}

    public function toMail($notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        $storeName = $this->tenant?->name ?: 'Store';
        $storeLogo = $this->tenant?->logo ?? null;
        $storeSupportEmail = null;
        $storeInfo = null;
        if ($this->tenant) {
            $storeInfo = \App\Models\WebsiteInfo::withoutTenantScope()
                ->where('tenant_id', $this->tenant->id)
                ->first();
            $storeSupportEmail = $storeInfo?->support_email ?: $storeInfo?->contact_email;
        }

        $platform = \App\Models\PlatformSetting::current();
        $fromAddress = $platform->support_email ?: config('mail.from.address');

        $recipientName = method_exists($notifiable, 'getDisplayName')
            ? $notifiable->getDisplayName()
            : ($notifiable->name ?? $notifiable->email);

        $message = (new MailMessage)
            ->subject('Verify your email address — ' . $storeName)
            ->view('emails.verify-email-customer', [
                'storeName' => $storeName,
                'storeLogo' => $storeLogo ? \App\Services\ImageService::url($storeLogo) : null,
                'recipientName' => $recipientName,
                'recipientEmail' => $notifiable->email,
                'verificationUrl' => $verificationUrl,
                'supportEmail' => $storeSupportEmail,
                'year' => date('Y'),
            ]);

        $message->from($fromAddress, $storeName);
        if ($storeSupportEmail) {
            $message->replyTo($storeSupportEmail, $storeName);
        }

        return $message;
    }
}
