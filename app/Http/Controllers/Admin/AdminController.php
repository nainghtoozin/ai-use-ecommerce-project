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
        $tz = config('app.timezone');

        // ── Recent orders (cached) ──
        //     Only the last 10; with minimal columns + eager load.
        //     Index on created_at DESC is critical once the table exceeds ~100k rows.
        $orders = Cache::remember('dashboard_recent_orders_v2', $ttl, fn() =>
            Order::with('user:id,name')
                ->select('id', 'user_id', 'customer_name', 'first_name', 'last_name', 'total_amount', 'order_status', 'created_at')
                ->orderByDesc('created_at')
                ->take(10)
                ->get()
        );

        // ── Inventory summary (cached) ──
        //     Aggregates total + low-stock counts in a single table scan.
        //     Low-stock / out-of-stock detail lists are separate but use
        //     LIMIT 5 so they never scan more than a handful of rows.
        $inventory = Cache::remember('dashboard_inventory_v2', $ttl, function () {
            $counts = Product::selectRaw('COUNT(*) as total_products')
                ->selectRaw("COALESCE(SUM(CASE WHEN stock > 0 AND stock < 10 THEN 1 ELSE 0 END), 0) as low_stock_count")
                ->first();

            return [
                'totalProducts' => (int) $counts->total_products,
                'lowStockCount' => (int) $counts->low_stock_count,
                'lowStock' => [
                    'data' => Product::select('id', 'name', 'stock', 'photo1')
                        ->where('stock', '>', 0)->where('stock', '<', 10)
                        ->orderBy('stock')->take(5)->get(),
                ],
                'outOfStock' => [
                    'data' => Product::select('id', 'name', 'stock', 'photo1')
                        ->where('stock', 0)->orderBy('name')->take(5)->get(),
                ],
            ];
        });

        // ── Date range ──
        $now = Carbon::now($tz);
        $range = $this->getDateRange($period, $startDate, $endDate, $now);
        $start = $range['start'];
        $end = $range['end'];

        // ── Per-period stats (cached) ──
        $suffix = $this->cacheSuffix($period, $startDate, $endDate);

        $filteredStats = Cache::remember("dashboard_stats{$suffix}", $ttl, fn() =>
            $this->computeStats($start, $end)
        );

        // ── Payment method breakdown (cached) ──
        $paymentMethodSummary = Cache::remember("dashboard_pm{$suffix}", $ttl, fn() =>
            $this->computePaymentSummary($start, $end)
        );

        return Inertia::render('Admin/Dashboard', array_merge(
            $filteredStats,
            $inventory,
            [
                'orders'               => $orders,
                'paymentMethodSummary' => $paymentMethodSummary,
                'selectedPeriod'       => $period,
                'startDate'            => $startDate,
                'endDate'              => $endDate,
            ]
        ));
    }

    /**
     * Single-pass aggregation for the four summary cards.
     * One table scan, four metrics, zero subqueries.
     */
    private function computeStats($start, $end): array
    {
        $stats = DB::table('orders')
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('COUNT(*) as filtered_orders_count')
            ->selectRaw("COALESCE(SUM(CASE WHEN payment_status = 'verified' OR order_status = 'confirmed' THEN total_amount ELSE 0 END), 0) as total_received_payments")
            ->selectRaw("COALESCE(SUM(CASE WHEN order_status = 'pending' THEN 1 ELSE 0 END), 0) as filtered_pending_orders")
            ->selectRaw('COUNT(DISTINCT user_id) as filtered_customers')
            ->first();

        return [
            'filteredOrdersCount'   => (int) $stats->filtered_orders_count,
            'totalReceivedPayments' => (float) $stats->total_received_payments,
            'filteredPendingOrders' => (int) $stats->filtered_pending_orders,
            'filteredCustomers'     => (int) $stats->filtered_customers,
        ];
    }

    /**
     * Per-payment-method aggregation.
     * JOIN on the tiny payment_methods table is negligible.
     */
    private function computePaymentSummary($start, $end)
    {
        return DB::table('orders')
            ->join('payment_methods', 'orders.payment_method_id', '=', 'payment_methods.id')
            ->whereBetween('orders.created_at', [$start, $end])
            ->whereNotNull('orders.payment_method_id')
            ->select('payment_methods.name', 'payment_methods.bank_name')
            ->selectRaw("COALESCE(SUM(CASE WHEN orders.payment_status = 'verified' OR orders.order_status = 'confirmed' THEN orders.total_amount ELSE 0 END), 0) as total")
            ->groupBy('orders.payment_method_id', 'payment_methods.name', 'payment_methods.bank_name')
            ->orderByDesc(DB::raw('total'))
            ->get();
    }

    /**
     * Deterministic period-to-date-range conversion.
     * Always uses the configured app timezone so that "today" means
     * today in the business's local time, matching how created_at
     * is stored (Laravel stores in app timezone by default).
     */
    private function getDateRange(string $period, ?string $startDate, ?string $endDate, Carbon $now): array
    {
        return match ($period) {
            'today'        => [
                'start' => $now->copy()->startOfDay(),
                'end'   => $now->copy()->endOfDay(),
            ],
            'last_7_days'  => [
                'start' => $now->copy()->subDays(6)->startOfDay(),
                'end'   => $now->copy()->endOfDay(),
            ],
            'last_30_days' => [
                'start' => $now->copy()->subDays(29)->startOfDay(),
                'end'   => $now->copy()->endOfDay(),
            ],
            'this_month'   => [
                'start' => $now->copy()->startOfMonth(),
                'end'   => $now->copy()->endOfMonth(),
            ],
            'last_month'   => [
                'start' => $now->copy()->subMonth()->startOfMonth(),
                'end'   => $now->copy()->subMonth()->endOfMonth(),
            ],
            'this_year'    => [
                'start' => $now->copy()->startOfYear(),
                'end'   => $now->copy()->endOfYear(),
            ],
            'custom'       => $startDate && $endDate
                ? [
                    'start' => Carbon::parse($startDate, $now->getTimezone())->startOfDay(),
                    'end'   => Carbon::parse($endDate, $now->getTimezone())->endOfDay(),
                ]
                : [
                    'start' => $now->copy()->startOfDay(),
                    'end'   => $now->copy()->endOfDay(),
                ],
            default        => [
                'start' => $now->copy()->startOfDay(),
                'end'   => $now->copy()->endOfDay(),
            ],
        };
    }

    /**
     * Short deterministic suffix for per-period cache keys.
     */
    private function cacheSuffix(string $period, ?string $startDate, ?string $endDate): string
    {
        if ($period === 'custom' && $startDate && $endDate) {
            return "_{$period}_{$startDate}_{$endDate}";
        }
        return "_{$period}";
    }

    public function showLogin()
    {
        return Inertia::render('Admin/Auth/Login');
    }
}
