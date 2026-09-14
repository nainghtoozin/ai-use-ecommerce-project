<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FlashSale;
use App\Models\FlashSaleProduct;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\FeatureGate;
use App\Services\SubscriptionLimitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AdminFlashSaleController extends Controller
{
    public function index()
    {
        if (!FeatureGate::enabled('flash_sales')) {
            return redirect()->back()->with('feature_locked', [
                'feature' => FeatureGate::getLabelStatic('flash_sales'),
                'required_plan' => FeatureGate::getUpgradeHintStatic('flash_sales') ?? 'Business',
            ]);
        }

        if (!auth()->user()->can('flash_sales.view')) {
            abort(403, 'Unauthorized');
        }

        $flashSales = FlashSale::withoutTenantScope()
            ->where('tenant_id', tenantId())
            ->withCount('products')
            ->latest()
            ->paginate(15);

        return Inertia::render('Admin/FlashSales/Index', [
            'flashSales' => $flashSales,
        ]);
    }

    public function create()
    {
        if (!FeatureGate::enabled('flash_sales')) {
            return redirect()->back()->with('feature_locked', [
                'feature' => FeatureGate::getLabelStatic('flash_sales'),
                'required_plan' => FeatureGate::getUpgradeHintStatic('flash_sales') ?? 'Business',
            ]);
        }

        if (!auth()->user()->can('flash_sales.create')) {
            abort(403, 'Unauthorized');
        }

        return Inertia::render('Admin/FlashSales/Create', [
            'products' => Product::all(['id', 'name', 'price']),
        ]);
    }

    public function store(Request $request)
    {
        if (!FeatureGate::enabled('flash_sales')) {
            return redirect()->back()->with('feature_locked', [
                'feature' => FeatureGate::getLabelStatic('flash_sales'),
                'required_plan' => FeatureGate::getUpgradeHintStatic('flash_sales') ?? 'Business',
            ]);
        }

        if (!auth()->user()->can('flash_sales.create')) {
            abort(403, 'Unauthorized');
        }

        $limitService = SubscriptionLimitService::for();
        if (!$limitService->checkLimit('flash_sale_limit')) {
            return redirect()->back()->with('error',
                'Flash sale limit reached. Please upgrade your plan to create more flash sales.');
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'discount_type' => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'is_active' => 'boolean',
            'priority' => 'integer|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.variant_id' => 'nullable|exists:product_variants,id',
            'products.*.flash_price' => 'required|numeric|min:0',
            'products.*.quantity_limit' => 'nullable|integer|min:1',
        ]);

        $data['created_by'] = auth()->id();

        $productsData = $data['products'];
        unset($data['products']);

        DB::transaction(function () use ($data, $productsData, &$flashSale) {
            $flashSale = FlashSale::create($data);

            foreach ($productsData as $item) {
                $flashSale->flashSaleProducts()->create([
                    'product_id' => $item['product_id'],
                    'variant_id' => $item['variant_id'] ?? null,
                    'flash_price' => $item['flash_price'],
                    'quantity_limit' => $item['quantity_limit'] ?? null,
                ]);
            }
        });

        return admin_redirect('admin.flash-sales.index')
            ->with('success', 'Flash sale created successfully.');
    }

    public function edit(FlashSale $flashSale)
    {
        if (!FeatureGate::enabled('flash_sales')) {
            return redirect()->back()->with('feature_locked', [
                'feature' => FeatureGate::getLabelStatic('flash_sales'),
                'required_plan' => FeatureGate::getUpgradeHintStatic('flash_sales') ?? 'Business',
            ]);
        }

        if (!auth()->user()->can('flash_sales.update')) {
            abort(403, 'Unauthorized');
        }

        abort_unless($flashSale->tenant_id === tenantId(), 404);

        $flashSale->load(['flashSaleProducts.product', 'flashSaleProducts.variant']);

        return Inertia::render('Admin/FlashSales/Edit', [
            'flashSale' => $flashSale,
            'products' => Product::all(['id', 'name', 'price']),
        ]);
    }

    public function update(Request $request, FlashSale $flashSale)
    {
        if (!FeatureGate::enabled('flash_sales')) {
            return redirect()->back()->with('feature_locked', [
                'feature' => FeatureGate::getLabelStatic('flash_sales'),
                'required_plan' => FeatureGate::getUpgradeHintStatic('flash_sales') ?? 'Business',
            ]);
        }

        if (!auth()->user()->can('flash_sales.update')) {
            abort(403, 'Unauthorized');
        }

        abort_unless($flashSale->tenant_id === tenantId(), 404);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'discount_type' => 'sometimes|in:percentage,fixed',
            'discount_value' => 'sometimes|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'is_active' => 'boolean',
            'priority' => 'integer|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'products' => 'sometimes|array',
            'products.*.product_id' => 'required_with:products|exists:products,id',
            'products.*.variant_id' => 'nullable|exists:product_variants,id',
            'products.*.flash_price' => 'required_with:products|numeric|min:0',
            'products.*.quantity_limit' => 'nullable|integer|min:1',
        ]);

        $productsData = $data['products'] ?? null;
        unset($data['products']);

        DB::transaction(function () use ($flashSale, $data, $productsData) {
            $flashSale->update($data);

            if ($productsData !== null) {
                $flashSale->flashSaleProducts()->delete();
                foreach ($productsData as $item) {
                    $flashSale->flashSaleProducts()->create([
                        'product_id' => $item['product_id'],
                        'variant_id' => $item['variant_id'] ?? null,
                        'flash_price' => $item['flash_price'],
                        'quantity_limit' => $item['quantity_limit'] ?? null,
                    ]);
                }
            }
        });

        return admin_redirect('admin.flash-sales.index')
            ->with('success', 'Flash sale updated successfully.');
    }

    public function destroy(FlashSale $flashSale)
    {
        if (!FeatureGate::enabled('flash_sales')) {
            return redirect()->back()->with('feature_locked', [
                'feature' => FeatureGate::getLabelStatic('flash_sales'),
                'required_plan' => FeatureGate::getUpgradeHintStatic('flash_sales') ?? 'Business',
            ]);
        }

        if (!auth()->user()->can('flash_sales.delete')) {
            abort(403, 'Unauthorized');
        }

        abort_unless($flashSale->tenant_id === tenantId(), 404);

        $flashSale->flashSaleProducts()->delete();
        $flashSale->delete();

        return admin_redirect('admin.flash-sales.index')
            ->with('success', 'Flash sale deleted successfully.');
    }

    public function toggle(FlashSale $flashSale)
    {
        if (!FeatureGate::enabled('flash_sales')) {
            return redirect()->back()->with('feature_locked', [
                'feature' => FeatureGate::getLabelStatic('flash_sales'),
                'required_plan' => FeatureGate::getUpgradeHintStatic('flash_sales') ?? 'Business',
            ]);
        }

        if (!auth()->user()->can('flash_sales.update')) {
            abort(403, 'Unauthorized');
        }

        abort_unless($flashSale->tenant_id === tenantId(), 404);

        $flashSale->update(['is_active' => !$flashSale->is_active]);

        return admin_redirect('admin.flash-sales.index')
            ->with('success', 'Flash sale status toggled.');
    }

    public function search(Request $request)
    {
        if (!FeatureGate::enabled('flash_sales')) {
            return redirect()->back()->with('feature_locked', [
                'feature' => FeatureGate::getLabelStatic('flash_sales'),
                'required_plan' => FeatureGate::getUpgradeHintStatic('flash_sales') ?? 'Business',
            ]);
        }

        if (!auth()->user()->can('flash_sales.view')) {
            abort(403, 'Unauthorized');
        }

        $query = $request->input('query');
        $escapedQuery = addcslashes($query, '%_');

        $flashSales = FlashSale::withoutTenantScope()
            ->where('tenant_id', tenantId())
            ->where(function ($q) use ($escapedQuery) {
                $q->where('name', 'like', "%{$escapedQuery}%")
                  ->orWhere('description', 'like', "%{$escapedQuery}%");
            })
            ->withCount('products')
            ->orderBy('updated_at', 'desc')
            ->paginate(15);

        $flashSales->appends(['query' => $query]);

        return Inertia::render('Admin/FlashSales/Index', [
            'flashSales' => $flashSales,
            'query' => $query,
        ]);
    }
}
