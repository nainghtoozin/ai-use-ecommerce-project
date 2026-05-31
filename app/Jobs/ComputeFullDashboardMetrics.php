<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ComputeFullDashboardMetrics implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600;

    private array $periods = ['today', 'last_7_days', 'last_30_days', 'this_month', 'last_month', 'this_year'];

    public function __construct(
        public ?int $tenantId = null
    ) {}

    public function handle(): void
    {
        if ($this->tenantId === null) {
            return;
        }

        foreach ($this->periods as $period) {
            $this->computeMetricsForPeriod($period);
        }

        $this->computeGeneralStats();
        $this->computeRevenueAnalytics();
    }

    private function computeMetricsForPeriod(string $period): void
    {
        $tenantSuffix = '_' . $this->tenantId;
        $dateRange = $this->getDateRangeFromPeriod($period);
        $start = $dateRange['start'];
        $end = $dateRange['end'];

        $previousRange = $this->getPreviousPeriod($period, $start, $end);

        $currentRevenue = DB::table('orders')
            ->where('tenant_id', $this->tenantId)
            ->where('order_status', 'completed')
            ->whereBetween('created_at', [$start, $end])
            ->sum('total_amount');

        $previousRevenue = DB::table('orders')
            ->where('tenant_id', $this->tenantId)
            ->where('order_status', 'completed')
            ->whereBetween('created_at', [$previousRange['start'], $previousRange['end']])
            ->sum('total_amount');

        $data = [
            'filteredOrdersCount' => DB::table('orders')
                ->where('tenant_id', $this->tenantId)
                ->whereBetween('created_at', [$start, $end])
                ->count(),
            'filteredRevenue' => $currentRevenue,
            'filteredSales' => DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.tenant_id', $this->tenantId)
                ->where('orders.order_status', 'completed')
                ->whereBetween('orders.created_at', [$start, $end])
                ->sum('order_items.quantity'),
            'filteredPendingOrders' => DB::table('orders')
                ->where('tenant_id', $this->tenantId)
                ->where('order_status', 'pending')
                ->whereBetween('created_at', [$start, $end])
                ->count(),
            'filteredVerifiedRevenue' => DB::table('orders')
                ->where('tenant_id', $this->tenantId)
                ->where('payment_status', 'verified')
                ->whereBetween('created_at', [$start, $end])
                ->sum('total_amount'),
            'growthPercentage' => $this->calculateGrowthPercentage($currentRevenue, $previousRevenue),
        ];

        Cache::put("dashboard_filtered_{$period}{$tenantSuffix}", $data, 600);
    }

    private function computeGeneralStats(): void
    {
        $tenantSuffix = '_' . $this->tenantId;

        $stats = [
            'total_products' => DB::table('products')
                ->where('tenant_id', $this->tenantId)
                ->count(),
            'total_orders' => DB::table('orders')
                ->where('tenant_id', $this->tenantId)
                ->count(),
            'total_revenue' => DB::table('orders')
                ->where('tenant_id', $this->tenantId)
                ->where('order_status', 'completed')
                ->sum('total_amount'),
            'total_sales' => DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.tenant_id', $this->tenantId)
                ->where('orders.order_status', 'completed')
                ->sum('order_items.quantity'),
        ];

        Cache::put('dashboard_general_stats_' . $tenantSuffix, (array) $stats, 600);
    }

    private function computeRevenueAnalytics(): void
    {
        $tenantSuffix = '_' . $this->tenantId;
        $now = Carbon::now();

        $baseQuery = DB::table('orders')
            ->where('tenant_id', $this->tenantId)
            ->where('order_status', 'completed');

        $revenueData = [
            'revenueToday' => (clone $baseQuery)->whereDate('created_at', $now)->sum('total_amount'),
            'revenueYesterday' => (clone $baseQuery)->whereDate('created_at', $now->copy()->yesterday())->sum('total_amount'),
            'revenueLast7Days' => (clone $baseQuery)->whereBetween('created_at', [$now->copy()->subDays(6), $now])->sum('total_amount'),
            'revenueLast30Days' => (clone $baseQuery)->whereBetween('created_at', [$now->copy()->subDays(29), $now])->sum('total_amount'),
            'revenueThisMonth' => (clone $baseQuery)->whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)->sum('total_amount'),
            'revenueLastMonth' => (clone $baseQuery)->whereMonth('created_at', $now->copy()->subMonth()->month)->whereYear('created_at', $now->copy()->subMonth()->year)->sum('total_amount'),
            'revenueThisYear' => (clone $baseQuery)->whereYear('created_at', $now->year)->sum('total_amount'),
        ];

        Cache::put('dashboard_revenue_analytics_' . $tenantSuffix, $revenueData, 600);
    }

    private function getDateRangeFromPeriod($period): array
    {
        $now = Carbon::now();
        return match ($period) {
            'today' => ['start' => $now->copy()->startOfDay(), 'end' => $now->copy()->endOfDay()],
            'last_7_days' => ['start' => $now->copy()->subDays(6)->startOfDay(), 'end' => $now->copy()->endOfDay()],
            'last_30_days' => ['start' => $now->copy()->subDays(29)->startOfDay(), 'end' => $now->copy()->endOfDay()],
            'this_month' => ['start' => $now->copy()->startOfMonth(), 'end' => $now->copy()->endOfMonth()],
            'last_month' => ['start' => $now->copy()->subMonth()->startOfMonth(), 'end' => $now->copy()->subMonth()->endOfMonth()],
            'this_year' => ['start' => $now->copy()->startOfYear(), 'end' => $now->copy()->endOfYear()],
            default => ['start' => $now->copy()->startOfDay(), 'end' => $now->copy()->endOfDay()],
        };
    }

    private function getPreviousPeriod($period, $start, $end): array
    {
        $start = Carbon::parse($start);
        $end = Carbon::parse($end);
        
        $length = $start->diffInDays($end) + 1;
        
        return [
            'start' => $start->copy()->subDays($length)->startOfDay(),
            'end' => $start->copy()->subDay()->endOfDay(),
        ];
    }

    private function calculateGrowthPercentage($current, $previous): float
    {
        if ($previous == 0) return $current > 0 ? 100 : 0;
        return round((($current - $previous) / $previous) * 100, 2);
    }
}