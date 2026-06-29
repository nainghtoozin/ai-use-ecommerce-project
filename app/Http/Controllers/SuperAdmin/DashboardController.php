<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Subscription;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $tenantStats = [
            'total' => Tenant::count(),
            'active' => Tenant::where('status', 'active')->count(),
            'suspended' => Tenant::where('status', 'suspended')->count(),
            'expired' => Tenant::expired()->count(),
        ];

        $totalSubscriptions = Subscription::count();

        $now = now();
        $monthlyRevenue = DB::table('orders')
            ->whereIn('order_status', ['confirmed', 'delivered', 'completed'])
            ->whereYear('created_at', $now->year)
            ->whereMonth('created_at', $now->month)
            ->sum('total_amount');

        $yearlyRevenue = DB::table('orders')
            ->whereIn('order_status', ['confirmed', 'delivered', 'completed'])
            ->whereYear('created_at', $now->year)
            ->sum('total_amount');

        $recentTenants = Tenant::withCount('users')
            ->with('subscription.plan')
            ->latest()
            ->take(10)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'email' => $t->email,
                'status' => $t->status,
                'users_count' => $t->users_count,
                'plan_name' => $t->subscription?->plan?->name,
                'subscription_status' => $t->subscription?->status,
                'created_at' => $t->created_at->toDateString(),
            ]);

        $subscriptionsByPlan = Plan::withCount(['subscriptions' => function ($q) {
            $q->whereIn('status', ['trialing', 'active']);
        }])
            ->where('status', 'active')
            ->orderByDesc('subscriptions_count')
            ->get()
            ->map(fn ($p) => [
                'name' => $p->name,
                'count' => $p->subscriptions_count,
                'monthly_price' => $p->monthly_price,
            ]);

        return Inertia::render('SuperAdmin/Dashboard', [
            'tenantStats' => $tenantStats,
            'totalSubscriptions' => $totalSubscriptions,
            'monthlyRevenue' => (float) $monthlyRevenue,
            'yearlyRevenue' => (float) $yearlyRevenue,
            'recentTenants' => $recentTenants,
            'subscriptionsByPlan' => $subscriptionsByPlan,
        ]);
    }
}
