<?php

namespace App\Http\Middleware;

use App\Services\AuthorizationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantMember
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!AuthorizationService::isTenantMember()) {
            abort(403, 'You are not a member of this store.');
        }

        return $next($request);
    }
}
