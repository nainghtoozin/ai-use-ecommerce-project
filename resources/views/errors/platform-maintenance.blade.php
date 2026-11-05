<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $settings->maintenance_title ?: "We'll be right back" }}</title>
    <style>
        :root { --accent: #2563eb; --accent-soft: #eff6ff; }
        * { box-sizing: border-box; }
        body {
            font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            background: radial-gradient(1200px 600px at 50% -10%, #eef2ff 0%, #f8fafc 60%);
            color: #1e293b;
            display: flex; align-items: center; justify-content: center;
            min-height: 100vh; margin: 0; padding: 24px;
        }
        .card {
            background: #fff; border: 1px solid #e2e8f0; border-radius: 20px;
            padding: 48px 40px; max-width: 640px; width: 100%; text-align: center;
            box-shadow: 0 20px 40px rgba(15,23,42,.06);
        }
        .logo { max-height: 52px; margin: 0 auto 20px; display: block; }
        .badge { display: inline-flex; align-items: center; gap: 6px; background: var(--accent-soft); color: var(--accent); font-size: 12px; font-weight: 600; padding: 6px 14px; border-radius: 999px; margin-bottom: 18px; }
        .badge::before { content: ''; width: 8px; height: 8px; border-radius: 50%; background: var(--accent); }
        h1 { font-size: 28px; margin: 0 0 12px; font-weight: 700; letter-spacing: -0.02em; }
        .message { color: #475569; line-height: 1.75; margin: 0 0 20px; white-space: pre-line; font-size: 15px; }
        .return-box { background: #f1f5f9; border-radius: 12px; padding: 16px 20px; margin: 20px 0; }
        .return-label { font-size: 12px; text-transform: uppercase; letter-spacing: .06em; color: #64748b; font-weight: 600; margin-bottom: 6px; }
        .return-date { font-size: 18px; font-weight: 600; color: #0f172a; }
        .countdown { display: flex; justify-content: center; gap: 12px; margin: 16px 0 4px; }
        .countdown div { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 14px; min-width: 64px; }
        .countdown .num { font-size: 22px; font-weight: 700; color: var(--accent); display: block; }
        .countdown .unit { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: .06em; }
        .expired { color: #b45309; font-size: 14px; margin: 16px 0; }
        .actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; margin-top: 24px; }
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 11px 20px; border-radius: 10px; font-weight: 600; font-size: 14px; text-decoration: none; cursor: pointer; border: none; }
        .btn-primary { background: var(--accent); color: #fff; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-secondary { background: #f1f5f9; color: #334155; }
        .btn-secondary:hover { background: #e2e8f0; }
        .support { margin-top: 28px; padding-top: 24px; border-top: 1px solid #e2e8f0; }
        .support-title { font-size: 13px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: .06em; margin-bottom: 12px; }
        .support-links { display: flex; flex-direction: column; gap: 8px; align-items: center; }
        .support-links a { color: var(--accent); text-decoration: none; font-weight: 500; font-size: 14px; }
        .support-links a:hover { text-decoration: underline; }
        .footer { margin-top: 24px; font-size: 12px; color: #94a3b8; }
        .note { font-size: 13px; color: #64748b; margin-top: 8px; }
        @media (max-width: 480px) {
            .card { padding: 32px 24px; }
            h1 { font-size: 24px; }
            .countdown div { min-width: 56px; padding: 8px 10px; }
            .countdown .num { font-size: 18px; }
        }
        @media (prefers-reduced-motion: reduce) { * { transition: none !important; } }
    </style>
</head>
<body>
    <div class="card">
        @if($settings->site_logo_url ?? null)
            <img src="{{ $settings->site_logo_url }}" alt="Logo" class="logo">
        @endif
        <span class="badge">Scheduled Maintenance</span>
        <h1>{{ $settings->maintenance_title ?: "We'll be right back" }}</h1>
        <p class="message">{{ $settings->maintenance_message ?: 'We are currently performing scheduled maintenance to improve our platform. Thank you for your patience.' }}</p>

        @php
            $returnTime = $settings->maintenance_return_time;
            $returnIso = $returnTime ? $returnTime->toIso8601String() : null;
        @endphp

        @if($returnTime)
            <div class="return-box">
                <div class="return-label">Expected return</div>
                <div class="return-date">{{ $returnTime->format('F j, Y') }}<br>{{ $returnTime->format('g:i A') }}</div>
                <div id="countdown" class="countdown" data-return="{{ $returnIso }}"></div>
                <div id="expired" class="expired" style="display:none;">The expected maintenance time has passed. We're checking the final updates.</div>
            </div>
        @else
            <p class="note">We are working to restore service as soon as possible.</p>
        @endif

        <div class="actions">
            <button type="button" class="btn btn-secondary" onclick="closeTab()">Close This Tab</button>
            <button type="button" class="btn btn-primary" onclick="tryAgain()">Okay, Try Again</button>
        </div>
        <div id="close-help" class="note" style="display:none;">Your browser does not allow this page to close itself. Please close this tab using the X button in your browser.</div>

        @if($settings->support_email || $settings->support_phone || $settings->support_page_url)
            <div class="support">
                <div class="support-title">Need Assistance?</div>
                <div class="support-links">
                    @if($settings->support_email)
                        <a href="mailto:{{ $settings->support_email }}">Email: {{ $settings->support_email }}</a>
                    @endif
                    @if($settings->support_phone)
                        <a href="tel:{{ $settings->support_phone }}">Phone: {{ $settings->support_phone }}</a>
                    @endif
                    @if($settings->support_page_url)
                        <a href="{{ $settings->support_page_url }}" target="_blank" rel="noopener noreferrer">{{ $settings->support_button_text ?: 'Contact Support' }}</a>
                    @elseif($settings->support_email)
                        <a href="mailto:{{ $settings->support_email }}">{{ $settings->support_button_text ?: 'Contact Support' }}</a>
                    @elseif($settings->support_phone)
                        <a href="tel:{{ $settings->support_phone }}">{{ $settings->support_button_text ?: 'Contact Support' }}</a>
                    @endif
                </div>
            </div>
        @endif

        <div class="footer">{{ config('app.name') }} &middot; Maintenance in progress</div>
    </div>

    <script>
        (function () {
            var cd = document.getElementById('countdown');
            if (cd) {
                var returnIso = cd.getAttribute('data-return');
                var target = new Date(returnIso).getTime();
                var expired = document.getElementById('expired');
                function render() {
                    var now = Date.now();
                    var diff = target - now;
                    if (diff <= 0) {
                        cd.style.display = 'none';
                        if (expired) expired.style.display = 'block';
                        return;
                    }
                    var d = Math.floor(diff / 86400000);
                    var h = Math.floor((diff % 86400000) / 3600000);
                    var m = Math.floor((diff % 3600000) / 60000);
                    var s = Math.floor((diff % 60000) / 1000);
                    cd.innerHTML =
                        '<div><span class="num">' + d + '</span><span class="unit">Days</span></div>' +
                        '<div><span class="num">' + h + '</span><span class="unit">Hours</span></div>' +
                        '<div><span class="num">' + m + '</span><span class="unit">Min</span></div>' +
                        '<div><span class="num">' + s + '</span><span class="unit">Sec</span></div>';
                }
                render();
                var timer = setInterval(render, 1000);
                window.addEventListener('beforeunload', function () { clearInterval(timer); });
            }

            window.closeTab = function () {
                try {
                    window.close();
                } catch (e) {
                    // ignore
                }
                setTimeout(function () {
                    var help = document.getElementById('close-help');
                    if (help) help.style.display = 'block';
                }, 300);
            };
            window.tryAgain = function () {
                window.location.reload();
            };
        })();
    </script>
</body>
</html>
