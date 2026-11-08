@extends('emails.layout')

@section('content')
<h1 style="margin:0 0 8px;font-size:20px;color:#0f172a;">{{ $subject }}</h1>
@if(!empty($recipient_name))
<p style="margin:0 0 12px;font-size:14px;color:#475569;">Hello {{ $recipient_name }},</p>
@endif
<div style="margin:0 0 16px;font-size:14px;color:#334155;line-height:1.6;">
{!! nl2br(e($body)) !!}
</div>
<p style="margin:16px 0 0;font-size:12px;color:#94a3b8;">This is an operational platform communication. Please do not reply unless a reply address is provided.</p>
@endsection