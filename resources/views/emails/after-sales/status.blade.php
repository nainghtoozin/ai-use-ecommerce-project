@extends('emails.layout')

@section('content')
<h1 style="margin:0 0 8px;font-size:20px;color:#0f172a;">{{ $title }}</h1>
@if(!empty($storeName))
<p style="margin:0 0 12px;font-size:13px;color:#64748b;">{{ $storeName }}</p>
@endif
<p style="margin:0 0 16px;font-size:14px;color:#334155;">{{ $messageBody }}</p>
@if(!empty($orderNumber))
<p style="margin:0 0 16px;font-size:13px;color:#64748b;">Order <strong>#{{ $orderNumber }}</strong></p>
@endif
@if(!empty($actionUrl))
<p style="margin:16px 0 0;">
<a href="{{ $actionUrl }}" style="display:inline-block;padding:10px 20px;background-color:#2563eb;color:#ffffff;text-decoration:none;border-radius:8px;font-size:14px;font-weight:bold;">View details</a>
</p>
@endif
<p style="margin:24px 0 0;font-size:12px;color:#94a3b8;">You are receiving this email because you placed an order with this store.</p>
@endsection
