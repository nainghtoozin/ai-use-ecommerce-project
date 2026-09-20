<?php

namespace App\Mail\Billing;

use App\Mail\MerchantMailable;
use Illuminate\Mail\Mailables\Content;

class SubscriptionRenewalReminderMail extends MerchantMailable
{
    public function __construct(public readonly array $data) {}

    protected function subjectLine(): string
    {
        return 'Your subscription renews soon — ' . ($this->data['plan_name'] ?? 'your plan');
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.billing.subscription-renewal-reminder',
            text: 'emails.billing.subscription-renewal-reminder-text',
            with: $this->data,
        );
    }
}
