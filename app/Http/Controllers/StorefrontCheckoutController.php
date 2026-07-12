<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessOrderNotifications;
use App\Models\City;
use App\Models\Coupon;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Services\CouponService;
use App\Services\ImageService;
use App\Services\PromotionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class StorefrontCheckoutController extends Controller
{
    public function __construct(
        private readonly ImageService $imageService,
        private readonly CouponService $couponService,
        private readonly PromotionService $promotionService
    ) {}

    public function index()
    {
        $tenant = Tenant::getCurrent();
        if (!$tenant) {
            abort(404);
        }

        $guestCheckout = true;

        if (!auth()->check() && !$guestCheckout) {
            return redirect()->route('storefront.login', $tenant->slug)
                ->with('error', 'Please login to continue checkout.');
        }

        $cart = session()->get('cart', []);
        $cartItems = $this->filterCartByTenant($cart, $tenant);

        if (empty($cartItems)) {
            return redirect()->route('storefront.cart', $tenant->slug)
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
        $subtotal = (float) array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $cartItems));

        $addresses = collect();
        $defaultAddress = null;
        if (auth()->check()) {
            $addresses = auth()->user()->addresses()
                ->orderBy('is_default', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();
            $defaultAddress = $addresses->firstWhere('is_default', true) ?? $addresses->first();
        }

        $appliedCoupon = session()->get('applied_coupon');
        $couponDiscount = (float) ($appliedCoupon['discount'] ?? 0);

        $appliedPromotion = session()->get('applied_promotion');
        $promotionDiscount = (float) ($appliedPromotion['discount'] ?? 0);

        $totalDiscount = $couponDiscount + $promotionDiscount;
        $autoPromotions = $this->promotionService->getAutoPromotionsForCheckout($cartItems);

        return Inertia::render('Storefront/Checkout', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'store_url' => $tenant->store_url,
                'logo' => $tenant->logo,
                'status' => $tenant->status,
            ],
            'cartItems' => array_values($cartItems),
            'subtotal' => $subtotal,
            'paymentMethods' => $paymentMethods,
            'cities' => $cities,
            'appliedCoupon' => $appliedCoupon,
            'appliedPromotion' => $appliedPromotion,
            'discountAmount' => $totalDiscount,
            'autoPromotions' => $autoPromotions,
            'addresses' => $addresses,
            'defaultAddress' => $defaultAddress,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = Tenant::getCurrent();
        if (!$tenant) {
            abort(404);
        }

        $guestCheckout = true;

        if (!auth()->check() && !$guestCheckout) {
            return redirect()->route('storefront.login', $tenant->slug)
                ->with('error', 'Please login to continue checkout.');
        }

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['required', 'string'],
            'city_id' => ['nullable', 'exists:cities,id'],
            'township_id' => ['nullable', 'exists:townships,id'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'payer_name' => ['nullable', 'string', 'max:255'],
            'transaction_id' => ['nullable', 'string', 'max:255'],
            'payment_screenshot' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        if (auth()->check()) {
            $user = auth()->user();
            if ($user->tenant && $user->tenant->subscriptionExpired()) {
                return back()->with('error', 'Your subscription has expired. Please renew your subscription to place orders.');
            }
        }

        if (auth()->check()) {
            $codMethods = PaymentMethod::where('type', 'cod')->pluck('id');
            if ($codMethods->isNotEmpty() && $codMethods->contains($validated['payment_method_id'])) {
                $user = auth()->user();
                if (!$user || !$user->allow_cod) {
                    return back()->with('error', 'COD payment is not available for your account.');
                }
            }
        }

        $paymentScreenshotPath = null;
        if ($request->hasFile('payment_screenshot')) {
            $paymentScreenshotPath = $this->imageService->upload($request->file('payment_screenshot'), 'payment-proofs');
        }

        $cart = session()->get('cart', []);
        $cartItems = $this->filterCartByTenant($cart, $tenant);

        if (empty($cartItems)) {
            return back()->with('error', 'Cart is empty.');
        }

        $items = [];
        foreach ($cartItems as $item) {
            $productId = (int) ($item['product_id'] ?? $item['id']);
            $itemData = [
                'product_id' => $productId,
                'quantity' => (int) ($item['quantity'] ?? 1),
                'price' => (float) ($item['price'] ?? 0),
            ];

            if (!empty($item['variant_id'])) {
                $itemData['variant_id'] = (int) $item['variant_id'];
            }

            $items[] = $itemData;
        }

        $subtotal = (float) array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $items));

        $deliveryFee = 0;
        if (!empty($validated['city_id'])) {
            $city = City::find($validated['city_id']);
            if ($city) $deliveryFee = (float) $city->delivery_fee;
        }

        $couponData = $this->resolveCouponFromSession($items, $deliveryFee);
        $couponDiscount = (float) ($couponData['discount'] ?? 0);

        $promotionData = $this->resolvePromotionFromSession($items, $deliveryFee);
        $promotionDiscount = (float) ($promotionData['discount'] ?? 0);

        $totalDiscount = $couponDiscount + $promotionDiscount;
        $totalAmount = ($subtotal + $deliveryFee) - $totalDiscount;

        $orderData = [
            'user_id' => auth()->id(),
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?? null,
            'address' => $validated['address'],
            'city_id' => $validated['city_id'] ?? null,
            'township_id' => $validated['township_id'] ?? null,
            'postal_code' => $validated['postal_code'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'payment_method_id' => $validated['payment_method_id'],
            'payer_name' => $validated['payer_name'] ?? null,
            'payment_screenshot' => $paymentScreenshotPath,
            'transaction_id' => $validated['transaction_id'] ?? null,
            'subtotal' => $subtotal,
            'delivery_fee' => $deliveryFee,
            'discount_amount' => $totalDiscount,
            'total_amount' => $totalAmount,
            'payment_status' => Order::PAYMENT_STATUS_PENDING,
            'order_status' => Order::ORDER_STATUS_PENDING,
        ];

        if (!empty($promotionData['promotion'])) {
            $orderData['promotion_id'] = $promotionData['promotion']->id;
            $orderData['promotion_code'] = $promotionData['promotion']->code ?? 'AUTO';
        }

        $order = Order::create($orderData);

        if (!empty($couponData['coupon'])) {
            $this->couponService->applyCouponToOrder(
                $order,
                $couponData['coupon'],
                $couponData['discount']
            );
        }

        if (!empty($promotionData['promotion'])) {
            $this->promotionService->applyPromotionToOrder(
                $order,
                $promotionData['promotion'],
                $promotionData['discount']
            );
        }

        foreach ($items as $item) {
            $orderItemData = [
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
            ];

            if (!empty($item['variant_id'])) {
                $orderItemData['variant_id'] = $item['variant_id'];
            }

            $order->items()->create($orderItemData);
        }

        ProcessOrderNotifications::dispatch($order, $paymentScreenshotPath)
            ->onQueue('default');

        session()->forget('cart');
        session()->forget('applied_coupon');
        session()->forget('applied_promotion');

        return redirect()->route('storefront.customer.orders.show', [
            'store_slug' => $tenant->slug,
            'order' => $order->id,
        ])->with('success', 'Order placed successfully!');
    }

    private function filterCartByTenant(array $cart, Tenant $tenant): array
    {
        if (empty($cart)) {
            return [];
        }

        $tenantId = $tenant->id;
        $tenantProductIds = Product::where('tenant_id', $tenantId)->pluck('id')->toArray();

        $items = [];
        foreach ($cart as $cartKey => $item) {
            $productId = $item['product_id'] ?? $item['id'] ?? null;
            if (!$productId) {
                continue;
            }

            if (!in_array((int) $productId, $tenantProductIds)) {
                continue;
            }

            $product = Product::select(['id', 'name', 'price', 'type', 'photo1'])->find($productId);
            if (!$product) {
                continue;
            }

            $price = (float) $product->price;
            $variantName = null;
            $variantId = $item['variant_id'] ?? null;

            if ($variantId) {
                $variant = ProductVariant::select(['id', 'price', 'attributes'])->find($variantId);
                if ($variant) {
                    $price = (float) ($variant->price ?? $product->price);
                    $variantName = $variant->label;
                }
            }

            $items[$cartKey] = [
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

    private function resolveCouponFromSession(array $items, float $deliveryFee): array
    {
        $appliedCoupon = session()->get('applied_coupon');

        if ($appliedCoupon && isset($appliedCoupon['coupon_id'])) {
            $coupon = Coupon::find($appliedCoupon['coupon_id']);
            if ($coupon && $coupon->isValid()) {
                $cartItems = collect($items);
                $result = $this->couponService->validateCoupon(
                    $coupon->code,
                    $cartItems,
                    auth()->id(),
                    $deliveryFee
                );
                if ($result['valid']) {
                    return [
                        'coupon' => $coupon,
                        'discount' => $result['discount'],
                    ];
                }
            }
        }

        return [];
    }

    private function resolvePromotionFromSession(array $items, float $deliveryFee): array
    {
        $appliedPromotion = session()->get('applied_promotion');

        if ($appliedPromotion && isset($appliedPromotion['promotion_id'])) {
            $promotion = \App\Models\Promotion::find($appliedPromotion['promotion_id']);
            if ($promotion && $promotion->isCurrentlyActive()) {
                $result = $this->promotionService->validatePromotion(
                    $promotion->code,
                    $items,
                    auth()->id(),
                    $deliveryFee
                );
                if ($result['valid']) {
                    return [
                        'promotion' => $promotion,
                        'discount' => $result['discount'],
                    ];
                }
            }
        }

        return [];
    }
}
