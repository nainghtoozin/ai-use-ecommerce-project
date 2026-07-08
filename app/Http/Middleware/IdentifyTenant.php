<?php

namespace App\Http\Middleware;

use App\Models\Account;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;

class IdentifyTenant
{
    public function handle(Request $request, Closure $next)
    {
        if (auth()->check()) {
            $authenticatable = auth()->user();

            if (! $authenticatable->relationLoaded('roles')) {
                $authenticatable->load('roles');
            }

            if ($authenticatable->isSuperAdmin()) {
                return $next($request);
            }

            if ($authenticatable instanceof Account) {
                $membership = TenantMembership::where('account_id', $authenticatable->id)
                    ->with('tenant')
                    ->first();

                if ($membership && $membership->tenant) {
                    app()->instance('current.tenant', $membership->tenant);
                    $request->merge(['tenant' => $membership->tenant]);
                    return $next($request);
                }
            }

            if ($authenticatable instanceof User) {
                $tenant = $authenticatable->tenant_id
                    ? Tenant::find($authenticatable->tenant_id)
                    : null;

                if ($tenant) {
                    app()->instance('current.tenant', $tenant);
                    $request->merge(['tenant' => $tenant]);
                    return $next($request);
                }
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
