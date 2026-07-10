<?php

namespace App\Http\Middleware;

use App\Models\WebsiteInfo;
use App\Services\StoreResolver;
use Closure;
use Illuminate\Http\Request;

class Storefront
{
    public function __construct(private readonly StoreResolver $storeResolver) {}

    public function handle(Request $request, Closure $next)
    {
        $storeSlug = $request->route('store_slug');

        if (!$storeSlug) {
            abort(404);
        }

        $tenant = $this->storeResolver->resolve($storeSlug);

        if (!$tenant) {
            abort(404);
        }

        app()->instance('current.tenant', $tenant);
        $request->merge(['tenant' => $tenant]);
        $request->session()->put('current_tenant_slug', $tenant->slug);

        $settings = WebsiteInfo::first();
        \Inertia\Inertia::share('website_info', $settings ? $settings->toArray() : []);

        return $next($request);
    }
}
