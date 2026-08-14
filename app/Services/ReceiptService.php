<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\PaymentIntent;
use App\Models\Receipt;
use App\Models\BillingPaymentMethod;
use Illuminate\Support\Facades\DB;

class ReceiptService
{
    public function createFromCompletedIntent(PaymentIntent $intent): Receipt
    {
        return DB::transaction(function () use ($intent) {
            $intent->loadMissing('plan', 'evidences');
            $invoice = Invoice::where('payment_intent_id', $intent->id)->first();

            if (!$invoice) {
                $invoice = app(InvoiceService::class)->generateFromPaymentIntent($intent);
            }

            $invoice->update([
                'status' => Invoice::STATUS_PAID,
                'paid_at' => $intent->completed_at ?? now(),
                'total' => $invoice->subtotal ?? $invoice->amount,
                'tax' => 0,
            ]);

            return Receipt::firstOrCreate(
                ['payment_intent_id' => $intent->id],
                [
                    'tenant_id' => $intent->tenant_id,
                    'invoice_id' => $invoice->id,
                    'receipt_number' => Receipt::generateNumber(),
                    'amount' => $intent->amount,
                    'currency' => $intent->currency ?? 'MMK',
                    'paid_at' => $intent->completed_at ?? now(),
                    'details' => [
                        'plan_name' => $intent->plan?->name,
                        'billing_cycle' => $intent->billing_cycle,
                        'payment_method' => $this->paymentMethodName($intent),
                    ],
                ],
            );
        });
    }

    public function createFromPaidInvoice(Invoice $invoice): Receipt
    {
        $existing = $invoice->receipt;
        if ($existing) {
            return $existing;
        }

        $intent = $invoice->paymentIntent;
        if (!$intent) {
            throw new \RuntimeException('Receipt payment reference is unavailable.');
        }

        return $this->createFromCompletedIntent($intent);
    }

    private function paymentMethodName(PaymentIntent $intent): ?string
    {
        $methodId = $intent->evidences->first()?->metadata['payment_method_id'] ?? null;
        return $methodId ? BillingPaymentMethod::find($methodId)?->display_name : null;
    }
}
