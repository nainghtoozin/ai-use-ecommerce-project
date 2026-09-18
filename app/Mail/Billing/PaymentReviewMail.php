<?php

namespace App\Mail\Billing;

use App\Mail\MerchantMailable;
use Illuminate\Mail\Mailables\Content;

class PaymentReviewMail extends MerchantMailable
{
    public function __construct(public readonly array $data) {}

    protected function subjectLine(): string
    {
        return 'New payment requires review — ' . ($this->data['merchant_name'] ?? 'merchant') . ' — ' . ($this->data['plan_name'] ?? 'plan');
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.billing.payment-review',
            text: 'emails.billing.payment-review-text',
            with: $this->data,
        );
    }
}
