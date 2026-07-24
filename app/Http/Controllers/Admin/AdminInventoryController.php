<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\FeatureGate;
use App\Services\StockCalculationService;
use App\Services\StockMovementService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminInventoryController extends Controller
{
    public function __construct(
        private readonly StockCalculationService $calculator,
        private readonly StockMovementService $movements,
    ) {}

    public function dashboard()
    {
        if (!FeatureGate::enabled('inventory_management')) {
            return redirect()->back()->with('feature_locked', [
                'feature' => FeatureGate::getLabelStatic('inventory_management'),
                'required_plan' => FeatureGate::getUpgradeHintStatic('inventory_management') ?? 'Starter',
            ]);
        }

        if (!auth()->user()->can('inventory.view')) {
            abort(403, 'Unauthorized');
        }

        $stats = $this->getStats();

        $recentMovements = StockMovement::with(['product:id,name,sku,type,unit_id', 'variant:id,sku'])
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($m) {
                return [
                    'id' => $m->id,
                    'product_name' => $m->product?->name ?? 'Deleted Product',
                    'product_sku' => $m->product?->sku,
                    'type' => $m->type,
                    'quantity' => (float) $m->quantity,
                    'description' => $m->description,
                    'created_at' => $m->created_at->toDateTimeString(),
                ];
            });

        $recentActivity = ActivityLog::where('tenant_id', tenantId())
            ->where(function ($q) {
                $q->where('log_name', 'like', '%inventory%')
                  ->orWhere('log_name', 'like', '%stock%')
                  ->orWhere('log_name', 'like', '%product%');
            })
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'description' => $log->description,
                    'event' => $log->event,
                    'created_at' => $log->created_at->toDateTimeString(),
                ];
            });

        return Inertia::render('Admin/Inventory/Dashboard', [
            'stats' => $stats,
            'recentMovements' => $recentMovements,
            'recentActivity' => $recentActivity,
        ]);
    }

    public function index(Request $request)
    {
        if (!FeatureGate::enabled('inventory_management')) {
            return redirect()->back()->with('feature_locked', [
                'feature' => FeatureGate::getLabelStatic('inventory_management'),
                'required_plan' => FeatureGate::getUpgradeHintStatic('inventory_management') ?? 'Starter',
            ]);
        }

        if (!auth()->user()->can('inventory.view')) {
            abort(403, 'Unauthorized');
        }

        $query = Product::forCurrentTenant()
            ->with(['category', 'unit', 'variants'])
            ->withCount('stockMovements as movement_count');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($request->filled('stock_status')) {
            $status = $request->input('stock_status');
            match ($status) {
                'in_stock' => $query->where('stock', '>', 0),
                'low_stock' => $query->where('stock', '>', 0)->whereColumn('stock', '<=', 'low_stock_alert'),
                'out_of_stock' => $query->where('stock', '<=', 0),
                default => null,
            };
        }

        $sortField = $request->get('sort', 'name');
        $sortDir = $request->get('direction', 'asc') === 'desc' ? 'desc' : 'asc';
        $allowedSorts = ['name', 'sku', 'stock', 'status', 'updated_at'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDir);
        } else {
            $query->orderBy('name');
        }

        $perPage = min((int) $request->get('per_page', 20), 100);
        $products = $query->paginate($perPage);

        $products->getCollection()->transform(function ($product) {
            $summary = $this->calculator->getInventorySummary($product);
            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'type' => $product->type,
                'category' => $product->category?->name,
                'unit' => $product->unit?->short_name ?? $product->unit?->name,
                'price' => (float) $product->price,
                'status' => $product->status,
                'stock' => (int) $product->stock,
                'calculated_stock' => (int) $summary['total'],
                'stock_status' => $summary['status'],
                'low_stock_alert' => $product->low_stock_alert ?? 5,
                'variant_count' => $product->variants->count(),
                'movement_count' => (int) $product->movement_count,
                'updated_at' => $product->updated_at?->toDateTimeString(),
            ];
        });

        return Inertia::render('Admin/Inventory/Index', [
            'products' => $products,
            'filters' => $request->only(['search', 'stock_status', 'sort', 'direction', 'per_page']),
            'stats' => $this->getStats(),
        ]);
    }

    public function movements(Request $request)
    {
        if (!FeatureGate::enabled('inventory_management')) {
            return redirect()->back()->with('feature_locked', [
                'feature' => FeatureGate::getLabelStatic('inventory_management'),
                'required_plan' => FeatureGate::getUpgradeHintStatic('inventory_management') ?? 'Starter',
            ]);
        }

        if (!auth()->user()->can('inventory.view')) {
            abort(403, 'Unauthorized');
        }

        $query = StockMovement::with(['product:id,name,sku,type,unit_id', 'variant:id,sku,attributes'])
            ->latest();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->whereHas('product', fn($p) => $p->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"))
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->integer('product_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $perPage = min((int) $request->get('per_page', 50), 100);
        $movements = $query->paginate($perPage);

        $movements->getCollection()->transform(function ($m) {
            return [
                'id' => $m->id,
                'type' => $m->type,
                'quantity' => (float) $m->quantity,
                'description' => $m->description,
                'reference_type' => $m->reference_type,
                'reference_id' => $m->reference_id,
                'created_at' => $m->created_at->toDateTimeString(),
                'product' => $m->product ? [
                    'id' => $m->product->id,
                    'name' => $m->product->name,
                    'sku' => $m->product->sku,
                ] : null,
                'variant' => $m->variant ? [
                    'id' => $m->variant->id,
                    'sku' => $m->variant->sku,
                ] : null,
            ];
        });

        return Inertia::render('Admin/Inventory/Movements', [
            'movements' => $movements,
            'filters' => $request->only(['search', 'product_id', 'type', 'date_from', 'date_to', 'per_page']),
            'products' => Product::forCurrentTenant()->orderBy('name')->get(['id', 'name', 'sku']),
            'types' => [
                StockMovement::TYPE_OPENING_STOCK,
                StockMovement::TYPE_PURCHASE,
                StockMovement::TYPE_SALE,
                StockMovement::TYPE_RETURN,
                StockMovement::TYPE_ADJUSTMENT,
                StockMovement::TYPE_TRANSFER,
            ],
        ]);
    }

    public function show(Product $product)
    {
        if (!FeatureGate::enabled('inventory_management')) {
            return redirect()->back()->with('feature_locked', [
                'feature' => FeatureGate::getLabelStatic('inventory_management'),
                'required_plan' => FeatureGate::getUpgradeHintStatic('inventory_management') ?? 'Starter',
            ]);
        }

        if (!auth()->user()->can('inventory.view')) {
            abort(403, 'Unauthorized');
        }

        if ((int) $product->tenant_id !== (int) tenantId()) {
            abort(404);
        }

        $product->load(['category', 'unit', 'variants']);

        $summary = $this->calculator->getInventorySummary($product);

        $movements = StockMovement::with(['variant:id,sku,attributes'])
            ->where('product_id', $product->id)
            ->latest()
            ->paginate(20);

        $movements->getCollection()->transform(function ($m) {
            return [
                'id' => $m->id,
                'type' => $m->type,
                'quantity' => (float) $m->quantity,
                'description' => $m->description,
                'reference_type' => $m->reference_type,
                'reference_id' => $m->reference_id,
                'created_at' => $m->created_at->toDateTimeString(),
                'variant' => $m->variant ? [
                    'id' => $m->variant->id,
                    'sku' => $m->variant->sku,
                ] : null,
            ];
        });

        $productData = [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'type' => $product->type,
            'category' => $product->category?->name,
            'unit' => $product->unit?->short_name ?? $product->unit?->name,
            'price' => (float) $product->price,
            'status' => $product->status,
            'stock' => (int) $product->stock,
            'calculated_stock' => (int) $summary['total'],
            'stock_status' => $summary['status'],
            'low_stock_alert' => $product->low_stock_alert ?? 5,
            'variant_count' => $product->variants->count(),
            'variants' => $product->variants->map(fn($v) => [
                'id' => $v->id,
                'sku' => $v->sku,
                'attributes' => $v->attributes,
                'stock' => (int) $v->stock,
                'price' => (float) $v->price,
            ]),
        ];

        return Inertia::render('Admin/Inventory/ProductDetail', [
            'product' => $productData,
            'movements' => $movements,
        ]);
    }

    private function getStats(): array
    {
        $totalProducts = Product::forCurrentTenant()->count();
        $lowStockProducts = Product::forCurrentTenant()
            ->where('stock', '>', 0)
            ->whereColumn('stock', '<=', 'low_stock_alert')
            ->count();
        $outOfStock = Product::forCurrentTenant()
            ->where('stock', '<=', 0)
            ->count();

        return [
            'total_products' => $totalProducts,
            'low_stock' => $lowStockProducts,
            'out_of_stock' => $outOfStock,
            'in_stock' => $totalProducts - $lowStockProducts - $outOfStock,
        ];
    }
}
