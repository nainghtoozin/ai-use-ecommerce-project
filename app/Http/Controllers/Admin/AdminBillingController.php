<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Models\SubscriptionAuditLog;
use App\Services\SubscriptionAuditService;
use App\Services\SubscriptionLimitService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminBillingController extends Controller
{
    public function index()
    {
        if (!auth()->user()->can('billing.view')) {
            abort(403, 'Unauthorized');
        }

        $tenant = auth()->user()->tenant;

        if (!$tenant) {
            abort(403, 'Store not found.');
        }

        $subscription = $tenant->subscription;

        $usage = SubscriptionLimitService::for($tenant)->getAllUsage();

        $auditLogs = collect();
        if ($subscription) {
            $logs = SubscriptionAuditLog::where('subscription_id', $subscription->id)
                ->latest()
                ->take(20)
                ->get();

            $auditLogs = $logs->map(function ($log) {
                return [
                    'id' => $log->id,
                    'event' => $log->event,
                    'old_status' => $log->old_status,
                    'new_status' => $log->new_status,
                    'reason' => $log->reason,
                    'created_at' => $log->created_at->diffForHumans(),
                ];
            });
        }

        return Inertia::render('Admin/Billing/Index', [
            'subscription' => $subscription ? [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'plan' => $subscription->plan ? [
                    'id' => $subscription->plan->id,
                    'name' => $subscription->plan->name,
                    'description' => $subscription->plan->description,
                    'monthly_price' => $subscription->plan->monthly_price,
                    'yearly_price' => $subscription->plan->yearly_price,
                    'product_limit' => $subscription->plan->product_limit,
                    'staff_limit' => $subscription->plan->staff_limit,
                    'storage_limit' => $subscription->plan->storage_limit,
                ] : null,
                'billing_interval' => $subscription->billing_interval,
                'price' => $subscription->billedPrice(),
                'starts_at' => $subscription->starts_at?->toDateString(),
                'expires_at' => $subscription->expires_at?->toDateString(),
                'trial_ends_at' => $subscription->trial_ends_at?->toDateString(),
                'trial_days_remaining' => $subscription->daysLeftInTrial(),
                'cancelled_at' => $subscription->cancelled_at?->toDateString(),
                'suspended_at' => $subscription->suspended_at?->toDateString(),
                'days_until_expiry' => $subscription->daysUntilExpiry(),
                'days_since_expiry' => $subscription->daysSinceExpiry(),
            ] : null,
            'usage' => $usage,
            'auditLogs' => $auditLogs,
        ]);
    }

    public function renew(Request $request)
    {
        if (!auth()->user()->can('billing.renew')) {
            abort(403, 'Unauthorized');
        }

        $tenant = auth()->user()->tenant;

        if (!$tenant) {
            abort(403, 'Store not found.');
        }

        $subscription = $tenant->subscription;

        if (!$subscription) {
            return redirect()->back()->with('error', 'No subscription found.');
        }

        if ($subscription->isInGoodStanding()) {
            return redirect()->back()->with('error', 'Your subscription is already active.');
        }

        if ($subscription->isSuspended()) {
            return redirect()->back()->with('error', 'Your subscription has been suspended. Please contact support.');
        }

        // Trial renewal limit check
        if ($subscription->trial_ends_at) {
            $settings = PlatformSetting::current();
            if ($settings->allow_trial_renewal && $settings->max_trial_renewals > 0
                && $subscription->trial_renewals_count >= $settings->max_trial_renewals) {
                return redirect()->back()->with(
                    'error',
                    'Trial renewal limit reached. Please upgrade to a paid plan to continue.'
                );
            }
        }

        $subscription->renewFromInterval('Self-service renewal by merchant.');

        // Track trial renewal
        if ($subscription->trial_ends_at) {
            $subscription->increment('trial_renewals_count');
            SubscriptionAuditService::log($subscription, 'trial_renewed', [
                'reason' => 'Trial renewal via self-service.',
                'trial_renewals_count' => $subscription->trial_renewals_count,
            ]);
        }

        SubscriptionAuditService::log($subscription, 'renewed', [
            'reason' => 'Self-service renewal by merchant.',
        ]);

        return admin_redirect('admin.billing')
            ->with('success', 'Your subscription has been renewed!');
    }
}
