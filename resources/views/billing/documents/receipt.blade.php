<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Receipt {{ $receipt->receipt_number }}</title>
<style>
  * { box-sizing: border-box; }
  body { margin: 0; padding: 24px 16px; background: #f1f5f9; font-family: Arial, Helvetica, sans-serif; color: #0f172a; }
  .sheet { max-width: 720px; margin: 0 auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
  .head { padding: 24px; border-bottom: 2px solid #15803d; display: flex; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
  .title { font-size: 22px; font-weight: bold; margin: 0; }
  .number { font-size: 13px; color: #64748b; margin-top: 4px; }
  .badge { display: inline-block; padding: 3px 12px; border-radius: 999px; font-size: 12px; font-weight: bold; text-transform: uppercase; background: #dcfce7; color: #15803d; }
  .body { padding: 24px; }
  .thanks { font-size: 15px; margin: 0 0 16px; }
  table { width: 100%; border-collapse: collapse; font-size: 14px; }
  td { padding: 8px 0; border-bottom: 1px solid #f1f5f9; }
  td:first-child { color: #64748b; }
  td:last-child { text-align: right; font-weight: bold; }
  .amount { font-size: 26px; font-weight: bold; color: #15803d; margin: 16px 0 4px; }
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
      <p class="title">Payment Receipt</p>
      <p class="number">{{ $receipt->receipt_number }}</p>
    </div>
    <div style="text-align:right;">
      <span class="badge">Paid</span>
      <p class="number">{{ $receipt->paid_at?->format('M d, Y') ?? 'N/A' }}</p>
    </div>
  </div>
  <div class="body">
    <p class="thanks">Thank you for your subscription, <strong>{{ $tenant->name }}</strong>.</p>
    <p class="amount">{{ number_format((float) $receipt->amount, 2) }} {{ $receipt->currency }}</p>
    <table>
      <tr><td>Plan</td><td>{{ $receipt->invoice?->plan?->name ?? $receipt->details['plan_name'] ?? 'Subscription' }} ({{ ucfirst($receipt->invoice?->billing_interval ?? $receipt->details['billing_cycle'] ?? 'monthly') }})</td></tr>
      <tr><td>Payment reference</td><td style="font-family:monospace;">{{ $receipt->paymentIntent?->reference_number ?? 'N/A' }}</td></tr>
      @if($receipt->invoice)<tr><td>Invoice</td><td style="font-family:monospace;">{{ $receipt->invoice->invoice_number }}</td></tr>@endif
      @if($receipt->invoice?->billing_period_start)<tr><td>Subscription period</td><td>{{ $receipt->invoice->billing_period_start->format('M d, Y') }} → {{ $receipt->invoice->billing_period_end?->format('M d, Y') }}</td></tr>@endif
      <tr><td>Received from</td><td>{{ $tenant->name }}@if($tenant->email) ({{ $tenant->email }})@endif</td></tr>
    </table>
  </div>
  <div class="actions">
    <a class="btn btn-primary" href="{{ $downloadUrl }}">Download PDF</a>
    <button class="btn btn-secondary" type="button" onclick="window.print()">Print</button>
  </div>
  <div class="foot">{{ config('app.name') }} · This receipt confirms that the subscription payment was received and approved.</div>
</div>
</body>
</html>
