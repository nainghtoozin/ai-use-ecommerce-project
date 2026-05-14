<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionBanner;
use App\Models\PaymentMethod;
use App\Models\City;
use Inertia\Inertia;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->input('query', '');
        $categoryId = $request->input('category', '');
        $sort = $request->input('sort', 'latest');

        $products = Product::with('category');

        if ($query) {
            $products->where('name', 'LIKE', "%{$query}%");
        }

        if ($categoryId) {
            $products->where('category_id', $categoryId);
        }

        switch ($sort) {
            case 'price_asc':
                $products->orderBy('price', 'asc');
                break;
            case 'price_desc':
                $products->orderBy('price', 'desc');
                break;
            case 'name':
                $products->orderBy('name', 'asc');
                break;
            default:
                $products->orderBy('created_at', 'desc');
                break;
        }

        $promotions = Promotion::valid()->automatic()
            ->with(['products', 'categories'])
            ->orderBy('priority', 'desc')
            ->get();

        return Inertia::render('Client/Products/Index', [
            'products' => Inertia::scroll(fn () => $products->paginate(8)->through(function ($product) use ($promotions) {
                $best = $this->findBestPromotionForProduct($product, $promotions);
                if ($best) {
                    $product->promotion_badge = $best['badge'];
                    $product->promotion_discount = $best['discount'];
                    $product->promotion_price = max(0, (float) $product->price - $best['discount']);
                }
                return $product;
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
        $promotions = Promotion::valid()->automatic()
            ->with(['products', 'categories'])
            ->orderBy('priority', 'desc')
            ->get();

        $best = $this->findBestPromotionForProduct($product, $promotions);

        return Inertia::render('Client/Products/Show', [
            'product' => $product,
            'promotion' => $best,
        ]);
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

            $cartItem = [['id' => $product->id, 'price' => (float) $product->price, 'quantity' => 1]];
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
            $savings = min($discount, (float) $product->price);
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
}
