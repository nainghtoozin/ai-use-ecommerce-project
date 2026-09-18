@extends('emails.layout')

@section('content')
<h1 style="margin:0 0 8px;font-size:20px;color:#0f172a;">Payment rejected — action required</h1>
<p style="margin:0 0 16px;font-size:14px;color:#475569;">Your payment for <strong>{{ $store_name }}</strong> could not be approved. Please submit a new payment from your billing page.</p>
<table style="width:100%;border-collapse:collapse;font-size:14px;color:#334155;">
<tr><td style="padding:6px 0;color:#64748b;">Plan</td><td style="padding:6px 0;text-align:right;font-weight:bold;">{{ $plan_name }} ({{ ucfirst($billing_cycle) }})</td></tr>
<tr><td style="padding:6px 0;color:#64748b;">Amount</td><td style="padding:6px 0;text-align:right;font-weight:bold;">{{ $amount }}</td></tr>
<tr><td style="padding:6px 0;color:#64748b;">Reference</td><td style="padding:6px 0;text-align:right;font-family:monospace;">{{ $reference_number }}</td></tr>
@if(!empty($payment_date))
<tr><td style="padding:6px 0;color:#64748b;">Payment date</td><td style="padding:6px 0;text-align:right;">{{ $payment_date }}</td></tr>
@endif
@if(!empty($rejection_reason))
<tr><td style="padding:6px 0;color:#64748b;">Reason</td><td style="padding:6px 0;text-align:right;">{{ $rejection_reason }}</td></tr>
@endif
</table>
<p style="margin:16px 0 0;"><a href="{{ $billing_url }}" style="display:inline-block;padding:10px 20px;background-color:#2563eb;color:#ffffff;text-decoration:none;border-radius:8px;font-size:14px;font-weight:bold;">Submit a new payment</a></p>
@endsection
