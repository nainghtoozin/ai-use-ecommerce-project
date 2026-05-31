<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureTenantIsActive
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

        // Suspended — block all operations, redirect to suspension page
        if ($tenant->status === 'suspended') {
            return redirect()->route('admin.suspended');
        }

        // Banned or inactive — block all operations
        if (! in_array($tenant->status, ['active', 'trialing'])) {
            return redirect()->route('admin.suspended')
                ->with('error', 'Your account is currently restricted. Please contact support.');
        }

        // Free plan — skip subscription check (FeatureGate handles limits)
        $subscription = $tenant->subscription;
        if ($subscription && $subscription->plan?->isFree()) {
            return $next($request);
        }

        // Active or trialing subscription — allow
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

        // Expired — redirect to dashboard (accessible via tenant.valid only, outside tenant.active)
        return redirect()->route('admin.dashboard')
            ->with('error', 'Your subscription has expired. Please renew to restore access to all features.');
    }
}
