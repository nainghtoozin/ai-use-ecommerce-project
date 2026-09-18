{{ $app_name }} — {{ $store_name }}

Invoice {{ $invoice_number }}

Here are the details of your invoice for {{ $store_name }}.

Invoice: {{ $invoice_number }}
Plan: {{ $plan_name }} ({{ ucfirst($billing_cycle) }})
Amount: {{ $invoice_total }}
@if(!empty($billing_period))
Billing period: {{ $billing_period }}
@endif
@if(!empty($issued_at))
Issued: {{ $issued_at }}
@endif
Status: {{ ucfirst($invoice_status) }}

@if(!empty($invoice_url))
View invoice: {{ $invoice_url }}
@endif
@if(!empty($invoice_download_url))
Download invoice: {{ $invoice_download_url }}
@endif

View billing: {{ $billing_url }}
