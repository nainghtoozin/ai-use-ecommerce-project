<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Invoice {{ $invoice->invoice_number }}</title>
<style>
  * { box-sizing: border-box; }
  body { margin: 0; padding: 24px 16px; background: #f1f5f9; font-family: Arial, Helvetica, sans-serif; color: #0f172a; }
  .sheet { max-width: 720px; margin: 0 auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
  .head { padding: 24px; border-bottom: 2px solid #0f172a; display: flex; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
  .title { font-size: 22px; font-weight: bold; margin: 0; }
  .number { font-size: 13px; color: #64748b; margin-top: 4px; }
  .badge { display: inline-block; padding: 3px 12px; border-radius: 999px; font-size: 12px; font-weight: bold; text-transform: uppercase; }
  .badge-paid { background: #dcfce7; color: #15803d; }
  .badge-unpaid { background: #fef3c7; color: #b45309; }
  .badge-other { background: #f1f5f9; color: #475569; }
  .body { padding: 24px; }
  .grid { display: flex; gap: 24px; flex-wrap: wrap; margin-bottom: 20px; }
  .col h3 { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; margin: 0 0 6px; }
  .col p { font-size: 14px; margin: 2px 0; }
  table { width: 100%; border-collapse: collapse; font-size: 14px; margin-top: 8px; }
  th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; padding: 8px 0; border-bottom: 1px solid #e2e8f0; }
  td { padding: 10px 0; border-bottom: 1px solid #f1f5f9; }
  .right { text-align: right; }
  .totals { margin-top: 12px; margin-left: auto; max-width: 260px; font-size: 14px; }
  .totals .row { display: flex; justify-content: space-between; padding: 4px 0; }
  .totals .grand { font-weight: bold; font-size: 16px; border-top: 2px solid #0f172a; margin-top: 6px; padding-top: 8px; }
  .actions { display: flex; gap: 8px; padding: 16px 24px; border-top: 1px solid #e2e8f0; background: #f8fafc; flex-wrap: wrap; }
  .btn { display: inline-block; padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: bold; text-decoration: none; }
  .btn-primary { background: #2563eb; color: #fff; }
  .btn-secondary { background: #fff; color: #334155; border: 1px solid #cbd5e1; }
  .foot { padding: 16px 24px; font-size: 11px; color: #94a3b8; }
  @media print {
    body { background: #fff; padding: 0; }
    .sheet { border: none; border-radius: 0; max-width: none; }
    .actions { display: none; }
  }
  @media (max-width: 560px) { .head, .body { padding: 16px; } }
</style>
</head>
<body>
<div class="sheet">
  <div class="head">
    <div>
      <p class="title">Invoice</p>
      <p class="number">{{ $invoice->invoice_number }}</p>
    </div>
    <div style="text-align:right;">
      <span class="badge {{ $invoice->status === 'paid' ? 'badge-paid' : ($invoice->status === 'unpaid' ? 'badge-unpaid' : 'badge-other') }}">{{ $invoice->status }}</span>
      <p class="number">Issued {{ $invoice->issued_at?->format('M d, Y') ?? 'N/A' }}</p>
    </div>
  </div>
  <div class="body">
    <div class="grid">
      <div class="col">
        <h3>Billed To</h3>
        <p><strong>{{ $tenant->name }}</strong></p>
        @if($tenant->email)<p>{{ $tenant->email }}</p>@endif
      </div>
      <div class="col">
        <h3>Subscription</h3>
        <p><strong>{{ $invoice->plan?->name ?? 'Subscription' }}</strong> ({{ ucfirst($invoice->billing_interval ?? 'monthly') }})</p>
        <p>Billing period: {{ $invoice->billing_period_start?->format('M d, Y') ?? 'N/A' }} → {{ $invoice->billing_period_end?->format('M d, Y') ?? 'N/A' }}</p>
        @if($invoice->paymentIntent)<p>Payment ref: <span style="font-family:monospace;">{{ $invoice->paymentIntent->reference_number }}</span></p>@endif
      </div>
    </div>
    <table>
      <thead><tr><th>Description</th><th class="right">Amount</th></tr></thead>
      <tbody>
        @forelse($invoice->line_items ?? [] as $item)
        <tr><td>{{ $item['description'] ?? 'Subscription' }}</td><td class="right">{{ number_format((float) ($item['amount'] ?? 0), 2) }} {{ $invoice->currency }}</td></tr>
        @empty
        <tr><td>{{ $invoice->plan?->name ?? 'Subscription' }} ({{ ucfirst($invoice->billing_interval ?? 'monthly') }})</td><td class="right">{{ number_format((float) $invoice->amount, 2) }} {{ $invoice->currency }}</td></tr>
        @endforelse
      </tbody>
    </table>
    <div class="totals">
      <div class="row"><span>Subtotal</span><span>{{ number_format((float) $invoice->subtotal, 2) }} {{ $invoice->currency }}</span></div>
      <div class="row"><span>Tax</span><span>{{ number_format((float) $invoice->tax, 2) }} {{ $invoice->currency }}</span></div>
      <div class="row grand"><span>Total</span><span>{{ number_format((float) $invoice->total, 2) }} {{ $invoice->currency }}</span></div>
      @if($invoice->paid_at)<div class="row"><span>Paid on</span><span>{{ $invoice->paid_at->format('M d, Y') }}</span></div>@endif
    </div>
  </div>
  <div class="actions">
    <a class="btn btn-primary" href="{{ $downloadUrl }}">Download PDF</a>
    <button class="btn btn-secondary" type="button" onclick="window.print()">Print</button>
  </div>
  <div class="foot">{{ config('app.name') }} · Subscription billing document</div>
</div>
</body>
</html>
