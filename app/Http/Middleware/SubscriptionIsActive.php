<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SubscriptionIsActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        $tenant = $user->tenant;

        if (! $tenant) {
            abort(403, 'Store not found.');
        }

        $storeSlug = $request->route('store_slug');

        // Suspended tenant — block operations (same restriction as expired)
        if ($tenant->status !== 'active') {
            return $this->redirectToDashboard($storeSlug)
                ->with('error', 'Your account is currently suspended. Please contact support for assistance.');
        }

        // Free plan — skip subscription check (FeatureGate handles feature limits)
        $subscription = $tenant->subscription;
        if ($subscription && $subscription->plan?->isFree()) {
            return $next($request);
        }

        // Active or trialing — allow
        if ($tenant->hasActiveSubscription()) {
            return $next($request);
        }

        // No subscription record — allow (edge case; superadmin should assign one)
        if (! $subscription) {
            return $next($request);
        }

        // Canceled but still within paid period — allow
        if ($subscription->isCanceled() && $subscription->expires_at && $subscription->expires_at->isFuture()) {
            return $next($request);
        }

        // Expired — redirect to dashboard (which is accessible via TenantIsValid only)
        return $this->redirectToDashboard($storeSlug)
            ->with('error', 'Your subscription has expired. Please renew to restore access to all features.');
    }

    private function redirectToDashboard(?string $storeSlug): \Illuminate\Http\RedirectResponse
    {
        if ($storeSlug) {
            return redirect()->route('storefront.admin.dashboard', ['store_slug' => $storeSlug]);
        }
        return redirect()->route('admin.dashboard');
    }
}
