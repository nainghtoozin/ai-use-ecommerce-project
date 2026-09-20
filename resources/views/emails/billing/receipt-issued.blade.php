@extends('emails.layout')

@section('content')
<h1 style="margin:0 0 8px;font-size:20px;color:#0f172a;">Thank you for your subscription</h1>
<p style="margin:0 0 16px;font-size:14px;color:#475569;">Your payment for <strong>{{ $store_name }}</strong> was received and approved.</p>
<table style="width:100%;border-collapse:collapse;font-size:14px;color:#334155;">
<tr><td style="padding:6px 0;color:#64748b;">Plan</td><td style="padding:6px 0;text-align:right;font-weight:bold;">{{ $plan_name }} ({{ ucfirst($billing_cycle) }})</td></tr>
<tr><td style="padding:6px 0;color:#64748b;">Amount paid</td><td style="padding:6px 0;text-align:right;font-weight:bold;">{{ $receipt_total }}</td></tr>
<tr><td style="padding:6px 0;color:#64748b;">Payment reference</td><td style="padding:6px 0;text-align:right;font-family:monospace;">{{ $reference_number }}</td></tr>
<tr><td style="padding:6px 0;color:#64748b;">Receipt</td><td style="padding:6px 0;text-align:right;font-family:monospace;">{{ $receipt_number }}</td></tr>
@if(!empty($paid_at))
<tr><td style="padding:6px 0;color:#64748b;">Date</td><td style="padding:6px 0;text-align:right;">{{ $paid_at }}</td></tr>
@endif
@if(!empty($subscription_period))
<tr><td style="padding:6px 0;color:#64748b;">Subscription period</td><td style="padding:6px 0;text-align:right;">{{ $subscription_period }}</td></tr>
@endif
</table>
<p style="margin:16px 0 0;">
@if(!empty($receipt_url))
<a href="{{ $receipt_url }}" style="display:inline-block;padding:10px 20px;background-color:#2563eb;color:#ffffff;text-decoration:none;border-radius:8px;font-size:14px;font-weight:bold;">View Receipt</a>
@endif
@if(!empty($receipt_download_url))
<a href="{{ $receipt_download_url }}" style="display:inline-block;padding:10px 20px;border:1px solid #cbd5e1;color:#334155;text-decoration:none;border-radius:8px;font-size:14px;font-weight:bold;">Download Receipt</a>
@endif
</p>
@endsection
