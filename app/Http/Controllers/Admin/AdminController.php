<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ComputeFullDashboardMetrics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Models\WebsiteInfo;
use Inertia\Inertia;

class AdminController extends Controller
{
    private $cacheTtl = 600;

    private function getCacheKey($period, $startDate = null, $endDate = null)
    {
        return "dashboard_metrics_{$period}_{$startDate}_{$endDate}";
    }

    public function index(Request $request)
    {
        $period = $request->input('period', 'today');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        
        $cacheKey = $this->getCacheKey($period, $startDate, $endDate);
        
        $cachedData = Cache::get($cacheKey);
        
        if ($cachedData && $period !== 'custom') {
            $cachedData['selectedPeriod'] = $period;
            $cachedData['startDate'] = $startDate;
            $cachedData['endDate'] = $endDate;
            $cachedData['fromCache'] = true;
            return Inertia::render('Admin/Dashboard', $cachedData);
        }

        $dateRange = $this->getDateRangeFromPeriod($period, $startDate, $endDate);
        $start = $dateRange['start'];
        $end = $dateRange['end'];

        $previousRange = $this->getPreviousPeriod($period, $start, $end);
        $prevStart = $previousRange['start'];
        $prevEnd = $previousRange['end'];

        $orders = \App\Models\Order::with(['user', 'items.product'])
            ->latest()
            ->take(10)
            ->get();
            
        $stats = DB::selectOne("
            SELECT 
                (SELECT COUNT(*) FROM products) AS total_products,
                (SELECT COUNT(*) FROM orders) AS total_orders,
                (SELECT IFNULL(SUM(total_amount),0) FROM orders WHERE order_status = 'completed') AS total_revenue,
                (SELECT IFNULL(SUM(oi.quantity),0)
                 FROM order_items oi
                 JOIN orders o ON o.id = oi.order_id
                 WHERE o.order_status = 'completed') AS total_sales
        ");

        $stats = (array) $stats;

        $filteredOrdersCount = DB::table('orders')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $filteredRevenue = DB::table('orders')
            ->where('order_status', 'completed')
            ->whereBetween('created_at', [$start, $end])
            ->sum('total_amount');

        $filteredSales = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.order_status', 'completed')
            ->whereBetween('orders.created_at', [$start, $end])
            ->sum('order_items.quantity');

        $filteredPendingOrders = DB::table('orders')
            ->where('order_status', 'pending')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $filteredVerifiedRevenue = DB::table('orders')
            ->where('payment_status', 'verified')
            ->whereBetween('created_at', [$start, $end])
            ->sum('total_amount');

        $previousRevenue = DB::table('orders')
            ->where('order_status', 'completed')
            ->whereBetween('created_at', [$prevStart, $prevEnd])
            ->sum('total_amount');

        $growthPercentage = $this->calculateGrowthPercentage($filteredRevenue, $previousRevenue);

        $revenueToday      = $this->getRevenueToday();
        $revenueYesterday  = $this->getRevenueYesterday();
        $revenueLast7Days  = $this->getRevenueLast7Days();
        $revenueLast30Days = $this->getRevenueLast30Days();
        $revenueThisMonth  = $this->getRevenueThisMonth();
        $revenueLastMonth  = $this->getRevenueLastMonth();
        $revenueThisYear   = $this->getRevenueThisYear();

        $netrevenueToday      = $this->getNetRevenueToday();
        $netrevenueYesterday  = $this->getNetRevenueYesterday();
        $netrevenueLast7Days  = $this->getNetRevenueLast7Days();
        $netrevenueLast30Days = $this->getNetRevenueLast30Days();
        $netrevenueThisMonth  = $this->getNetRevenueThisMonth();
        $netrevenueLastMonth  = $this->getNetRevenueLastMonth();
        $netrevenueThisYear   = $this->getNetRevenueThisYear();

        $growthTodayVsYesterday = $this->calculateGrowthPercentage($revenueToday, $revenueYesterday);
        $growthThisMonthVsLastMonth = $this->calculateGrowthPercentage($revenueThisMonth, $revenueLastMonth);
        $outOfStock = $this->getOutOfStockProducts();
        $lowStock = $this->getLowStockProducts(); 
        
        $pendingOrders = $this->getPendingOrdersCount();
        $verifiedRevenue = $this->getVerifiedRevenue();

        $topSelling = $this->getTopSellingProducts($start, $end);

        $activePromotions = \App\Models\Promotion::where('is_active', true)->count();

        $promotionDiscountsThisMonth = \App\Models\Order::whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->where('discount_amount', '>', 0)
            ->whereNotIn('order_status', ['cancelled', 'rejected'])
            ->sum('discount_amount');

        $mostUsedCoupon = DB::table('order_coupon')
            ->join('orders', 'orders.id', '=', 'order_coupon.order_id')
            ->select('order_coupon.coupon_id', 'order_coupon.code', DB::raw('COUNT(*) as usage_count'))
            ->whereNotIn('orders.order_status', ['cancelled', 'rejected'])
            ->groupBy('order_coupon.coupon_id', 'order_coupon.code')
            ->orderByDesc('usage_count')
            ->first();

        $mostUsedCouponData = null;
        if ($mostUsedCoupon) {
            $couponName = null;
            if ($mostUsedCoupon->coupon_id) {
                $coupon = \App\Models\Coupon::find($mostUsedCoupon->coupon_id);
                $couponName = $coupon?->name;
            }
            $mostUsedCouponData = [
                'code' => $mostUsedCoupon->code,
                'name' => $couponName ?? $mostUsedCoupon->code,
                'usage_count' => $mostUsedCoupon->usage_count,
            ];
        }

        $dashboardData = [
            'orders'           => $orders,
            'totalProducts'    => $stats['total_products'],
            'totalOrders'      => $stats['total_orders'],
            'totalRevenue'     => $stats['total_revenue'],
            'totalSales'       => $stats['total_sales'],
            'revenueToday'     => $revenueToday,
            'revenueYesterday' => $revenueYesterday,
            'revenueLast7Days' => $revenueLast7Days,
            'revenueLast30Days'=> $revenueLast30Days,
            'revenueThisMonth' => $revenueThisMonth,
            'revenueLastMonth' => $revenueLastMonth,
            'revenueThisYear'  => $revenueThisYear,
            'netrevenueToday'     => $netrevenueToday,
            'netrevenueYesterday' => $netrevenueYesterday,
            'netrevenueLast7Days' => $netrevenueLast7Days,
            'netrevenueLast30Days'=> $netrevenueLast30Days,
            'netrevenueThisMonth' => $netrevenueThisMonth,
            'netrevenueLastMonth' => $netrevenueLastMonth,
            'netrevenueThisYear'  => $netrevenueThisYear,
            'topSelling'       => $topSelling,
            'growthTodayVsYesterday' => $growthTodayVsYesterday,
            'growthThisMonthVsLastMonth' => $growthThisMonthVsLastMonth,
            'outOfStock' => $outOfStock,
            'lowStock'   => $lowStock,
            'pendingOrders' => $pendingOrders,
            'verifiedRevenue' => $verifiedRevenue,
            'activePromotions' => $activePromotions,
            'promotionDiscountsThisMonth' => round($promotionDiscountsThisMonth, 2),
            'mostUsedCoupon' => $mostUsedCouponData,
            'selectedPeriod' => $period,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'filteredOrdersCount' => $filteredOrdersCount,
            'filteredRevenue' => $filteredRevenue,
            'filteredSales' => $filteredSales,
            'filteredPendingOrders' => $filteredPendingOrders,
            'filteredVerifiedRevenue' => $filteredVerifiedRevenue,
            'growthPercentage' => $growthPercentage,
        ];

        if ($period !== 'custom') {
            Cache::put($cacheKey, $dashboardData, $this->cacheTtl);
        }

        return Inertia::render('Admin/Dashboard', $dashboardData);
    }

    private function getDateRangeFromPeriod($period, $startDate = null, $endDate = null)
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
            case 'custom':
                if ($startDate && $endDate) {
                    return [
                        'start' => Carbon::parse($startDate)->startOfDay(),
                        'end' => Carbon::parse($endDate)->endOfDay(),
                    ];
                }
                return [
                    'start' => $now->copy()->startOfDay(),
                    'end' => $now->copy()->endOfDay(),
                ];
            default:
                return [
                    'start' => $now->copy()->startOfDay(),
                    'end' => $now->copy()->endOfDay(),
                ];
        }
    }

    private function getPreviousPeriod($period, $start, $end)
    {
        $startCarbon = $start instanceof Carbon ? $start : Carbon::parse($start);
        $endCarbon = $end instanceof Carbon ? $end : Carbon::parse($end);
        
        $diff = $startCarbon->diffInDays($endCarbon);
        
        return [
            'start' => $startCarbon->subDays($diff + 1)->startOfDay(),
            'end' => $startCarbon->copy()->endOfDay(),
        ];
    }

    public function showLogin()
    {
        return Inertia::render('Admin/Auth/Login');
    }


    // Advanced Revenue Calcualtion
       //  Revenue Today
    private function getRevenueToday()
    {
        return DB::table('orders')
            ->where('order_status', 'completed')
            ->whereDate('created_at', Carbon::today())
            ->sum('total_amount');
    }

    //  Revenue Yesterday
    private function getRevenueYesterday()
    {
        return DB::table('orders')
            ->where('order_status', 'completed')
            ->whereDate('created_at', Carbon::yesterday())
            ->sum('total_amount');
    }

    //  Revenue Last 7 Days (including today)
    private function getRevenueLast7Days()
    {
        return DB::table('orders')
            ->where('order_status', 'completed')
            ->whereBetween('created_at', [Carbon::now()->subDays(6), Carbon::now()])
            ->sum('total_amount');
    }

    //  Revenue Last 30 Days (including today)
    private function getRevenueLast30Days()
    {
        return DB::table('orders')
            ->where('order_status', 'completed')
            ->whereBetween('created_at', [Carbon::now()->subDays(29), Carbon::now()])
            ->sum('total_amount');
    }

    //  Revenue This Month
    private function getRevenueThisMonth()
    {
        return DB::table('orders')
            ->where('order_status', 'completed')
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->sum('total_amount');
    }

    //  Revenue Last Month
    private function getRevenueLastMonth()
    {
        $lastMonth = Carbon::now()->subMonth();

        return DB::table('orders')
            ->where('order_status', 'completed')
            ->whereMonth('created_at', $lastMonth->month)
            ->whereYear('created_at', $lastMonth->year)
            ->sum('total_amount');
    }

    //  Revenue This Year
    private function getRevenueThisYear()
    {
        return DB::table('orders')
            ->where('order_status', 'completed')
            ->whereYear('created_at', Carbon::now()->year)
            ->sum('total_amount');
    }

    //  Top Selling Products (paginated)
    private function getTopSellingProducts($startDate = null, $endDate = null)
    {
        $query = DB::table('order_items AS oi')
            ->join('orders AS o', 'o.id', '=', 'oi.order_id')
            ->join('products AS p', 'p.id', '=', 'oi.product_id')
            ->select('p.id', 'p.name', DB::raw('SUM(oi.quantity) AS total_sold'))
            ->where('o.order_status', 'completed')
            ->groupBy('p.id', 'p.name')
            ->orderByDesc('total_sold');

        if ($startDate && $endDate) {
            $query->whereBetween('o.created_at', [$startDate, $endDate]);
        }

        return $query->paginate(5);
    }

    // Calcualet Growth percentage
    private function calculateGrowthPercentage($current, $previous)
    {
        // Avoid division by zero
        if ($previous == 0) {
            // If there was no previous value but now there is a value, growth is infinite — we'll just return 100
            return $current > 0 ? 100 : 0;
        }

        // Calculate percentage difference
        $growth = (($current - $previous) / $previous) * 100;

        // Round for cleaner output
        return round($growth, 2);
    }

    // Products Out of Stock
    private function getOutOfStockProducts()
    {
        return DB::table('products')
            ->where('stock', 0)
            ->select('id', 'name', 'stock','photo1')
            ->orderBy('name')
            ->paginate(5);
    }

    // Products Low in Stock (less than 10)
    private function getLowStockProducts($threshold = 10)
    {
        return DB::table('products')
            ->where('stock', '<', $threshold)
            ->where('stock', '>', 0) // exclude already out of stock
            ->select('id', 'name', 'stock','photo1')
            ->orderBy('stock')
            ->paginate(5);
    }

    private function getPendingOrdersCount()
    {
        return DB::table('orders')
            ->where('order_status', 'pending')
            ->count();
    }

    private function getVerifiedRevenue()
    {
        return DB::table('orders')
            ->where('payment_status', 'verified')
            ->sum('total_amount');
    }


    private function getNetRevenueToday()
    {
        $startOfDay = Carbon::today()->startOfDay(); // 00:00:00
        $endOfDay = Carbon::today()->endOfDay();     // 23:59:59

        return $this->calculateNetRevenue($startOfDay, $endOfDay);
    }

    private function getNetRevenueYesterday()
    {
        $startOfYesterday = Carbon::yesterday()->startOfDay(); // 00:00:00
        $endOfYesterday = Carbon::yesterday()->endOfDay();     // 23:59:59

        return $this->calculateNetRevenue($startOfYesterday, $endOfYesterday);
    }

    private function getNetRevenueLast7Days()
    {
        $startDate = Carbon::now()->subDays(6)->startOfDay(); // 6 days ago, 00:00:00
        $endDate   = Carbon::now()->endOfDay();               // today, 23:59:59

        return $this->calculateNetRevenue($startDate, $endDate);
    }

    private function getNetRevenueLast30Days()
    {
        $startDate = Carbon::now()->subDays(29)->startOfDay(); // 29 days ago, 00:00:00
        $endDate   = Carbon::now()->endOfDay();               // today, 23:59:59

        return $this->calculateNetRevenue($startDate, $endDate);
    }

    private function getNetRevenueThisYear()
    {
        $start = Carbon::now()->startOfYear();
        $end = Carbon::now()->endOfYear();
        return $this->calculateNetRevenue($start, $end);
    }
    
    private function getNetRevenueThisMonth()
    {
        $start = Carbon::now()->startOfMonth();
        $end = Carbon::now()->endOfMonth();
        return $this->calculateNetRevenue($start, $end);
    }

    private function getNetRevenueLastMonth()
    {
        $start = Carbon::now()->subMonth()->startOfMonth();
        $end = Carbon::now()->subMonth()->endOfMonth();
        return $this->calculateNetRevenue($start, $end);
    }

    /**
     * Core calculation method
     */
    private function calculateNetRevenue($startDate, $endDate)
    {
        $orders = \App\Models\Order::with('items.product')
            ->where('order_status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $netRevenue = 0;

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $profit = ($item->product->price - $item->product->base_price) * $item->quantity;
                $netRevenue += $profit;
            }
        }

        return $netRevenue;
    }
}
