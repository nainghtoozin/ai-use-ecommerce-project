{{ $app_name }} — {{ $store_name }}

Thank you for your subscription

Your payment for {{ $store_name }} was received and approved.

Plan: {{ $plan_name }} ({{ ucfirst($billing_cycle) }})
Amount paid: {{ $receipt_total }}
Payment reference: {{ $reference_number }}
Receipt: {{ $receipt_number }}
@if(!empty($paid_at))
Date: {{ $paid_at }}
@endif
@if(!empty($subscription_period))
Subscription period: {{ $subscription_period }}
@endif
@if(!empty($receipt_url))

View receipt: {{ $receipt_url }}
@endif
@if(!empty($receipt_download_url))
Download receipt: {{ $receipt_download_url }}
@endif
