<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\OnboardingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Inertia\Inertia;

class AdminController extends Controller
{
    public function index(Request $request)
    {
        if (!auth()->user()->can('dashboard.view')) {
            abort(403, 'Unauthorized');
        }

        $period = $request->input('period', 'today');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $tz = config('app.timezone');
        if ($period === 'custom') {
            $request->validate([
                'start_date' => ['required', 'date'],
                'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            ]);
        }
        $now = Carbon::now($tz);
        $range = $this->getDateRange($period, $startDate, $endDate, $now);
        $start = $range['start'];
        $end = $range['end'];

        $orders = Order::with('user:id,name')
            ->select('id', 'user_id', 'customer_name', 'first_name', 'last_name', 'total_amount', 'paid_amount', 'payment_status', 'order_status', 'created_at')
            ->whereBetween('created_at', [$start, $end])
            ->orderByDesc('created_at')
            ->take(10)
            ->get();

        $inventory = (function () {
            $products = Product::query()
                ->withSum(['variants as variant_total_stock' => function ($q) {
                    $q->where('status', ProductVariant::STATUS_ACTIVE);
                }], 'stock')
                ->get(['id', 'name', 'stock', 'type', 'photo1']);

            $rows = $products->map(function (Product $product) {
                $stock = $product->isVariable()
                    ? (float) ($product->variant_total_stock ?? 0)
                    : (float) $product->getEffectiveStock();

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'stock' => $stock,
                    'photo1' => $product->photo1,
                    'photo1_url' => $product->photo1_url,
                ];
            });

            return [
                'totalProducts' => $rows->count(),
                'lowStockCount' => $rows->where('stock', '>', 0)->where('stock', '<', 10)->count(),
                'lowStock' => [
                    'data' => $rows->where('stock', '>', 0)->where('stock', '<', 10)
                        ->sortBy('stock')->take(5)->values(),
                ],
                'outOfStock' => [
                    'data' => $rows->where('stock', '<=', 0)
                        ->sortBy('name')->take(5)->values(),
                ],
            ];
        })();

        // ── Per-period stats ──
        $filteredStats = $this->computeStats($start, $end);

        // ── Payment method breakdown ──
        $paymentMethodSummary = $this->computePaymentSummary($start, $end);

        return Inertia::render('Admin/Dashboard', array_merge(
            $filteredStats,
            $inventory,
            [
                'orders'               => $orders,
                'paymentMethodSummary' => $paymentMethodSummary,
                'selectedPeriod'       => $period,
                'startDate'            => $startDate,
                'endDate'              => $endDate,
                'onboarding'           => $this->getOnboardingData($request),
            ]
        ));
    }

    private function getOnboardingData(Request $request): ?array
    {
        $tenant = tenant();
        if (!$tenant) {
            return null;
        }

        $user = $request->user();
        if (!$user instanceof Account) {
            return null;
        }

        if (!$user->isOwner()) {
            return null;
        }

        return app(OnboardingService::class)->getOnboardingData($tenant, $user);
    }

    public function dismissOnboarding(Request $request)
    {
        $tenant = tenant();
        if (!$tenant) {
            return back();
        }

        app(OnboardingService::class)->dismiss($tenant);

        return back();
    }

    public function resetOnboarding(Request $request)
    {
        $tenant = tenant();
        if (!$tenant) {
            return back();
        }

        app(OnboardingService::class)->resetOnboarding($tenant);

        return back();
    }

    /**
     * Single-pass aggregation for the four summary cards.
     * One table scan, four metrics, zero subqueries.
     */
    private function computeStats($start, $end): array
    {
        $stats = DB::table('orders')
            ->when(tenant(), fn($q, $t) => $q->where('orders.tenant_id', $t->id))
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw("COALESCE(SUM(CASE WHEN order_status IN ('confirmed', 'processing', 'shipped', 'delivered') THEN 1 ELSE 0 END), 0) as filtered_orders_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN COALESCE(paid_amount, total_amount) ELSE 0 END), 0) as total_received_payments")
            ->selectRaw("COALESCE(SUM(CASE WHEN order_status != 'cancelled' AND (order_status = 'pending' OR payment_status = 'pending') THEN 1 ELSE 0 END), 0) as filtered_pending_orders")
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
            ->when(tenant(), fn($q, $t) => $q->where('orders.tenant_id', $t->id))
             ->whereBetween('orders.created_at', [$start, $end])
             ->whereNotNull('orders.payment_method_id')
             ->where('orders.payment_status', Order::PAYMENT_STATUS_PAID)
            ->select('payment_methods.name', 'payment_methods.bank_name')
             ->selectRaw("COALESCE(SUM(CASE WHEN orders.payment_status = 'paid' THEN COALESCE(orders.paid_amount, orders.total_amount) ELSE 0 END), 0) as total")
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

    public function onboardingSettings(Request $request)
    {
        $tenant = tenant();
        if (!$tenant) {
            return redirect()->route('admin.dashboard');
        }

        $user = $request->user();
        $onboarding = null;

        if ($user instanceof Account && $user->isOwner()) {
            $service = app(OnboardingService::class);
            $onboarding = $service->getSetupGuideData($tenant, $user);
        }

        return Inertia::render('Admin/Settings/SetupGuide', [
            'onboarding' => $onboarding,
        ]);
    }

    public function showLogin()
    {
        return Inertia::render('Admin/Auth/Login');
    }
}
