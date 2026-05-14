<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use App\Models\City;
use App\Services\CouponService;
use App\Services\PromotionService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CouponService $couponService,
        private readonly PromotionService $promotionService
    ) {}

    public function index()
    {
        $cart = session()->get('cart', []);

        if (empty($cart)) {
            return redirect()->route('cart.index')
                ->with('error', 'Your cart is empty.');
        }

        $paymentMethods = PaymentMethod::active()->orderBy('name')->get();
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
        foreach ($cart as $id => $item) {
            $product = \App\Models\Product::find($id);
            if ($product) {
                $items[] = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => (float) $product->price,
                    'photo1' => $product->photo1,
                    'quantity' => $item['quantity'],
                ];
            }
        }
        return $items;
    }
}
