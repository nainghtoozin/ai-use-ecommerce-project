<?php

namespace App\Http\Middleware;

use App\Models\PlatformSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckPlatformMaintenanceMode
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $settings = PlatformSetting::current();
        } catch (\Throwable $e) {
            return $next($request);
        }

        if (!$settings || !$settings->maintenance_mode) {
            return $next($request);
        }

        $user = Auth::user();
        if ($user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return $next($request);
        }

        if ($request->is('login', 'logout', 'register', 'forgot-password', 'reset-password/*', 'up', 'storage/*', 'build/*', 'favicon.ico')) {
            return $next($request);
        }

        if ($request->routeIs('login', 'logout', 'register', 'password.*', 'verification.*')) {
            return $next($request);
        }

        $settings = PlatformSetting::current();
        return response()->view('errors.platform-maintenance', ['settings' => $settings], 503);
    }
}
