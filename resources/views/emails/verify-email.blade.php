<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify your email address</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f6f8;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f6f8;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background-color:#ffffff;border-radius:12px;border:1px solid #e5e7eb;overflow:hidden;">
                    <tr>
                        <td style="padding:32px 40px 16px 40px;text-align:center;">
                            @if($logoUrl)
                                <img src="{{ $logoUrl }}" alt="{{ $platformName }}" style="max-height:48px;max-width:180px;height:auto;margin:0 auto 12px auto;display:block;" />
                            @endif
                            <h1 style="margin:0;font-size:22px;line-height:28px;color:#111827;font-weight:700;">Welcome aboard!</h1>
                            <p style="margin:8px 0 0 0;font-size:14px;line-height:20px;color:#6b7280;">{{ $platformName }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 40px 24px 40px;">
                            <p style="margin:0 0 16px 0;font-size:15px;line-height:22px;color:#111827;">Hello {{ $recipientName }},</p>
                            <p style="margin:0 0 16px 0;font-size:15px;line-height:22px;color:#374151;">
                                Thank you for creating your merchant account with {{ $platformName }}.
                            </p>
                            <p style="margin:0 0 24px 0;font-size:15px;line-height:22px;color:#374151;">
                                To protect your account and ensure you receive important store and account notifications, please verify your email address by clicking the button below.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" style="padding:0 0 24px 0;">
                                        <a href="{{ $verificationUrl }}" style="display:inline-block;background-color:#2563eb;color:#ffffff;text-decoration:none;font-size:15px;font-weight:600;padding:12px 28px;border-radius:8px;">
                                            Verify My Email Address
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <div style="background-color:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:16px;margin:0 0 20px 0;">
                                <p style="margin:0 0 8px 0;font-size:14px;font-weight:600;color:#111827;">Why verify your email?</p>
                                <ul style="margin:0;padding-left:18px;font-size:14px;line-height:22px;color:#374151;">
                                    <li>Help protect your merchant account.</li>
                                    <li>Receive important account and store notifications.</li>
                                    <li>Access your merchant dashboard after verification.</li>
                                </ul>
                            </div>

                            <p style="margin:0 0 16px 0;font-size:13px;line-height:20px;color:#6b7280;">
                                This verification link is unique to your account. Please do not share it with anyone.
                            </p>
                            <p style="margin:0 0 20px 0;font-size:13px;line-height:20px;color:#6b7280;">
                                If you did not create this account, you can safely ignore this email. No action is required.
                            </p>

                            <p style="margin:0 0 8px 0;font-size:13px;line-height:20px;color:#6b7280;">
                                If the button above does not work, copy and paste the following link into your browser:
                            </p>
                            <p style="margin:0 0 24px 0;font-size:12px;line-height:18px;color:#2563eb;word-break:break-all;">
                                <a href="{{ $verificationUrl }}" style="color:#2563eb;text-decoration:underline;">{{ $verificationUrl }}</a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 40px;border-top:1px solid #e5e7eb;background-color:#f9fafb;">
                            <p style="margin:0 0 8px 0;font-size:12px;line-height:18px;color:#6b7280;text-align:center;">
                                {{ $platformName }} &copy; {{ $year }}
                            </p>
                            @if($supportEmail || $supportUrl)
                                <p style="margin:0 0 8px 0;font-size:12px;line-height:18px;color:#6b7280;text-align:center;">
                                    @if($supportEmail)
                                        Support: <a href="mailto:{{ $supportEmail }}" style="color:#2563eb;text-decoration:none;">{{ $supportEmail }}</a>
                                    @endif
                                    @if($supportUrl)
                                        &nbsp;|&nbsp; <a href="{{ $supportUrl }}" style="color:#2563eb;text-decoration:none;">Support Center</a>
                                    @endif
                                </p>
                            @endif
                            <p style="margin:0;font-size:11px;line-height:16px;color:#9ca3af;text-align:center;">
                                For your security, never share your verification link or account credentials.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
