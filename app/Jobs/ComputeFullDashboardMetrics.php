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

    public function handle(): void
    {
        foreach ($this->periods as $period) {
            $this->computeMetricsForPeriod($period);
        }

        $this->computeGeneralStats();
        $this->computeRevenueAnalytics();
    }

    private function computeMetricsForPeriod(string $period): void
    {
        $dateRange = $this->getDateRangeFromPeriod($period);
        $start = $dateRange['start'];
        $end = $dateRange['end'];

        $previousRange = $this->getPreviousPeriod($period, $start, $end);

        $data = [
            'filteredOrdersCount' => DB::table('orders')->whereBetween('created_at', [$start, $end])->count(),
            'filteredRevenue' => DB::table('orders')->where('order_status', 'completed')->whereBetween('created_at', [$start, $end])->sum('total_amount'),
            'filteredSales' => DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')->where('orders.order_status', 'completed')->whereBetween('orders.created_at', [$start, $end])->sum('order_items.quantity'),
            'filteredPendingOrders' => DB::table('orders')->where('order_status', 'pending')->whereBetween('created_at', [$start, $end])->count(),
            'filteredVerifiedRevenue' => DB::table('orders')->where('payment_status', 'verified')->whereBetween('created_at', [$start, $end])->sum('total_amount'),
            'growthPercentage' => $this->calculateGrowthPercentage(
                DB::table('orders')->where('order_status', 'completed')->whereBetween('created_at', [$start, $end])->sum('total_amount'),
                DB::table('orders')->where('order_status', 'completed')->whereBetween('created_at', [$previousRange['start'], $previousRange['end']])->sum('total_amount')
            ),
        ];

        Cache::put("dashboard_filtered_{$period}_", $data, 600);
    }

    private function computeGeneralStats(): void
    {
        $stats = DB::selectOne("
            SELECT 
                (SELECT COUNT(*) FROM products) AS total_products,
                (SELECT COUNT(*) FROM orders) AS total_orders,
                (SELECT IFNULL(SUM(total_amount),0) FROM orders WHERE order_status = 'completed') AS total_revenue,
                (SELECT IFNULL(SUM(oi.quantity),0) FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE o.order_status = 'completed') AS total_sales
        ");

        Cache::put('dashboard_general_stats_', (array) $stats, 600);
    }

    private function computeRevenueAnalytics(): void
    {
        $now = Carbon::now();

        $revenueData = [
            'revenueToday' => DB::table('orders')->where('order_status', 'completed')->whereDate('created_at', $now)->sum('total_amount'),
            'revenueYesterday' => DB::table('orders')->where('order_status', 'completed')->whereDate('created_at', $now->copy()->yesterday())->sum('total_amount'),
            'revenueLast7Days' => DB::table('orders')->where('order_status', 'completed')->whereBetween('created_at', [$now->copy()->subDays(6), $now])->sum('total_amount'),
            'revenueLast30Days' => DB::table('orders')->where('order_status', 'completed')->whereBetween('created_at', [$now->copy()->subDays(29), $now])->sum('total_amount'),
            'revenueThisMonth' => DB::table('orders')->where('order_status', 'completed')->whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)->sum('total_amount'),
            'revenueLastMonth' => DB::table('orders')->where('order_status', 'completed')->whereMonth('created_at', $now->copy()->subMonth()->month)->whereYear('created_at', $now->copy()->subMonth()->year)->sum('total_amount'),
            'revenueThisYear' => DB::table('orders')->where('order_status', 'completed')->whereYear('created_at', $now->year)->sum('total_amount'),
        ];

        Cache::put('dashboard_revenue_analytics_', $revenueData, 600);
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
        $diff = Carbon::parse($start)->diffInDays(Carbon::parse($end));
        return [
            'start' => Carbon::parse($start)->subDays($diff + 1)->startOfDay(),
            'end' => Carbon::parse($start)->copy()->endOfDay(),
        ];
    }

    private function calculateGrowthPercentage($current, $previous): float
    {
        if ($previous == 0) return $current > 0 ? 100 : 0;
        return round((($current - $previous) / $previous) * 100, 2);
    }
}