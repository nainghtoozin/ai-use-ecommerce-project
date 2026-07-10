<?php

namespace App\Http\Middleware;

use App\Models\Account;
use App\Models\Tenant;
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

        $tenant = $user instanceof Account ? Tenant::getCurrent() : $user->tenant;

        if (! $tenant) {
            $storeSlug = $request->route('store_slug');
            if ($storeSlug) {
                $tenant = Tenant::where('slug', $storeSlug)->first();
            }
            if (! $tenant) {
                abort(403, 'Store not found.');
            }
        }

        $storeSlug = $request->route('store_slug');

        // Pending — owner has not verified email yet
        if ($tenant->status === 'pending' && !$user->hasVerifiedEmail()) {
            return $this->redirectToSuspended($storeSlug)
                ->with('error', 'Please verify your email first.');
        }

        // Suspended — block all operations, redirect to suspension page
        if ($tenant->status === 'suspended') {
            return $this->redirectToSuspended($storeSlug);
        }

        // Banned or inactive — block all operations
        if (! in_array($tenant->status, ['active', 'trialing'])) {
            return $this->redirectToSuspended($storeSlug)
                ->with('error', 'Your account is currently restricted. Please contact support.');
        }

        $subscription = $tenant->subscription;

        // No subscription record — allow (edge case; superadmin should assign one)
        if (! $subscription) {
            return $next($request);
        }

        // Free plan — skip subscription check (FeatureGate handles limits)
        if ($subscription->plan?->isFree()) {
            return $next($request);
        }

        // Locked tenant — pass through (CheckStoreLocked middleware handles mutation blocking)
        if ($tenant->isLocked()) {
            return $next($request);
        }

        // Pending — allow (tenant may still be bootstrapping or email unverified)
        if ($subscription->isPending()) {
            return $next($request);
        }

        // Active, trialing — allow
        if ($subscription->isInGoodStanding()) {
            return $next($request);
        }

        // Past due — redirect to billing page instead of expired (still in grace period)
        if ($subscription->isPastDue()) {
            if ($storeSlug) {
                return redirect()->route('storefront.admin.billing', ['store_slug' => $storeSlug]);
            }
            return redirect()->route('admin.billing');
        }

        // Canceled but still within paid period — allow
        if ($subscription->isCanceled() && $subscription->expires_at && $subscription->expires_at->isFuture()) {
            return $next($request);
        }

        // Expired — redirect to standalone expired page (outside tenant.active to avoid loop)
        return $this->redirectToExpired($storeSlug);
    }

    private function redirectToSuspended(?string $storeSlug): \Illuminate\Http\RedirectResponse
    {
        if ($storeSlug) {
            return redirect()->route('storefront.admin.suspended', ['store_slug' => $storeSlug]);
        }
        return redirect()->route('admin.suspended');
    }

    private function redirectToExpired(?string $storeSlug): \Illuminate\Http\RedirectResponse
    {
        if ($storeSlug) {
            return redirect()->route('storefront.admin.expired', ['store_slug' => $storeSlug]);
        }
        return redirect()->route('admin.expired');
    }
}
