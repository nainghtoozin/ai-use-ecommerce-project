<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\PlatformSetting;
use App\Models\Tenant;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionDocumentPdfService
{
    public function invoice(Invoice $invoice): Response
    {
        $invoice->loadMissing(['plan', 'subscription', 'tenant']);
        $owner = $this->owner($invoice->tenant);
        $platform = PlatformSetting::current();
        $period = $this->period($invoice->billing_period_start, $invoice->billing_period_end);
        $cycle = ucfirst($invoice->billing_interval ?? 'monthly');

        $lines = [
            $platform->site_name ?? config('app.name'),
            $platform->support_email ?? '',
            '',
            'SUBSCRIPTION INVOICE',
            $invoice->invoice_number . '    ' . strtoupper($invoice->status),
            'Invoice Date: ' . ($invoice->issued_at?->format('M d, Y') ?? 'N/A'),
            '',
            'BILL TO',
            $owner['name'],
            $owner['email'],
            $invoice->tenant?->name ?? 'N/A',
            '',
            'SUBSCRIPTION',
            'Plan: ' . ($invoice->plan?->name ?? 'Subscription'),
            'Billing Cycle: ' . $cycle,
            'Subscription Start: ' . ($invoice->billing_period_start?->format('M d, Y') ?? 'N/A'),
            'Subscription End: ' . ($invoice->billing_period_end?->format('M d, Y') ?? 'N/A'),
            'Subscription Period: ' . $period,
            '',
            'DESCRIPTION                         PERIOD                  AMOUNT',
            str_pad(($invoice->plan?->name ?? 'Subscription Plan'), 36) . str_pad($cycle, 24) . number_format((float) $invoice->amount, 2) . ' ' . $invoice->currency,
            '',
            'Subtotal: ' . number_format((float) ($invoice->subtotal ?? $invoice->amount), 2) . ' ' . $invoice->currency,
            'TOTAL:    ' . number_format((float) ($invoice->total ?? $invoice->amount), 2) . ' ' . $invoice->currency,
            '',
            'Thank you for subscribing to ' . ($platform->site_name ?? config('app.name')) . '.',
            '',
            'TERMS & CONDITIONS',
            '- Subscription access is based on the selected plan and billing period.',
            '- Manual payments are subject to verification.',
            '- Plan limits and features follow the selected subscription plan.',
            '',
            'Support: ' . ($platform->support_email ?? 'N/A'),
        ];

        return $this->download($lines, $invoice->invoice_number . '.pdf');
    }

    public function receipt(Receipt $receipt): Response
    {
        $receipt->loadMissing(['invoice.plan', 'invoice.subscription', 'tenant', 'paymentIntent']);
        $platform = PlatformSetting::current();
        $owner = $this->owner($receipt->tenant);
        $invoice = $receipt->invoice;
        $details = $receipt->details ?? [];
        $cycle = $invoice?->billing_interval ?? $details['billing_cycle'] ?? 'monthly';

        $lines = [
            $platform->site_name ?? config('app.name'),
            $platform->support_email ?? '',
            '',
            'PAYMENT RECEIPT',
            $receipt->receipt_number . '    PAID',
            'Payment Date: ' . ($receipt->paid_at?->format('M d, Y') ?? 'N/A'),
            '',
            'RECEIVED FROM',
            $owner['name'],
            $owner['email'],
            $receipt->tenant?->name ?? 'N/A',
            '',
            'PAYMENT DETAILS',
            'Plan: ' . ($invoice?->plan?->name ?? $details['plan_name'] ?? 'Subscription'),
            'Billing Cycle: ' . ucfirst($cycle),
            'Payment Method: ' . ($details['payment_method'] ?? 'Manual payment'),
            'Subscription Period: ' . $this->period($invoice?->billing_period_start, $invoice?->billing_period_end),
            'Payment Status: PAID',
            '',
            'AMOUNT PAID: ' . number_format((float) $receipt->amount, 2) . ' ' . $receipt->currency,
            '',
            'Thank you for your payment.',
            'This receipt confirms that the subscription payment was received and approved.',
            '',
            'Support: ' . ($platform->support_email ?? 'N/A'),
        ];

        return $this->download($lines, $receipt->receipt_number . '.pdf');
    }

    private function owner(?Tenant $tenant): array
    {
        $owner = $tenant?->ownerMembership()->with('account')->first()?->account ?? $tenant?->users()->first();

        return [
            'name' => $owner?->name ?? $tenant?->name ?? 'N/A',
            'email' => $owner?->email ?? $tenant?->email ?? 'N/A',
        ];
    }

    private function period($start, $end): string
    {
        return ($start?->format('M d, Y') ?? 'N/A') . ' -> ' . ($end?->format('M d, Y') ?? 'N/A');
    }

    private function download(array $lines, string $filename): Response
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
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function pdfText(string $text): string
    {
        $text = iconv('UTF-8', 'Windows-1252//TRANSLIT', $text) ?: $text;
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
