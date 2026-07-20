<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; font-size: 12px; color: #1f2937; line-height: 1.5; padding: 40px; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 32px; padding-bottom: 24px; border-bottom: 2px solid #e5e7eb; }
        .header-left h1 { font-size: 28px; font-weight: 700; color: #111827; margin-bottom: 4px; }
        .header-left .subtitle { font-size: 13px; color: #6b7280; }
        .header-right { text-align: right; }
        .header-right .badge { display: inline-block; padding: 4px 14px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .badge-paid { background: #d1fae5; color: #065f46; }
        .badge-unpaid { background: #fef3c7; color: #92400e; }
        .badge-draft { background: #f3f4f6; color: #374151; }
        .badge-cancelled { background: #fee2e2; color: #991b1b; }
        .badge-refunded { background: #f3e8ff; color: #6b21a8; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 32px; }
        .info-block { background: #f9fafb; border-radius: 8px; padding: 16px; }
        .info-block h3 { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: #6b7280; margin-bottom: 8px; }
        .info-block p { font-size: 13px; color: #1f2937; }
        .info-block .label { font-size: 11px; color: #9ca3af; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        th { text-align: left; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: #6b7280; padding: 10px 12px; border-bottom: 2px solid #e5e7eb; }
        td { padding: 10px 12px; border-bottom: 1px solid #e5e7eb; font-size: 13px; }
        .text-right { text-align: right; }
        .totals { margin-left: auto; width: 280px; }
        .totals table { margin-bottom: 0; }
        .totals td { border: none; padding: 6px 12px; font-size: 13px; }
        .totals .total-row td { font-weight: 700; font-size: 15px; border-top: 2px solid #1f2937; padding-top: 10px; }
        .footer { margin-top: 48px; padding-top: 16px; border-top: 1px solid #e5e7eb; text-align: center; font-size: 11px; color: #9ca3af; }
        .notes { margin-top: 24px; padding: 16px; background: #f9fafb; border-radius: 8px; }
        .notes h3 { font-size: 11px; font-weight: 600; text-transform: uppercase; color: #6b7280; margin-bottom: 6px; }
        .notes p { font-size: 13px; color: #4b5563; }
        @media print {
            body { padding: 20px; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <h1>{{ $invoice->invoice_number }}</h1>
            <p class="subtitle">{{ $invoice->plan?->name ?? 'Subscription' }} Invoice</p>
        </div>
        <div class="header-right">
            <span class="badge badge-{{ $invoice->status }}">
                {{ ucfirst($invoice->status) }}
            </span>
        </div>
    </div>

    <div class="info-grid">
        <div class="info-block">
            <h3>Bill To</h3>
            <p><strong>{{ $invoice->tenant?->name ?? $invoice->tenant?->business_name ?? 'N/A' }}</strong></p>
            <p class="label">{{ $invoice->tenant?->email ?? '' }}</p>
            <p class="label">{{ $invoice->tenant?->phone ?? '' }}</p>
        </div>
        <div class="info-block">
            <h3>Invoice Details</h3>
            <p><strong>Issued:</strong> {{ $invoice->issued_at?->format('M d, Y') ?? 'N/A' }}</p>
            <p><strong>Period:</strong> {{ $invoice->billing_period_start?->format('M d, Y') ?? 'N/A' }} — {{ $invoice->billing_period_end?->format('M d, Y') ?? 'N/A' }}</p>
            <p><strong>Billing Cycle:</strong> {{ ucfirst($invoice->billing_interval ?? 'monthly') }}</p>
            <p><strong>Due Date:</strong> {{ $invoice->billing_period_end?->format('M d, Y') ?? 'N/A' }}</p>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Unit Price</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @php $lineItems = $invoice->line_items ?: [['description' => ($invoice->plan?->name ?? 'Subscription Plan'), 'quantity' => 1, 'unit_price' => (float) $invoice->amount, 'amount' => (float) $invoice->amount]]; @endphp
            @forelse ($lineItems as $item)
                <tr>
                    <td>{{ $item['description'] ?? 'Subscription' }}</td>
                    <td class="text-right">{{ $item['quantity'] ?? 1 }}</td>
                    <td class="text-right">{{ number_format($item['unit_price'] ?? 0, 2) }} {{ $invoice->currency }}</td>
                    <td class="text-right">{{ number_format($item['amount'] ?? 0, 2) }} {{ $invoice->currency }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align:center;color:#9ca3af;">No line items</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="totals">
        <table>
            <tr>
                <td>Subtotal</td>
                <td class="text-right">{{ number_format((float) ($invoice->subtotal ?? $invoice->amount), 2) }} {{ $invoice->currency }}</td>
            </tr>
            @if ((float) ($invoice->tax ?? 0) > 0)
            <tr>
                <td>Tax</td>
                <td class="text-right">{{ number_format((float) $invoice->tax, 2) }} {{ $invoice->currency }}</td>
            </tr>
            @endif
            <tr class="total-row">
                <td>Total</td>
                <td class="text-right"><strong>{{ number_format((float) ($invoice->total ?? $invoice->amount), 2) }} {{ $invoice->currency }}</strong></td>
            </tr>
        </table>
    </div>

    @if ($invoice->notes)
    <div class="notes">
        <h3>Notes</h3>
        <p>{{ $invoice->notes }}</p>
    </div>
    @endif

    <div class="footer">
        <p>{{ config('app.name') }} — Invoice {{ $invoice->invoice_number }}</p>
    </div>
</body>
</html>
