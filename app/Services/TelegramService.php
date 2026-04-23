<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    public function sendMessage(string $text): bool
    {
        $botToken = config('services.telegram.bot_token');
        $chatId = config('services.telegram.chat_id');

        if (blank($botToken) || blank($chatId)) {
            Log::warning('Telegram notification skipped: missing bot token or chat ID.');

            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->retry(2, 200)
                ->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $text,
                ]);

            if ($response->failed()) {
                Log::warning('Telegram notification failed.', [
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $exception) {
            Log::warning('Telegram notification exception.', [
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    public function sendOrderNotification(Order $order): bool
    {
        $order->loadMissing(['payment', 'paymentMethod', 'city', 'township', 'items.product']);

        return $this->sendMessage($this->buildOrderMessage($order));
    }

    private function buildOrderMessage(Order $order): string
    {
        $customerName = $order->customer_name
            ?: trim(($order->first_name ?? '') . ' ' . ($order->last_name ?? ''));

        $items = $order->items
            ->map(function ($item) {
                $productName = $item->product_name
                    ?? $item->product?->name
                    ?? 'Unknown Product';

                return "{$productName} = {$item->quantity} pcs";
            })
            ->filter()
            ->map(fn (string $productName) => "* {$productName}")
            ->implode("\n");

        if ($items === '') {
            $items = '* N/A';
        }

        $orderNumber = $order->order_number ?? $order->id;
        $paymentName = $order->payment?->name ?? $order->paymentMethod?->name ?? 'N/A';
        $transactionId = $order->transaction_id ?: 'N/A';
        $cityName = $order->city?->name ?? 'N/A';
        $townshipName = $order->township?->name ?? 'N/A';

        return "🛒 New Order Received\n\n"
            . "Order No: {$orderNumber}\n"
            . "Customer: {$customerName}\n"
            . "Payment: {$paymentName}\n"
            . "Transaction: {$transactionId}\n\n"
            . "📍 Location:\n"
            . "{$cityName} / {$townshipName}\n\n"
            . "📦 Items:\n\n"
            . "{$items}\n\n"
            . "💰 Pricing:\n"
            . 'Subtotal: ' . number_format((float) $order->subtotal, 0) . " MMK\n"
            . 'Delivery: ' . number_format((float) $order->delivery_fee, 0) . " MMK\n"
            . 'Total: ' . number_format((float) $order->total_amount, 0) . ' MMK';
    }
}
