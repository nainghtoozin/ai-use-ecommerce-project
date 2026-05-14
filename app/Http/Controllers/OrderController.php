<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessOrderNotifications;
use App\Models\Coupon;
use App\Models\Order;
use App\Services\CouponService;
use App\Services\ImageService;
use App\Services\NotificationPreferenceService;
use App\Services\OrderNotificationService;
use App\Services\PromotionService;
use App\Services\TelegramService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function __construct(
        private readonly TelegramService $telegramService,
        private readonly OrderNotificationService $orderNotificationService,
        private readonly NotificationPreferenceService $preferenceService,
        private readonly ImageService $imageService,
        private readonly CouponService $couponService,
        private readonly PromotionService $promotionService
    ) {}

    public function index(Request $request): \Inertia\Response
    {
        $orders = Order::with(['items.product', 'paymentMethod'])
            ->where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->simplePaginate(10);

        return Inertia::render('Client/Orders/Index', [
            'orders' => $orders,
        ]);
    }

    public function show(string $id): \Inertia\Response
    {
        $order = Order::with(['items.product', 'paymentMethod', 'city', 'township'])
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        return Inertia::render('Client/Orders/Show', [
            'order' => $order,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
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

        $paymentScreenshotPath = null;
        if ($request->hasFile('payment_screenshot')) {
            $paymentScreenshotPath = $this->imageService->upload($request->file('payment_screenshot'), 'payment-proofs');
        }

        $cart = session()->get('cart', []);

        if (empty($cart)) {
            return back()->with('error', 'Cart is empty.');
        }

        $items = [];
        foreach ($cart as $item) {
            $items[] = [
                'id' => (int) $item['id'],
                'product_id' => (int) $item['id'],
                'quantity' => (int) $item['quantity'],
                'price' => (float) $item['price'],
            ];
        }

        $subtotal = (float) array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $items));

        $deliveryFee = (float) 0;
        if ($validated['city_id']) {
            $city = \App\Models\City::find($validated['city_id']);
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
            'payment_status' => $paymentScreenshotPath ? Order::PAYMENT_STATUS_PENDING : Order::PAYMENT_STATUS_UNPAID,
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
            $order->items()->create($item);
        }

        ProcessOrderNotifications::dispatch($order, $paymentScreenshotPath)
            ->onQueue('default');

        session()->forget('cart');
        session()->forget('applied_coupon');
        session()->forget('applied_promotion');

        return redirect()->route('client.orders.show', $order->id)
            ->with('success', 'Order placed successfully!');
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
