<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessOrderNotifications;
use App\Jobs\ProcessOrderStatusChange;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\CouponService;
use App\Services\ImageService;
use App\Services\OrderService;
use App\Services\PromotionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ClientOrderController extends Controller
{
    protected $orderService;
    protected $imageService;
    protected $couponService;
    protected $promotionService;

    public function __construct(
        OrderService $orderService,
        ImageService $imageService,
        CouponService $couponService,
        PromotionService $promotionService
    ) {
        $this->orderService = $orderService;
        $this->imageService = $imageService;
        $this->couponService = $couponService;
        $this->promotionService = $promotionService;
    }

    public function index()
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

    public function store(Request $request)
    {
        Log::info('========== CHECKOUT STARTED ==========');
        Log::info('Raw request data:', $request->all());

        $customerData = [];
        $itemsData = [];

        if ($request->has('customer')) {
            $customerInput = $request->customer;
            if (is_string($customerInput)) {
                $customerData = json_decode($customerInput, true);
                Log::info('Parsed customer from JSON string:', $customerData);
            } else {
                $customerData = $customerInput;
                Log::info('Using customer as array:', $customerData);
            }
        }

        if ($request->has('items')) {
            $itemsInput = $request->items;
            if (is_string($itemsInput)) {
                $itemsData = json_decode($itemsInput, true);
                Log::info('Parsed items from JSON string:', $itemsData);
            } else {
                $itemsData = $itemsInput;
                Log::info('Using items as array:', $itemsData);
            }
        }

        $validated = $request->validate([
            'transaction_id' => ['nullable', 'string', 'max:255'],
        ]);

        if (empty($customerData)) {
            Log::error('Customer data is empty');

            return response()->json(['message' => 'Customer data is required'], 422);
        }

        if (empty($itemsData) || ! is_array($itemsData)) {
            Log::error('Items data is empty or invalid');

            return response()->json(['message' => 'Cart items are required'], 422);
        }

        $firstName = $customerData['firstName'] ?? $customerData['first_name'] ?? $customerData['first name'] ?? null;
        $lastName = $customerData['lastName'] ?? $customerData['last_name'] ?? $customerData['last name'] ?? null;
        $phone = $customerData['phone'] ?? null;
        $email = $customerData['email'] ?? null;
        $address = $customerData['address'] ?? null;
        $postalCode = $customerData['postalCode'] ?? $customerData['postal_code'] ?? $customerData['postal code'] ?? null;
        $notes = $customerData['notes'] ?? null;

        if (! $firstName || ! $lastName || ! $phone || ! $address) {
            Log::error('Missing required customer fields', [
                'firstName' => $firstName,
                'lastName' => $lastName,
                'phone' => $phone,
                'address' => $address,
            ]);

            return response()->json([
                'message' => 'Missing required customer information: firstName, lastName, phone, address',
            ], 422);
        }

        $items = [];
        foreach ($itemsData as $item) {
            $productId = $item['id'] ?? $item['product_id'] ?? null;
            $variantId = $item['variant_id'] ?? null;
            $itemData = [
                'id' => (int) $productId,
                'product_id' => (int) $productId,
                'quantity' => $item['qty'] ?? $item['quantity'] ?? $item['qty'] ?? 1,
                'price' => $item['price'] ?? $item['price'] ?? 0,
            ];

            if ($variantId) {
                $itemData['variant_id'] = (int) $variantId;
            }

            $items[] = $itemData;
        }

        foreach ($items as $index => $item) {
            if (! $item['product_id']) {
                Log::error("Item $index missing product_id");

                return response()->json([
                    'message' => "Item $index is missing product ID",
                ], 422);
            }
        }

        Log::info('Transformed items:', $items);

        try {
            $this->orderService->validateStock($items);
        } catch (\InvalidArgumentException $e) {
            Log::warning('Stock validation failed:', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        $orderData = [
            'user_id' => auth()->id(),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $phone,
            'email' => $email,
            'address' => $address,
            'city_id' => $request->city_id ?? null,
            'township_id' => $request->township_id ?? null,
            'postal_code' => $postalCode,
            'notes' => $notes,
            'payment_method_id' => $request->payment_method_id ?? $request->paymentMethodId ?? null,
            'transaction_id' => $validated['transaction_id'] ?? $request->transactionId ?? null,
        ];

        if (! $orderData['payment_method_id']) {
            return response()->json(['message' => 'Payment method is required'], 422);
        }

        if ($request->hasFile('payment_proof')) {
            $request->validate(['payment_proof' => 'image|mimes:jpg,jpeg,png,webp|max:2048']);
            $path = $this->imageService->upload($request->file('payment_proof'), 'payment-proofs');
            $orderData['payment_proof'] = $path;
            Log::info('Payment proof uploaded:', ['path' => $path]);
        }

        Log::info('Order data prepared:', $orderData);

        $couponData = $this->resolveCouponFromSession($items, $orderData['city_id'] ?? null);
        $promotionData = $this->resolvePromotionFromSession($items, $orderData['city_id'] ?? null);
        $orderData['discount_amount'] = ($couponData['discount'] ?? 0) + ($promotionData['discount'] ?? 0);

        try {
            $order = $this->orderService->createOrder($orderData, $items, $couponData, $promotionData);

            Log::info('========== ORDER CREATED SUCCESS ==========');
            Log::info('Order ID:', ['order_id' => $order->id]);

            $verifyOrder = Order::find($order->id);
            $verifyItems = OrderItem::where('order_id', $order->id)->get();

            Log::info('Verification:', [
                'order_exists' => $verifyOrder ? 'YES' : 'NO',
                'items_count' => $verifyItems->count(),
            ]);

            if (! $verifyOrder) {
                throw new \Exception('Order not found in database after creation!');
            }

            if ($verifyItems->count() === 0) {
                throw new \Exception('Order items not saved in database!');
            }

            ProcessOrderNotifications::dispatch($order, $orderData['payment_proof'] ?? null)->onQueue('default');

            session()->forget('applied_coupon');
            session()->forget('applied_promotion');

            return response()->json([
                'message' => 'Order placed successfully!',
                'order_id' => $order->id,
                'verified' => true,
            ], 200);

        } catch (\Exception $e) {
            Log::error('========== ORDER CREATION FAILED ==========');
            Log::error('Error:', ['message' => $e->getMessage()]);

            return response()->json([
                'message' => 'Failed to create order: '.$e->getMessage(),
            ], 500);
        }
    }

    private function resolveCouponFromSession(array $items, $cityId = null): array
    {
        $appliedCoupon = session()->get('applied_coupon');

        if ($appliedCoupon && isset($appliedCoupon['coupon_id'])) {
            $coupon = Coupon::find($appliedCoupon['coupon_id']);
            if ($coupon && $coupon->isValid()) {
                $deliveryFee = 0;
                if ($cityId) {
                    $city = \App\Models\City::find($cityId);
                    if ($city) $deliveryFee = (float) $city->delivery_fee;
                }
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

    private function resolvePromotionFromSession(array $items, $cityId = null): array
    {
        $appliedPromotion = session()->get('applied_promotion');

        if ($appliedPromotion && isset($appliedPromotion['promotion_id'])) {
            $promotion = \App\Models\Promotion::find($appliedPromotion['promotion_id']);
            if ($promotion && $promotion->isCurrentlyActive()) {
                $deliveryFee = 0;
                if ($cityId) {
                    $city = \App\Models\City::find($cityId);
                    if ($city) $deliveryFee = (float) $city->delivery_fee;
                }
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

    public function show(string $id)
    {
        $order = auth()->user()
            ->orders()
            ->with(['items.product', 'paymentMethod', 'city', 'township'])
            ->findOrFail($id);

        return Inertia::render('Client/Orders/Show', [
            'order' => $order,
        ]);
    }

    public function uploadPaymentProof(Request $request, string $id)
    {
        $user = auth()->user();
        if ($user->tenant && $user->tenant->subscriptionExpired()) {
            return redirect()->back()->with('error', 'Your subscription has expired. Please renew to continue.');
        }

        $order = auth()->user()
            ->orders()
            ->with('user')
            ->findOrFail($id);

        $request->validate([
            'payment_proof' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
            'transaction_id' => 'nullable|string|max:100',
        ]);

        if ($order->payment_status !== Order::PAYMENT_STATUS_PENDING) {
            return redirect()->back()->with('error', 'You cannot upload payment proof for this order.');
        }

        if ($request->hasFile('payment_proof')) {
            $path = $this->imageService->upload($request->file('payment_proof'), 'payment-proofs');

            $order->update([
                'payment_proof' => $path,
                'payment_status' => Order::PAYMENT_STATUS_PAID,
                'transaction_id' => $request->transaction_id,
            ]);

            ProcessOrderStatusChange::dispatch($order, 'payment_proof_uploaded');
        }

        return redirect()->back()->with('success', 'Payment proof uploaded successfully.');
    }

    public function cancelOrder(string $id)
    {
        $user = auth()->user();
        if ($user->tenant && $user->tenant->subscriptionExpired()) {
            return redirect()->back()->with('error', 'Your subscription has expired. Please renew to continue.');
        }

        $order = auth()->user()
            ->orders()
            ->with('user')
            ->findOrFail($id);

        if (! $order->canCancel()) {
            return redirect()->back()->with('error', 'You cannot cancel this order.');
        }

        $oldStatus = $order->order_status;
        $order->update(['order_status' => 'cancelled']);
        $this->orderService->restoreStock($order);

        ProcessOrderStatusChange::dispatch($order, 'cancelled_by_customer', oldStatus: $oldStatus);

        return redirect()->route('client.orders.index')
            ->with('success', 'Order cancelled. Stock has been restored.');
    }

    public function confirmPayment(Request $request, string $id)
    {
        $user = auth()->user();
        if ($user->tenant && $user->tenant->subscriptionExpired()) {
            return redirect()->back()->with('error', 'Your subscription has expired. Please renew to continue.');
        }

        $order = auth()->user()
            ->orders()
            ->findOrFail($id);

        if ($order->payment_status !== Order::PAYMENT_STATUS_PENDING) {
            return redirect()->back()->with('error', 'You have already confirmed payment for this order.');
        }

        $order->update([
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'transaction_id' => $request->transaction_id ?? null,
        ]);

        return redirect()->back()->with('success', 'Payment confirmed. We will verify your payment shortly.');
    }
}
