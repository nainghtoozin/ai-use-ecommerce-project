{{ $app_name }} - Action Required: New Recovered Store Awaiting Activation

A merchant has successfully restored a backup into a new store. The new store remains inactive until its subscription and eligibility are reviewed. Please review the store and complete the appropriate activation process.

Store name: {{ $store_name }}
Store slug: {{ $store_slug }}
@if(!empty($merchant_name))
Merchant: {{ $merchant_name }}
@endif
@if(!empty($merchant_email))
Merchant email: {{ $merchant_email }}
@endif
Assigned plan: {{ $plan_name }}
Tenant status: {{ $tenant_status }}
Subscription status: {{ $subscription_status }}
Recovery date/time: {{ $recovery_date }}

Review store: {{ $review_url }}
