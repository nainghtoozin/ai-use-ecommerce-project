<?php

namespace App\Http\Middleware;

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
            if ($user->isSuperAdmin() || $user->can('bypass maintenance mode') || session()->has('impersonator_id')) {
                return $next($request);
            }
        }

        return redirect()->route('maintenance');
    }
}
