<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;

class IdentifyTenant
{
    public function handle(Request $request, Closure $next)
    {
        if (auth()->check()) {
            $user = auth()->user();

            if (! $user->relationLoaded('roles')) {
                $user->load('roles');
            }

            if ($user->isSuperAdmin()) {
                return $next($request);
            }

            $tenant = $user->tenant_id
                ? Tenant::find($user->tenant_id)
                : null;

            if ($tenant) {
                app()->instance('current.tenant', $tenant);
                $request->merge(['tenant' => $tenant]);
                return $next($request);
            }
        }

        $tenant = $this->resolveFromSubdomain($request)
            ?? $this->resolveFromHeader($request)
            ?? $this->resolveFromSession($request)
            ?? Tenant::getDefault();

        if ($tenant) {
            app()->instance('current.tenant', $tenant);
            $request->merge(['tenant' => $tenant]);
        }

        return $next($request);
    }

    protected function resolveFromSubdomain(Request $request): ?Tenant
    {
        $host = $request->getHost();
        $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

        if ($host === $mainDomain || $host === 'localhost' || $host === '127.0.0.1') {
            return null;
        }

        $parts = explode('.', $host);
        if (count($parts) < 3) {
            return null;
        }

        $subdomain = $parts[0];

        if ($subdomain === 'www' || $subdomain === 'superadmin') {
            return null;
        }

        return Tenant::where('slug', $subdomain)->first();
    }

    protected function resolveFromHeader(Request $request): ?Tenant
    {
        $header = $request->header('X-Tenant');
        if (! $header) {
            return null;
        }
        return Tenant::where('slug', $header)
            ->orWhere('domain', $header)
            ->first();
    }

    protected function resolveFromSession(Request $request): ?Tenant
    {
        $slug = $request->session()->get('current_tenant_slug');
        if (! $slug) {
            return null;
        }
        return Tenant::where('slug', $slug)->first();
    }
}
