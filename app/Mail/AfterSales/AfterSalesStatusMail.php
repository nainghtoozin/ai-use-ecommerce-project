<?php

namespace App\Mail\AfterSales;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AfterSalesStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $title,
        public readonly string $messageBody,
        public readonly ?string $actionUrl = null,
        public readonly ?string $orderNumber = null,
        public readonly ?string $storeName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->title . ($this->storeName ? ' — ' . $this->storeName : ''),
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.after-sales.status',
            text: 'emails.after-sales.status-text',
            with: [
                'app_name' => (string) config('app.name'),
                'store_name' => $this->storeName,
                'title' => $this->title,
                'messageBody' => $this->messageBody,
                'actionUrl' => $this->actionUrl,
                'orderNumber' => $this->orderNumber,
                'storeName' => $this->storeName,
            ],
        );
    }

    public static function forOrder(Order $order, string $title, string $message, ?string $url = null): self
    {
        return new self(
            title: $title,
            messageBody: $message,
            actionUrl: $url,
            orderNumber: $order->invoice_number ?: (string) $order->id,
            storeName: $order->tenant?->name,
        );
    }
}
