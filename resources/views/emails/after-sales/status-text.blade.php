{{ $title }}
@if(!empty($storeName))
{{ $storeName }}
@endif

{{ $messageBody }}
@if(!empty($orderNumber))

Order #{{ $orderNumber }}
@endif
@if(!empty($actionUrl))

View details: {{ $actionUrl }}
@endif

You are receiving this email because you placed an order with this store.
