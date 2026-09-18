@extends('emails.layout')

@section('content')
<h1 style="margin:0 0 8px;font-size:20px;color:#0f172a;">Payment approved</h1>
<p style="margin:0 0 16px;font-size:14px;color:#475569;">Good news — your payment for <strong>{{ $store_name }}</strong> has been approved and your subscription is now active.</p>
<table style="width:100%;border-collapse:collapse;font-size:14px;color:#334155;">
<tr><td style="padding:6px 0;color:#64748b;">Plan</td><td style="padding:6px 0;text-align:right;font-weight:bold;">{{ $plan_name }} ({{ ucfirst($billing_cycle) }})</td></tr>
<tr><td style="padding:6px 0;color:#64748b;">Amount</td><td style="padding:6px 0;text-align:right;font-weight:bold;">{{ $amount }}</td></tr>
<tr><td style="padding:6px 0;color:#64748b;">Reference</td><td style="padding:6px 0;text-align:right;font-family:monospace;">{{ $reference_number }}</td></tr>
<tr><td style="padding:6px 0;color:#64748b;">Status</td><td style="padding:6px 0;text-align:right;font-weight:bold;color:#15803d;">{{ $approval_status }}</td></tr>
@if(!empty($invoice_number))
<tr><td style="padding:6px 0;color:#64748b;">Invoice</td><td style="padding:6px 0;text-align:right;font-family:monospace;">{{ $invoice_number }}</td></tr>
@endif
@if(!empty($receipt_number))
<tr><td style="padding:6px 0;color:#64748b;">Receipt</td><td style="padding:6px 0;text-align:right;font-family:monospace;">{{ $receipt_number }}</td></tr>
@endif
@if(!empty($subscription_period))
<tr><td style="padding:6px 0;color:#64748b;">Subscription period</td><td style="padding:6px 0;text-align:right;">{{ $subscription_period }}</td></tr>
@endif
</table>
<p style="margin:16px 0 0;">@if(!empty($receipt_url))<a href="{{ $receipt_url }}" style="display:inline-block;padding:10px 20px;background-color:#2563eb;color:#ffffff;text-decoration:none;border-radius:8px;font-size:14px;font-weight:bold;">View Receipt</a> @endif@if(!empty($invoice_url))<a href="{{ $invoice_url }}" style="display:inline-block;padding:10px 20px;border:1px solid #cbd5e1;color:#334155;text-decoration:none;border-radius:8px;font-size:14px;font-weight:bold;">View Invoice</a>@endif</p>
@endsection
