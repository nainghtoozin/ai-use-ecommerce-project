<?php

namespace App\Mail\Billing;

use App\Mail\MerchantMailable;
use Illuminate\Mail\Mailables\Content;

class PaymentRejectedMail extends MerchantMailable
{
    public function __construct(public readonly array $data) {}

    protected function subjectLine(): string
    {
        return 'Payment rejected — action required';
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.billing.payment-rejected',
            text: 'emails.billing.payment-rejected-text',
            with: $this->data,
        );
    }
}
