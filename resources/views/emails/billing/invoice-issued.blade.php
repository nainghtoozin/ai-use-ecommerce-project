@extends('emails.layout')

@section('content')
<h1 style="margin:0 0 8px;font-size:20px;color:#0f172a;">Invoice {{ $invoice_number }}</h1>
<p style="margin:0 0 16px;font-size:14px;color:#475569;">Here are the details of your invoice for <strong>{{ $store_name }}</strong>.</p>
<table style="width:100%;border-collapse:collapse;font-size:14px;color:#334155;">
<tr><td style="padding:6px 0;color:#64748b;">Invoice</td><td style="padding:6px 0;text-align:right;font-family:monospace;font-weight:bold;">{{ $invoice_number }}</td></tr>
<tr><td style="padding:6px 0;color:#64748b;">Plan</td><td style="padding:6px 0;text-align:right;font-weight:bold;">{{ $plan_name }} ({{ ucfirst($billing_cycle) }})</td></tr>
<tr><td style="padding:6px 0;color:#64748b;">Amount</td><td style="padding:6px 0;text-align:right;font-weight:bold;">{{ $invoice_total }}</td></tr>
@if(!empty($billing_period))
<tr><td style="padding:6px 0;color:#64748b;">Billing period</td><td style="padding:6px 0;text-align:right;">{{ $billing_period }}</td></tr>
@endif
@if(!empty($issued_at))
<tr><td style="padding:6px 0;color:#64748b;">Issued</td><td style="padding:6px 0;text-align:right;">{{ $issued_at }}</td></tr>
@endif
<tr><td style="padding:6px 0;color:#64748b;">Status</td><td style="padding:6px 0;text-align:right;font-weight:bold;">{{ ucfirst($invoice_status) }}</td></tr>
</table>
<p style="margin:16px 0 0;">
@if(!empty($invoice_url))
<a href="{{ $invoice_url }}" style="display:inline-block;padding:10px 20px;background-color:#2563eb;color:#ffffff;text-decoration:none;border-radius:8px;font-size:14px;font-weight:bold;">View Invoice</a>
@endif
@if(!empty($invoice_download_url))
<a href="{{ $invoice_download_url }}" style="display:inline-block;padding:10px 20px;border:1px solid #cbd5e1;color:#334155;text-decoration:none;border-radius:8px;font-size:14px;font-weight:bold;">Download Invoice</a>
@endif
</p>
@endsection
