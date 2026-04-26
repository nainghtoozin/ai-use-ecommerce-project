<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\WebsiteInfo;
use App\Services\OrderNotificationService;
use App\Services\OrderService;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ClientOrderController extends Controller
{
    protected $orderService;

    protected $telegramService;

    protected $orderNotificationService;

    public function __construct(
        OrderService $orderService,
        TelegramService $telegramService,
        OrderNotificationService $orderNotificationService
    ) {
        $this->orderService = $orderService;
        $this->telegramService = $telegramService;
        $this->orderNotificationService = $orderNotificationService;
    }

    /**
     * Display customer's order history
     */
    public function index()
    {
        $orders = Order::query()
            ->with(['items.product', 'paymentMethod'])
            ->where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->simplePaginate(10);

        $websiteInfo = WebsiteInfo::first();

        return view('client.orders.index', compact('orders', 'websiteInfo'));
    }

    /**
     * Create order from checkout - HANDLES JSON STRINGS PROPERLY
     */
    public function store(Request $request)
    {
        Log::info('========== CHECKOUT STARTED ==========');
        Log::info('Raw request data:', $request->all());

        // ============================================
        // STEP 1: PARSE JSON STRINGS IF PRESENT
        // ============================================
        $customerData = [];
        $itemsData = [];

        // Handle customer (can be array or JSON string)
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

        // Handle items (can be array or JSON string)
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

        // ============================================
        // STEP 2: VALIDATE PARSED DATA
        // ============================================
        $validated = $request->validate([
            'transaction_id' => ['nullable', 'string', 'max:255'],
        ]);

        // Basic validation
        if (empty($customerData)) {
            Log::error('Customer data is empty');

            return response()->json(['message' => 'Customer data is required'], 422);
        }

        if (empty($itemsData) || ! is_array($itemsData)) {
            Log::error('Items data is empty or invalid');

            return response()->json(['message' => 'Cart items are required'], 422);
        }

        // Extract customer fields
        $firstName = $customerData['firstName'] ?? $customerData['first_name'] ?? $customerData['first name'] ?? null;
        $lastName = $customerData['lastName'] ?? $customerData['last_name'] ?? $customerData['last name'] ?? null;
        $phone = $customerData['phone'] ?? null;
        $email = $customerData['email'] ?? null;
        $address = $customerData['address'] ?? null;
        $postalCode = $customerData['postalCode'] ?? $customerData['postal_code'] ?? $customerData['postal code'] ?? null;
        $notes = $customerData['notes'] ?? null;

        // Validate required fields
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

        // Transform items to correct format
        $items = [];
        foreach ($itemsData as $item) {
            $items[] = [
                'product_id' => $item['id'] ?? $item['product_id'] ?? null,
                'quantity' => $item['qty'] ?? $item['quantity'] ?? $item['qty'] ?? 1,
                'price' => $item['price'] ?? $item['price'] ?? 0,
            ];
        }

        // Validate items
        foreach ($items as $index => $item) {
            if (! $item['product_id']) {
                Log::error("Item $index missing product_id");

                return response()->json([
                    'message' => "Item $index is missing product ID",
                ], 422);
            }
        }

        Log::info('Transformed items:', $items);

        // ============================================
        // STEP 3: VALIDATE STOCK
        // ============================================
        try {
            $this->orderService->validateStock($items);
        } catch (\InvalidArgumentException $e) {
            Log::warning('Stock validation failed:', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        // ============================================
        // STEP 4: PREPARE ORDER DATA
        // ============================================
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

        // Validate payment method
        if (! $orderData['payment_method_id']) {
            return response()->json(['message' => 'Payment method is required'], 422);
        }

        // Handle payment proof upload
        if ($request->hasFile('payment_proof')) {
            $path = $request->file('payment_proof')->store('payment-proofs', 'public');
            $orderData['payment_proof'] = $path;
            Log::info('Payment proof uploaded:', ['path' => $path]);
        }

        Log::info('Order data prepared:', $orderData);

        // ============================================
        // STEP 5: CREATE ORDER (WITH GUARANTEED SAVE)
        // ============================================
        try {
            $order = $this->orderService->createOrder($orderData, $items);

            Log::info('========== ORDER CREATED SUCCESS ==========');
            Log::info('Order ID:', ['order_id' => $order->id]);

            // Verify order exists in database
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

            // Telegram failures are logged and should never roll back a successful order.
            $this->telegramService->sendOrderNotification($order);
            $this->orderNotificationService->notifyOrderPlaced($order);

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

    /**
     * View single order details
     */
    public function show(string $id)
    {
        $order = Order::query()
            ->with(['items.product', 'paymentMethod', 'city', 'township'])
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        $websiteInfo = WebsiteInfo::first();

        return view('client.orders.show', compact('order', 'websiteInfo'));
    }

    /**
     * Upload payment proof
     */
    public function uploadPaymentProof(Request $request, string $id)
    {
        $order = Order::query()
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        $request->validate([
            'payment_proof' => 'required|image|max:2048',
            'transaction_id' => 'nullable|string|max:100',
        ]);

        if ($order->payment_status !== 'unpaid') {
            return redirect()->back()->with('error', 'You cannot upload payment proof for this order.');
        }

        if ($request->hasFile('payment_proof')) {
            $path = $request->file('payment_proof')->store('payment-proofs', 'public');

            $order->update([
                'payment_proof' => $path,
                'payment_status' => 'paid',
                'transaction_id' => $request->transaction_id,
            ]);
        }

        return redirect()->back()->with('success', 'Payment proof uploaded successfully.');
    }

    /**
     * Cancel order
     */
    public function cancelOrder(string $id)
    {
        $order = Order::query()
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        if (! $order->canCancel()) {
            return redirect()->back()->with('error', 'You cannot cancel this order.');
        }

        $order->update(['order_status' => 'cancelled']);
        $this->orderService->restoreStock($order);

        return redirect()->route('client.orders.index')
            ->with('success', 'Order cancelled. Stock has been restored.');
    }

    /**
     * Customer confirms payment sent
     */
    public function confirmPayment(Request $request, string $id)
    {
        $order = Order::query()
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        if ($order->payment_status !== 'unpaid') {
            return redirect()->back()->with('error', 'You have already confirmed payment for this order.');
        }

        $order->update([
            'payment_status' => 'paid',
            'transaction_id' => $request->transaction_id ?? null,
        ]);

        return redirect()->back()->with('success', 'Payment confirmed. We will verify your payment shortly.');
    }
}
