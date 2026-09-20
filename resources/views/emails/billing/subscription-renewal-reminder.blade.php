@extends('emails.layout')

@section('content')
<h1 style="margin:0 0 8px;font-size:20px;color:#0f172a;">Your subscription renews soon</h1>
<p style="margin:0 0 16px;font-size:14px;color:#475569;">Hi, the subscription for <strong>{{ $store_name }}</strong> expires in <strong>{{ $days_remaining }} day(s)</strong>. Renew now to avoid interruption.</p>
<table style="width:100%;border-collapse:collapse;font-size:14px;color:#334155;">
<tr><td style="padding:6px 0;color:#64748b;">Plan</td><td style="padding:6px 0;text-align:right;font-weight:bold;">{{ $plan_name }} ({{ ucfirst($billing_cycle) }})</td></tr>
<tr><td style="padding:6px 0;color:#64748b;">Expires on</td><td style="padding:6px 0;text-align:right;font-weight:bold;">{{ $expires_at }}</td></tr>
<tr><td style="padding:6px 0;color:#64748b;">Days remaining</td><td style="padding:6px 0;text-align:right;font-weight:bold;">{{ $days_remaining }}</td></tr>
<tr><td style="padding:6px 0;color:#64748b;">Renewal amount</td><td style="padding:6px 0;text-align:right;font-weight:bold;">{{ $renewal_amount }}</td></tr>
<tr><td style="padding:6px 0;color:#64748b;">Subscription</td><td style="padding:6px 0;text-align:right;font-family:monospace;">{{ $subscription_reference }}</td></tr>
</table>
<p style="margin:16px 0 0;"><a href="{{ $billing_url }}" style="display:inline-block;padding:10px 20px;background-color:#2563eb;color:#ffffff;text-decoration:none;border-radius:8px;font-size:14px;font-weight:bold;">Renew Subscription</a></p>
<p style="margin:8px 0 0;font-size:13px;color:#64748b;">This is a reminder only — no invoice has been issued. Your renewal invoice is generated after payment approval.</p>
@endsection
