<?php

namespace App\Mail\Billing;

use App\Mail\MerchantMailable;
use Illuminate\Mail\Mailables\Content;

class InvoiceIssuedMail extends MerchantMailable
{
    public function __construct(public readonly array $data) {}

    protected function subjectLine(): string
    {
        return 'Invoice ' . ($this->data['invoice_number'] ?? '');
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.billing.invoice-issued',
            text: 'emails.billing.invoice-issued-text',
            with: $this->data,
        );
    }
}
