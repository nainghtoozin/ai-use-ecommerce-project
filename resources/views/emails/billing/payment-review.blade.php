@extends('emails.layout')

@section('content')
<h1 style="margin:0 0 8px;font-size:20px;color:#0f172a;">New payment requires review</h1>
<p style="margin:0 0 16px;font-size:14px;color:#475569;">A merchant submitted a manual payment. Please review it in the billing console. Approval and rejection happen only on the review page.</p>
<table style="width:100%;border-collapse:collapse;font-size:14px;color:#334155;">
<tr><td style="padding:6px 0;color:#64748b;">Merchant</td><td style="padding:6px 0;text-align:right;font-weight:bold;">{{ $merchant_name }}</td></tr>
@if(!empty($merchant_email))
<tr><td style="padding:6px 0;color:#64748b;">Merchant email</td><td style="padding:6px 0;text-align:right;">{{ $merchant_email }}</td></tr>
@endif
<tr><td style="padding:6px 0;color:#64748b;">Plan</td><td style="padding:6px 0;text-align:right;font-weight:bold;">{{ $plan_name }} ({{ ucfirst($billing_cycle) }})</td></tr>
<tr><td style="padding:6px 0;color:#64748b;">Amount</td><td style="padding:6px 0;text-align:right;font-weight:bold;">{{ $amount }}</td></tr>
<tr><td style="padding:6px 0;color:#64748b;">Reference</td><td style="padding:6px 0;text-align:right;font-family:monospace;">{{ $reference_number }}</td></tr>
<tr><td style="padding:6px 0;color:#64748b;">Payment method</td><td style="padding:6px 0;text-align:right;">{{ $payment_method }}</td></tr>
@if(!empty($transfer_date))
<tr><td style="padding:6px 0;color:#64748b;">Payment date</td><td style="padding:6px 0;text-align:right;">{{ $transfer_date }}@if(!empty($transfer_time)) {{ $transfer_time }}@endif</td></tr>
@endif
@if(!empty($submitted_at))
<tr><td style="padding:6px 0;color:#64748b;">Submitted</td><td style="padding:6px 0;text-align:right;">{{ $submitted_at }}</td></tr>
@endif
<tr><td style="padding:6px 0;color:#64748b;">Evidence files</td><td style="padding:6px 0;text-align:right;">{{ $evidence_count }} uploaded — view in review console</td></tr>
</table>
<p style="margin:16px 0 0;"><a href="{{ $review_url }}" style="display:inline-block;padding:10px 20px;background-color:#2563eb;color:#ffffff;text-decoration:none;border-radius:8px;font-size:14px;font-weight:bold;">Review Payment</a></p>
@endsection
