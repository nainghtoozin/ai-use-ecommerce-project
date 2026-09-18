@extends('emails.layout')

@section('content')
<h1 style="margin:0 0 8px;font-size:20px;color:#0f172a;">Payment received — awaiting review</h1>
<p style="margin:0 0 16px;font-size:14px;color:#475569;">Hi, we received your payment for <strong>{{ $store_name }}</strong>. It is now pending review by our team.</p>
<table style="width:100%;border-collapse:collapse;font-size:14px;color:#334155;">
<tr><td style="padding:6px 0;color:#64748b;">Plan</td><td style="padding:6px 0;text-align:right;font-weight:bold;">{{ $plan_name }} ({{ ucfirst($billing_cycle) }})</td></tr>
<tr><td style="padding:6px 0;color:#64748b;">Amount</td><td style="padding:6px 0;text-align:right;font-weight:bold;">{{ $amount }}</td></tr>
<tr><td style="padding:6px 0;color:#64748b;">Reference</td><td style="padding:6px 0;text-align:right;font-family:monospace;">{{ $reference_number }}</td></tr>
<tr><td style="padding:6px 0;color:#64748b;">Status</td><td style="padding:6px 0;text-align:right;font-weight:bold;color:#b45309;">Pending review</td></tr>
</table>
<p style="margin:16px 0 0;font-size:13px;color:#64748b;">We will notify you as soon as your payment is approved. Your subscription activates automatically after approval.</p>
<p style="margin:8px 0 0;font-size:13px;color:#64748b;">Keep your payment reference <span style="font-family:monospace;">{{ $reference_number }}</span> for your records.</p>
@endsection
