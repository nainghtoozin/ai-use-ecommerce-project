<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Enums\ProductType;
use App\Models\ActivityLog;
use App\Services\ImageService;
use App\Services\ProductService;
use App\Services\SkuService;
use App\Services\SubscriptionLimitService;
use App\Services\PerPageTrait;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AdminProductController extends Controller
{
    use PerPageTrait;

    public function __construct(
        private readonly ImageService $imageService,
        private readonly ProductService $productService,
        private readonly SkuService $skuService,
    ) {}

    public function index(Request $request)
    {
        $query = Product::with('category');

        // Eager load active variant stock sum to avoid N+1 on effective_stock
        $query->withSum(['variants as variant_total_stock' => function ($q) {
            $q->where('status', ProductVariant::STATUS_ACTIVE);
        }], 'stock');

        // Eager load active variant count for display in stock column
        $query->withCount(['variants as active_variant_count' => function ($q) {
            $q->where('status', ProductVariant::STATUS_ACTIVE);
        }]);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('id', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('stock')) {
            $stockFilter = $request->input('stock');
            switch ($stockFilter) {
                case 'out_of_stock':
                    $query->where(function ($q) {
                        $q->where(function ($q2) {
                            $q2->where('type', ProductType::VARIABLE)
                               ->whereDoesntHave('variants', fn($v) => $v->where('stock', '>', 0)->where('status', ProductVariant::STATUS_ACTIVE));
                        })->orWhere(function ($q2) {
                            $q2->where('type', '!=', ProductType::VARIABLE)->where('stock', '<=', 0);
                        });
                    });
                    break;
                case 'low_stock':
                    $query->where(function ($q) {
                        $q->where(function ($q2) {
                            $q2->where('type', ProductType::VARIABLE)
                               ->whereHas('variants', fn($v) => $v->where('stock', '>', 0)->where('stock', '<', 10)->where('status', ProductVariant::STATUS_ACTIVE));
                        })->orWhere(function ($q2) {
                            $q2->where('type', '!=', ProductType::VARIABLE)->where('stock', '>', 0)->where('stock', '<', 10);
                        });
                    });
                    break;
                case 'in_stock':
                    $query->where(function ($q) {
                        $q->where(function ($q2) {
                            $q2->where('type', ProductType::VARIABLE)
                               ->whereHas('variants', fn($v) => $v->where('stock', '>=', 10)->where('status', ProductVariant::STATUS_ACTIVE));
                        })->orWhere(function ($q2) {
                            $q2->where('type', '!=', ProductType::VARIABLE)->where('stock', '>=', 10);
                        });
                    });
                    break;
            }
        }

        $resolved = $this->resolvePerPage($request);
        $perPage = $resolved['per_page'];
        $warning = $resolved['warning'];
        
        if ($resolved['should_paginate']) {
            $products = $query->latest()->paginate($perPage)->appends($request->query());
            $showPagination = true;
        } else {
            $total = $query->count();
            $items = $query->latest()->get();
            
            $products = new \Illuminate\Pagination\LengthAwarePaginator(
                $items,
                $total,
                $total,
                1,
                ['path' => $request->url(), 'query' => $request->query()]
            );
            $showPagination = false;
        }

        $categories = Category::all();

        return Inertia::render('Admin/Products/Index', [
            'products' => $products,
            'categories' => $categories,
            'showPagination' => $showPagination,
            'warning' => $warning,
            'filters' => [
                'search' => $request->input('search', ''),
                'category_id' => $request->input('category_id', ''),
                'type' => $request->input('type', ''),
                'status' => $request->input('status', ''),
                'stock' => $request->input('stock', ''),
            ],
        ]);
    }

    public function typeSelect()
    {
        $featureGate = \App\Services\FeatureGate::forUser();

        return Inertia::render('Admin/Products/TypeSelect', [
            'availableTypes' => ProductType::availableTypes(),
            'allTypes' => ProductType::all(),
            'featureStatus' => $featureGate->getAllFeaturesStatus(),
        ]);
    }

    public function create(Request $request)
    {
        $categories = Category::all();
        $productType = $request->input('type', ProductType::SINGLE);

        $selectableProducts = null;
        if ($productType === ProductType::COMBO) {
            $selectableProducts = Product::comboSelectable()
                ->with(['category', 'variants' => fn($q) => $q->active()])
                ->get()
                ->map(fn($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'type' => $p->type,
                    'price' => $p->price,
                    'stock' => $p->getEffectiveStock(),
                    'photo1_url' => $p->photo1_url,
                    'category_name' => $p->category?->name,
                    'variants' => $p->type === 'variable'
                        ? $p->variants->map(fn($v) => [
                            'id' => $v->id,
                            'label' => $v->label,
                            'price' => $v->getEffectivePrice(),
                            'stock' => $v->stock,
                            'sku' => $v->sku,
                        ])->toArray()
                        : [],
                ]);
        }

        return Inertia::render('Admin/Products/Create', [
            'categories' => $categories,
            'productType' => $productType,
            'selectableProducts' => $selectableProducts,
        ]);
    }

    public function store(StoreProductRequest $request)
    {
        $data = $request->validated();

        // Set product type, defaulting to single for backward compatibility
        $data['type'] = $data['type'] ?? ProductType::SINGLE;

        // Validate the product type (includes SaaS feature-gating)
        $this->productService->validateType($data['type']);

        // Enforce plan product limit
        SubscriptionLimitService::for()->assertCanCreateProduct();

        // Combo products don't need stock (derived from components)
        if ($data['type'] === ProductType::COMBO) {
            $data['stock'] = 0;
        }

        if (!isset($data['status'])) {
            $data['status'] = Product::STATUS_ACTIVE;
        }

        // Sanitize data to remove type-inapplicable fields
        $data = $this->productService->sanitizeData($data, $data['type']);

        $variantsPayload = null;
        if ($request->filled('variants')) {
            $variantsPayload = json_decode($request->input('variants'), true);
            if (!is_array($variantsPayload)) {
                $variantsPayload = null;
            }
        }

        $comboItemsPayload = null;
        if ($request->filled('combo_items')) {
            $comboItemsPayload = json_decode($request->input('combo_items'), true);
            if (!is_array($comboItemsPayload)) {
                $comboItemsPayload = null;
            }
        }

        // Validate combo items structure
        if ($data['type'] === ProductType::COMBO && $comboItemsPayload) {
            $this->validateComboItems($comboItemsPayload);
        }

        DB::transaction(function () use ($data, $request, $variantsPayload, $comboItemsPayload) {
            if ($request->hasFile('photo1')) {
                $data['photo1'] = $this->imageService->upload($request->file('photo1'), 'products');
            }

            if ($request->hasFile('photo2')) {
                $data['photo2'] = $this->imageService->upload($request->file('photo2'), 'products');
            }

            $product = Product::create($data);

            // Auto-generate SKU if left empty
            if (empty($product->sku)) {
                $generatedSku = $this->skuService->generateProductSku($product);
                if ($generatedSku) {
                    $product->update(['sku' => $generatedSku]);
                }
            }

            if ($product->isVariable() && $variantsPayload) {
                $normalized = $this->normalizeVariants($variantsPayload);
                $this->productService->syncVariants($product, $normalized);

                // Auto-generate SKUs for variants that don't have one
                $product->variants()->whereNull('sku')->each(function ($variant) {
                    $generatedSku = $this->skuService->generateVariantSku($variant);
                    if ($generatedSku) {
                        $variant->update(['sku' => $generatedSku]);
                    }
                });
            }

            if ($product->isCombo() && $comboItemsPayload) {
                $this->productService->syncComboItems($product, $comboItemsPayload);
            }

            
        });

        return admin_redirect('admin.products.index')
            ->with('success', 'Product created successfully!');
    }

    public function edit(Product $product)
    {
        $categories = Category::all();

        $selectableProducts = null;
        if ($product->isCombo()) {
            $selectableProducts = Product::comboSelectable()
                ->where('id', '!=', $product->id)
                ->with(['category', 'variants' => fn($q) => $q->active()])
                ->get()
                ->map(fn($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'type' => $p->type,
                    'price' => $p->price,
                    'stock' => $p->getEffectiveStock(),
                    'photo1_url' => $p->photo1_url,
                    'category_name' => $p->category?->name,
                    'variants' => $p->type === 'variable'
                        ? $p->variants->map(fn($v) => [
                            'id' => $v->id,
                            'label' => $v->label,
                            'price' => $v->getEffectivePrice(),
                            'stock' => $v->stock,
                            'sku' => $v->sku,
                        ])->toArray()
                        : [],
                ]);
        }

        return Inertia::render('Admin/Products/Edit', [
            'product' => $product->load(['category', 'variants', 'comboItems.comboProduct', 'comboItems.linkedVariant']),
            'categories' => $categories,
            'selectableProducts' => $selectableProducts,
        ]);
    }

    public function show(Product $product)
    {
        $product->load(['category', 'variants', 'comboItems.comboProduct', 'comboItems.linkedVariant', 'orderItems']);

        // Append price range for variable products
        if ($product->isVariable()) {
            $priceRange = $product->getPriceRange();
            $product->setAttribute('price_range', [
                'min' => $priceRange[0],
                'max' => $priceRange[1],
            ]);
        }

        // Append combo availability for combo products
        if ($product->isCombo()) {
            $product->setAttribute('combo_availability', $product->calculateComboAvailability());
            $product->setAttribute('combo_summary', $product->getComboSummary());
        }

        $relatedCombos = [];
        if ($product->isSingle() || $product->isVariable()) {
            $relatedCombos = Product::where('type', ProductType::COMBO)
                ->whereHas('comboItems', fn($q) => $q->where('combo_product_id', $product->id))
                ->with('comboItems.comboProduct', 'comboItems.linkedVariant')
                ->active()
                ->get();
        }

        return Inertia::render('Admin/Products/Show', [
            'product' => $product,
            'relatedCombos' => $relatedCombos,
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $data = $request->validated();

        // Validate type if provided (don't overwrite existing type if not sent)
        if (isset($data['type'])) {
            $this->productService->validateType($data['type']);
            $data = $this->productService->sanitizeData($data, $data['type']);
        }

        // Combo products don't need stock (derived from components)
        if (isset($data['type']) && $data['type'] === ProductType::COMBO) {
            $data['stock'] = 0;
        }

        $variantsPayload = null;
        if ($request->filled('variants')) {
            $variantsPayload = json_decode($request->input('variants'), true);
            if (!is_array($variantsPayload)) {
                $variantsPayload = null;
            }
        }

        $comboItemsPayload = null;
        if ($request->filled('combo_items')) {
            $comboItemsPayload = json_decode($request->input('combo_items'), true);
            if (!is_array($comboItemsPayload)) {
                $comboItemsPayload = null;
            }
        }

        // Determine the effective type for validation (existing type if not provided)
        $effectiveType = $data['type'] ?? $product->type;

        // Validate combo items structure
        if ($effectiveType === ProductType::COMBO && $comboItemsPayload) {
            $this->validateComboItems($comboItemsPayload);
        }

        DB::transaction(function () use ($data, $request, $product, $variantsPayload, $comboItemsPayload, $effectiveType) {
            if ($request->hasFile('photo1')) {
                $this->imageService->delete($product->photo1);
                $data['photo1'] = $this->imageService->upload($request->file('photo1'), 'products');
            }

            if ($request->hasFile('photo2')) {
                $this->imageService->delete($product->photo2);
                $data['photo2'] = $this->imageService->upload($request->file('photo2'), 'products');
            }

            $product->update($data);

            // Auto-generate SKU if it was empty and is still empty after update
            if (empty($product->sku)) {
                $generatedSku = $this->skuService->generateProductSku($product);
                if ($generatedSku) {
                    $product->update(['sku' => $generatedSku]);
                }
            }

            if ($product->isVariable()) {
                // Empty array means intentionally clear all variants
                // null means no variant data was sent, leave variants unchanged
                if (is_array($variantsPayload)) {
                    if (!empty($variantsPayload)) {
                        $normalized = $this->normalizeVariants($variantsPayload);
                        $this->productService->syncVariants($product, $normalized);

                        // Auto-generate SKUs for new variants that don't have one
                        $product->variants()->whereNull('sku')->each(function ($variant) {
                            $generatedSku = $this->skuService->generateVariantSku($variant);
                            if ($generatedSku) {
                                $variant->update(['sku' => $generatedSku]);
                            }
                        });
                    } else {
                        // Empty variants array = delete all variants
                        $product->variants()->delete();
                    }
                }
            } elseif ($product->variants()->exists()) {
                $product->variants()->delete();
            }

            if ($product->isCombo()) {
                // Empty array means intentionally clear all combo items
                // null means no combo data was sent, leave combo items unchanged
                if (is_array($comboItemsPayload)) {
                    if (!empty($comboItemsPayload)) {
                        $this->productService->syncComboItems($product, $comboItemsPayload);
                    } else {
                        // Empty combo items array = delete all combo items
                        $product->comboItems()->delete();
                    }
                }
            } elseif ($product->comboItems()->exists()) {
                $product->comboItems()->delete();
            }

            
        });

        return admin_redirect('admin.products.index')
            ->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product)
    {
        if ($product->hasOrders()) {
            return back()->with('error', 'Cannot delete product because it exists in customer orders.');
        }

        DB::transaction(function () use ($product) {
            // Always clean up variants regardless of current type
            // to prevent orphaned records if type was changed before deletion
            $product->variants()->delete();

            if ($product->isCombo()) {
                $product->comboItems()->delete();
                ProductCombo::where('combo_product_id', $product->id)->delete();
            }

            $this->imageService->delete($product->photo1);
            $this->imageService->delete($product->photo2);

            $product->delete();
        });

        

        return admin_redirect('admin.products.index')
            ->with('success', 'Product deleted successfully!');
    }

    public function bulkDestroy(Request $request)
    {
        if (!Auth::user()->hasRole('admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:products,id',
        ]);

        $ids = $request->input('ids');
        $products = Product::whereIn('id', $ids)->get();

        $blockedProducts = $products->filter(fn($p) => $p->hasOrders());

        if ($blockedProducts->isNotEmpty()) {
            $blockedNames = $blockedProducts->pluck('name')->take(3)->implode(', ');
            $moreCount = $blockedProducts->count() - 3;
            $blockedMsg = $moreCount > 0 ? " and {$moreCount} more" : '';

            return back()->with('error', "Cannot delete {$blockedProducts->count()} product(s) because they exist in customer orders: {$blockedNames}{$blockedMsg}");
        }

        DB::transaction(function () use ($products, $ids) {
            foreach ($products as $product) {
                // Always clean up variants regardless of current type
                $product->variants()->delete();

                if ($product->isCombo()) {
                    $product->comboItems()->delete();
                    ProductCombo::where('combo_product_id', $product->id)->delete();
                }

                $this->imageService->delete($product->photo1);
                $this->imageService->delete($product->photo2);

                $product->delete();
            }
        });

        ActivityLogger::log(
            "Bulk deleted {$products->count()} product(s): " . $products->pluck('name')->take(5)->implode(', '),
            'product_bulk_deleted',
            null,
            ['product_ids' => $ids, 'count' => $products->count()]
        );

        

        return admin_redirect('admin.products.index')
            ->with('success', "{$products->count()} product(s) deleted successfully.");
    }

    public function bulkActivate(Request $request)
    {
        if (!Auth::user()->hasRole('admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:products,id',
        ]);

        $ids = $request->input('ids');
        $count = Product::whereIn('id', $ids)->update(['status' => Product::STATUS_ACTIVE]);

        ActivityLogger::log(
            "Bulk activated {$count} product(s)",
            'product_bulk_activated',
            null,
            ['product_ids' => $ids, 'count' => $count]
        );

        

        return admin_redirect('admin.products.index')
            ->with('success', "{$count} product(s) activated successfully.");
    }

    public function bulkDeactivate(Request $request)
    {
        if (!Auth::user()->hasRole('admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:products,id',
        ]);

        $ids = $request->input('ids');
        $count = Product::whereIn('id', $ids)->update(['status' => Product::STATUS_INACTIVE]);

        ActivityLogger::log(
            "Bulk deactivated {$count} product(s)",
            'product_bulk_deactivated',
            null,
            ['product_ids' => $ids, 'count' => $count]
        );

        

        return admin_redirect('admin.products.index')
            ->with('success', "{$count} product(s) deactivated successfully.");
    }

    public function search(Request $request)
    {
        $query = $request->input('query');

        $products = Product::with('category')
            ->where('name', 'like', "%{$query}%")
            ->latest()
            ->paginate(10);

        $products->appends(['query' => $query]);

        return Inertia::render('Admin/Products/Index', [
            'products' => $products,
            'query' => $query,
        ]);
    }

    /**
     * Normalize frontend variant payload for database persistence.
     *
     * The frontend sends variants with an `options` array of simple values
     * (e.g. ['XL', 'Black']) but the ProductVariant model stores them as
     * an `attributes` associative array (e.g. {'option1': 'XL', 'option2': 'Black'}).
     *
     * Existing variants include an `id` field for update detection.
     *
     * @param array $variants
     * @return array
     */
    protected function normalizeVariants(array $variants): array
    {
        $normalized = [];

        foreach ($variants as $variant) {
            $attributes = [];

            if (isset($variant['options']) && is_array($variant['options'])) {
                foreach ($variant['options'] as $index => $value) {
                    $key = 'option' . ($index + 1);
                    $attributes[$key] = (string) $value;
                }
            }

            $normalized[] = [
                'id' => isset($variant['id']) && !str_starts_with((string) $variant['id'], 'temp_')
                    ? (int) $variant['id']
                    : null,
                'sku' => $variant['sku'] ?? '',
                'price' => isset($variant['price']) && $variant['price'] !== '' ? max(0, (float) $variant['price']) : null,
                'compare_price' => isset($variant['compare_price']) && $variant['compare_price'] !== '' ? (float) $variant['compare_price'] : null,
                'cost_price' => isset($variant['cost_price']) && $variant['cost_price'] !== '' ? (float) $variant['cost_price'] : null,
                'stock' => isset($variant['stock']) ? (int) $variant['stock'] : 0,
                'attributes' => $attributes,
                'status' => $variant['status'] ?? ProductVariant::STATUS_ACTIVE,
            ];
        }

        return $normalized;
    }

    /**
     * Validate combo items payload structure and data integrity.
     *
     * Ensures:
     * - Each item has a valid combo_product_id
     * - Quantities are positive integers
     * - Referenced products exist
     * - If linked_variant_id is provided, it exists and belongs to the product
     * - No duplicate entries (same product + same variant)
     *
     * @param array $items
     * @return void
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateComboItems(array $items): void
    {
        if (empty($items)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'combo_items' => 'A combo product must have at least one component.',
            ]);
        }

        $seenKeys = [];
        $itemNumber = 1;

        foreach ($items as $index => $item) {
            $productKey = "combo_items.{$index}";

            // Validate combo_product_id
            if (empty($item['combo_product_id'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    $productKey => 'Component #' . $itemNumber . ' must reference a valid product.',
                ]);
            }

            $productId = (int) $item['combo_product_id'];

            // Check product exists
            $componentProduct = Product::find($productId);
            if (!$componentProduct) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    $productKey => 'Product #' . $productId . ' does not exist.',
                ]);
            }

            // Combo products cannot include other combos
            if ($componentProduct->isCombo()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    $productKey => 'Combo products cannot include other combos.',
                ]);
            }

            // Validate quantity
            $quantity = isset($item['quantity']) ? (int) $item['quantity'] : 1;
            if ($quantity < 1) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "{$productKey}.quantity" => 'Quantity must be at least 1.',
                ]);
            }

            // Validate linked_variant_id if provided
            if (isset($item['linked_variant_id']) && $item['linked_variant_id']) {
                $variantId = (int) $item['linked_variant_id'];
                $variant = ProductVariant::find($variantId);

                if (!$variant) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "{$productKey}.linked_variant_id" => 'Variant #' . $variantId . ' does not exist.',
                    ]);
                }

                if ($variant->product_id !== $productId) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "{$productKey}.linked_variant_id" => 'Variant does not belong to the selected product.',
                    ]);
                }
            }

            // Check for duplicates (same product_id + same variant_id)
            $variantId = isset($item['linked_variant_id']) ? (int) $item['linked_variant_id'] : null;
            $uniqueKey = "{$productId}:{$variantId}";

            if (isset($seenKeys[$uniqueKey])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    $productKey => 'Duplicate component detected.',
                ]);
            }

            $seenKeys[$uniqueKey] = true;
            $itemNumber++;
        }
    }
}
