<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use App\Models\City;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CodEligibilityService;
use App\Services\CouponService;
use App\Services\DeliveryFeeService;
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
        private readonly ProductService $productService,
        private readonly CodEligibilityService $codEligibilityService,
        private readonly DeliveryFeeService $deliveryFeeService
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

        if (!auth()->check()) {
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

        $codAvailability = [];
        $codMethod = $paymentMethods->firstWhere('type', 'cod');
        if ($codMethod) {
            $user = auth()->check() ? auth()->user() : null;
            foreach ($cities as $city) {
                $codAvailability[$city->id] = $this->codEligibilityService->isCodAvailable(
                    $codMethod,
                    $user instanceof \App\Models\User ? $user : null,
                    $city->id,
                    $subtotal
                );
            }
        }

        return Inertia::render('Client/Cart/Checkout', [
            'cartItems' => $cartItems,
            'subtotal' => $subtotal,
            'paymentMethods' => $paymentMethods,
            'cities' => $cities,
            'codAvailability' => $codAvailability,
            'appliedCoupon' => $appliedCoupon,
            'appliedPromotion' => $appliedPromotion,
            'discountAmount' => $totalDiscount,
            'autoPromotions' => $autoPromotions,
        ]);
    }

    public function codQuote(Request $request)
    {
        $validated = $request->validate([
            'city_id' => ['nullable', 'exists:cities,id'],
            'township_id' => ['nullable', 'exists:townships,id'],
        ]);

        $cartItems = $this->getCartItems(session()->get('cart', []));
        $subtotal = (float) array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $cartItems));

        $city = null;
        $township = null;
        if (!empty($validated['city_id']) || !empty($validated['township_id'])) {
            try {
                $location = app(\App\Services\LocationService::class)->resolveTenantLocation(
                    $validated['city_id'] ?? null,
                    $validated['township_id'] ?? null,
                    \App\Models\Tenant::getCurrent()
                );
            } catch (\Illuminate\Validation\ValidationException $e) {
                return response()->json(['message' => 'Invalid location.'], 422);
            }
            $city = $location['city'];
            $township = $location['township'];
        }

        $deliveryFee = (float) $this->deliveryFeeService->getTownshipFee($township);

        $appliedCoupon = session()->get('applied_coupon');
        $appliedPromotion = session()->get('applied_promotion');
        $totalDiscount = (float) ($appliedCoupon['discount'] ?? 0) + (float) ($appliedPromotion['discount'] ?? 0);

        $basis = ($subtotal + $deliveryFee) - $totalDiscount;

        $codMethod = PaymentMethod::active()->orderBy('name')->get()->firstWhere('type', 'cod');
        if (!$codMethod) {
            return response()->json([
                'available' => null, 'unavailable_reason' => null,
                'total' => max(0, $basis),
            ]);
        }

        $user = auth()->check() ? auth()->user() : null;
        $available = $this->codEligibilityService->isCodAvailable(
            $codMethod, $user instanceof \App\Models\User ? $user : null, $city?->id, $basis
        );

        return response()->json([
            'available' => $available,
            'unavailable_reason' => $available ? null : $this->codEligibilityService->getIneligibilityReason(
                $codMethod, $user instanceof \App\Models\User ? $user : null, $city?->id, $basis
            ),
            'total' => max(0, $basis),
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
                'photo1_url' => $product->photo1_url,
                'quantity' => $item['quantity'],
            ];
        }
        return $items;
    }
}
