<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Tenant;
use App\Services\ProductService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StorefrontController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
    ) {}

    public function index(Request $request)
    {
        $tenant = Tenant::getCurrent();
        if (!$tenant) {
            abort(404);
        }

        if ($tenant->isLocked()) {
            return $this->renderLocked($tenant);
        }

        $query = $request->input('query', '');
        $categoryId = $request->input('category', '');
        $sort = $request->input('sort', 'latest');

        $products = Product::active()
            ->with(['category', 'brand'])
            ->with(['variants' => fn($q) => $q->active(), 'comboItems.comboProduct', 'comboItems.linkedVariant']);

        if ($query) {
            $products->where('name', 'LIKE', "%{$query}%");
        }

        if ($categoryId) {
            $products->where('category_id', $categoryId);
        }

        $this->applySorting($products, $sort);

        $promotions = Promotion::valid()->automatic()
            ->with(['products', 'categories'])
            ->orderBy('priority', 'desc')
            ->get();

        $hasProducts = Product::active()->exists();
        $categories = Category::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Storefront/Index', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'store_url' => $tenant->store_url,
                'logo' => $tenant->logo,
                'status' => $tenant->status,
            ],
            'products' => Inertia::scroll(fn () => $products->paginate(8)->through(function ($product) use ($promotions) {
                return $this->enrichProductWithPromotion($product, $promotions);
            })),
            'hasProducts' => $hasProducts,
            'categories' => $categories,
            'searchQuery' => $query,
            'filters' => [
                'category_id' => $categoryId,
                'sort' => $sort,
            ],
        ]);
    }

    public function products(Request $request)
    {
        $tenant = Tenant::getCurrent();
        if (!$tenant) {
            abort(404);
        }

        if ($tenant->isLocked()) {
            return $this->renderLocked($tenant);
        }

        $query = $request->input('query', '');
        $categoryId = $request->input('category', '');
        $sort = $request->input('sort', 'latest');
        $inStock = $request->boolean('in_stock');

        $products = Product::active()
            ->with(['category', 'brand'])
            ->with(['variants' => fn($q) => $q->active(), 'comboItems.comboProduct', 'comboItems.linkedVariant']);

        if ($query) {
            $products->where('name', 'LIKE', "%{$query}%");
        }

        if ($categoryId) {
            $products->where('category_id', $categoryId);
        }

        if ($inStock) {
            $this->applyInStockFilter($products);
        }

        $this->applySorting($products, $sort);

        $promotions = Promotion::valid()->automatic()
            ->with(['products', 'categories'])
            ->orderBy('priority', 'desc')
            ->get();

        $categories = Category::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Storefront/Products', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'store_url' => $tenant->store_url,
                'logo' => $tenant->logo,
                'status' => $tenant->status,
            ],
            'products' => Inertia::scroll(fn () => $products->paginate(12)->through(function ($product) use ($promotions) {
                return $this->enrichProductWithPromotion($product, $promotions);
            })),
            'categories' => $categories,
            'searchQuery' => $query,
            'filters' => [
                'category_id' => $categoryId,
                'sort' => $sort,
                'in_stock' => $inStock,
            ],
        ]);
    }

    public function show(Request $request, Product $product)
    {
        $tenant = Tenant::getCurrent();
        if (!$tenant) {
            abort(404);
        }

        if ($tenant->isLocked()) {
            return $this->renderLocked($tenant);
        }

        if ($product->status !== Product::STATUS_ACTIVE) {
            abort(404);
        }

        $promotions = Promotion::valid()->automatic()
            ->with(['products', 'categories'])
            ->orderBy('priority', 'desc')
            ->get();

        $product->loadMissing(['category', 'brand']);
        if ($product->isVariable()) {
            $product->loadMissing(['variants' => fn($q) => $q->active()]);
        }
        if ($product->isCombo()) {
            $product->loadMissing(['comboItems.comboProduct', 'comboItems.linkedVariant']);
        }

        $promotion = $this->findBestPromotionForProduct($product, $promotions);
        $detail = $this->productService->resolveForDetail($product);

        return Inertia::render('Storefront/Show', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'store_url' => $tenant->store_url,
                'logo' => $tenant->logo,
                'status' => $tenant->status,
            ],
            'product' => $product,
            'promotion' => $promotion,
            'detail' => $detail,
        ]);
    }

    private function enrichProductWithPromotion($product, $promotions)
    {
        $bestPromotion = $this->findBestPromotionForProduct($product, $promotions);
        if ($bestPromotion) {
            $product->promotion_badge = $this->formatPromotionBadge($bestPromotion);
            $product->promotion_discount = $bestPromotion->discount_value;
            $product->promotion_price = $bestPromotion->promotion_type === 'percentage'
                ? $product->price - ($product->price * $bestPromotion->discount_value / 100)
                : $product->price - $bestPromotion->discount_value;
        }
        return $product;
    }

    private function findBestPromotionForProduct($product, $promotions)
    {
        $bestPromotion = null;
        $maxDiscount = 0;

        foreach ($promotions as $promotion) {
            $applies = $promotion->applies_to === 'all';

            if (!$applies && $promotion->applies_to === 'products') {
                $applies = $promotion->products->contains($product->id);
            }

            if (!$applies && $promotion->applies_to === 'categories') {
                $applies = $product->category && $promotion->categories->contains($product->category->id);
            }

            if ($applies) {
                $discount = $promotion->promotion_type === 'percentage'
                    ? ($product->price * $promotion->discount_value / 100)
                    : $promotion->discount_value;

                if ($discount > $maxDiscount) {
                    $maxDiscount = $discount;
                    $bestPromotion = $promotion;
                }
            }
        }

        return $bestPromotion;
    }

    private function formatPromotionBadge($promotion): string
    {
        return match ($promotion->promotion_type) {
            'percentage' => "-{$promotion->discount_value}%",
            'fixed_amount' => "-{$promotion->discount_value} MMK",
            'free_shipping' => 'Free Shipping',
            default => 'Sale',
        };
    }

    private function applySorting($query, string $sort): void
    {
        match ($sort) {
            'price_asc' => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'name' => $query->orderBy('name', 'asc'),
            default => $query->latest(),
        };
    }

    private function applyInStockFilter($query): void
    {
        $query->where(function ($q) {
            $q->where('type', Product::TYPE_SINGLE)
              ->where('stock', '>', 0);
            $q->orWhere('type', Product::TYPE_VARIABLE)
              ->whereHas('variants', fn($v) => $v->selectRaw('SUM(stock) > 0'));
            $q->orWhere('type', Product::TYPE_COMBO)
              ->whereHas('comboItems.comboProduct', fn($c) => $c->where('stock', '>', 0));
        });
    }

    private function renderLocked(Tenant $tenant)
    {
        return \Inertia\Inertia::render('Storefront/Locked', [
            'tenant' => [
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'logo' => $tenant->logo,
            ],
        ]);
    }
}
