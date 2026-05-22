<?php

namespace App\Services;

use App\Jobs\ComputeFullDashboardMetrics;
use Illuminate\Support\Facades\Cache;

class DashboardCacheService
{
    private array $periods = [
        'today',
        'last_7_days',
        'last_30_days',
        'this_month',
        'last_month',
        'this_year',
    ];

    public function clearAllDashboardCache(): void
    {
        foreach ($this->periods as $period) {
            $this->clearCacheForPeriod($period);
        }
        Cache::forget('dashboard_metrics_custom__');
    }

    public function clearCacheForPeriod(string $period): void
    {
        Cache::forget("dashboard_metrics_{$period}__");
        Cache::forget("dashboard_metrics_{$period}_null_null");
    }

    public function clearOrderRelatedCache(): void
    {
        $this->clearAllDashboardCache();
        Cache::forget('sales_report_summary_default');
        ComputeFullDashboardMetrics::dispatch()->onQueue('default');
    }

    public function clearProductRelatedCache(): void
    {
        Cache::forget('dashboard_metrics_today__');
        ComputeFullDashboardMetrics::dispatch()->onQueue('default');
    }

    public function refreshAllMetrics(): void
    {
        ComputeFullDashboardMetrics::dispatch()->onQueue('default');
    }

    public function getCacheKey(string $period, ?string $startDate = null, ?string $endDate = null): string
    {
        $start = $startDate ?? '';
        $end = $endDate ?? '';
        return "dashboard_metrics_{$period}_{$start}_{$end}";
    }
}