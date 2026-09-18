{{ $app_name }} — new payment requires review

A merchant submitted a manual payment. Please review it in the billing console. Approval and rejection happen only on the review page.

Merchant: {{ $merchant_name }}
@if(!empty($merchant_email))
Merchant email: {{ $merchant_email }}
@endif
Plan: {{ $plan_name }} ({{ ucfirst($billing_cycle) }})
Amount: {{ $amount }}
Reference: {{ $reference_number }}
Payment method: {{ $payment_method }}
@if(!empty($transfer_date))
Payment date: {{ $transfer_date }}@if(!empty($transfer_time)) {{ $transfer_time }}@endif
@endif
@if(!empty($submitted_at))
Submitted: {{ $submitted_at }}
@endif
Evidence files: {{ $evidence_count }} uploaded — view in review console

Review payment: {{ $review_url }}
