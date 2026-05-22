<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AdminReportController extends Controller
{
    private array $perPageOptions = [25, 50, 100, 500, 1000];
    private int $defaultPerPage = 25;
    private int $cacheTTL = 300;

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
