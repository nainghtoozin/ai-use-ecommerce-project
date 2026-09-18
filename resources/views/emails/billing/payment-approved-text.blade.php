{{ $app_name }} — {{ $store_name }}

Payment approved

Good news — your payment for {{ $store_name }} has been approved and your subscription is now active.

Plan: {{ $plan_name }} ({{ ucfirst($billing_cycle) }})
Amount: {{ $amount }}
Reference: {{ $reference_number }}
Status: {{ $approval_status }}
@if(!empty($invoice_number))
Invoice: {{ $invoice_number }}
@endif
@if(!empty($receipt_number))
Receipt: {{ $receipt_number }}
@endif
@if(!empty($subscription_period))
Subscription period: {{ $subscription_period }}
@endif

@if(!empty($receipt_url))
View receipt: {{ $receipt_url }}
@endif
@if(!empty($invoice_url))
View invoice: {{ $invoice_url }}
@endif
