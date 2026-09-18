<?php

namespace App\Mail\Billing;

use App\Mail\MerchantMailable;
use Illuminate\Mail\Mailables\Content;

class PaymentSubmittedMail extends MerchantMailable
{
    public function __construct(public readonly array $data) {}

    protected function subjectLine(): string
    {
        return 'Payment received — awaiting review';
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.billing.payment-submitted',
            text: 'emails.billing.payment-submitted-text',
            with: $this->data,
        );
    }
}
