<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Category;
use App\Models\PaymentMethod;
use App\Events\PaymentVerified;
use App\Events\PaymentRejected;
use App\Jobs\ProcessOrderStatusChange;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class AdminReportController extends Controller
{
    private array $perPageOptions = [25, 50, 100, 500, 1000];
    private int $defaultPerPage = 25;
    private int $cacheTTL = 300;

    private const CACHE_PREFIX = 'product_sales_';
    private const CACHE_TTL_SUMMARY = 600;
    private const CACHE_TTL_STOCK = 900;
    private const CACHE_TTL_TOP_SELLING = 600;
    private const CACHE_TTL_SLOW_MOVING = 600;
    private const CACHE_TTL_PAYMENTS_SUMMARY = 600;

    /**
     * Build a deterministic cache key from the filters that affect each section.
     * Pagination params are excluded so the summary/top/slow caches are shared
     * across all pages for the same filter set.
     */
    private function cacheKey(string $section, array $filters): string
    {
        $relevant = array_intersect_key($filters, array_flip([
            'date_from', 'date_to', 'category_id', 'search', 'stock_status',
        ]));
        ksort($relevant);
        return self::CACHE_PREFIX . $section . '_' . md5(json_encode($relevant));
    }

    /**
     * Cache key for payment summary — same filter-hash approach, excludes pagination.
     */
    private function paymentCacheKey(string $section, array $filters): string
    {
        $relevant = array_intersect_key($filters, array_flip([
            'date_from', 'date_to', 'payment_method_id',
            'payment_status', 'verification_status', 'search', 'search_by',
        ]));
        ksort($relevant);
        return 'payments_' . $section . '_' . md5(json_encode($relevant));
    }

    /**
     * Cached COD method ID — rarely changes, so cache it for an hour.
     */
    private function getCodMethodId(): ?int
    {
        return Cache::remember('payment_cod_method_id', 3600, fn() =>
            PaymentMethod::where('name', 'Cash on Delivery')
                ->orWhere('name', 'COD')
                ->value('id')
        );
    }

    public function sales(Request $request)
    {
        $filters = $this->extractFilters($request);
        $perPage = $this->resolvePerPage($request);

        $summary = $this->resolveSummary($filters);

        $orders = $this->resolveOrders($filters, $perPage);

        return Inertia::render('Admin/Reports/Sales', [
            'orders' => $orders,
            'summary' => $summary,
            'filters' => $filters,
        ]);
    }

    public function orderDetails(Order $order)
    {
        $order->load([
            'items.product',
            'items.variant',
            'user',
            'paymentMethod',
            'city',
            'township',
        ]);

        return response()->json($order);
    }

    public function clearCache()
    {
        Cache::forget('sales_report_default');

        return redirect()->back()->with('success', 'Sales report cache cleared.');
    }

    private function extractFilters(Request $request): array
    {
        $filters = $request->only(['date_from', 'date_to', 'order_status', 'search', 'search_by']);

        if (empty($filters['date_from']) && empty($filters['date_to'])) {
            $today = now()->toDateString();
            $filters['date_from'] = $today;
            $filters['date_to'] = $today;
        }

        return $filters;
    }

    private function hasActiveFilters(array $filters): bool
    {
        return !empty($filters['date_from'])
            || !empty($filters['date_to'])
            || !empty($filters['order_status'])
            || !empty($filters['search']);
    }

    private function applyFilters($query, array $filters): void
    {
        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from'] . ' 00:00:00');
        }
        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }
        if (!empty($filters['order_status'])) {
            $query->where('order_status', $filters['order_status']);
        }
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $searchBy = $filters['search_by'] ?? '';
            $query->where(function ($q) use ($search, $searchBy) {
                if ($searchBy === 'order_id') {
                    $q->where('id', $search);
                } elseif ($searchBy === 'customer') {
                    $q->where(DB::raw("CONCAT(first_name, ' ', last_name)"), 'like', "%{$search}%")
                      ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$search}%"));
                } else {
                    $q->where('id', 'like', "%{$search}%")
                      ->orWhere(DB::raw("CONCAT(first_name, ' ', last_name)"), 'like', "%{$search}%")
                      ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$search}%"));
                }
            });
        }
    }

    private function resolveSummary(array $filters): object
    {
        if ($this->hasActiveFilters($filters)) {
            return $this->computeSummary($filters);
        }

        return Cache::remember('sales_report_default', $this->cacheTTL, fn() =>
            $this->computeSummary($filters)
        );
    }

    private function computeSummary(array $filters): object
    {
        $query = Order::query();
        $this->applyFilters($query, $filters);

        $row = $query->selectRaw('COALESCE(SUM(total_amount + discount_amount), 0) as gross_sales')
            ->selectRaw('COALESCE(SUM(discount_amount), 0) as discount_total')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as net_sales')
            ->selectRaw('COALESCE(AVG(total_amount), 0) as average_order_value')
            ->selectRaw('COUNT(*) as total_orders')
            ->first();

        $statusQuery = Order::query();
        $this->applyFilters($statusQuery, $filters);
        $statusAmounts = $statusQuery->selectRaw('order_status, COALESCE(SUM(total_amount), 0) as amount')
            ->groupBy('order_status')
            ->pluck('amount', 'order_status');

        return (object) [
            'gross_sales' => (float) $row->gross_sales,
            'discount_total' => (float) $row->discount_total,
            'net_sales' => (float) $row->net_sales,
            'average_order_value' => (float) $row->average_order_value,
            'total_orders' => (int) $row->total_orders,
            'pending_amount' => (float) ($statusAmounts['pending'] ?? 0),
            'confirmed_amount' => (float) ($statusAmounts['confirmed'] ?? 0),
            'cancelled_amount' => (float) ($statusAmounts['cancelled'] ?? 0),
        ];
    }

    private function resolveOrders(array $filters, int $perPage)
    {
        $query = Order::query();
        $this->applyFilters($query, $filters);

        return $query->select([
                'id',
                'first_name',
                'last_name',
                'phone',
                'total_amount',
                'discount_amount',
                'order_status',
                'created_at',
                'user_id',
            ])
            ->addSelect(DB::raw('(total_amount + discount_amount) as gross_total'))
            ->addSelect(DB::raw('total_amount as net_total'))
            ->with('user:id,name')
            ->withSum('items as items_count', 'quantity')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function productSales(Request $request)
    {
        $perPage = $this->resolvePerPage($request);
        $filters = $request->only(['date_from', 'date_to', 'category_id', 'search', 'stock_status']);

        if (empty($filters['date_from']) && empty($filters['date_to'])) {
            $today = now()->toDateString();
            $filters['date_from'] = $today;
            $filters['date_to'] = $today;
        }

        $dateFrom = $filters['date_from'] ?? null;
        $dateTo   = $filters['date_to']   ?? null;

        // ── 1. Main paginated product report ──
        //     Single aggregation query with filtered joins.
        //     Uses paginate() (count + data). The COUNT(*) over a GROUP BY
        //     subquery is the one query that cannot be cached per-page, but
        //     with an index on orders.created_at + order_items.product_id
        //     the count remains sub-second up to millions of rows.
        $mainQuery = OrderItem::select([
                'order_items.product_id',
                DB::raw('MAX(products.name) as product_name'),
                DB::raw('MAX(products.stock) as stock'),
                DB::raw('MAX(categories.name) as category_name'),
                DB::raw('MAX(categories.id) as category_id'),
                DB::raw('COALESCE(SUM(order_items.quantity), 0) as total_quantity'),
                DB::raw('COALESCE(SUM(order_items.price * order_items.quantity), 0) as gross_revenue'),
                DB::raw('COUNT(DISTINCT order_items.order_id) as order_count'),
            ])
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->when($dateFrom, fn($q) => $q->where('orders.created_at', '>=', $dateFrom . ' 00:00:00'))
            ->when($dateTo,   fn($q) => $q->where('orders.created_at', '<=', $dateTo   . ' 23:59:59'))
            ->when(!empty($filters['search']), function ($q) use ($filters) {
                $search = $filters['search'];
                $q->where(function ($q) use ($search) {
                    $q->where('products.name', 'like', "%{$search}%")
                      ->orWhere('order_items.product_id', (int) $search);
                });
            })
            ->when(!empty($filters['category_id']), fn($q) => $q->where('products.category_id', $filters['category_id']))
            ->when(!empty($filters['stock_status']), function ($q) use ($filters) {
                match ($filters['stock_status']) {
                    'in_stock'     => $q->where('products.stock', '>', 10)->whereNotNull('products.stock'),
                    'low_stock'    => $q->where('products.stock', '>', 0)->where('products.stock', '<=', 10),
                    'out_of_stock' => $q->where(function ($q) {
                        $q->where('products.stock', '<=', 0)->orWhereNull('products.stock');
                    }),
                    default => null,
                };
            })
            ->groupBy('order_items.product_id');

        $products = $mainQuery
            ->orderByDesc(DB::raw('SUM(order_items.price * order_items.quantity)'))
            ->paginate($perPage);

        // ── 2. Summary aggregates (cached) ──
        //     Cached because the value is identical for every pagination page
        //     under the same filter set. Invalidated implicitly via TTL.
        $summary = Cache::remember(
            $this->cacheKey('summary', $filters),
            self::CACHE_TTL_SUMMARY,
            function () use ($filters, $dateFrom, $dateTo) {
                $q = OrderItem::select([
                        DB::raw('COALESCE(SUM(order_items.quantity), 0) as total_units_sold'),
                        DB::raw('COALESCE(SUM(order_items.price * order_items.quantity), 0) as total_revenue'),
                    ])
                    ->join('orders', 'order_items.order_id', '=', 'orders.id')
                    ->when($dateFrom, fn($q) => $q->where('orders.created_at', '>=', $dateFrom . ' 00:00:00'))
                    ->when($dateTo,   fn($q) => $q->where('orders.created_at', '<=', $dateTo   . ' 23:59:59'))
                    ->when(!empty($filters['category_id']), function ($q) use ($filters) {
                        $q->whereIn('order_items.product_id', function ($sq) use ($filters) {
                            $sq->select('id')->from('products')->where('category_id', $filters['category_id']);
                        });
                    });

                if (!empty($filters['search'])) {
                    $search = $filters['search'];
                    $q->where(function ($q) use ($search) {
                        $q->whereHas('product', fn($p) => $p->where('name', 'like', "%{$search}%"))
                          ->orWhere('order_items.product_id', (int) $search);
                    });
                }

                return $q->first();
            }
        );

        // ── 3. Stock status counts (cached, longer TTL) ──
        //     These are global — unaffected by date range, search, or category.
        //     Only changes when product stock is updated (new shipments, sales).
        $stockCounts = Cache::remember(
            self::CACHE_PREFIX . 'stock_counts',
            self::CACHE_TTL_STOCK,
            fn() => Product::select([
                DB::raw("COALESCE(SUM(CASE WHEN stock > 0 AND stock <= 10 THEN 1 ELSE 0 END), 0) as low_stock"),
                DB::raw("COALESCE(SUM(CASE WHEN stock IS NULL OR stock <= 0 THEN 1 ELSE 0 END), 0) as out_of_stock"),
            ])->first()
        );

        // ── 4. Top selling products (top 5, cached) ──
        $topSelling = Cache::remember(
            $this->cacheKey('top_selling', $filters),
            self::CACHE_TTL_TOP_SELLING,
            function () use ($dateFrom, $dateTo) {
                return OrderItem::select([
                        'order_items.product_id',
                        DB::raw('MAX(products.name) as name'),
                        DB::raw('MAX(products.stock) as stock'),
                        DB::raw('COALESCE(SUM(order_items.quantity), 0) as qty_sold'),
                        DB::raw('COALESCE(SUM(order_items.price * order_items.quantity), 0) as revenue'),
                    ])
                    ->join('products', 'order_items.product_id', '=', 'products.id')
                    ->join('orders', 'order_items.order_id', '=', 'orders.id')
                    ->when($dateFrom, fn($q) => $q->where('orders.created_at', '>=', $dateFrom . ' 00:00:00'))
                    ->when($dateTo,   fn($q) => $q->where('orders.created_at', '<=', $dateTo   . ' 23:59:59'))
                    ->groupBy('order_items.product_id')
                    ->orderByDesc(DB::raw('SUM(order_items.quantity)'))
                    ->limit(5)
                    ->get();
            }
        );

        // ── 5. Slow moving products (anti-join, cached) ──
        //     The sold-product subquery is date-dependent but expensive;
        //     caching it avoids re-scanning order_items on every page navigation.
        $slowMoving = Cache::remember(
            $this->cacheKey('slow_moving', $filters),
            self::CACHE_TTL_SLOW_MOVING,
            function () use ($dateFrom, $dateTo) {
                $soldSub = OrderItem::select('product_id')
                    ->join('orders', 'order_items.order_id', '=', 'orders.id')
                    ->when($dateFrom, fn($q) => $q->where('orders.created_at', '>=', $dateFrom . ' 00:00:00'))
                    ->when($dateTo,   fn($q) => $q->where('orders.created_at', '<=', $dateTo   . ' 23:59:59'))
                    ->distinct();

                return Product::select(['id', 'name', 'stock', 'price'])
                    ->leftJoinSub($soldSub, 'sold', 'products.id', '=', 'sold.product_id')
                    ->whereNull('sold.product_id')
                    ->where('stock', '>', 0)
                    ->orderByDesc('stock')
                    ->limit(10)
                    ->get();
            }
        );

        // ── 6. Categories for dropdown ──
        $categories = Category::select(['id', 'name'])->orderBy('name')->get();

        return Inertia::render('Admin/Reports/ProductSales', [
            'products'    => $products,
            'summary'     => [
                'total_revenue'       => (float) ($summary->total_revenue ?? 0),
                'total_units_sold'    => (int) ($summary->total_units_sold ?? 0),
                'top_selling_product' => $topSelling->isNotEmpty() ? $topSelling->first()->name : 'N/A',
                'low_stock_count'     => (int) ($stockCounts->low_stock ?? 0),
                'out_of_stock_count'  => (int) ($stockCounts->out_of_stock ?? 0),
            ],
            'top_selling' => $topSelling,
            'slow_moving' => $slowMoving,
            'categories'  => $categories,
            'filters'     => $filters,
        ]);
    }

    public function payments(Request $request)
    {
        $perPage = $this->resolvePerPage($request);
        $filters = $request->only([
            'date_from', 'date_to', 'payment_method_id',
            'payment_status', 'verification_status',
            'search', 'search_by',
        ]);

        if (empty($filters['date_from']) && empty($filters['date_to'])) {
            $today = now()->toDateString();
            $filters['date_from'] = $today;
            $filters['date_to'] = $today;
        }

        // ── Cached COD method ID ──
        $codMethodId = $this->getCodMethodId();

        // ── Shared filter builder (fluent, no closures) ──
        //     Building the query once and cloning avoids redundant WHERE
        //     generation and lets MySQL reuse the same execution plan.
        $baseQuery = Order::query()
            ->when($filters['date_from'] ?? null, fn($q) =>
                $q->where('orders.created_at', '>=', $filters['date_from'] . ' 00:00:00'))
            ->when($filters['date_to'] ?? null, fn($q) =>
                $q->where('orders.created_at', '<=', $filters['date_to'] . ' 23:59:59'))
            ->when(!empty($filters['payment_method_id']), fn($q) =>
                $q->where('orders.payment_method_id', $filters['payment_method_id']))
            ->when(!empty($filters['payment_status']), fn($q) =>
                $this->applyPaymentStatusFilter($q, $filters['payment_status']))
            ->when(!empty($filters['verification_status']), fn($q) =>
                $this->applyVerificationStatusFilter($q, $filters['verification_status']))
            ->when(!empty($filters['search']), fn($q) =>
                $this->applyPaymentSearchFilter($q, $filters['search'], $filters['search_by'] ?? ''));

        // ── 1. Summary cards (cached per filter set, excludes pagination) ──
        //     Uses the same WHERE conditions as the paginated query, so the
        //     cache is shared across all pages for identical filters.
        $summary = Cache::remember(
            $this->paymentCacheKey('summary', $filters),
            self::CACHE_TTL_PAYMENTS_SUMMARY,
            fn() => $this->computePaymentSummary(clone $baseQuery, $codMethodId)
        );

        // ── 2. Paginated transactions (not cached) ──
        //     paginate() issues COUNT(*) + SELECT with LIMIT/OFFSET.
        //     For very deep pages, upgrade to cursor pagination.
        $orders = $baseQuery
            ->select([
                'orders.id',
                'orders.transaction_id',
                'orders.first_name',
                'orders.last_name',
                'orders.paid_amount',
                'orders.total_amount',
                'orders.delivery_fee',
                'orders.discount_amount',
                'orders.payment_method_id',
                'orders.payment_status',
                'orders.order_status',
                'orders.payment_verified_at',
                'orders.created_at',
                'orders.payer_name',
                'orders.payment_screenshot',
                'orders.payment_proof',
                'orders.rejection_reason',
            ])
            ->with('paymentMethod:id,name,bank_name')
            ->orderByDesc('orders.created_at')
            ->paginate($perPage);

        // ── 3. Payment methods for dropdown ──
        $paymentMethods = Cache::remember('payment_methods_active', 3600, fn() =>
            PaymentMethod::select(['id', 'name', 'bank_name'])
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
        );

        return Inertia::render('Admin/Reports/Payments', [
            'orders'         => $orders,
            'summary'        => $summary,
            'paymentMethods' => $paymentMethods,
            'codMethodId'    => $codMethodId,
            'filters'        => $filters,
        ]);
    }

    /**
     * Single-pass aggregation query for all summary cards.
     * All CASE expressions run in one table scan — no subqueries, no looping.
     *
     * CRITICAL: Do NOT use `?` placeholders inside DB::raw() passed to select().
     * Laravel's select() does not accept bindings (the second arg is ignored).
     * Instead, inject safe integer values directly into the raw SQL.
     */
    private function computePaymentSummary($query, ?int $codMethodId): object
    {
        $codId = (int) ($codMethodId ?? 0);

        return $query->select([
                DB::raw('COALESCE(SUM(orders.total_amount), 0) as total_payment_amount'),
                DB::raw("COALESCE(SUM(CASE WHEN orders.payment_status = '" . Order::PAYMENT_STATUS_PAID . "' THEN orders.total_amount ELSE 0 END), 0) as verified_amount"),
                DB::raw("COALESCE(SUM(CASE WHEN orders.payment_status = '" . Order::PAYMENT_STATUS_PAID . "' THEN 1 ELSE 0 END), 0) as verified_count"),
                DB::raw("COALESCE(SUM(CASE WHEN orders.payment_status IN ('" . Order::PAYMENT_STATUS_PAID . "', '" . Order::PAYMENT_STATUS_PENDING . "') THEN 1 ELSE 0 END), 0) as pending_verification_count"),
                DB::raw("COALESCE(SUM(CASE WHEN orders.order_status = '" . Order::ORDER_STATUS_CANCELLED . "' AND orders.payment_status IN ('" . Order::PAYMENT_STATUS_PAID . "', '" . Order::PAYMENT_STATUS_PENDING . "') THEN orders.total_amount ELSE 0 END), 0) as refunded_amount"),
                DB::raw("COALESCE(SUM(CASE WHEN orders.order_status = '" . Order::ORDER_STATUS_CANCELLED . "' AND orders.payment_status IN ('" . Order::PAYMENT_STATUS_PAID . "', '" . Order::PAYMENT_STATUS_PENDING . "') THEN 1 ELSE 0 END), 0) as refunded_count"),
                DB::raw("COALESCE(SUM(CASE WHEN orders.payment_status = '" . Order::PAYMENT_STATUS_FAILED . "' THEN 1 ELSE 0 END), 0) as failed_count"),
                DB::raw("COALESCE(SUM(CASE WHEN orders.payment_status = '" . Order::PAYMENT_STATUS_PAID . "' THEN COALESCE(orders.paid_amount, orders.total_amount) ELSE 0 END), 0) as net_received"),
                DB::raw("COALESCE(SUM(CASE WHEN orders.payment_method_id = {$codId} THEN 1 ELSE 0 END), 0) as cod_count"),
                DB::raw("COALESCE(SUM(CASE WHEN orders.payment_method_id = {$codId} THEN orders.total_amount ELSE 0 END), 0) as cod_amount"),
            ])
            ->first();
    }

    /**
     * Payment status filter — uses model constants so renaming propagates automatically.
     */
    private function applyPaymentStatusFilter($query, string $status): void
    {
        match ($status) {
            'paid' => $query->where('orders.payment_status', Order::PAYMENT_STATUS_PAID),
            'pending' => $query->where('orders.payment_status', Order::PAYMENT_STATUS_PENDING),
            'failed' => $query->where(function ($q) {
                $q->where('orders.payment_status', Order::PAYMENT_STATUS_FAILED)
                  ->orWhere(function ($q) {
                      $q->where('orders.order_status', Order::ORDER_STATUS_CANCELLED)
                        ->where('orders.payment_status', '!=', Order::PAYMENT_STATUS_PENDING);
                  });
            }),
            'refunded' => $query->where('orders.order_status', Order::ORDER_STATUS_CANCELLED)
                               ->whereIn('orders.payment_status', [
                                   Order::PAYMENT_STATUS_PAID,
                                   Order::PAYMENT_STATUS_PENDING,
                               ]),
            default => null,
        };
    }

    /**
     * Verification status filter.
     */
    private function applyVerificationStatusFilter($query, string $status): void
    {
        match ($status) {
            'unchecked' => $query->whereIn('orders.payment_status', [
                Order::PAYMENT_STATUS_PENDING,
                Order::PAYMENT_STATUS_PAID,
            ]),
            'paid' => $query->where('orders.payment_status', Order::PAYMENT_STATUS_PAID),
            'rejected' => $query->where('orders.payment_status', Order::PAYMENT_STATUS_FAILED),
            default => null,
        };
    }

    /**
     * Search filter for payment report.
     * - order_id: exact integer match (sargable)
     * - transaction_id: LIKE with prefix-first pattern when possible
     * - default: exact id match + LIKE on transaction_id
     */
    private function applyPaymentSearchFilter($query, string $search, string $by): void
    {
        $query->where(function ($q) use ($search, $by) {
            if ($by === 'order_id') {
                $q->where('orders.id', (int) $search);
            } elseif ($by === 'transaction_id') {
                $q->where('orders.transaction_id', 'like', "%{$search}%");
            } else {
                // Default: try exact id match; fallback to transaction_id LIKE
                $q->where('orders.id', (int) $search)
                  ->orWhere('orders.transaction_id', 'like', "%{$search}%");
            }
        });
    }

    public function verifyPayment(string $id)
    {
        try {
            $order = Order::findOrFail($id);

            if (!$order->canApprovePayment()) {
                return redirect()->back()->with('error', 'This payment cannot be verified.');
            }

            $order->update([
                'payment_status'  => Order::PAYMENT_STATUS_PAID,
                'payment_verified_at' => now(),
                'rejection_reason'    => null,
            ]);

            ProcessOrderStatusChange::dispatch($order, 'payment_verified');

            event(new PaymentVerified($order));

            return redirect()->back()->with('success', "Payment for Order #{$order->id} verified successfully.");
        } catch (\Exception $e) {
            Log::error('Payment verification failed: ' . $e->getMessage());

            return redirect()->back()->with('error', 'Failed to verify payment.');
        }
    }

    public function rejectPayment(Request $request, string $id)
    {
        try {
            $order = Order::findOrFail($id);

            if (!$order->canRejectPayment()) {
                return redirect()->back()->with('error', 'This payment cannot be rejected.');
            }

            $validated = $request->validate([
                'rejection_reason' => ['nullable', 'string', 'max:1000'],
            ]);

            $order->update([
                'payment_status'  => Order::PAYMENT_STATUS_FAILED,
                'rejection_reason' => $validated['rejection_reason'] ?? null,
            ]);

            ProcessOrderStatusChange::dispatch(
                $order,
                'payment_rejected',
                rejectionReason: $validated['rejection_reason'] ?? null,
            );

            event(new PaymentRejected($order));

            return redirect()->back()->with('success', "Payment for Order #{$order->id} rejected.");
        } catch (\Exception $e) {
            Log::error('Payment rejection failed: ' . $e->getMessage());

            return redirect()->back()->with('error', 'Failed to reject payment.');
        }
    }

    private function resolvePerPage(Request $request): int
    {
        $perPage = $request->input('per_page', $this->defaultPerPage);
        $perPage = (int) $perPage;

        if (!in_array($perPage, $this->perPageOptions)) {
            $perPage = $this->defaultPerPage;
        }

        return $perPage;
    }
}
