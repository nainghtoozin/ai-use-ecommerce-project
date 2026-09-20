<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\Tenant;
use App\Services\BillingEmailService;
use App\Services\ReceiptService;
use App\Services\SubscriptionDocumentPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class BillingDocumentController extends Controller
{
    public function showInvoice(Request $request, Invoice $invoice)
    {
        if (!auth()->user()->can('billing.view')) {
            abort(403);
        }

        $tenant = Tenant::getCurrent();

        if (!$tenant || $invoice->tenant_id !== $tenant->id) {
            abort(404);
        }

        $invoice->load(['plan', 'subscription', 'paymentIntent', 'receipt', 'tenant']);

        return view('billing.documents.invoice', [
            'invoice' => $invoice,
            'tenant' => $tenant,
            'downloadUrl' => URL::temporarySignedRoute(
                'billing.documents.invoice.pdf',
                now()->addDays(BillingEmailService::DOCUMENT_LINK_DAYS),
                ['invoice' => $invoice->id]
            ),
        ]);
    }

    public function invoicePdf(Request $request, Invoice $invoice, SubscriptionDocumentPdfService $documents)
    {
        if (!auth()->user()->can('billing.view')) {
            abort(403);
        }

        $tenant = Tenant::getCurrent();

        if (!$tenant || $invoice->tenant_id !== $tenant->id) {
            abort(404);
        }

        $invoice->load(['plan', 'subscription', 'paymentIntent', 'tenant']);

        return $documents->invoice($invoice, $request->boolean('view'));
    }

    public function showReceipt(Request $request, Receipt $receipt)
    {
        if (!auth()->user()->can('billing.view')) {
            abort(403);
        }

        $tenant = Tenant::getCurrent();

        if (!$tenant || $receipt->tenant_id !== $tenant->id) {
            abort(404);
        }

        $receipt->load(['invoice.plan', 'invoice.subscription', 'tenant', 'paymentIntent']);

        return view('billing.documents.receipt', [
            'receipt' => $receipt,
            'tenant' => $tenant,
            'downloadUrl' => URL::temporarySignedRoute(
                'billing.documents.receipt.pdf',
                now()->addDays(BillingEmailService::DOCUMENT_LINK_DAYS),
                ['receipt' => $receipt->id]
            ),
        ]);
    }

    public function receiptPdf(Request $request, Receipt $receipt, SubscriptionDocumentPdfService $documents)
    {
        if (!auth()->user()->can('billing.view')) {
            abort(403);
        }

        $tenant = Tenant::getCurrent();

        if (!$tenant || $receipt->tenant_id !== $tenant->id) {
            abort(404);
        }

        $receipt->load(['invoice.plan', 'invoice.subscription', 'tenant', 'paymentIntent']);

        return $documents->receipt($receipt, $request->boolean('view'));
    }

    public function publicInvoice(Invoice $invoice)
    {
        $invoice->load(['plan', 'subscription', 'paymentIntent', 'receipt', 'tenant']);

        return view('billing.documents.invoice', [
            'invoice' => $invoice,
            'tenant' => $invoice->tenant,
            'downloadUrl' => URL::temporarySignedRoute(
                'billing.documents.invoice.pdf',
                now()->addDays(BillingEmailService::DOCUMENT_LINK_DAYS),
                ['invoice' => $invoice->id]
            ),
        ]);
    }

    public function publicInvoicePdf(Invoice $invoice, SubscriptionDocumentPdfService $documents)
    {
        $invoice->load(['plan', 'subscription', 'paymentIntent', 'tenant']);

        return $documents->invoice($invoice);
    }

    public function showReceiptPublic(Receipt $receipt)
    {
        $receipt->load(['invoice.plan', 'invoice.subscription', 'tenant', 'paymentIntent']);

        return view('billing.documents.receipt', [
            'receipt' => $receipt,
            'tenant' => $receipt->tenant,
            'downloadUrl' => URL::temporarySignedRoute(
                'billing.documents.receipt.pdf',
                now()->addDays(BillingEmailService::DOCUMENT_LINK_DAYS),
                ['receipt' => $receipt->id]
            ),
        ]);
    }

    public function receiptPdfPublic(Receipt $receipt, SubscriptionDocumentPdfService $documents)
    {
        $receipt->load(['invoice.plan', 'invoice.subscription', 'tenant', 'paymentIntent']);

        return $documents->receipt($receipt);
    }
}
