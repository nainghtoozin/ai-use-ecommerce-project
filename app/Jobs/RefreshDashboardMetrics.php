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

class RefreshDashboardMetrics implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private array $periods = [
        'today',
        'last_7_days',
        'last_30_days',
        'this_month',
        'last_month',
        'this_year',
    ];

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(
        public ?int $tenantId = null
    ) {}

    public function handle(): void
    {
        if ($this->tenantId === null) {
            return;
        }

        foreach ($this->periods as $period) {
            $this->computeAndCacheMetrics($period);
        }

        $this->computeGeneralStats();
    }

    private function computeAndCacheMetrics(string $period): void
    {
        $tenantSuffix = '_' . $this->tenantId;
        $dateRange = $this->getDateRangeFromPeriod($period);
        $start = $dateRange['start'];
        $end = $dateRange['end'];

        $previousRange = $this->getPreviousPeriod($period, $start, $end);
        $prevStart = $previousRange['start'];
        $prevEnd = $previousRange['end'];

        $filteredOrdersCount = DB::table('orders')
            ->where('tenant_id', $this->tenantId)
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $filteredRevenue = DB::table('orders')
            ->where('tenant_id', $this->tenantId)
            ->where('order_status', 'completed')
            ->whereBetween('created_at', [$start, $end])
            ->sum('total_amount');

        $filteredSales = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.tenant_id', $this->tenantId)
            ->where('orders.order_status', 'completed')
            ->whereBetween('orders.created_at', [$start, $end])
            ->sum('order_items.quantity');

        $filteredPendingOrders = DB::table('orders')
            ->where('tenant_id', $this->tenantId)
            ->where('order_status', 'pending')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $filteredVerifiedRevenue = DB::table('orders')
            ->where('tenant_id', $this->tenantId)
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$start, $end])
            ->sum('total_amount');

        $previousRevenue = DB::table('orders')
            ->where('tenant_id', $this->tenantId)
            ->where('order_status', 'completed')
            ->whereBetween('created_at', [$prevStart, $prevEnd])
            ->sum('total_amount');

        $growthPercentage = $this->calculateGrowthPercentage($filteredRevenue, $previousRevenue);

        $data = [
            'selectedPeriod' => $period,
            'startDate' => null,
            'endDate' => null,
            'fromCache' => true,
            'filteredOrdersCount' => $filteredOrdersCount,
            'filteredRevenue' => $filteredRevenue,
            'filteredSales' => $filteredSales,
            'filteredPendingOrders' => $filteredPendingOrders,
            'filteredVerifiedRevenue' => $filteredVerifiedRevenue,
            'growthPercentage' => $growthPercentage,
        ];

        Cache::put("dashboard_metrics_{$period}{$tenantSuffix}", $data, 600);
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

        Cache::put('dashboard_general_stats' . $tenantSuffix, (array) $stats, 600);
    }

    private function getDateRangeFromPeriod($period): array
    {
        $now = Carbon::now();
        
        switch ($period) {
            case 'today':
                return [
                    'start' => $now->copy()->startOfDay(),
                    'end' => $now->copy()->endOfDay(),
                ];
            case 'last_7_days':
                return [
                    'start' => $now->copy()->subDays(6)->startOfDay(),
                    'end' => $now->copy()->endOfDay(),
                ];
            case 'last_30_days':
                return [
                    'start' => $now->copy()->subDays(29)->startOfDay(),
                    'end' => $now->copy()->endOfDay(),
                ];
            case 'this_month':
                return [
                    'start' => $now->copy()->startOfMonth(),
                    'end' => $now->copy()->endOfMonth(),
                ];
            case 'last_month':
                return [
                    'start' => $now->copy()->subMonth()->startOfMonth(),
                    'end' => $now->copy()->subMonth()->endOfMonth(),
                ];
            case 'this_year':
                return [
                    'start' => $now->copy()->startOfYear(),
                    'end' => $now->copy()->endOfYear(),
                ];
            default:
                return [
                    'start' => $now->copy()->startOfDay(),
                    'end' => $now->copy()->endOfDay(),
                ];
        }
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
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }
        return round((($current - $previous) / $previous) * 100, 2);
    }
}