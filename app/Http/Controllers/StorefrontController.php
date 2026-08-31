<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Tenant;
use App\Models\StorefrontRevision;
use App\Models\WebsiteInfo;
use App\Services\ProductService;
use App\Services\WebsiteFaqService;
use App\Services\StorefrontConfigurationResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StorefrontController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly WebsiteFaqService $faqService,
        private readonly StorefrontConfigurationResolver $resolver,
    ) {}

    public function index(Request $request)
    {
        return $this->renderIndex($request);
    }

    public function preview(Request $request, ?StorefrontRevision $revision = null)
    {
        abort_unless($request->user()?->can('settings.website'), 403);
        $tenant = Tenant::getCurrent();
        abort_unless($tenant, 404);

        // Remember that this admin session is previewing the draft so follow-up
        // storefront pages (products, product detail) keep resolving the draft
        // configuration until they return to the admin area.
        $request->session()->put('storefront_preview_draft', (int) $tenant->id);

        $configuration = $revision ? $this->resolver->resolveRevision($revision) : null;
        return $this->renderIndex($request, 'draft', $configuration, $revision);
    }

    private function draftPreviewActive(Request $request): bool
    {
        $tenant = Tenant::getCurrent();

        return $tenant
            && (int) $request->session()->get('storefront_preview_draft') === (int) $tenant->id
            && (bool) $request->user()?->can('settings.website');
    }

    private function renderIndex(Request $request, string $context = 'published', ?array $configuration = null, ?StorefrontRevision $previewRevision = null)
    {
        $tenant = Tenant::getCurrent();
        if (!$tenant) {
            abort(404);
        }

        if ($tenant->isLocked()) {
            return $this->renderLocked($tenant);
        }

        return Inertia::render('Storefront/Index', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'store_url' => $tenant->store_url,
                'logo' => $tenant->logo,
                'status' => $tenant->status,
            ],
            'storefront' => $configuration ?? $this->resolver->resolve($tenant, $context),
            'previewMode' => $context === 'draft' ? [
                'mode' => in_array($request->query('viewport'), ['mobile', 'desktop'], true) ? $request->query('viewport') : 'desktop',
                'revision_number' => $previewRevision?->revision_number,
                'admin_url' => route('storefront.admin.storefront.index', ['store_slug' => $tenant->slug]),
            ] : null,
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

        $query = (string) $request->input('query', '');
        $categoryId = $this->normalizeId($request->input('category'));
        $brandId = $this->normalizeId($request->input('brand'));
        $type = $this->normalizeType($request->input('type'));
        [$minPrice, $maxPrice, $priceApplied] = $this->normalizePriceRange(
            $request->input('min_price'),
            $request->input('max_price')
        );
        $sort = $this->normalizeSort($request->input('sort'));
        $inStock = $request->boolean('in_stock');

        $products = Product::active()
            ->with(['category', 'brand'])
            ->with(['variants' => fn($q) => $q->active(), 'comboItems.comboProduct', 'comboItems.linkedVariant']);

        if ($query !== '') {
            $products->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('sku', 'LIKE', "%{$query}%")
                  ->orWhereHas('brand', fn($bq) => $bq->where('name', 'LIKE', "%{$query}%"))
                  ->orWhereHas('category', fn($cq) => $cq->where('name', 'LIKE', "%{$query}%"));
            });
        }

        if ($categoryId !== null) {
            $products->where('category_id', $categoryId);
        }

        if ($brandId !== null) {
            $products->where('brand_id', $brandId);
        }

        if ($type !== null) {
            $products->where('type', $type);
        }

        if ($priceApplied && $minPrice !== null) {
            $products->where('price', '>=', $minPrice);
        }
        if ($priceApplied && $maxPrice !== null) {
            $products->where('price', '<=', $maxPrice);
        }

        if ($inStock) {
            $this->applyInStockFilter($products);
        }

        $this->applySorting($products, $sort);

        $promotions = Promotion::valid()->automatic()
            ->with(['products', 'categories'])
            ->orderBy('priority', 'desc')
            ->get();

        $categories = Category::active()->orderBy('name')->get(['id', 'name', 'slug', 'image']);
        $brands = Brand::active()->orderBy('name')->get(['id', 'name', 'slug', 'logo']);

        $websiteInfo = WebsiteInfo::firstWhere('tenant_id', $tenant->id);
        $currencySymbol = $websiteInfo->currency_symbol ?? 'K';

        return Inertia::render('Storefront/Products', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'store_url' => $tenant->store_url,
                'logo' => $tenant->logo,
                'status' => $tenant->status,
            ],
            'products' => Inertia::scroll(fn () => $products->paginate(12)->through(function ($product) use ($promotions, $currencySymbol) {
                return $this->enrichProductWithPromotion($product, $promotions, $currencySymbol);
            })),
            'categories' => $categories,
            'brands' => $brands,
            'searchQuery' => $query,
            'filters' => [
                'category_id' => $categoryId !== null ? (string) $categoryId : '',
                'brand_id' => $brandId !== null ? (string) $brandId : '',
                'type' => $type ?? '',
                'min_price' => $minPrice,
                'max_price' => $maxPrice,
                'sort' => $sort,
                'in_stock' => $inStock,
            ],
            'previewMode' => $this->draftPreviewActive($request) ? [
                'mode' => 'desktop',
                'admin_url' => route('storefront.admin.storefront.index', ['store_slug' => $tenant->slug]),
            ] : null,
        ]);
    }

    private function normalizeId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            return null;
        }
        $int = (int) $value;
        return $int > 0 ? $int : null;
    }

    private function normalizeType(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $value = (string) $value;
        $allowed = [Product::TYPE_SINGLE, Product::TYPE_VARIABLE, Product::TYPE_COMBO];
        return in_array($value, $allowed, true) ? $value : null;
    }

    private function normalizePriceRange(mixed $rawMin, mixed $rawMax): array
    {
        $min = null;
        $max = null;
        $applied = false;

        if ($rawMin !== null && $rawMin !== '' && is_numeric($rawMin) && (float) $rawMin >= 0) {
            $min = (float) $rawMin;
            $applied = true;
        }
        if ($rawMax !== null && $rawMax !== '' && is_numeric($rawMax) && (float) $rawMax >= 0) {
            $max = (float) $rawMax;
            $applied = true;
        }

        if ($min !== null && $max !== null && $min > $max) {
            [$min, $max] = [$max, $min];
        }

        return [$min, $max, $applied];
    }

    private function normalizeSort(mixed $value): string
    {
        $allowed = ['recommended', 'newest', 'price_asc', 'price_desc', 'name_asc', 'name_desc', 'name'];
        $value = is_string($value) ? $value : '';
        if (!in_array($value, $allowed, true)) {
            return 'recommended';
        }
        if ($value === 'name') {
            return 'name_asc';
        }
        return $value;
    }

    public function brands(Request $request)
    {
        $tenant = Tenant::getCurrent();
        if (!$tenant) {
            abort(404);
        }

        if ($tenant->isLocked()) {
            return $this->renderLocked($tenant);
        }

        $featured = $request->boolean('featured');

        $brands = Brand::active()
            ->when($featured, fn($q) => $q->featured())
            ->sorted()
            ->withCount(['products as products_count' => fn($q) => $q->active()])
            ->get(['id', 'name', 'slug', 'logo', 'banner', 'description']);

        return Inertia::render('Storefront/Brands', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'store_url' => $tenant->store_url,
                'logo' => $tenant->logo,
                'status' => $tenant->status,
            ],
            'brands' => $brands,
            'filters' => [
                'featured' => $featured,
            ],
            'previewMode' => $this->draftPreviewActive($request) ? [
                'mode' => 'desktop',
                'admin_url' => route('storefront.admin.storefront.index', ['store_slug' => $tenant->slug]),
            ] : null,
        ]);
    }

    public function brand(Request $request, Brand $brand)
    {
        $tenant = Tenant::getCurrent();
        if (!$tenant) {
            abort(404);
        }

        if ($tenant->isLocked()) {
            return $this->renderLocked($tenant);
        }

        if ($brand->tenant_id !== $tenant->id) {
            abort(404);
        }

        if (!$brand->is_active) {
            abort(404);
        }

        $sort = $this->normalizeSort($request->input('sort'));
        $inStock = $request->boolean('in_stock');

        $products = Product::active()
            ->where('brand_id', $brand->id)
            ->with(['category', 'variants' => fn($q) => $q->active(), 'comboItems.comboProduct', 'comboItems.linkedVariant']);

        if ($inStock) {
            $this->applyInStockFilter($products);
        }

        $this->applySorting($products, $sort);

        $promotions = Promotion::valid()->automatic()
            ->with(['products', 'categories'])
            ->orderBy('priority', 'desc')
            ->get();

        $categories = Category::active()->orderBy('name')->get(['id', 'name', 'slug', 'image']);
        $brands = Brand::active()->orderBy('name')->get(['id', 'name', 'slug', 'logo']);

        $websiteInfo = WebsiteInfo::firstWhere('tenant_id', $tenant->id);
        $currencySymbol = $websiteInfo->currency_symbol ?? 'K';

        return Inertia::render('Storefront/BrandProducts', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'store_url' => $tenant->store_url,
                'logo' => $tenant->logo,
                'status' => $tenant->status,
            ],
            'brand' => [
                'id' => $brand->id,
                'name' => $brand->name,
                'slug' => $brand->slug,
                'description' => $brand->description,
                'logo_url' => $brand->logo_url,
                'banner_url' => $brand->banner_url,
            ],
            'products' => Inertia::scroll(fn () => $products->paginate(12)->through(function ($product) use ($promotions, $currencySymbol) {
                return $this->enrichProductWithPromotion($product, $promotions, $currencySymbol);
            })),
            'categories' => $categories,
            'brands' => $brands,
            'searchQuery' => '',
            'filters' => [
                'category_id' => '',
                'brand_id' => (string) $brand->id,
                'type' => '',
                'min_price' => null,
                'max_price' => null,
                'sort' => $sort,
                'in_stock' => $inStock,
            ],
            'previewMode' => $this->draftPreviewActive($request) ? [
                'mode' => 'desktop',
                'admin_url' => route('storefront.admin.storefront.index', ['store_slug' => $tenant->slug]),
            ] : null,
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

        $websiteInfo = WebsiteInfo::firstWhere('tenant_id', $tenant->id);
        $currencySymbol = $websiteInfo->currency_symbol ?? 'K';

        $relatedProducts = collect();
        if ($product->category_id) {
            $relatedProducts = Product::active()
                ->where('category_id', $product->category_id)
                ->where('id', '!=', $product->id)
                ->with(['category', 'brand'])
                ->limit(6)
                ->get();
        }
        if ($relatedProducts->count() < 4 && $product->brand_id) {
            $excludeIds = $relatedProducts->pluck('id')->toArray();
            $excludeIds[] = $product->id;
            $brandProducts = Product::active()
                ->where('brand_id', $product->brand_id)
                ->whereNotIn('id', $excludeIds)
                ->with(['category', 'brand'])
                ->limit(6 - $relatedProducts->count())
                ->get();
            $relatedProducts = $relatedProducts->merge($brandProducts);
        }

        $relatedProducts = $relatedProducts->map(fn ($rp) => $this->enrichProductWithPromotion($rp, $promotions, $currencySymbol))->values();

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
            'relatedProducts' => $relatedProducts->values(),
            'previewMode' => $this->draftPreviewActive($request) ? [
                'mode' => 'desktop',
                'admin_url' => route('storefront.admin.storefront.index', ['store_slug' => $tenant->slug]),
            ] : null,
        ]);
    }

    public function faq(Request $request)
    {
        $tenant = Tenant::getCurrent();
        if (!$tenant) {
            abort(404);
        }

        if ($tenant->isLocked()) {
            return $this->renderLocked($tenant);
        }

        $faqs = $this->faqService->getActiveForTenant($tenant->id);
        $categories = $this->faqService->getCategories();

        $faqCategories = $faqs->pluck('category')->unique()->filter()->values()->toArray();
        $availableCategories = array_intersect_key($categories, array_flip($faqCategories));

        return Inertia::render('Storefront/Faq', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'store_url' => $tenant->store_url,
                'logo' => $tenant->logo,
                'status' => $tenant->status,
            ],
            'faqs' => $faqs->map(fn ($faq) => [
                'id' => $faq->id,
                'category' => $faq->category,
                'question' => $faq->question,
                'answer' => $faq->answer,
            ]),
            'categories' => $availableCategories,
        ]);
    }

    private function enrichProductWithPromotion($product, $promotions, string $currencySymbol = 'K')
    {
        $bestPromotion = $this->findBestPromotionForProduct($product, $promotions);
        if ($bestPromotion) {
            $product->promotion_badge = $this->formatPromotionBadge($bestPromotion, $currencySymbol);
            $product->promotion_discount = (float) $bestPromotion->value;
            $discount = $bestPromotion->type === Promotion::TYPE_PERCENTAGE
                ? $product->price * (float) $bestPromotion->value / 100
                : (float) $bestPromotion->value;
            if ($bestPromotion->max_discount_amount !== null) {
                $discount = min($discount, (float) $bestPromotion->max_discount_amount);
            }
            $product->promotion_price = max(0, round($product->price - $discount, 2));
        }
        return $product;
    }

    private function findBestPromotionForProduct($product, $promotions)
    {
        $bestPromotion = null;
        $maxDiscount = 0;

        foreach ($promotions as $promotion) {
            if (!$promotion->isCurrentlyActive()) {
                continue;
            }

            $applies = $promotion->applies_to === Promotion::APPLIES_ALL;

            if (!$applies && $promotion->applies_to === Promotion::APPLIES_PRODUCTS) {
                $applies = $promotion->products->contains($product->id);
            }

            if (!$applies && $promotion->applies_to === Promotion::APPLIES_CATEGORIES) {
                $applies = $product->category && $promotion->categories->contains($product->category->id);
            }

            if ($applies) {
                $discount = $promotion->type === Promotion::TYPE_PERCENTAGE
                    ? ($product->price * (float) $promotion->value / 100)
                    : (float) $promotion->value;

                if ($promotion->max_discount_amount !== null) {
                    $discount = min($discount, (float) $promotion->max_discount_amount);
                }

                if ($discount > $maxDiscount) {
                    $maxDiscount = $discount;
                    $bestPromotion = $promotion;
                }
            }
        }

        return $bestPromotion;
    }

    private function formatPromotionBadge($promotion, string $currencySymbol = 'K'): string
    {
        return match ($promotion->type) {
            Promotion::TYPE_PERCENTAGE => "-{$promotion->value}%",
            Promotion::TYPE_FIXED => "-" . number_format((float) $promotion->value, 0) . ' ' . $currencySymbol,
            Promotion::TYPE_FREE_SHIPPING => 'Free Shipping',
            default => 'Sale',
        };
    }

    private function applySorting($query, string $sort): void
    {
        match ($sort) {
            'price_asc' => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'name_asc' => $query->orderBy('name', 'asc'),
            'name_desc' => $query->orderBy('name', 'desc'),
            'newest' => $query->orderBy('created_at', 'desc'),
            'recommended' => $query->sorted(),
            default => $query->sorted(),
        };
    }

    private function applyInStockFilter($query): void
    {
        $query->where(function ($q) {
            $q->where(function ($sq) {
                $sq->where('type', Product::TYPE_SINGLE)
                    ->where('stock', '>', 0);
            })->orWhere(function ($sq) {
                $sq->where('type', Product::TYPE_VARIABLE)
                    ->whereHas('variants', fn($v) => $v->selectRaw('SUM(stock) > 0'));
            })->orWhere(function ($sq) {
                $sq->where('type', Product::TYPE_COMBO)
                    ->whereHas('comboItems')
                    ->whereDoesntHave('comboItems', function ($ci) {
                        $ci->whereHas('comboProduct', fn($c) => $c->where('stock', '<=', 0));
                    });
            });
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
