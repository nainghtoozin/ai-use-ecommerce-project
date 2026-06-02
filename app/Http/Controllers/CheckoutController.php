<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use App\Models\City;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CouponService;
use App\Services\PromotionService;
use App\Services\ProductService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CouponService $couponService,
        private readonly PromotionService $promotionService,
        private readonly ProductService $productService
    ) {}

    public function index()
    {
        $settings = \App\Models\WebsiteInfo::getSettings();
        $guestCheckout = (bool) ($settings->guest_checkout_enabled ?? true);

        if (!auth()->check() && !$guestCheckout) {
            return redirect()->route('login')
                ->with('error', 'Please login to continue checkout.');
        }

        $cart = session()->get('cart', []);

        if (empty($cart)) {
            return redirect()->route('cart.index')
                ->with('error', 'Your cart is empty.');
        }

        $paymentMethods = PaymentMethod::active()->orderBy('name')->get();

        if (auth()->check()) {
            $user = auth()->user();
            $paymentMethods = $paymentMethods->filter(function ($pm) use ($user) {
                if ($pm->type === 'cod') {
                    return $user->allow_cod;
                }
                return true;
            })->values();
        } else {
            $paymentMethods = $paymentMethods->reject(function ($pm) {
                return $pm->type === 'cod';
            })->values();
        }

        $cities = City::getActiveWithTownships();

        $cartItems = $this->getCartItems($cart);
        $subtotal = (float) array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $cartItems));

        $appliedCoupon = session()->get('applied_coupon');
        $couponDiscount = (float) ($appliedCoupon['discount'] ?? 0);

        $appliedPromotion = session()->get('applied_promotion');
        $promotionDiscount = (float) ($appliedPromotion['discount'] ?? 0);

        $totalDiscount = $couponDiscount + $promotionDiscount;
        $autoPromotions = $this->promotionService->getAutoPromotionsForCheckout($cartItems);

        return Inertia::render('Client/Cart/Checkout', [
            'cartItems' => $cartItems,
            'subtotal' => $subtotal,
            'paymentMethods' => $paymentMethods,
            'cities' => $cities,
            'appliedCoupon' => $appliedCoupon,
            'appliedPromotion' => $appliedPromotion,
            'discountAmount' => $totalDiscount,
            'autoPromotions' => $autoPromotions,
        ]);
    }

    private function getCartItems(array $cart): array
    {
        $items = [];
        foreach ($cart as $cartKey => $item) {
            $productId = $item['product_id'] ?? $item['id'] ?? null;
            if (!$productId) {
                continue;
            }
            $variantId = $item['variant_id'] ?? null;

            $product = Product::select(['id', 'name', 'price', 'type', 'photo1'])->find($productId);
            if (!$product) {
                continue;
            }

            $price = (float) $product->price;
            $variantName = null;

            if ($variantId) {
                $variant = ProductVariant::select(['id', 'price', 'attributes'])->find($variantId);
                if ($variant) {
                    $price = (float) ($variant->price ?? $product->price);
                    $variantName = $variant->label;
                }
            }

            $items[] = [
                'cart_key' => $cartKey,
                'id' => $product->id,
                'variant_id' => $variantId,
                'name' => $product->name,
                'variant_name' => $variantName,
                'price' => $price,
                'photo1' => $product->photo1,
                'quantity' => $item['quantity'],
            ];
        }
        return $items;
    }
}
