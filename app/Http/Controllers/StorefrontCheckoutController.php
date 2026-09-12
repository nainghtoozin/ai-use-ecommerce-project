<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessOrderNotifications;
use App\Models\City;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\PackagingOption;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\Township;
use App\Services\CodEligibilityService;
use App\Services\DeliveryFeeService;
use App\Services\ImageService;
use App\Services\PromotionService;
use App\Services\StockCalculationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class StorefrontCheckoutController extends Controller
{
    public function __construct(
        private readonly ImageService $imageService,
        private readonly PromotionService $promotionService,
        private readonly StockCalculationService $stockCalculationService,
        private readonly DeliveryFeeService $deliveryFeeService,
        private readonly CodEligibilityService $codEligibilityService,
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

        $deliveryServices = $this->deliveryFeeService->getAvailableServices();
        $packagingOptions = PackagingOption::active()->ordered()->get();

        $paymentMethodsFiltered = $paymentMethods;
        $codMethod = $paymentMethods->firstWhere('type', 'cod');
        if ($codMethod && auth()->check()) {
            $codEligibility = $this->codEligibilityService->isCodAvailable(
                $codMethod,
                auth()->user(),
                null,
                $subtotal
            );
            if (!$codEligibility) {
                $paymentMethodsFiltered = $paymentMethods->reject(fn($pm) => $pm->type === 'cod')->values();
            }
        }

        return Inertia::render('Storefront/CheckoutV2', [
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
            'paymentMethods' => $paymentMethodsFiltered,
            'cities' => $cities,
            'appliedCoupon' => $appliedCoupon,
            'appliedPromotion' => $appliedPromotion,
            'discountAmount' => $totalDiscount,
            'autoPromotions' => $autoPromotions,
            'addresses' => $addresses,
            'defaultAddress' => $defaultAddress,
            'deliveryServices' => $deliveryServices,
            'packagingOptions' => $packagingOptions,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = Tenant::getCurrent();
        if (!$tenant) {
            abort(404);
        }

        if (!auth()->check()) {
            return redirect()->route('storefront.login', $tenant->slug)
                ->with('error', 'Please sign in to place your order.');
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
            'sender_account_number' => ['nullable', 'string', 'max:50'],
            'transaction_id' => ['nullable', 'string', 'max:255'],
            'payment_date' => ['nullable', 'date'],
            'payment_time' => ['nullable', 'string', 'max:10'],
            'payment_note' => ['nullable', 'string', 'max:500'],
            'payment_screenshot' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'delivery_service_id' => ['nullable', 'exists:delivery_services,id'],
            'packaging_id' => ['nullable', 'exists:packaging_options,id'],
        ]);

        if (!empty($validated['city_id']) && !empty($validated['township_id'])) {
            $township = Township::find($validated['township_id']);
            if (!$township || (int) $township->city_id !== (int) $validated['city_id']) {
                return back()->withErrors(['township_id' => 'The selected township is not valid for the chosen city.'])->withInput();
            }
            if (!empty($validated['postal_code']) && $township->postal_code && $validated['postal_code'] !== $township->postal_code) {
                return back()->withErrors(['postal_code' => 'The postal code does not match the selected township.'])->withInput();
            }
            $validated['postal_code'] = $township->postal_code ?? $validated['postal_code'];
        }

        if ($tenant->subscriptionExpired()) {
            return back()->with('error', 'Your subscription has expired. Please renew your subscription to place orders.');
        }

        $idempotencyKey = $request->header('X-Idempotency-Key') ?? session()->get('checkout_idempotency_key');
        if ($idempotencyKey) {
            $existingOrder = Order::where('user_id', auth()->id())
                ->where('created_at', '>=', now()->subMinutes(5))
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existingOrder) {
                return redirect()->route('storefront.customer.orders.show', [
                    'store_slug' => $tenant->slug,
                    'order' => $existingOrder->id,
                ])->with('success', 'Order already submitted.');
            }
        }

        $paymentMethod = PaymentMethod::find($validated['payment_method_id']);
        if (!$paymentMethod || $paymentMethod->tenant_id !== $tenant->id) {
            return back()->withErrors(['payment_method_id' => 'Invalid payment method.'])->withInput();
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

        $stockErrors = $this->validateStock($items);
        if (!empty($stockErrors)) {
            return back()->with('error', implode(' ', $stockErrors));
        }

        $subtotal = (float) array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $items));

        $deliveryFee = 0;
        $deliveryServiceId = $validated['delivery_service_id'] ?? null;
        $deliveryDaysMin = null;
        $deliveryDaysMax = null;

        if ($deliveryServiceId) {
            $deliveryService = \App\Models\DeliveryService::find($deliveryServiceId);
            if (!$deliveryService || $deliveryService->tenant_id !== $tenant->id) {
                return back()->withErrors(['delivery_service_id' => 'Invalid delivery service.'])->withInput();
            }
        }

        if (!empty($validated['city_id'])) {
            $city = City::find($validated['city_id']);
            if ($city) {
                $deliveryFee = $this->deliveryFeeService->resolveDeliveryFee($city, $deliveryServiceId);
                $deliveryDays = $this->deliveryFeeService->resolveDeliveryDays($deliveryServiceId, $city);
                $deliveryDaysMin = $deliveryDays['min'];
                $deliveryDaysMax = $deliveryDays['max'];
            }
        }

        $discountData = $this->resolveDiscountsFromSession($items, $deliveryFee);
        $couponDiscount = (float) ($discountData['coupon_discount'] ?? 0);
        $promotionDiscount = (float) ($discountData['promotion_discount'] ?? 0);

        $packagingFee = 0;
        $packagingId = $validated['packaging_id'] ?? null;
        if ($packagingId) {
            $packagingOption = PackagingOption::find($packagingId);
            if (!$packagingOption || $packagingOption->tenant_id !== $tenant->id) {
                return back()->withErrors(['packaging_id' => 'Invalid packaging option.'])->withInput();
            }
            $packagingFee = (int) $packagingOption->fee;
        }

        $totalDiscount = $couponDiscount + $promotionDiscount;
        $totalBeforeCod = ($subtotal + $deliveryFee + $packagingFee) - $totalDiscount;

        $codFee = 0;
        if ($paymentMethod && $paymentMethod->type === 'cod') {
            $user = auth()->check() ? auth()->user() : null;
            $cityId = $validated['city_id'] ?? null;

            $reason = $this->codEligibilityService->getIneligibilityReason(
                $paymentMethod,
                $user,
                $cityId,
                $totalBeforeCod
            );

            if ($reason !== null) {
                return back()->with('error', $reason);
            }

            $city = City::find($cityId);
            if ($city) {
                $codFee = $this->codEligibilityService->getCodFee($city->id, $totalBeforeCod);
            }
        }

        $totalAmount = $totalBeforeCod + $codFee;

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
            'sender_account_number' => $validated['sender_account_number'] ?? null,
            'payment_screenshot' => $paymentScreenshotPath,
            'transaction_id' => $validated['transaction_id'] ?? null,
            'payment_date' => $validated['payment_date'] ?? null,
            'payment_time' => $validated['payment_time'] ?? null,
            'payment_note' => $validated['payment_note'] ?? null,
            'subtotal' => $subtotal,
            'delivery_fee' => $deliveryFee,
            'delivery_service_id' => $deliveryServiceId,
            'delivery_days_min' => $deliveryDaysMin,
            'delivery_days_max' => $deliveryDaysMax,
            'packaging_id' => $packagingId,
            'packaging_fee' => $packagingFee > 0 ? $packagingFee : null,
            'discount_amount' => $totalDiscount,
            'total_amount' => $totalAmount,
            'payment_status' => Order::PAYMENT_STATUS_PENDING,
            'order_status' => Order::ORDER_STATUS_PENDING,
            'cod_fee' => $codFee > 0 ? $codFee : null,
        ];

        if (!empty($discountData['promotion'])) {
            $orderData['promotion_id'] = $discountData['promotion']->id;
            $orderData['promotion_code'] = $discountData['promotion']->code ?? 'AUTO';
        }

        $orderData['idempotency_key'] = $idempotencyKey;

        try {
            $order = DB::transaction(function () use ($orderData, $items, $discountData) {
                $stockErrors = $this->validateStockWithLock($items);
                if (!empty($stockErrors)) {
                    throw new \App\Exceptions\CheckoutStockException(implode(' ', $stockErrors));
                }

                try {
                    $order = Order::create($orderData);
                } catch (\Illuminate\Database\QueryException $e) {
                    if ($orderData['idempotency_key'] && str_contains($e->getMessage(), 'Duplicate entry')) {
                        $existingOrder = Order::where('idempotency_key', $orderData['idempotency_key'])->first();
                        if ($existingOrder) {
                            return $existingOrder;
                        }
                    }
                    throw $e;
                }

                $date = $order->created_at->format('Ymd');
                $order->update(['invoice_number' => 'ORD-' . $date . '-' . str_pad($order->id, 5, '0', STR_PAD_LEFT)]);

                if (!empty($discountData['promotion'])) {
                    $this->promotionService->applyPromotionToOrder(
                        $order,
                        $discountData['promotion'],
                        $discountData['promotion_discount']
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

                return $order;
            });
        } catch (\App\Exceptions\CheckoutStockException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        } catch (\Exception $e) {
            Log::error('Order creation failed: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'tenant_id' => $tenant->id,
            ]);
            return back()->with('error', 'Failed to create order. Please try again.')->withInput();
        }

        ProcessOrderNotifications::dispatch($order, $paymentScreenshotPath)
            ->onQueue('default');

        session()->forget('cart');
        session()->forget('applied_coupon');
        session()->forget('applied_promotion');
        session()->forget('checkout_idempotency_key');

        return redirect()->route('storefront.customer.orders.show', [
            'store_slug' => $tenant->slug,
            'order' => $order->id,
        ])->with('success', 'Order placed successfully!');
    }

    private function resolveDiscountsFromSession(array $items, float $deliveryFee): array
    {
        $result = [
            'coupon_discount' => 0,
            'promotion_discount' => 0,
            'promotion' => null,
        ];

        $appliedCoupon = session()->get('applied_coupon');
        if ($appliedCoupon && isset($appliedCoupon['promotion_id'])) {
            $promotion = \App\Models\Promotion::find($appliedCoupon['promotion_id']);
            if ($promotion && $promotion->isCurrentlyActive()) {
                $validation = $this->promotionService->validateCouponCode(
                    $promotion->code,
                    collect($items),
                    auth()->id(),
                    $deliveryFee
                );
                if ($validation['valid']) {
                    $result['coupon_discount'] = $validation['discount'];
                    $result['promotion'] = $promotion;
                }
            }
        }

        $appliedPromotion = session()->get('applied_promotion');
        if ($appliedPromotion && isset($appliedPromotion['promotion_id'])) {
            $promotion = \App\Models\Promotion::find($appliedPromotion['promotion_id']);
            if ($promotion && $promotion->isCurrentlyActive()) {
                $validation = $this->promotionService->validatePromotion(
                    $promotion->code,
                    $items,
                    auth()->id(),
                    $deliveryFee
                );
                if ($validation['valid']) {
                    $result['promotion_discount'] = $validation['discount'];
                    if (!$result['promotion']) {
                        $result['promotion'] = $promotion;
                    }
                }
            }
        }

        return $result;
    }

    private function filterCartByTenant(array $cart, Tenant $tenant): array
    {
        if (empty($cart)) {
            return [];
        }

        $tenantId = $tenant->id;

        $cartProductIds = [];
        foreach ($cart as $item) {
            $productId = $item['product_id'] ?? $item['id'] ?? null;
            if ($productId) {
                $cartProductIds[] = (int) $productId;
            }
        }
        $cartProductIds = array_unique($cartProductIds);

        if (empty($cartProductIds)) {
            return [];
        }

        $tenantProducts = Product::where('tenant_id', $tenantId)
            ->whereIn('id', $cartProductIds)
            ->select(['id', 'name', 'price', 'type', 'photo1'])
            ->get()
            ->keyBy('id');

        $cartVariantIds = [];
        foreach ($cart as $item) {
            if (!empty($item['variant_id'])) {
                $cartVariantIds[] = (int) $item['variant_id'];
            }
        }
        $cartVariantIds = array_unique($cartVariantIds);

        $variants = !empty($cartVariantIds)
            ? ProductVariant::whereIn('id', $cartVariantIds)
                ->select(['id', 'product_id', 'price', 'attributes'])
                ->get()
                ->keyBy('id')
            : collect();

        $items = [];
        foreach ($cart as $cartKey => $item) {
            $productId = $item['product_id'] ?? $item['id'] ?? null;
            if (!$productId) {
                continue;
            }

            $product = $tenantProducts->get((int) $productId);
            if (!$product) {
                continue;
            }

            $price = (float) $product->price;
            $variantName = null;
            $variantId = $item['variant_id'] ?? null;

            if ($variantId) {
                $variant = $variants->get((int) $variantId);
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

    private function validateStock(array $items): array
    {
        $errors = [];
        $productIds = array_unique(array_column($items, 'product_id'));
        $variantIds = array_values(array_unique(array_filter(array_column($items, 'variant_id'))));

        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');
        $variants = !empty($variantIds)
            ? ProductVariant::whereIn('id', $variantIds)->get()->keyBy('id')
            : collect();

        foreach ($items as $item) {
            if (!empty($item['variant_id'])) {
                $variant = $variants->get($item['variant_id']);
                if (!$variant) {
                    $errors[] = 'A product variant in your cart no longer exists. Please review your cart.';
                } else {
                    $stock = $this->stockCalculationService->forVariant($variant);
                    if ($stock < $item['quantity']) {
                        $errors[] = "Insufficient stock for a product variant. Only {$stock} available.";
                    }
                }
            } else {
                $product = $products->get($item['product_id']);
                if (!$product) {
                    $errors[] = 'A product in your cart no longer exists. Please review your cart.';
                } else {
                    $stock = $this->stockCalculationService->forProduct($product);
                    if ($stock <= 0) {
                        $errors[] = "{$product->name} is out of stock.";
                    } elseif ($stock < $item['quantity']) {
                        $errors[] = "Insufficient stock for {$product->name}. Only {$stock} available.";
                    }
                }
            }
        }

        return $errors;
    }

    private function validateStockWithLock(array $items): array
    {
        $errors = [];
        $productIds = array_unique(array_column($items, 'product_id'));
        $variantIds = array_values(array_unique(array_filter(array_column($items, 'variant_id'))));

        $products = Product::whereIn('id', $productIds)->lockForUpdate()->get()->keyBy('id');
        $variants = !empty($variantIds)
            ? ProductVariant::whereIn('id', $variantIds)->lockForUpdate()->get()->keyBy('id')
            : collect();

        foreach ($items as $item) {
            if (!empty($item['variant_id'])) {
                $variant = $variants->get($item['variant_id']);
                if (!$variant) {
                    $errors[] = 'A product variant in your cart no longer exists. Please review your cart.';
                } else {
                    $stock = $this->stockCalculationService->forVariant($variant);
                    if ($stock < $item['quantity']) {
                        $errors[] = "Insufficient stock for a product variant. Only {$stock} available.";
                    }
                }
            } else {
                $product = $products->get($item['product_id']);
                if (!$product) {
                    $errors[] = 'A product in your cart no longer exists. Please review your cart.';
                } else {
                    $stock = $this->stockCalculationService->forProduct($product);
                    if ($stock <= 0) {
                        $errors[] = "{$product->name} is out of stock.";
                    } elseif ($stock < $item['quantity']) {
                        $errors[] = "Insufficient stock for {$product->name}. Only {$stock} available.";
                    }
                }
            }
        }

        return $errors;
    }
}
