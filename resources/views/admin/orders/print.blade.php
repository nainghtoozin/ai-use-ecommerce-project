<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Orders Report — {{ $storeName }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 24px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #111827; font-size: 12px; }
        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .toolbar button { padding: 8px 16px; font-size: 13px; font-weight: 600; color: #fff; background: #2563eb; border: none; border-radius: 8px; cursor: pointer; }
        h1 { margin: 0 0 4px; font-size: 20px; }
        .meta { color: #6b7280; margin-bottom: 4px; }
        .filters { color: #374151; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        tfoot td { font-weight: 700; }
        .note { margin-top: 12px; color: #6b7280; }
        @media print {
            body { padding: 0; }
            .toolbar { display: none; }
            th { background: #f3f4f6 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <span>{{ $orders->count() }} order(s){{ $truncated ? ' (first 1000 shown)' : '' }}</span>
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
    </div>

    <h1>Orders Report — {{ $storeName }}</h1>
    <p class="meta">Generated {{ $generatedAt }}</p>
    @if(!empty($filters))
        <p class="filters">
            Filters:
            @foreach($filters as $key => $value)
                <strong>{{ str_replace('_', ' ', $key) }}:</strong> {{ $value }}{{ !$loop->last ? ' · ' : '' }}
            @endforeach
        </p>
    @endif

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Date</th>
                <th>Customer</th>
                <th>Phone</th>
                <th>Status</th>
                <th>Payment</th>
                <th class="num">Items</th>
                <th class="num">Total ({{ $currencySymbol }})</th>
            </tr>
        </thead>
        <tbody>
            @forelse($orders as $order)
                <tr>
                    <td>{{ $order->invoice_number ?? $order->id }}</td>
                    <td>{{ $order->created_at?->format('Y-m-d H:i') }}</td>
                    <td>{{ $order->customer_name ?? trim(($order->first_name ?? '') . ' ' . ($order->last_name ?? '')) }}</td>
                    <td>{{ $order->phone }}</td>
                    <td>{{ ucfirst($order->order_status) }}</td>
                    <td>{{ ucfirst($order->payment_status) }}</td>
                    <td class="num">{{ $order->items?->sum('quantity') ?? 0 }}</td>
                    <td class="num">{{ number_format((float) $order->total_amount, 0) }}</td>
                </tr>
            @empty
                <tr><td colspan="8">No orders found for the selected filters.</td></tr>
            @endforelse
        </tbody>
        @if($orders->count())
            <tfoot>
                <tr>
                    <td colspan="7">Total ({{ $orders->count() }} order(s))</td>
                    <td class="num">{{ number_format((float) $orders->sum('total_amount'), 0) }} {{ $currencySymbol }}</td>
                </tr>
            </tfoot>
        @endif
    </table>

    @if($autoprint)
        <script>window.addEventListener('load', function () { window.print(); });</script>
    @endif
</body>
</html>
