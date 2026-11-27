<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PlatformSetting;
use App\Models\Tenant;
use Symfony\Component\HttpFoundation\Response;

class CustomerOrderInvoicePdfService
{
    public function generate(Order $order, ?Tenant $tenant = null, bool $inline = false): Response
    {
        $order->loadMissing(['items.product', 'items.variant', 'paymentMethod', 'city', 'township']);

        $tenant = $tenant ?? $order->tenant;
        $platform = PlatformSetting::current();

        $lines = [];
        $lines[] = $platform->site_name ?? config('app.name');
        $lines[] = $platform->support_email ?? '';
        $lines[] = '';
        $lines[] = 'CUSTOMER INVOICE';
        $lines[] = $order->invoice_number . '    ' . strtoupper($order->order_status ?? '');
        $lines[] = 'Invoice Date: ' . ($order->created_at?->format('M d, Y') ?? 'N/A');
        $lines[] = '';
        $lines[] = 'BILL TO';
        $lines[] = trim(($order->first_name ?? '') . ' ' . ($order->last_name ?? ''));
        $lines[] = $order->phone ?? '';
        $lines[] = $order->address ?? '';
        $city = $order->city?->name ?? $order->city_name ?? null;
        $township = $order->township?->name ?? $order->township_name ?? null;
        if ($city || $township || $order->postal_code) {
            $lines[] = implode(', ', array_filter([$city, $township])) . ($order->postal_code ? '  ' . $order->postal_code : '');
        }
        $lines[] = '';
        $lines[] = 'ORDER STATUS';
        $lines[] = 'Order: ' . ($order->order_status ?? 'N/A');
        $lines[] = 'Payment: ' . ($order->payment_status ?? 'N/A');
        $lines[] = '';
        $lines[] = 'ITEMS';
        $lines[] = str_pad('Product', 30) . str_pad('Qty', 6) . str_pad('Price', 12) . 'Total';
        foreach ($order->items ?? [] as $item) {
            $name = $item->product?->name ?? ('Product #' . $item->product_id);
            if ($item->variant) {
                $name .= ' (' . $item->variant . ')';
            }
            $price = (float) ($item->price ?? $item->unit_price ?? 0);
            $qty = (int) ($item->quantity ?? 0);
            $lines[] = str_pad(substr($name, 0, 28), 30) . str_pad((string) $qty, 6) . str_pad(number_format($price, 2), 12) . number_format($price * $qty, 2);
        }
        $lines[] = '';
        $lines[] = 'Subtotal: ' . number_format((float) ($order->subtotal ?? $order->items_total ?? 0), 2);
        if ((float) ($order->discount_amount ?? 0) > 0) {
            $lines[] = 'Discount: -' . number_format((float) $order->discount_amount, 2);
        }
        if ((float) ($order->delivery_fee ?? 0) > 0) {
            $lines[] = 'Delivery Fee: ' . number_format((float) $order->delivery_fee, 2);
        }
        if ((float) ($order->packaging_fee ?? 0) > 0) {
            $lines[] = 'Packaging: ' . number_format((float) $order->packaging_fee, 2);
        }
        $lines[] = 'TOTAL: ' . number_format((float) $order->total_amount, 2);
        $lines[] = '';
        $lines[] = 'Payment Method: ' . ($order->paymentMethod?->name ?? $order->payment_method?->name ?? 'N/A');
        $lines[] = '';
        $lines[] = 'Thank you for your order.';
        $lines[] = '';
        $lines[] = 'Support: ' . ($platform->support_email ?? 'N/A');

        return $this->download($lines, 'invoice-' . $order->invoice_number . '.pdf', $inline);
    }

    private function download(array $lines, string $filename, bool $inline = false): Response
    {
        $pdf = "%PDF-1.4\n";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $content = "BT /F1 10 Tf 50 790 Td 15 TL\n";
        foreach ($lines as $line) {
            $safe = $this->pdfText((string) $line);
            $content .= '(' . $safe . ") Tj T*\n";
        }
        $content .= "ET";
        $objects[] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream";

        $offsets = [];
        foreach ($objects as $index => $object) {
            $offsets[$index + 1] = strlen($pdf);
            $pdf .= ($index + 1) . " 0 obj\n" . $object . "\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment') . '; filename="' . $filename . '"',
        ]);
    }

    private function pdfText(string $text): string
    {
        $text = iconv('UTF-8', 'Windows-1252//TRANSLIT', $text) ?: $text;
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
