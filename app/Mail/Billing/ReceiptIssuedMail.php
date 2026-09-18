<?php

namespace App\Mail\Billing;

use App\Mail\MerchantMailable;
use Illuminate\Mail\Mailables\Content;

class ReceiptIssuedMail extends MerchantMailable
{
    public function __construct(public readonly array $data) {}

    protected function subjectLine(): string
    {
        return 'Payment receipt — ' . ($this->data['receipt_number'] ?? 'your subscription');
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.billing.receipt-issued',
            text: 'emails.billing.receipt-issued-text',
            with: $this->data,
        );
    }
}
