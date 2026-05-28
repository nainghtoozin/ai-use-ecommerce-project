<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class DashboardCacheService
{
    public function clearOrderRelatedCache(): void
    {
        Cache::forget('sales_report_summary_default');
    }

    public function clearProductRelatedCache(): void
    {
        //
    }

    public function getCacheKey(string $period, ?string $startDate = null, ?string $endDate = null): string
    {
        $start = $startDate ?? '';
        $end = $endDate ?? '';
        return "dashboard_metrics_{$period}_{$start}_{$end}";
    }
}