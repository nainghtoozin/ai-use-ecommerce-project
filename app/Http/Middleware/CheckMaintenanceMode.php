<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\WebsiteInfo;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckMaintenanceMode
{
    private const ALLOWED_ROUTES = [
        'maintenance',
        'login',
        'register',
        'password.request',
        'password.email',
        'password.reset',
        'password.store',
        'verification.*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (!Tenant::getCurrent()) {
            return $next($request);
        }

        $settings = WebsiteInfo::getSettings();

        if (!$settings->maintenance_mode) {
            return $next($request);
        }

        if ($request->routeIs(...self::ALLOWED_ROUTES)) {
            return $next($request);
        }

        if ($request->is('login', 'register', 'forgot-password', 'reset-password/*', 'storage/*', 'build/*', 'favicon.ico', 'robots.txt', 'service-worker.js', 'manifest.json')) {
            return $next($request);
        }

        if (Auth::check()) {
            $user = Auth::user();

            // ─────────────────────────────────────────────────────────
            // PLATFORM IDENTITY BYPASS
            //
            // Per Platform Identity Design Lock:
            //   - SuperAdmin is platform-only — bypass maintenance mode
            //   - Never blocked by tenant maintenance settings
            // ─────────────────────────────────────────────────────────
            if ($user->isSuperAdmin()) {
                return $next($request);
            }

            if ($user->can('bypass maintenance mode') || session()->has('impersonator_id')) {
                return $next($request);
            }
        }

        return redirect()->route('maintenance');
    }
}
