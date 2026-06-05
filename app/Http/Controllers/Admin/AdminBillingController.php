<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SubscriptionLimitService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminBillingController extends Controller
{
    public function index()
    {
        $tenant = auth()->user()->tenant;

        if (!$tenant) {
            abort(403, 'Store not found.');
        }

        $subscription = $tenant->subscription;

        $usage = SubscriptionLimitService::for($tenant)->getAllUsage();

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
                'cancelled_at' => $subscription->cancelled_at?->toDateString(),
                'suspended_at' => $subscription->suspended_at?->toDateString(),
                'days_until_expiry' => $subscription->daysUntilExpiry(),
                'days_since_expiry' => $subscription->daysSinceExpiry(),
            ] : null,
            'usage' => $usage,
        ]);
    }

    public function renew(Request $request)
    {
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

        $subscription->renewFromInterval('Self-service renewal by merchant.');

        return admin_redirect('admin.billing')
            ->with('success', 'Your subscription has been renewed!');
    }
}
