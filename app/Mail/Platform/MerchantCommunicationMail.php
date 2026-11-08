<?php

namespace App\Mail\Platform;

use App\Mail\MerchantMailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class MerchantCommunicationMail extends MerchantMailable
{
    public function __construct(
        public readonly string $subjectText,
        public readonly string $messageText,
        public readonly string $emailType,
        public readonly ?string $recipientName = null,
        public readonly ?string $replyToAddress = null,
    ) {}

    protected function subjectLine(): string
    {
        return $this->subjectText;
    }

    public function envelope(): Envelope
    {
        $envelope = parent::envelope();

        if ($this->replyToAddress) {
            $envelope->replyTo($this->replyToAddress);
        }

        return $envelope;
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.platform.merchant-communication',
            text: 'emails.platform.merchant-communication-text',
            with: [
                'subject' => $this->subjectText,
                'body' => $this->messageText,
                'email_type' => $this->emailType,
                'recipient_name' => $this->recipientName,
                'app_name' => config('app.name'),
                'store_name' => null,
            ],
        );
    }
}
