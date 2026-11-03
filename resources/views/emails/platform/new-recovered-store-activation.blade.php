@extends('emails.layout')

@section('content')
<h1 style="margin:0 0 8px;font-size:20px;color:#0f172a;">Action Required: New Recovered Store Awaiting Activation</h1>
<p style="margin:0 0 16px;font-size:14px;color:#475569;">A merchant has successfully restored a backup into a new store. The new store remains inactive until its subscription and eligibility are reviewed. Please review the store and complete the appropriate activation process.</p>
<table style="width:100%;border-collapse:collapse;font-size:14px;color:#334155;">
<tr><td style="padding:6px 0;color:#64748b;">Store name</td><td style="padding:6px 0;text-align:right;font-weight:bold;">{{ $store_name }}</td></tr>
<tr><td style="padding:6px 0;color:#64748b;">Store slug</td><td style="padding:6px 0;text-align:right;font-family:monospace;">{{ $store_slug }}</td></tr>
@if(!empty($merchant_name))
<tr><td style="padding:6px 0;color:#64748b;">Merchant</td><td style="padding:6px 0;text-align:right;">{{ $merchant_name }}</td></tr>
@endif
@if(!empty($merchant_email))
<tr><td style="padding:6px 0;color:#64748b;">Merchant email</td><td style="padding:6px 0;text-align:right;">{{ $merchant_email }}</td></tr>
@endif
<tr><td style="padding:6px 0;color:#64748b;">Assigned plan</td><td style="padding:6px 0;text-align:right;font-weight:bold;">{{ $plan_name }}</td></tr>
<tr><td style="padding:6px 0;color:#64748b;">Tenant status</td><td style="padding:6px 0;text-align:right;">{{ $tenant_status }}</td></tr>
<tr><td style="padding:6px 0;color:#64748b;">Subscription status</td><td style="padding:6px 0;text-align:right;">{{ $subscription_status }}</td></tr>
<tr><td style="padding:6px 0;color:#64748b;">Recovery date/time</td><td style="padding:6px 0;text-align:right;">{{ $recovery_date }}</td></tr>
</table>
<p style="margin:16px 0 0;"><a href="{{ $review_url }}" style="display:inline-block;padding:10px 20px;background-color:#2563eb;color:#ffffff;text-decoration:none;border-radius:8px;font-size:14px;font-weight:bold;">Review Store</a></p>
@endsection
