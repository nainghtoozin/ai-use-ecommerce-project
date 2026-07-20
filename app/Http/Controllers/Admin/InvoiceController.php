<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService
    ) {}

    public function index(Request $request)
    {
        if (!auth()->user()->can('billing.view')) {
            abort(403);
        }

        $tenant = Tenant::getCurrent();

        if (!$tenant) {
            abort(403, 'Store not found.');
        }

        $query = Invoice::forTenant($tenant->id)
            ->with(['plan', 'subscription'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('plan', fn($pq) => $pq->where('name', 'like', "%{$search}%"));
            });
        }

        $perPage = min((int) $request->get('per_page', 15), 100);
        $invoices = $query->paginate($perPage);

        $invoices->getCollection()->transform(fn($inv) => [
            'id' => $inv->id,
            'invoice_number' => $inv->invoice_number,
            'plan' => $inv->plan ? ['id' => $inv->plan->id, 'name' => $inv->plan->name] : null,
            'billing_interval' => $inv->billing_interval,
            'billing_period_start' => $inv->billing_period_start?->toDateString(),
            'billing_period_end' => $inv->billing_period_end?->toDateString(),
            'amount' => (float) $inv->amount,
            'subtotal' => (float) $inv->subtotal,
            'tax' => (float) $inv->tax,
            'total' => (float) $inv->total,
            'currency' => $inv->currency,
            'status' => $inv->status,
            'issued_at' => $inv->issued_at?->toDateTimeString(),
            'paid_at' => $inv->paid_at?->toDateTimeString(),
            'created_at' => $inv->created_at->toDateTimeString(),
        ]);

        $plans = Plan::active()->ordered()->get(['id', 'name']);

        return Inertia::render('Admin/Billing/Invoices', [
            'invoices' => $invoices,
            'filters' => $request->only(['status', 'date_from', 'date_to', 'search', 'per_page']),
            'plans' => $plans,
            'stats' => $this->invoiceService->getTenantStats($tenant),
        ]);
    }

    public function show(Request $request, Invoice $invoice)
    {
        if (!auth()->user()->can('billing.view')) {
            abort(403);
        }

        $tenant = Tenant::getCurrent();

        if (!$tenant || $invoice->tenant_id !== $tenant->id) {
            abort(404);
        }

        $invoice->load(['plan', 'subscription', 'paymentIntent']);

        return Inertia::render('Admin/Billing/InvoiceDetail', [
            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'plan' => $invoice->plan ? [
                    'id' => $invoice->plan->id,
                    'name' => $invoice->plan->name,
                    'slug' => $invoice->plan->slug,
                    'monthly_price' => $invoice->plan->monthly_price,
                    'yearly_price' => $invoice->plan->yearly_price,
                ] : null,
                'subscription' => $invoice->subscription ? [
                    'id' => $invoice->subscription->id,
                    'status' => $invoice->subscription->status,
                    'billing_interval' => $invoice->subscription->billing_interval,
                ] : null,
                'billing_interval' => $invoice->billing_interval,
                'billing_period_start' => $invoice->billing_period_start?->toDateString(),
                'billing_period_end' => $invoice->billing_period_end?->toDateString(),
                'amount' => (float) $invoice->amount,
                'subtotal' => (float) $invoice->subtotal,
                'tax' => (float) $invoice->tax,
                'total' => (float) $invoice->total,
                'currency' => $invoice->currency,
                'status' => $invoice->status,
                'notes' => $invoice->notes,
                'line_items' => $invoice->line_items ?? [],
                'issued_at' => $invoice->issued_at?->toDateTimeString(),
                'paid_at' => $invoice->paid_at?->toDateTimeString(),
                'created_at' => $invoice->created_at->toDateTimeString(),
                'payment_intent' => $invoice->paymentIntent ? [
                    'id' => $invoice->paymentIntent->id,
                    'reference_number' => $invoice->paymentIntent->reference_number,
                    'gateway' => $invoice->paymentIntent->gateway,
                    'status' => $invoice->paymentIntent->status,
                ] : null,
            ],
        ]);
    }

    public function download(Request $request, Invoice $invoice)
    {
        if (!auth()->user()->can('billing.view')) {
            abort(403);
        }

        $tenant = Tenant::getCurrent();

        if (!$tenant || $invoice->tenant_id !== $tenant->id) {
            abort(404);
        }

        $invoice->load(['plan', 'subscription', 'paymentIntent', 'tenant']);

        $html = view('pdf.invoice', ['invoice' => $invoice])->render();

        $filename = $invoice->invoice_number . '.html';

        return response($html, 200, [
            'Content-Type' => 'text/html',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function markPaid(Request $request, Invoice $invoice)
    {
        if (!auth()->user()->can('billing.manage')) {
            abort(403);
        }

        $tenant = Tenant::getCurrent();

        if (!$tenant || $invoice->tenant_id !== $tenant->id) {
            abort(404);
        }

        $invoice->markAsPaid();

        return redirect()->back()->with('success', 'Invoice marked as paid.');
    }

    public function markCancelled(Request $request, Invoice $invoice)
    {
        if (!auth()->user()->can('billing.manage')) {
            abort(403);
        }

        $tenant = Tenant::getCurrent();

        if (!$tenant || $invoice->tenant_id !== $tenant->id) {
            abort(404);
        }

        $invoice->markAsCancelled();

        return redirect()->back()->with('success', 'Invoice cancelled.');
    }
}
