<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateTenantBinding
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Tenant::getCurrent();

        if (!$tenant) {
            return $next($request);
        }

        $user = $request->user();
        if ($user && $user->isSuperAdmin()) {
            return $next($request);
        }

        $tenantId = (int) $tenant->id;

        foreach ($request->route()->parameters() as $value) {
            if (!$value instanceof Model) {
                continue;
            }

            if (is_null($value->tenant_id)) {
                continue;
            }

            if ((int) $value->tenant_id !== $tenantId) {
                abort(404);
            }
        }

        return $next($request);
    }
}
