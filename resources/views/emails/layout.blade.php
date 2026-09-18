<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $subject ?? $app_name }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:Arial,Helvetica,sans-serif;">
<div style="max-width:600px;margin:0 auto;padding:24px 16px;">
<div style="background-color:#ffffff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
<div style="padding:20px 24px;border-bottom:1px solid #e2e8f0;">
<p style="margin:0;font-size:16px;font-weight:bold;color:#0f172a;">{{ $app_name }}</p>
<p style="margin:2px 0 0;font-size:12px;color:#64748b;">{{ $store_name ?? '' }}</p>
</div>
<div style="padding:24px;">
@yield('content')
</div>
<div style="padding:16px 24px;border-top:1px solid #e2e8f0;background-color:#f8fafc;">
<p style="margin:0;font-size:11px;color:#94a3b8;">This is an automated billing notification. Please do not reply directly to this email.</p>
</div>
</div>
</div>
</body>
</html>
