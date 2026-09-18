<?php

namespace App\Mail\Billing;

use App\Mail\MerchantMailable;
use Illuminate\Mail\Mailables\Content;

class PaymentApprovedMail extends MerchantMailable
{
    public function __construct(public readonly array $data) {}

    protected function subjectLine(): string
    {
        return 'Payment approved — ' . ($this->data['plan_name'] ?? 'your plan');
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.billing.payment-approved',
            text: 'emails.billing.payment-approved-text',
            with: $this->data,
        );
    }
}
