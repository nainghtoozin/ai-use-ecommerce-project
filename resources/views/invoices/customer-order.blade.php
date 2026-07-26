<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $order->invoice_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif; color: #1a1a1a; line-height: 1.6; background: #fff; }
        .invoice { max-width: 800px; margin: 0 auto; padding: 48px; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; padding-bottom: 24px; border-bottom: 3px solid #2563eb; }
        .header-left h1 { font-size: 28px; font-weight: 800; color: #111; letter-spacing: -0.5px; }
        .header-left p { color: #6b7280; font-size: 14px; margin-top: 4px; }
        .header-right { text-align: right; }
        .header-right .invoice-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: #6b7280; }
        .header-right .invoice-number { font-size: 22px; font-weight: 800; color: #2563eb; margin-top: 4px; font-family: 'SF Mono', 'Fira Code', 'Consolas', monospace; }
        .header-right .date { color: #6b7280; font-size: 13px; margin-top: 6px; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 32px; margin-bottom: 32px; }
        .info-box { background: #f9fafb; border-radius: 12px; padding: 20px; border: 1px solid #f3f4f6; }
        .section-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #6b7280; margin-bottom: 10px; }
        .info-box p { font-size: 14px; color: #374151; }
        .info-box .name { font-weight: 700; font-size: 16px; color: #111; margin-bottom: 4px; }
        .status-section { display: flex; gap: 12px; margin-bottom: 32px; }
        .status-badge { display: inline-block; padding: 6px 16px; border-radius: 9999px; font-size: 13px; font-weight: 600; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-confirmed { background: #dbeafe; color: #1e40af; }
        .status-processing { background: #e0e7ff; color: #3730a3; }
        .status-shipped { background: #dbeafe; color: #1e40af; }
        .status-delivered { background: #d1fae5; color: #065f46; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        .status-paid { background: #dbeafe; color: #1e40af; }
        .status-unpaid { background: #fef3c7; color: #92400e; }
        .status-verified { background: #d1fae5; color: #065f46; }
        .status-rejected { background: #fee2e2; color: #991b1b; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 0; }
        thead th { background: #111; color: #fff; padding: 12px 16px; text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; }
        thead th:first-child { border-radius: 8px 0 0 0; }
        thead th:last-child { border-radius: 0 8px 0 0; text-align: right; }
        tbody td { padding: 14px 16px; font-size: 14px; border-bottom: 1px solid #f3f4f6; }
        tbody td:last-child { text-align: right; font-weight: 600; }
        tbody tr:last-child td { border-bottom: 2px solid #e5e7eb; }
        .summary { margin-top: 0; }
        .summary-row { display: flex; justify-content: space-between; padding: 8px 16px; font-size: 14px; }
        .summary-row.total { font-weight: 800; font-size: 18px; border-top: 2px solid #111; margin-top: 8px; padding-top: 12px; }
        .summary-row.discount { color: #059669; }
        .payment-section { background: #f9fafb; border-radius: 12px; padding: 24px; margin-top: 32px; border: 1px solid #f3f4f6; }
        .payment-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .payment-item label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #6b7280; display: block; margin-bottom: 4px; }
        .payment-item p { font-size: 14px; font-weight: 600; color: #111; }
        .payment-item .mono { font-family: 'SF Mono', 'Fira Code', 'Consolas', monospace; }
        .footer { margin-top: 48px; padding-top: 24px; border-top: 2px solid #e5e7eb; text-align: center; }
        .footer p { color: #9ca3af; font-size: 13px; }
        .footer .thankyou { font-size: 16px; font-weight: 700; color: #374151; margin-bottom: 4px; }
        .no-print { display: none; }
        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .invoice { padding: 24px; }
            .no-print { display: none !important; }
        }
        .print-bar { position: fixed; top: 0; left: 0; right: 0; background: #111; color: #fff; padding: 12px 24px; display: flex; justify-content: space-between; align-items: center; z-index: 1000; font-size: 14px; }
        .print-bar button { background: #2563eb; color: #fff; border: none; padding: 8px 20px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; margin-left: 8px; }
        .print-bar button:hover { background: #1d4ed8; }
        .print-bar .secondary { background: #374151; }
        .print-bar .secondary:hover { background: #4b5563; }
        .content-wrapper { margin-top: 56px; }
        @media print { .print-bar { display: none !important; } .content-wrapper { margin-top: 0; } }
    </style>
</head>
<body>
    <div class="print-bar no-print">
        <span>Invoice {{ $order->invoice_number }}</span>
        <div>
            <button class="secondary" onclick="window.close()">Close</button>
            <button onclick="window.print()">Print / Save PDF</button>
        </div>
    </div>

    <div class="content-wrapper">
    <div class="invoice">
        <div class="header">
            <div class="header-left">
                <h1>{{ $tenant->name }}</h1>
                <p>Order Invoice</p>
            </div>
            <div class="header-right">
                <div class="invoice-label">Invoice Number</div>
                <div class="invoice-number">{{ $order->invoice_number }}</div>
                <div class="date">{{ $order->created_at->format('M d, Y \a\t g:i A') }}</div>
            </div>
        </div>

        <div class="info-grid">
            <div class="info-box">
                <div class="section-title">Bill To</div>
                <p class="name">{{ $order->first_name }} {{ $order->last_name }}</p>
                <p>{{ $order->phone }}</p>
                @if($order->email)<p>{{ $order->email }}</p>@endif
            </div>
            <div class="info-box">
                <div class="section-title">Delivery Address</div>
                <p>{{ $order->address }}</p>
                @if($order->city)<p>{{ $order->city->name }}@if($order->township), {{ $order->township->name }}@endif</p>@endif
                @if($order->postal_code)<p>Postal Code: {{ $order->postal_code }}</p>@endif
            </div>
        </div>

        <div class="status-section">
            <div>
                <div class="section-title">Order Status</div>
                <span class="status-badge status-{{ $order->order_status }}">{{ ucfirst($order->order_status) }}</span>
            </div>
            <div>
                <div class="section-title">Payment Status</div>
                <span class="status-badge status-{{ $order->payment_status }}">{{ ucfirst($order->payment_status) }}</span>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th style="text-align:right">Price</th>
                    <th style="text-align:right">Qty</th>
                    <th style="text-align:right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                <tr>
                    <td>{{ $item->product->name ?? 'Product #' . $item->product_id }}</td>
                    <td style="text-align:right">{{ number_format($item->price, 2) }}</td>
                    <td style="text-align:right">{{ $item->quantity }}</td>
                    <td style="text-align:right">{{ number_format($item->price * $item->quantity, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="summary">
            <div class="summary-row">
                <span>Subtotal</span>
                <span>{{ number_format($order->subtotal ?? $order->items_total, 2) }}</span>
            </div>
            <div class="summary-row">
                <span>Delivery Fee</span>
                <span>{{ number_format($order->delivery_fee ?? 0, 2) }}</span>
            </div>
            @if($order->discount_amount > 0)
            <div class="summary-row discount">
                <span>Discount</span>
                <span>-{{ number_format($order->discount_amount, 2) }}</span>
            </div>
            @endif
            <div class="summary-row total">
                <span>Grand Total</span>
                <span>{{ number_format($order->total_amount, 2) }}</span>
            </div>
        </div>

        <div class="payment-section">
            <div class="section-title" style="margin-bottom:16px">Payment Information</div>
            <div class="payment-grid">
                <div class="payment-item">
                    <label>Payment Method</label>
                    <p>{{ $order->paymentMethod->name ?? $order->payment_method->name ?? 'N/A' }}</p>
                </div>
                <div class="payment-item">
                    <label>Payment Status</label>
                    <p>{{ ucfirst($order->payment_status) }}</p>
                </div>
                @if($order->payer_name)
                <div class="payment-item">
                    <label>Sender Name</label>
                    <p>{{ $order->payer_name }}</p>
                </div>
                @endif
                @if($order->sender_account_number)
                <div class="payment-item">
                    <label>Sender Account Number</label>
                    <p class="mono">{{ $order->sender_account_number }}</p>
                </div>
                @endif
                @if($order->transaction_id)
                <div class="payment-item">
                    <label>Transaction ID</label>
                    <p class="mono">{{ $order->transaction_id }}</p>
                </div>
                @endif
                @if($order->paid_amount)
                <div class="payment-item">
                    <label>Paid Amount</label>
                    <p>{{ number_format($order->paid_amount, 2) }}</p>
                </div>
                @endif
            </div>
        </div>

        <div class="footer">
            <p class="thankyou">Thank you for shopping with {{ $tenant->name }}!</p>
            <p>If you have any questions about this invoice, please contact us.</p>
        </div>
    </div>
    </div>
</body>
</html>
