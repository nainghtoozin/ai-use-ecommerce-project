<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Models\PromotionUsage;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class AdminPromotionReportController extends Controller
{
    public function index()
    {
        if (!auth()->user()->can('reports.orders')) {
            abort(403, 'Unauthorized');
        }

        return Inertia::render('Admin/Promotions/Reports', [
            'promotions' => Promotion::select('id', 'name', 'code')->orderBy('name')->get(),
            'products' => Product::select('id', 'name')->orderBy('name')->get(),
            'categories' => Category::select('id', 'name')->orderBy('name')->get(),
        ]);
    }

    public function getData(Request $request)
    {
        if (!auth()->user()->can('reports.orders')) {
            abort(403, 'Unauthorized');
        }

        $startDate = $request->input('start_date') ? Carbon::parse($request->start_date)->startOfDay() : Carbon::now()->subMonth()->startOfDay();
        $endDate = $request->input('end_date') ? Carbon::parse($request->end_date)->endOfDay() : Carbon::now()->endOfDay();
        $promotionId = $request->input('promotion_id');
        $productId = $request->input('product_id');
        $categoryId = $request->input('category_id');

        $orderBase = Order::whereBetween('created_at', [$startDate, $endDate])
            ->whereNotIn('order_status', ['cancelled']);

        if ($productId) {
            $orderBase->whereHas('items', fn($q) => $q->where('product_id', $productId));
        }

        if ($categoryId) {
            $orderBase->whereHas('items.product', fn($q) => $q->where('category_id', $categoryId));
        }

        $discountedOrdersQuery = clone $orderBase;
        $discountedOrdersQuery->where('discount_amount', '>', 0);

        if ($promotionId) {
            $discountedOrdersQuery->where('promotion_id', $promotionId);
        }

        $totalDiscountsGiven = (clone $discountedOrdersQuery)->sum('discount_amount');
        $ordersUsingPromotions = (clone $discountedOrdersQuery)->count();

        $allOrdersCount = (clone $orderBase)->count();
        $allOrdersRevenue = (clone $orderBase)->sum('total_amount');

        $discountedOrdersRevenue = (clone $discountedOrdersQuery)->sum('total_amount');
        $discountedOrdersCount = $ordersUsingPromotions;

        $nonDiscountedOrdersCount = max(0, $allOrdersCount - $discountedOrdersCount);
        $nonDiscountedOrdersRevenue = max(0, $allOrdersRevenue - $discountedOrdersRevenue);

        $conversionRate = $allOrdersCount > 0 ? round(($discountedOrdersCount / $allOrdersCount) * 100, 1) : 0;

        $avgDiscountPerOrder = $discountedOrdersCount > 0 ? round($totalDiscountsGiven / $discountedOrdersCount, 2) : 0;

        $revenueImpactPercent = $allOrdersRevenue > 0 ? round(($totalDiscountsGiven / ($allOrdersRevenue + $totalDiscountsGiven)) * 100, 1) : 0;

        $topPromotions = $this->getTopPromotions($startDate, $endDate, $promotionId, $productId, $categoryId);

        $couponUsage = $this->getCouponUsage($startDate, $endDate, $productId, $categoryId);

        $dailyDiscountTrend = $this->getDailyDiscountTrend($startDate, $endDate, $promotionId, $productId, $categoryId);

        $promotionTypeBreakdown = $this->getPromotionTypeBreakdown($startDate, $endDate, $productId, $categoryId);

        $monthlyComparison = $this->getMonthlyComparison($startDate, $endDate);

        return response()->json([
            'summary' => [
                'total_discounts_given' => round($totalDiscountsGiven, 2),
                'orders_using_promotions' => $ordersUsingPromotions,
                'all_orders_count' => $allOrdersCount,
                'conversion_rate' => $conversionRate,
                'avg_discount_per_order' => $avgDiscountPerOrder,
                'revenue_impact_percent' => $revenueImpactPercent,
                'discounted_revenue' => round($discountedOrdersRevenue, 2),
                'non_discounted_revenue' => round($nonDiscountedOrdersRevenue, 2),
                'non_discounted_orders' => $nonDiscountedOrdersCount,
                'all_revenue' => round($allOrdersRevenue, 2),
            ],
            'top_promotions' => $topPromotions,
            'coupon_usage' => $couponUsage,
            'daily_trend' => $dailyDiscountTrend,
            'type_breakdown' => $promotionTypeBreakdown,
            'monthly_comparison' => $monthlyComparison,
        ]);
    }

    private function getTopPromotions($startDate, $endDate, $promotionId, $productId, $categoryId)
    {
        $query = PromotionUsage::select(
            'promotion_id',
            DB::raw('COUNT(*) as usage_count'),
            DB::raw('SUM(discount_amount) as total_discount'),
            DB::raw('COUNT(DISTINCT user_id) as unique_users')
        )
            ->whereBetween('used_at', [$startDate, $endDate])
            ->groupBy('promotion_id')
            ->orderByDesc('total_discount');

        if ($promotionId) {
            $query->where('promotion_id', $promotionId);
        }

        if ($productId || $categoryId) {
            $query->whereHas('order', function ($q) use ($productId, $categoryId) {
                if ($productId) {
                    $q->whereHas('items', fn($i) => $i->where('product_id', $productId));
                }
                if ($categoryId) {
                    $q->whereHas('items.product', fn($p) => $p->where('category_id', $categoryId));
                }
            });
        }

        return $query->with('promotion:id,name,code,type,value')
            ->take(10)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->promotion_id,
                    'name' => $item->promotion?->name ?? 'Deleted',
                    'code' => $item->promotion?->code,
                    'type' => $item->promotion?->type,
                    'value' => $item->promotion?->value,
                    'usage_count' => $item->usage_count,
                    'total_discount' => round($item->total_discount, 2),
                    'unique_users' => $item->unique_users,
                    'avg_discount' => $item->usage_count > 0 ? round($item->total_discount / $item->usage_count, 2) : 0,
                ];
            });
    }

    private function getCouponUsage($startDate, $endDate, $productId, $categoryId)
    {
        $query = DB::table('order_coupon')
            ->join('orders', 'orders.id', '=', 'order_coupon.order_id')
            ->select(
                'order_coupon.coupon_id',
                'order_coupon.code',
                'order_coupon.type',
                DB::raw('COUNT(*) as usage_count'),
                DB::raw('SUM(order_coupon.discount_amount) as total_discount')
            )
            ->where('orders.tenant_id', tenant()?->id)
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->whereNotIn('orders.order_status', ['cancelled'])
            ->groupBy('order_coupon.coupon_id', 'order_coupon.code', 'order_coupon.type')
            ->orderByDesc('total_discount');

        if ($productId) {
            $query->whereExists(function ($q) use ($productId) {
                $q->select(DB::raw(1))
                    ->from('order_items')
                    ->whereColumn('order_items.order_id', 'orders.id')
                    ->where('order_items.product_id', $productId);
            });
        }

        if ($categoryId) {
            $query->whereExists(function ($q) use ($categoryId) {
                $q->select(DB::raw(1))
                    ->from('order_items')
                    ->join('products', 'products.id', '=', 'order_items.product_id')
                    ->whereColumn('order_items.order_id', 'orders.id')
                    ->where('products.category_id', $categoryId);
            });
        }

        return $query->take(10)->get()->map(function ($item) {
            $couponName = null;
            if ($item->coupon_id) {
                $coupon = Coupon::find($item->coupon_id);
                $couponName = $coupon?->name;
            }
            return [
                'coupon_id' => $item->coupon_id,
                'code' => $item->code,
                'name' => $couponName ?? $item->code,
                'type' => $item->type,
                'usage_count' => $item->usage_count,
                'total_discount' => round($item->total_discount, 2),
            ];
        });
    }

    private function getDailyDiscountTrend($startDate, $endDate, $promotionId, $productId, $categoryId)
    {
        $query = Order::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as order_count'),
            DB::raw('SUM(discount_amount) as total_discount'),
            DB::raw('SUM(total_amount) as revenue')
        )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('discount_amount', '>', 0)
            ->whereNotIn('order_status', ['cancelled'])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date');

        if ($promotionId) {
            $query->where('promotion_id', $promotionId);
        }

        if ($productId) {
            $query->whereHas('items', fn($q) => $q->where('product_id', $productId));
        }

        if ($categoryId) {
            $query->whereHas('items.product', fn($q) => $q->where('category_id', $categoryId));
        }

        return $query->get()->map(function ($item) {
            return [
                'date' => $item->date,
                'order_count' => (int) $item->order_count,
                'total_discount' => round($item->total_discount, 2),
                'revenue' => round($item->revenue, 2),
            ];
        });
    }

    private function getPromotionTypeBreakdown($startDate, $endDate, $productId, $categoryId)
    {
        $query = DB::table('promotion_usages')
            ->join('promotions', 'promotions.id', '=', 'promotion_usages.promotion_id')
            ->select(
                'promotions.type',
                DB::raw('COUNT(*) as usage_count'),
                DB::raw('SUM(promotion_usages.discount_amount) as total_discount')
            )
            ->where('promotions.tenant_id', tenant()?->id)
            ->whereBetween('promotion_usages.used_at', [$startDate, $endDate])
            ->groupBy('promotions.type');

        if ($productId || $categoryId) {
            $query->whereExists(function ($q) use ($productId, $categoryId) {
                $q->select(DB::raw(1))
                    ->from('orders')
                    ->whereColumn('orders.id', 'promotion_usages.order_id')
                     ->whereNotIn('orders.order_status', ['cancelled']);
                if ($productId) {
                    $q->whereExists(function ($sq) use ($productId) {
                        $sq->select(DB::raw(1))
                            ->from('order_items')
                            ->whereColumn('order_items.order_id', 'orders.id')
                            ->where('order_items.product_id', $productId);
                    });
                }
                if ($categoryId) {
                    $q->whereExists(function ($sq) use ($categoryId) {
                        $sq->select(DB::raw(1))
                            ->from('order_items')
                            ->join('products', 'products.id', '=', 'order_items.product_id')
                            ->whereColumn('order_items.order_id', 'orders.id')
                            ->where('products.category_id', $categoryId);
                    });
                }
            });
        }

        return $query->get()->map(function ($item) {
            return [
                'type' => $item->type,
                'usage_count' => (int) $item->usage_count,
                'total_discount' => round($item->total_discount, 2),
            ];
        });
    }

    private function getMonthlyComparison($startDate, $endDate)
    {
        $months = collect();

        $start = Carbon::parse($startDate)->startOfMonth();
        $end = Carbon::parse($endDate)->startOfMonth();

        while ($start <= $end) {
            $monthStart = $start->copy()->startOfMonth();
            $monthEnd = $start->copy()->endOfMonth();

            $orders = Order::whereBetween('created_at', [$monthStart, $monthEnd])
                ->whereNotIn('order_status', ['cancelled']);

            $discounted = (clone $orders)->where('discount_amount', '>', 0);

            $totalOrders = (clone $orders)->count();
            $totalRevenue = (clone $orders)->sum('total_amount');
            $discountedOrders = (clone $discounted)->count();
            $totalDiscounts = (clone $discounted)->sum('discount_amount');

            $months->push([
                'month' => $monthStart->format('Y-m'),
                'label' => $monthStart->format('M Y'),
                'total_orders' => $totalOrders,
                'discounted_orders' => $discountedOrders,
                'total_revenue' => round($totalRevenue, 2),
                'total_discounts' => round($totalDiscounts, 2),
                'conversion_rate' => $totalOrders > 0 ? round(($discountedOrders / $totalOrders) * 100, 1) : 0,
            ]);

            $start->addMonth();
        }

        return $months;
    }
}
