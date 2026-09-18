{{ $app_name }} — {{ $store_name }}

Payment rejected — action required

Your payment for {{ $store_name }} could not be approved. Please submit a new payment from your billing page.

Plan: {{ $plan_name }} ({{ ucfirst($billing_cycle) }})
Amount: {{ $amount }}
Reference: {{ $reference_number }}
@if(!empty($payment_date))
Payment date: {{ $payment_date }}
@endif
@if(!empty($rejection_reason))
Reason: {{ $rejection_reason }}
@endif

Submit a new payment: {{ $billing_url }}
