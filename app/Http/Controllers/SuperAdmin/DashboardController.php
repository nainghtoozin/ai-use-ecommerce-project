<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\PaymentIntent;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $start = $this->parseDate($request->query('start_date'), now()->startOfMonth());
        $end = $this->parseDate($request->query('end_date'), now()->endOfDay(), true);

        if ($start->gt($end)) {
            $start = $end->copy()->startOfMonth();
        }

        $tenantStats = [
            'total' => Tenant::count(),
            'active' => Tenant::where('status', 'active')->count(),
            'trialing' => Tenant::where('status', 'trialing')->count(),
            'suspended' => Tenant::where('status', 'suspended')->count(),
            'expired' => Tenant::expired()->count(),
            'pending' => Tenant::where('status', 'pending')->count(),
        ];

        $subscriptionStats = [
            'total' => Subscription::count(),
            'active' => Subscription::where('status', 'active')->count(),
            'trialing' => Subscription::where('status', 'trialing')->count(),
            'past_due' => Subscription::where('status', 'past_due')->count(),
            'expired' => Subscription::where('status', 'expired')->count(),
            'canceled' => Subscription::where('status', 'canceled')->count(),
            'pending' => Subscription::where('status', 'pending')->count(),
        ];

        $paymentStats = [
            'pending' => PaymentIntent::withoutTenantScope()->where('status', 'waiting_review')->count(),
            'approved' => PaymentIntent::withoutTenantScope()->whereIn('status', ['approved', 'paid', 'completed'])->count(),
            'rejected' => PaymentIntent::withoutTenantScope()->where('status', 'rejected')->count(),
            'completed' => PaymentIntent::withoutTenantScope()->where('status', 'completed')->count(),
        ];

        $rangeRevenue = (float) DB::table('orders')
            ->whereIn('order_status', ['confirmed', 'delivered', 'completed'])
            ->whereBetween('created_at', [$start, $end])
            ->sum('total_amount');

        $now = now();
        $monthRevenue = (float) DB::table('orders')
            ->whereIn('order_status', ['confirmed', 'delivered', 'completed'])
            ->whereYear('created_at', $now->year)
            ->whereMonth('created_at', $now->month)
            ->sum('total_amount');

        $yearRevenue = (float) DB::table('orders')
            ->whereIn('order_status', ['confirmed', 'delivered', 'completed'])
            ->whereYear('created_at', $now->year)
            ->sum('total_amount');

        $revenueSeries = $this->revenueSeries($start, $end);

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

        $recentTenants = Tenant::withCount('users')
            ->with('subscription.plan')
            ->latest()
            ->take(8)
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

        $recentPayments = PaymentIntent::withoutTenantScope()
            ->with(['tenant', 'plan'])
            ->latest()
            ->take(8)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'reference_number' => $p->reference_number,
                'tenant_name' => $p->tenant?->name,
                'payment_type' => $p->payment_type ?? $p->paymentType(),
                'payment_type_label' => $p->paymentTypeLabel(),
                'plan_name' => $p->plan?->name,
                'amount' => $p->amount,
                'currency' => $p->currency,
                'status' => $p->status,
                'created_at' => $p->created_at?->toDateTimeString(),
            ]);

        $recentActivity = ActivityLog::withoutTenantScope()
            ->latest()
            ->take(8)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'description' => $a->description,
                'event' => $a->event,
                'category' => $a->category,
                'created_at' => $a->created_at?->toDateTimeString(),
                'created_at_human' => $a->created_at?->diffForHumans(),
            ]);

        return Inertia::render('SuperAdmin/Dashboard', [
            'tenantStats' => $tenantStats,
            'subscriptionStats' => $subscriptionStats,
            'paymentStats' => $paymentStats,
            'monthlyRevenue' => $monthRevenue,
            'yearlyRevenue' => $yearRevenue,
            'rangeRevenue' => $rangeRevenue,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'revenueSeries' => $revenueSeries,
            'recentTenants' => $recentTenants,
            'recentPayments' => $recentPayments,
            'recentActivity' => $recentActivity,
            'subscriptionsByPlan' => $subscriptionsByPlan,
            'pendingActivationCount' => $tenantStats['pending'],
            'pendingPaymentCount' => $paymentStats['pending'],
        ]);
    }

    private function parseDate($value, Carbon $default, bool $end = false): Carbon
    {
        if (!$value) {
            return $default;
        }

        try {
            $date = Carbon::parse($value);
            return $end ? $date->endOfDay() : $date->startOfDay();
        } catch (\Throwable $e) {
            return $default;
        }
    }

    private function revenueSeries(Carbon $start, Carbon $end): array
    {
        $days = $start->diffInDays($end);

        $rows = DB::table('orders')
            ->selectRaw('DATE(created_at) as d, SUM(total_amount) as total')
            ->whereIn('order_status', ['confirmed', 'delivered', 'completed'])
            ->whereBetween('created_at', [$start, $end])
            ->groupByRaw('DATE(created_at)')
            ->get()
            ->keyBy('d');

        if ($days <= 60) {
            $series = [];
            $cursor = $start->copy();
            while ($cursor->lte($end)) {
                $key = $cursor->toDateString();
                $series[] = [
                    'label' => $cursor->format('M d'),
                    'value' => (float) ($rows[$key]->total ?? 0),
                ];
                $cursor->addDay();
            }
            return $series;
        }

        // Monthly aggregation
        $monthly = [];
        $cursor = $start->copy()->startOfMonth();
        while ($cursor->lte($end)) {
            $monthKey = $cursor->format('Y-m');
            $total = 0;
            foreach ($rows as $date => $row) {
                if (str_starts_with($date, $monthKey)) {
                    $total += (float) $row->total;
                }
            }
            $monthly[] = [
                'label' => $cursor->format('M Y'),
                'value' => $total,
            ];
            $cursor->addMonth();
        }
        return $monthly;
    }
}
