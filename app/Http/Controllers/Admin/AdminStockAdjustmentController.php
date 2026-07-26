<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\AdjustmentService;
use App\Services\FeatureGate;
use App\Services\StockCalculationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminStockAdjustmentController extends Controller
{
    public function __construct(
        private readonly AdjustmentService $adjustmentService,
        private readonly StockCalculationService $calculator,
    ) {}

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

        $reasons = AdjustmentService::getReasons();
        $defaultWarehouseName = Warehouse::forCurrentTenant()->default()->first()?->name;

        $query = StockMovement::with(['product:id,name,sku', 'variant:id,sku', 'warehouse:id,name,code'])
            ->where('type', StockMovement::TYPE_ADJUSTMENT);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('adjustment_number', 'like', "%{$search}%")
                  ->orWhereHas('product', fn($p) => $p->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('reason')) {
            $reasonLabel = $reasons[$request->input('reason')] ?? null;
            if ($reasonLabel) {
                $query->where('description', 'like', $reasonLabel . '%');
            }
        }

        if ($request->filled('type')) {
            $type = $request->input('type');
            $query->where(function ($q) use ($type) {
                if ($type === 'increase') {
                    $q->where('quantity', '>', 0);
                } elseif ($type === 'decrease') {
                    $q->where('quantity', '<', 0);
                }
            });
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

        $perPage = min((int) $request->get('per_page', 15), 100);
        $allowedPerPages = [10, 25, 50, 100];
        if (!in_array($perPage, $allowedPerPages)) {
            $perPage = 15;
        }

        $history = $query->latest()->paginate($perPage)->appends($request->query());

        $history->getCollection()->transform(function ($m) use ($reasons, $defaultWarehouseName) {
            $beforeStock = $this->calculator->forProduct($m->product) - (float) $m->quantity;
            $reasonKey = AdjustmentService::extractReasonKey($m->description, $reasons);

            return [
                'id' => $m->id,
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
                'quantity' => (float) $m->quantity,
                'adjustment_number' => $m->adjustment_number,
                'before_stock' => max(0, (int) $beforeStock),
                'after_stock' => max(0, (int) ($beforeStock + (float) $m->quantity)),
                'reason_key' => $reasonKey,
                'reason_label' => $reasons[$reasonKey] ?? 'Other',
                'description' => $m->description,
                'created_at' => $m->created_at->toDateTimeString(),
                'user' => null,
            ];
        });

        $warehouses = Warehouse::forCurrentTenant()->active()->orderBy('name')->get(['id', 'name', 'code']);

        return Inertia::render('Admin/Inventory/Adjustments', [
            'adjustments' => $history,
            'warehouses' => $warehouses,
            'reasons' => $reasons,
            'filters' => $request->only(['search', 'reason', 'type', 'warehouse_id', 'date_from', 'date_to', 'per_page']),
        ]);
    }

    public function show(StockMovement $movement)
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

        $reasons = AdjustmentService::getReasons();
        $reasonKey = AdjustmentService::extractReasonKey($movement->description, $reasons);
        $beforeStock = $this->calculator->forProduct($movement->product) - (float) $movement->quantity;

        $defaultWarehouseName = Warehouse::forCurrentTenant()->default()->first()?->name;

        $data = [
            'id' => $movement->id,
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
            'quantity' => (float) $movement->quantity,
            'adjustment_number' => $movement->adjustment_number,
            'before_stock' => max(0, (int) $beforeStock),
            'after_stock' => max(0, (int) ($beforeStock + (float) $movement->quantity)),
            'reason_key' => $reasonKey,
            'reason_label' => $reasons[$reasonKey] ?? 'Other',
            'description' => $movement->description,
            'reference' => AdjustmentService::extractReference($movement->description),
            'notes' => AdjustmentService::extractNotes($movement->description),
            'created_at' => $movement->created_at->toDateTimeString(),
            'user' => null,
        ];

        return Inertia::render('Admin/Inventory/AdjustmentDetail', [
            'adjustment' => $data,
        ]);
    }

    public function create()
    {
        if (!FeatureGate::enabled('inventory_management')) {
            return redirect()->back()->with('feature_locked', [
                'feature' => FeatureGate::getLabelStatic('inventory_management'),
                'required_plan' => FeatureGate::getUpgradeHintStatic('inventory_management') ?? 'Starter',
            ]);
        }

        if (!auth()->user()->can('inventory.create')) {
            abort(403, 'Unauthorized');
        }

        $products = Product::forCurrentTenant()
            ->where('type', '!=', 'combo')
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'type']);

        $warehouses = Warehouse::forCurrentTenant()->active()->orderBy('name')->get(['id', 'name', 'code', 'is_default']);

        return Inertia::render('Admin/Inventory/AdjustmentCreate', [
            'products' => $products,
            'warehouses' => $warehouses,
            'reasons' => AdjustmentService::getReasons(),
        ]);
    }

    public function store(Request $request)
    {
        if (!FeatureGate::enabled('inventory_management')) {
            return redirect()->back()->with('feature_locked', [
                'feature' => FeatureGate::getLabelStatic('inventory_management'),
                'required_plan' => FeatureGate::getUpgradeHintStatic('inventory_management') ?? 'Starter',
            ]);
        }

        if (!auth()->user()->can('inventory.create')) {
            abort(403, 'Unauthorized');
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'variant_id' => 'nullable|exists:product_variants,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'type' => 'required|in:increase,decrease',
            'quantity' => 'required|numeric|min:0.01',
            'reason' => 'required|string',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        $product = Product::forCurrentTenant()->findOrFail($validated['product_id']);
        $variant = $validated['variant_id']
            ? ProductVariant::where('product_id', $product->id)->find($validated['variant_id'])
            : null;

        $quantity = (float) $validated['quantity'];
        if ($validated['type'] === 'decrease') {
            $quantity = -$quantity;
        }

        $this->adjustmentService->adjust(
            product: $product,
            quantity: $quantity,
            reason: $validated['reason'],
            description: $validated['notes'] ?? '',
            reference: $validated['reference'] ?? '',
            variant: $variant,
            warehouseId: $validated['warehouse_id'] ?? null,
        );

        return admin_redirect('admin.inventory.adjustments')
            ->with('success', 'Stock adjustment recorded successfully.');
    }

    public function destroy(StockMovement $movement)
    {
        if (!FeatureGate::enabled('inventory_management')) {
            return redirect()->back()->with('feature_locked', [
                'feature' => FeatureGate::getLabelStatic('inventory_management'),
                'required_plan' => FeatureGate::getUpgradeHintStatic('inventory_management') ?? 'Starter',
            ]);
        }

        if (!auth()->user()->can('inventory.delete')) {
            abort(403, 'Unauthorized');
        }

        if ((int) $movement->tenant_id !== (int) tenantId()) {
            abort(404);
        }

        if ($movement->type !== StockMovement::TYPE_ADJUSTMENT) {
            return back()->with('error', 'Only adjustment movements can be deleted.');
        }

        $movement->delete();

        return admin_redirect('admin.inventory.adjustments')
            ->with('success', 'Stock adjustment deleted successfully.');
    }

    public function preview(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'variant_id' => 'nullable|exists:product_variants,id',
            'type' => 'required|in:increase,decrease',
            'quantity' => 'required|numeric|min:0.01',
        ]);

        $product = Product::forCurrentTenant()->findOrFail($request->input('product_id'));
        $variant = $request->input('variant_id')
            ? ProductVariant::where('product_id', $product->id)->find($request->input('variant_id'))
            : null;

        $quantity = (float) $request->input('quantity');
        if ($request->input('type') === 'decrease') {
            $quantity = -$quantity;
        }

        return response()->json(
            $this->adjustmentService->preview($product, $quantity, $variant)
        );
    }
}
