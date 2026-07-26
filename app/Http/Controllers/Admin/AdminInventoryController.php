<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\AdjustmentService;
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
            'warehouseSummary' => $this->getWarehouseSummary(),
            'defaultWarehouseId' => $this->getDefaultWarehouseId(),
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

        $defaultWarehouseId = $this->getDefaultWarehouseId();

        $products->getCollection()->transform(function ($product) use ($defaultWarehouseId) {
            $summary = $this->calculator->getInventorySummary($product);
            $stockByWarehouse = $this->calculator->getStockByWarehouse($product);

            $storeStock = 0;
            $warehouseStock = 0;
            foreach ($stockByWarehouse as $wh) {
                if ($wh['warehouse_id'] === $defaultWarehouseId) {
                    $storeStock = (int) $wh['stock'];
                } else {
                    $warehouseStock += (int) $wh['stock'];
                }
            }

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
                'store_stock' => $storeStock,
                'warehouse_stock' => $warehouseStock,
                'total_stock' => (int) $summary['total'],
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

    public function stockHistory(Request $request)
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

        // Product search API for autocomplete
        if ($request->filled('search') && !$request->filled('product_id') && $request->expectsJson()) {
            $search = $request->input('search');
            $products = Product::forCurrentTenant()
                ->where('type', '!=', 'combo')
                ->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('sku', 'like', "%{$search}%")
                      ->orWhere('barcode', 'like', "%{$search}%");
                })
                ->orderBy('name')
                ->limit(20)
                ->get(['id', 'name', 'sku', 'type']);

            return response()->json(['products' => $products]);
        }

        $product = null;
        $movements = [];
        $summary = null;

        if ($request->filled('product_id')) {
            $product = Product::forCurrentTenant()
                ->with(['category', 'unit', 'variants'])
                ->find($request->integer('product_id'));

            if ($product) {
                $defaultWarehouseName = Warehouse::forCurrentTenant()->default()->first()?->name;

                $movementQuery = StockMovement::with(['variant:id,sku,attributes', 'warehouse:id,name,code'])
                    ->where('product_id', $product->id)
                    ->latest()
                    ->limit(100);

                $movementRecords = $movementQuery->get();

                $currentStock = $this->calculator->forProduct($product);
                $runningBalance = $currentStock;

                $movements = $movementRecords->map(function ($m) use (&$runningBalance, $defaultWarehouseName) {
                    $balanceBefore = $runningBalance;
                    $runningBalance -= (float) $m->quantity;

                    return [
                        'id' => $m->id,
                        'type' => $m->type,
                        'quantity' => (float) $m->quantity,
                        'balance_after' => max(0, (int) $balanceBefore),
                        'adjustment_number' => $m->adjustment_number,
                        'reference_type' => $m->reference_type,
                        'reference_id' => $m->reference_id,
                        'description' => $m->description,
                        'created_at' => $m->created_at->toDateTimeString(),
                        'variant' => $m->variant ? [
                            'id' => $m->variant->id,
                            'sku' => $m->variant->sku,
                        ] : null,
                        'warehouse' => $m->warehouse ? [
                            'id' => $m->warehouse->id,
                            'name' => $m->warehouse->name,
                            'code' => $m->warehouse->code,
                        ] : ['name' => $defaultWarehouseName],
                    ];
                })->toArray();

                $stockByWarehouse = $this->calculator->getStockByWarehouse($product);
                $defaultWarehouse = Warehouse::forCurrentTenant()->default()->first();

                $summary = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'type' => $product->type,
                    'stock' => (int) $currentStock,
                    'stock_status' => $this->calculator->getStockStatus($product),
                    'low_stock_alert' => $product->low_stock_alert ?? 5,
                    'warehouse' => $defaultWarehouse ? [
                        'id' => $defaultWarehouse->id,
                        'name' => $defaultWarehouse->name,
                        'code' => $defaultWarehouse->code,
                    ] : null,
                    'stock_by_warehouse' => $stockByWarehouse,
                ];
            }
        }

        return Inertia::render('Admin/Inventory/ProductStockHistory', [
            'product' => $summary,
            'movements' => $movements,
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

        $defaultWarehouseName = Warehouse::forCurrentTenant()->default()->first()?->name;

        $query = StockMovement::with(['product:id,name,sku,type,unit_id', 'variant:id,sku,attributes', 'warehouse:id,name,code'])
            ->latest();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->whereHas('product', fn($p) => $p->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"))
                  ->orWhere('adjustment_number', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->integer('product_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->integer('warehouse_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $perPage = min((int) $request->get('per_page', 20), 100);
        $allowedPerPages = [10, 20, 50, 100];
        if (!in_array($perPage, $allowedPerPages)) {
            $perPage = 20;
        }

        $movements = $query->paginate($perPage)->appends($request->query());

        $movements->getCollection()->transform(function ($m) use ($defaultWarehouseName) {
            $balanceAfter = $this->calculator->forProduct($m->product) - (float) $m->quantity + (float) $m->quantity;

            return [
                'id' => $m->id,
                'type' => $m->type,
                'quantity' => (float) $m->quantity,
                'balance_after' => max(0, (int) $balanceAfter),
                'adjustment_number' => $m->adjustment_number,
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
                'warehouse' => $m->warehouse ? [
                    'id' => $m->warehouse->id,
                    'name' => $m->warehouse->name,
                    'code' => $m->warehouse->code,
                ] : ['name' => $defaultWarehouseName],
            ];
        });

        $warehouses = Warehouse::forCurrentTenant()->active()->orderBy('name')->get(['id', 'name', 'code']);

        return Inertia::render('Admin/Inventory/StockHistory', [
            'movements' => $movements,
            'warehouses' => $warehouses,
            'types' => [
                StockMovement::TYPE_OPENING_STOCK,
                StockMovement::TYPE_PURCHASE,
                StockMovement::TYPE_SALE,
                StockMovement::TYPE_RETURN,
                StockMovement::TYPE_ADJUSTMENT,
                StockMovement::TYPE_TRANSFER,
            ],
            'filters' => $request->only(['search', 'product_id', 'type', 'warehouse_id', 'date_from', 'date_to', 'per_page']),
        ]);
    }

    public function movementShow(StockMovement $movement)
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

        if ((int) $movement->tenant_id !== (int) tenantId()) {
            abort(404);
        }

        $movement->load(['product', 'variant', 'warehouse']);

        $defaultWarehouseName = Warehouse::forCurrentTenant()->default()->first()?->name;
        $currentStock = $this->calculator->forProduct($movement->product);
        $beforeStock = $currentStock - (float) $movement->quantity;

        $reasons = AdjustmentService::getReasons();
        $reasonKey = AdjustmentService::extractReasonKey($movement->description, $reasons);

        $data = [
            'id' => $movement->id,
            'type' => $movement->type,
            'quantity' => (float) $movement->quantity,
            'before_stock' => max(0, (int) $beforeStock),
            'after_stock' => max(0, (int) ($beforeStock + (float) $movement->quantity)),
            'adjustment_number' => $movement->adjustment_number,
            'reason_key' => $reasonKey,
            'reason_label' => $reasons[$reasonKey] ?? null,
            'description' => $movement->description,
            'reference_type' => $movement->reference_type,
            'reference_id' => $movement->reference_id,
            'created_at' => $movement->created_at->toDateTimeString(),
            'product' => $movement->product ? [
                'id' => $movement->product->id,
                'name' => $movement->product->name,
                'sku' => $movement->product->sku,
                'type' => $movement->product->type,
            ] : null,
            'variant' => $movement->variant ? [
                'id' => $movement->variant->id,
                'sku' => $movement->variant->sku,
                'attributes' => $movement->variant->attributes,
            ] : null,
            'warehouse' => $movement->warehouse ? [
                'id' => $movement->warehouse->id,
                'name' => $movement->warehouse->name,
                'code' => $movement->warehouse->code,
            ] : ['name' => $defaultWarehouseName],
        ];

        return Inertia::render('Admin/Inventory/StockHistoryDetail', [
            'movement' => $data,
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

        $movements = StockMovement::with(['variant:id,sku,attributes', 'warehouse:id,name,code'])
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
                'warehouse' => $m->warehouse ? [
                    'id' => $m->warehouse->id,
                    'name' => $m->warehouse->name,
                    'code' => $m->warehouse->code,
                ] : null,
            ];
        });

        $stockByWarehouse = $this->calculator->getStockByWarehouse($product);

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
            'stockByWarehouse' => $stockByWarehouse,
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

    private function getWarehouseSummary(): array
    {
        $warehouses = Warehouse::forCurrentTenant()->active()->orderBy('name')->get();

        return $warehouses->map(function ($wh) {
            $movements = StockMovement::where('warehouse_id', $wh->id)->count();
            $totalStock = (float) StockMovement::where('warehouse_id', $wh->id)->sum('quantity');

            return [
                'id' => $wh->id,
                'name' => $wh->name,
                'code' => $wh->code,
                'is_default' => $wh->is_default,
                'movement_count' => $movements,
                'total_stock' => max(0, $totalStock),
            ];
        })->toArray();
    }

    private function getDefaultWarehouseId(): ?int
    {
        $default = Warehouse::forCurrentTenant()->default()->first();
        return $default?->id;
    }
}
