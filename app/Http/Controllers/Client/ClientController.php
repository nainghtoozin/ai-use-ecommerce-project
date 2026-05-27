<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\PromotionBanner;
use App\Models\PaymentMethod;
use App\Models\City;
use App\Services\ProductService;
use Inertia\Inertia;

class ClientController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
    ) {}

    public function index(Request $request)
    {
        $query = $request->input('query', '');
        $categoryId = $request->input('category', '');
        $sort = $request->input('sort', 'latest');

        $products = Product::active()
            ->with('category')
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

        return Inertia::render('Client/Products/Index', [
            'products' => Inertia::scroll(fn () => $products->paginate(8)->through(function ($product) use ($promotions) {
                return $this->enrichProductWithPromotion($product, $promotions);
            })),
            'categories' => fn () => Category::all(),
            'banners' => fn () => PromotionBanner::active()->latest()->get(),
            'searchQuery' => $query,
            'filters' => [
                'category_id' => $categoryId,
                'sort' => $sort,
            ],
        ]);
    }

    public function products(Request $request)
    {
        $query = $request->input('query', '');
        $categoryId = $request->input('category', '');
        $sort = $request->input('sort', 'latest');
        $inStock = $request->boolean('in_stock');

        $products = Product::active()
            ->with('category')
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

        return Inertia::render('Client/Products/Products', [
            'products' => Inertia::scroll(fn () => $products->paginate(12)->through(function ($product) use ($promotions) {
                return $this->enrichProductWithPromotion($product, $promotions);
            })),
            'categories' => fn () => Category::all(),
            'searchQuery' => $query,
            'filters' => [
                'category_id' => $categoryId,
                'sort' => $sort,
                'in_stock' => $inStock,
            ],
        ]);
    }

    public function cart()
    {
        return Inertia::render('Client/Cart/Index');
    }

    public function showLogin()
    {
        return Inertia::render('Auth/Login');
    }

    public function showRegister()
    {
        return Inertia::render('Auth/Register');
    }

    public function show_product(Product $product)
    {
        if ($product->status !== Product::STATUS_ACTIVE) {
            abort(404);
        }

        $promotions = Promotion::valid()->automatic()
            ->with(['products', 'categories'])
            ->orderBy('priority', 'desc')
            ->get();

        $product->loadMissing('category');
        if ($product->isVariable()) {
            $product->loadMissing(['variants' => fn($q) => $q->active()]);
        }
        if ($product->isCombo()) {
            $product->loadMissing(['comboItems.comboProduct', 'comboItems.linkedVariant']);
        }

        $best = $this->findBestPromotionForProduct($product, $promotions);
        $detail = $this->productService->resolveForDetail($product);

        $data = [
            'product' => $product,
            'promotion' => $best,
            'detail' => $detail,
        ];

        return Inertia::render('Client/Products/Show', $data);
    }

    public function checkout()
    {
        $paymentMethods = PaymentMethod::active()->orderBy('name')->get();
        $cities = City::getActiveWithTownships();
        return Inertia::render('Client/Cart/Checkout', [
            'paymentMethods' => $paymentMethods,
            'cities' => $cities,
        ]);
    }

    public function orders(Product $product)
    {
        return Inertia::render('Client/Orders/Index', [
            'product' => $product,
        ]);
    }

    private function enrichProductWithPromotion(Product $product, $promotions): Product
    {
        $best = $this->findBestPromotionForProduct($product, $promotions);
        if ($best) {
            $product->promotion_badge = $best['badge'];
            $product->promotion_discount = $best['discount'];
            $effectivePrice = $this->productService->getPrice($product);
            $product->promotion_price = max(0, $effectivePrice - $best['discount']);
        }
        return $product;
    }

    private function findBestPromotionForProduct(Product $product, $promotions): ?array
    {
        $best = null;
        $bestDiscount = 0;

        foreach ($promotions as $promotion) {
            $applies = false;

            if ($promotion->applies_to === Promotion::APPLIES_ALL) {
                $applies = true;
            } elseif ($promotion->applies_to === Promotion::APPLIES_PRODUCTS) {
                $applies = $promotion->products->contains('id', $product->id);
            } elseif ($promotion->applies_to === Promotion::APPLIES_CATEGORIES) {
                $applies = $promotion->categories->contains('id', $product->category_id);
            }

            if (!$applies) continue;

            $effectivePrice = $this->productService->getPrice($product);
            $cartItem = [['id' => $product->id, 'price' => $effectivePrice, 'quantity' => 1]];
            $discount = $promotion->calculateDiscount($cartItem);

            if ($discount > 0 && $discount > $bestDiscount) {
                $bestDiscount = $discount;
                $best = [
                    'id' => $promotion->id,
                    'name' => $promotion->name,
                    'type' => $promotion->type,
                    'value' => $promotion->value,
                    'discount' => $discount,
                    'badge' => $this->formatPromotionBadge($promotion, $discount, $product),
                ];
            }
        }

        return $best;
    }

    private function formatPromotionBadge(Promotion $promotion, float $discount, Product $product): string
    {
        if ($promotion->type === Promotion::TYPE_PERCENTAGE) {
            return '-' . round($promotion->value) . '%';
        }

        if ($promotion->type === Promotion::TYPE_FIXED) {
            $effectivePrice = $this->productService->getPrice($product);
            $savings = min($discount, $effectivePrice);
            if ($savings >= 1000) {
                return 'Save ' . number_format($savings) . ' MMK';
            }
            return '-' . number_format($savings) . ' MMK';
        }

        if ($promotion->type === Promotion::TYPE_FREE_SHIPPING) {
            return 'Free Shipping';
        }

        return 'SALE';
    }

    private function applySorting($query, string $sort): void
    {
        switch ($sort) {
            case 'price_asc':
                $query->orderBy('price', 'asc');
                break;
            case 'price_desc':
                $query->orderBy('price', 'desc');
                break;
            case 'name':
                $query->orderBy('name', 'asc');
                break;
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }
    }

    private function applyInStockFilter($query): void
    {
        $query->where(function ($q) {
            $q->where(function ($sq) {
                $sq->where('type', Product::TYPE_SINGLE)
                    ->where('stock', '>', 0);
            })->orWhere(function ($sq) {
                $sq->where('type', Product::TYPE_VARIABLE)
                    ->whereIn('id', function ($sub) {
                        $sub->select('product_id')
                            ->from('product_variants')
                            ->where('status', ProductVariant::STATUS_ACTIVE)
                            ->groupBy('product_id')
                            ->havingRaw('SUM(stock) > 0');
                    });
            })->orWhere(function ($sq) {
                $sq->where('type', Product::TYPE_COMBO)
                    ->whereIn('id', function ($sub) {
                        $sub->select('product_id')
                            ->from('product_combos')
                            ->whereIn('combo_product_id', function ($sub2) {
                                $sub2->select('id')
                                    ->from('products')
                                    ->where('status', Product::STATUS_ACTIVE)
                                    ->where('stock', '>', 0);
                            });
                    });
            });
        });
    }
}
