<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ session('theme', 'system') === 'dark' ? 'dark' : '' }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        $siteTitle = $page['props']['platform_setting']['site_name'] ?? 'My E-Commerce Store';
        $tenantFavicon = $page['props']['website_info']['favicon_url'] ?? null;
        $platformFavicon = $page['props']['platform_setting']['favicon'] ?? null;
        $faviconUrl = $tenantFavicon ?? ($platformFavicon ? \App\Services\ImageService::url($platformFavicon) : null);
    @endphp

    <title inertia>{{ $siteTitle }}</title>

    @if ($faviconUrl)
        <link rel="icon" type="image/png" href="{{ $faviconUrl }}?v={{ time() }}">
    @endif

    @php
        $themeColor = ($page['props']['website_info']['theme_color'] ?? '') ?: '#3B82F6';
        $themeColor = preg_match('/^#[0-9A-Fa-f]{6}$/', $themeColor) ? $themeColor : '#3B82F6';
        $themeColorRgb = implode(', ', array_map(function($hex) {
            return hexdec($hex);
        }, str_split(ltrim($themeColor, '#'), 2)));
    @endphp

    <style>
        :root {
            --theme-color: {{ $themeColor }};
            --theme-color-rgb: {{ $themeColorRgb }};
        }
    </style>

    <!-- Theme initialization script (prevents flash of wrong theme) -->
    <script>
        (function() {
            var theme = '{{ session("theme", "system") }}';
            var resolved = theme;
            if (theme === 'system') {
                resolved = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }
            if (resolved === 'dark') {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>

    <!-- Bootstrap CSS (legacy support) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">


    @viteReactRefresh
    @vite(['resources/js/app.jsx'])
    @inertiaHead
</head>

<body class="font-sans antialiased">
    @inertia
</body>

</html>
