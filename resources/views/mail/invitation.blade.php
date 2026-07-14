<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>{{ __('You\'ve been invited to join :store', ['store' => $storeName]) }}</title>
    <style>
        /* Reset */
        body, table, td, p, a, li { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }

        /* Base */
        body {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            background-color: #f4f5f7;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: #1a1a2e;
        }

        .email-wrapper {
            width: 100%;
            background-color: #f4f5f7;
            padding: 40px 20px;
        }

        .email-container {
            max-width: 560px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
        }

        /* Header */
        .email-header {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            padding: 40px 40px 32px;
            text-align: center;
        }

        .email-header img {
            max-height: 48px;
            width: auto;
            margin-bottom: 16px;
        }

        .email-header h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
            color: #ffffff;
            line-height: 1.3;
        }

        /* Body */
        .email-body {
            padding: 40px;
        }

        .greeting {
            margin: 0 0 20px;
            font-size: 18px;
            font-weight: 600;
            color: #1a1a2e;
        }

        .message {
            margin: 0 0 28px;
            font-size: 15px;
            line-height: 1.6;
            color: #4a5568;
        }

        /* Invitation Card */
        .invitation-card {
            background-color: #f8f9fb;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 24px;
            margin: 0 0 28px;
        }

        .invitation-card-title {
            margin: 0 0 16px;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #718096;
        }

        .invitation-detail {
            display: table;
            width: 100%;
            margin-bottom: 10px;
        }

        .invitation-detail:last-child {
            margin-bottom: 0;
        }

        .invitation-label {
            display: table-cell;
            width: 120px;
            font-size: 13px;
            font-weight: 500;
            color: #a0aec0;
            vertical-align: top;
            padding-right: 12px;
        }

        .invitation-value {
            display: table-cell;
            font-size: 14px;
            font-weight: 600;
            color: #2d3748;
            vertical-align: top;
        }

        /* Role Badge */
        .role-badge {
            display: inline-block;
            background-color: #eef2ff;
            color: #4f46e5;
            font-size: 12px;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 20px;
            text-transform: capitalize;
        }

        /* CTA Button */
        .cta-wrapper {
            text-align: center;
            margin: 0 0 28px;
        }

        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: #ffffff !important;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            padding: 14px 36px;
            border-radius: 8px;
            mso-padding-alt: 0;
        }

        .cta-button:hover {
            opacity: 0.9;
        }

        /* Expiry Notice */
        .expiry-notice {
            background-color: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 8px;
            padding: 12px 16px;
            margin: 0 0 28px;
            font-size: 13px;
            color: #92400e;
            line-height: 1.5;
        }

        .expiry-notice strong {
            font-weight: 600;
        }

        /* Footer */
        .email-footer {
            padding: 24px 40px 32px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
        }

        .footer-text {
            margin: 0 0 8px;
            font-size: 12px;
            color: #a0aec0;
            line-height: 1.5;
        }

        .footer-text a {
            color: #4f46e5;
            text-decoration: underline;
        }

        /* Dark Mode */
        @media (prefers-color-scheme: dark) {
            body, .email-wrapper {
                background-color: #1a1a2e !important;
            }
            .email-container {
                background-color: #16213e !important;
            }
            .greeting, .invitation-value {
                color: #e2e8f0 !important;
            }
            .message {
                color: #a0aec0 !important;
            }
            .invitation-card {
                background-color: #1a1a2e !important;
                border-color: #2d3748 !important;
            }
            .invitation-label {
                color: #718096 !important;
            }
            .invitation-card-title {
                color: #a0aec0 !important;
            }
            .role-badge {
                background-color: #2d3748 !important;
                color: #a78bfa !important;
            }
            .expiry-notice {
                background-color: #422006 !important;
                border-color: #92400e !important;
                color: #fbbf24 !important;
            }
            .email-footer {
                border-color: #2d3748 !important;
            }
            .footer-text {
                color: #718096 !important;
            }
        }

        /* Responsive */
        @media only screen and (max-width: 600px) {
            .email-wrapper {
                padding: 20px 12px !important;
            }
            .email-header {
                padding: 32px 24px 24px !important;
            }
            .email-header h1 {
                font-size: 19px !important;
            }
            .email-body {
                padding: 28px 24px !important;
            }
            .invitation-card {
                padding: 18px !important;
            }
            .invitation-label {
                display: block !important;
                width: 100% !important;
                padding-right: 0 !important;
                margin-bottom: 2px !important;
            }
            .invitation-value {
                display: block !important;
                margin-bottom: 14px !important;
            }
            .cta-button {
                display: block !important;
                padding: 14px 24px !important;
            }
            .email-footer {
                padding: 20px 24px 28px !important;
            }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-container">

            {{-- Header --}}
            <div class="email-header">
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $storeName }}" height="48">
                @else
                    <h1 style="margin:0;font-size:22px;color:#fff;">{{ $storeName }}</h1>
                @endif
                <h1>{{ __('You\'re Invited to Join') }}</h1>
            </div>

            {{-- Body --}}
            <div class="email-body">
                <p class="greeting">{{ __('Hello :name', ['name' => $inviteeName ?? $email]) }},</p>

                <p class="message">
                    {!! __(':inviter has invited you to join <strong>:store</strong> as a :role. Accept the invitation below to get started.', [
                        'inviter' => $inviterName,
                        'store' => $storeName,
                        'role' => $roleLabel,
                    ]) !!}
                </p>

                {{-- Invitation Details Card --}}
                <div class="invitation-card">
                    <p class="invitation-card-title">{{ __('Invitation Details') }}</p>

                    <div class="invitation-detail">
                        <span class="invitation-label">{{ __('Store') }}</span>
                        <span class="invitation-value">{{ $storeName }}</span>
                    </div>

                    <div class="invitation-detail">
                        <span class="invitation-label">{{ __('Role') }}</span>
                        <span class="invitation-value"><span class="role-badge">{{ $roleLabel }}</span></span>
                    </div>

                    <div class="invitation-detail">
                        <span class="invitation-label">{{ __('Invited by') }}</span>
                        <span class="invitation-value">{{ $inviterName }}</span>
                    </div>

                    <div class="invitation-detail">
                        <span class="invitation-label">{{ __('Expires') }}</span>
                        <span class="invitation-value">{{ $expiresAt }}</span>
                    </div>
                </div>

                {{-- CTA Button --}}
                <div class="cta-wrapper">
                    <!--[if mso]>
                    <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ $acceptUrl }}" style="height:48px;v-text-anchor:middle;width:260px;" arcsize="17%" strokecolor="#4f46e5" fillcolor="#4f46e5">
                        <w:anchorlock/>
                        <center style="color:#ffffff;font-family:sans-serif;font-size:15px;font-weight:600;">{{ __('Accept Invitation') }}</center>
                    </v:roundrect>
                    <![endif]-->
                    <!--[if !mso]><!-->
                    <a href="{{ $acceptUrl }}" class="cta-button" target="_blank">{{ __('Accept Invitation') }}</a>
                    <!--<![endif]-->
                </div>

                {{-- Expiry Notice --}}
                <div class="expiry-notice">
                    <strong>{{ __('This invitation expires on :date.', ['date' => $expiresAt]) }}</strong>
                    {{ __('If you did not expect this invitation, you can safely ignore this email.') }}
                </div>
            </div>

            {{-- Footer --}}
            <div class="email-footer">
                <p class="footer-text">
                    {{ __('This email was sent by :store', ['store' => $storeName]) }}.
                    {{ __('If you have questions, contact us at') }}
                    <a href="mailto:{{ $storeEmail }}">{{ $storeEmail }}</a>.
                </p>
                <p class="footer-text">
                    {{ __('You received this because :inviter invited you to join the staff.', ['inviter' => $inviterName]) }}
                </p>
            </div>

        </div>
    </div>
</body>
</html>
