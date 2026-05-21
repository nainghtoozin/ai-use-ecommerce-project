<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Inertia\Inertia;

class AdminController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->input('period', 'today');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $ttl = 300;

        $orders = Cache::remember('dashboard_recent_orders', $ttl, function () {
            return Order::with('user:id,name')
                ->select('id', 'user_id', 'customer_name', 'first_name', 'last_name', 'total_amount', 'order_status', 'created_at')
                ->latest()
                ->take(10)
                ->get();
        });

        $inventory = Cache::remember('dashboard_inventory', $ttl, function () {
            return [
                'totalProducts' => DB::table('products')->count(),
                'lowStockCount' => DB::table('products')
                    ->where('stock', '<', 10)
                    ->where('stock', '>', 0)
                    ->count(),
                'lowStock' => [
                    'data' => Product::select('id', 'name', 'stock', 'photo1')
                        ->where('stock', '<', 10)
                        ->where('stock', '>', 0)
                        ->orderBy('stock')
                        ->take(5)
                        ->get(),
                ],
                'outOfStock' => [
                    'data' => Product::select('id', 'name', 'stock', 'photo1')
                        ->where('stock', 0)
                        ->orderBy('name')
                        ->take(5)
                        ->get(),
                ],
            ];
        });

        $cacheKey = 'dashboard_stats';
        if ($period === 'custom' && $startDate && $endDate) {
            $cacheKey .= "_custom_{$startDate}_{$endDate}";
        } else {
            $cacheKey .= "_{$period}";
        }

        $filteredStats = Cache::remember($cacheKey, $ttl, function () use ($period, $startDate, $endDate) {
            $dateRange = $this->getDateRangeFromPeriod($period, $startDate, $endDate);
            $start = $dateRange['start'];
            $end = $dateRange['end'];

            $stats = DB::table('orders')
                ->whereBetween('created_at', [$start, $end])
                ->select(DB::raw("
                    COUNT(*) as filtered_orders_count,
                    COALESCE(SUM(CASE WHEN order_status = 'completed' THEN total_amount ELSE 0 END), 0) as filtered_revenue,
                    COALESCE(SUM(CASE WHEN order_status = 'pending' THEN 1 ELSE 0 END), 0) as filtered_pending_orders,
                    COUNT(DISTINCT user_id) as filtered_customers
                "))
                ->first();

            return [
                'filteredOrdersCount' => (int) $stats->filtered_orders_count,
                'filteredRevenue' => (float) $stats->filtered_revenue,
                'filteredPendingOrders' => (int) $stats->filtered_pending_orders,
                'filteredCustomers' => (int) $stats->filtered_customers,
            ];
        });

        return Inertia::render('Admin/Dashboard', array_merge(
            $filteredStats,
            $inventory,
            [
                'orders' => $orders,
                'selectedPeriod' => $period,
                'startDate' => $startDate,
                'endDate' => $endDate,
            ]
        ));
    }

    private function getDateRangeFromPeriod($period, $startDate = null, $endDate = null)
    {
        $now = Carbon::now();

        switch ($period) {
            case 'today':
                return ['start' => $now->copy()->startOfDay(), 'end' => $now->copy()->endOfDay()];
            case 'last_7_days':
                return ['start' => $now->copy()->subDays(6)->startOfDay(), 'end' => $now->copy()->endOfDay()];
            case 'last_30_days':
                return ['start' => $now->copy()->subDays(29)->startOfDay(), 'end' => $now->copy()->endOfDay()];
            case 'this_month':
                return ['start' => $now->copy()->startOfMonth(), 'end' => $now->copy()->endOfMonth()];
            case 'last_month':
                return ['start' => $now->copy()->subMonth()->startOfMonth(), 'end' => $now->copy()->subMonth()->endOfMonth()];
            case 'this_year':
                return ['start' => $now->copy()->startOfYear(), 'end' => $now->copy()->endOfYear()];
            case 'custom':
                if ($startDate && $endDate) {
                    return ['start' => Carbon::parse($startDate)->startOfDay(), 'end' => Carbon::parse($endDate)->endOfDay()];
                }
                return ['start' => $now->copy()->startOfDay(), 'end' => $now->copy()->endOfDay()];
            default:
                return ['start' => $now->copy()->startOfDay(), 'end' => $now->copy()->endOfDay()];
        }
    }

    public function showLogin()
    {
        return Inertia::render('Admin/Auth/Login');
    }
}
