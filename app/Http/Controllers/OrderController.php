<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessOrderNotifications;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Township;
use App\Services\StockCalculationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\FlashSaleService;
use App\Services\ImageService;
use App\Services\NotificationPreferenceService;
use App\Services\OrderNotificationService;
use App\Services\PromotionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderNotificationService $orderNotificationService,
        private readonly NotificationPreferenceService $preferenceService,
        private readonly ImageService $imageService,
        private readonly PromotionService $promotionService,
        private readonly StockCalculationService $stockCalculationService,
        private readonly FlashSaleService $flashSaleService,
    ) {}

    public function index(Request $request): \Inertia\Response
    {
        $orders = auth()->user()
            ->orders()
            ->with(['items.product', 'items.variant', 'paymentMethod'])
            ->orderBy('created_at', 'desc')
            ->simplePaginate(10);

        return Inertia::render('Client/Orders/Index', [
            'orders' => $orders,
        ]);
    }

    public function show(string $id): \Inertia\Response
    {
        $order = auth()->user()
            ->orders()
            ->with(['items.product', 'items.variant', 'paymentMethod', 'city', 'township'])
            ->findOrFail($id);

        return Inertia::render('Client/Orders/Show', [
            'order' => $order,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (!auth()->check()) {
            return redirect()->route('login')
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
            'payment_screenshot' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
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

        if (auth()->check()) {
            $user = auth()->user();
            if ($user->tenant && $user->tenant->subscriptionExpired()) {
                return back()->with('error', 'Your subscription has expired. Please renew your subscription to place orders.');
            }
        }

        if (auth()->check()) {
            $codMethods = \App\Models\PaymentMethod::where('type', 'cod')->pluck('id');
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

        if (empty($cart)) {
            return back()->with('error', 'Cart is empty.');
        }

        $items = [];
        foreach ($cart as $key => $item) {
            if (!isset($item['id']) && !isset($item['product_id'])) {
                Log::error('OrderController: cart item missing id and product_id', [
                    'cart_key' => $key,
                    'item' => $item,
                ]);
                return back()->with('error', 'One or more cart items are invalid. Please remove and re-add them.');
            }

            $productId = (int) ($item['product_id'] ?? $item['id']);
            $itemData = [
                'product_id' => $productId,
                'quantity' => (int) ($item['quantity'] ?? 1),
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

        $this->hydratePricesFromDatabase($items);

        $subtotal = (float) array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $items));

        $deliveryFee = (float) 0;
        if ($validated['city_id']) {
            $city = \App\Models\City::find($validated['city_id']);
            if ($city) $deliveryFee = (float) $city->delivery_fee;
        }

        $discountData = $this->resolveDiscountsFromSession($items, $deliveryFee);
        $couponDiscount = (float) ($discountData['coupon_discount'] ?? 0);
        $promotionDiscount = (float) ($discountData['promotion_discount'] ?? 0);

        $totalDiscount = $couponDiscount + $promotionDiscount;
        $totalAmount = ($subtotal + $deliveryFee) - $totalDiscount;

        $orderData = [
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
            'subtotal' => $subtotal,
            'delivery_fee' => $deliveryFee,
            'discount_amount' => $totalDiscount,
            'total_amount' => $totalAmount,
            'payment_status' => Order::PAYMENT_STATUS_PENDING,
            'order_status' => Order::ORDER_STATUS_PENDING,
        ];

        if (!empty($discountData['promotion'])) {
            $orderData['promotion_id'] = $discountData['promotion']->id;
            $orderData['promotion_code'] = $discountData['promotion']->code ?? 'AUTO';
        }

        try {
            DB::beginTransaction();

            $flashSaleErrors = $this->flashSaleService->validateFlashSaleQuantity($items);
            if (!empty($flashSaleErrors)) {
                DB::rollBack();
                return back()->with('error', implode(' ', $flashSaleErrors));
            }

            foreach ($items as &$item) {
                $basePrice = (float) $item['price'];
                $flashData = $this->flashSaleService->resolveEffectivePrice(
                    $item['product_id'],
                    $item['variant_id'] ?? null,
                    $basePrice
                );
                $item['price'] = $flashData['price'];
                $item['original_price'] = $flashData['original_price'];
                $item['flash_sale_id'] = $flashData['flash_sale_id'];
                $item['is_flash_sale'] = $flashData['is_flash_sale'];
            }
            unset($item);

            $subtotal = (float) array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $items));
            $orderData['subtotal'] = $subtotal;
            $orderData['total_amount'] = ($subtotal + $deliveryFee) - $totalDiscount;

            $order = auth()->check()
                ? auth()->user()->orders()->create($orderData)
                : Order::create($orderData);

            $date = $order->created_at->format('Ymd');
            $order->update(['invoice_number' => 'ORD-' . $date . '-' . str_pad($order->id, 5, '0', STR_PAD_LEFT)]);

            if (!empty($discountData['promotion'])) {
                $this->promotionService->applyPromotionToOrder(
                    $order,
                    $discountData['promotion'],
                    $discountData['promotion_discount']
                );
            }

            $orderItemsForFlashSale = [];
            foreach ($items as $item) {
                $orderItemData = [
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                ];

                if (!empty($item['variant_id'])) {
                    $orderItemData['variant_id'] = $item['variant_id'];
                }

                if (!empty($item['flash_sale_id'])) {
                    $orderItemData['flash_sale_id'] = $item['flash_sale_id'];
                    $orderItemData['original_price'] = $item['original_price'];
                }

                $order->items()->create($orderItemData);

                $orderItemsForFlashSale[] = [
                    'product_id' => $item['product_id'],
                    'variant_id' => $item['variant_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'flash_sale_id' => $item['flash_sale_id'] ?? null,
                ];
            }

            $this->flashSaleService->incrementQuantitySold($orderItemsForFlashSale);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Order creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->with('error', 'Failed to place order. Please try again.');
        }

        ProcessOrderNotifications::dispatch($order, $paymentScreenshotPath)
            ->onQueue('default');

        session()->forget('cart');
        session()->forget('applied_coupon');
        session()->forget('applied_promotion');

        return redirect()->route('client.orders.show', $order->id)
            ->with('success', 'Order placed successfully!');
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
                        $errors[] = "{$product->name} is out of stock and has been removed from your cart.";
                    } elseif ($stock < $item['quantity']) {
                        $errors[] = "Insufficient stock for {$product->name}. Only {$stock} available.";
                    }
                }
            }
        }

        return $errors;
    }

    private function hydratePricesFromDatabase(array &$items): void
    {
        $productIds = array_unique(array_column($items, 'product_id'));
        $variantIds = array_values(array_unique(array_filter(array_column($items, 'variant_id'))));

        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');
        $variants = !empty($variantIds)
            ? ProductVariant::whereIn('id', $variantIds)->get()->keyBy('id')
            : collect();

        foreach ($items as &$item) {
            $basePrice = 0;
            if (!empty($item['variant_id'])) {
                $variant = $variants->get($item['variant_id']);
                $basePrice = $variant ? (float) $variant->price : 0;
            } else {
                $product = $products->get($item['product_id']);
                $basePrice = $product ? (float) $product->getEffectivePrice() : 0;
            }

            $flashData = $this->flashSaleService->resolveEffectivePrice(
                $item['product_id'],
                $item['variant_id'] ?? null,
                $basePrice
            );

            $item['price'] = $flashData['price'];
            $item['original_price'] = $flashData['original_price'];
            $item['flash_sale_id'] = $flashData['flash_sale_id'];
            $item['is_flash_sale'] = $flashData['is_flash_sale'];
        }
        unset($item);
    }
}
