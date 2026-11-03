<?php

namespace App\Mail\Platform;

use App\Mail\MerchantMailable;
use Illuminate\Mail\Mailables\Content;

class NewRecoveredStoreActivationMail extends MerchantMailable
{
    public function __construct(public readonly array $data) {}

    protected function subjectLine(): string
    {
        return 'Action Required: New Recovered Store Awaiting Activation';
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.platform.new-recovered-store-activation',
            text: 'emails.platform.new-recovered-store-activation-text',
            with: $this->data,
        );
    }
}
