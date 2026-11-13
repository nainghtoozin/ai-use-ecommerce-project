<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Verify your email</title></head>
<body style="margin:0;padding:0;background-color:#f4f6f8;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f6f8;padding:24px 0;">
<tr><td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#fff;border-radius:12px;border:1px solid #e5e7eb;">
<tr><td style="padding:32px 40px 16px 40px;text-align:center;">
@if($storeLogo)<img src="{{ $storeLogo }}" alt="{{ $storeName }}" style="max-height:48px;max-width:180px;height:auto;margin:0 auto 12px;display:block;" />@endif
<h1 style="margin:0;font-size:22px;color:#111827;font-weight:700;">Welcome to {{ $storeName }}!</h1>
</td></tr>
<tr><td style="padding:8px 40px 24px 40px;">
<p style="margin:0 0 16px;font-size:15px;color:#111827;">Hello {{ $recipientName }},</p>
<p style="margin:0 0 16px;font-size:15px;color:#374151;">Thank you for registering with {{ $storeName }}. Please verify your email address to activate your customer account and continue shopping.</p>
<table role="presentation" width="100%"><tr><td align="center" style="padding:0 0 24px;">
<a href="{{ $verificationUrl }}" style="display:inline-block;background:#2563eb;color:#fff;text-decoration:none;font-size:15px;font-weight:600;padding:12px 28px;border-radius:8px;">Verify Email Address</a>
</td></tr></table>
<p style="margin:0 0 8px;font-size:13px;color:#6b7280;">If the button does not work, copy and paste this link:</p>
<p style="margin:0 0 24px;font-size:12px;color:#2563eb;word-break:break-all;"><a href="{{ $verificationUrl }}" style="color:#2563eb;">{{ $verificationUrl }}</a></p>
<p style="margin:0;font-size:13px;color:#6b7280;">If you did not create this account, you can safely ignore this email.</p>
</td></tr>
<tr><td style="padding:20px 40px;border-top:1px solid #e5e7eb;background:#f9fafb;">
<p style="margin:0;font-size:12px;color:#6b7280;text-align:center;">{{ $storeName }} &copy; {{ $year }}</p>
@if($supportEmail)<p style="margin:8px 0 0;font-size:12px;color:#6b7280;text-align:center;">Support: <a href="mailto:{{ $supportEmail }}" style="color:#2563eb;">{{ $supportEmail }}</a></p>@endif
</td></tr>
</table></td></tr></table>
</body></html>
