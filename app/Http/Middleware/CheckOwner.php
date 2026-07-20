<?php

namespace App\Http\Middleware;

use App\Services\AuthorizationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        AuthorizationService::authorizeOwner();

        return $next($request);
    }
}
