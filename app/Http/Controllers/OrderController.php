<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderNotificationService;
use App\Services\TelegramService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(
        private readonly TelegramService $telegramService,
        private readonly OrderNotificationService $orderNotificationService
    ) {}

    public function create(): View
    {
        return view('orders.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'total_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $nameParts = preg_split('/\s+/', trim($validated['customer_name']), 2);

        $order = Order::create([
            'user_id' => auth()->id(),
            'customer_name' => $validated['customer_name'],
            'first_name' => $nameParts[0],
            'last_name' => $nameParts[1] ?? '',
            'phone' => 'N/A',
            'address' => 'N/A',
            'subtotal' => $validated['total_amount'],
            'total_amount' => $validated['total_amount'],
            'delivery_fee' => 0,
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'order_status' => Order::ORDER_STATUS_PENDING,
        ]);

        // Telegram failures are logged inside the service and never block order creation.
        $this->telegramService->sendOrderNotification($order);
        $this->orderNotificationService->notifyOrderPlaced($order);

        return redirect()
            ->route('orders.create')
            ->with('success', 'Order placed successfully.');
    }
}
