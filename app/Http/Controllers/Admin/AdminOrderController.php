<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\WebsiteInfo;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminOrderController extends Controller
{
    protected $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    public function index(Request $request)
    {
        $filters = $request->only(['order_status', 'payment_status', 'search']);

        $ordersQuery = Order::query()->with([
            'user', 
            'items.product', 
            'paymentMethod',
            'city',
            'township'
        ]);

        if (!empty($filters['order_status'])) {
            $ordersQuery->where('order_status', $filters['order_status']);
        }

        if (!empty($filters['payment_status'])) {
            $ordersQuery->where('payment_status', $filters['payment_status']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $ordersQuery->where(function ($query) use ($search) {
                $query->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $orders = $ordersQuery->latest()->paginate(15)->withQueryString();
        $websiteInfo = WebsiteInfo::first();

        return view('Admin.orders.index', compact('orders', 'websiteInfo'));
    }

    public function show(string $id)
    {
        $order = Order::with([
            'user',
            'items.product',
            'paymentMethod',
            'city',
            'township'
        ])->findOrFail($id);

        $websiteInfo = WebsiteInfo::first();

        return view('Admin.orders.show', compact('order', 'websiteInfo'));
    }

    public function updateOrderStatus(Request $request, string $id)
    {
        $request->validate([
            'order_status' => 'required|in:pending,confirmed,shipped,delivered,cancelled',
        ]);

        try {
            $order = Order::findOrFail($id);
            $this->orderService->updateOrderStatus($order, $request->order_status);

            return redirect()->route('admin.orders.show', $id)
                ->with('success', 'Order status updated successfully.');
        } catch (\Exception $e) {
            Log::error('Order status update failed: ' . $e->getMessage());

            return redirect()->route('admin.orders.show', $id)
                ->with('error', 'Failed to update order status.');
        }
    }

    public function confirmOrder(string $id)
    {
        try {
            $order = Order::findOrFail($id);

            if (!$order->canConfirm()) {
                return redirect()->route('admin.orders.show', $id)
                    ->with('error', 'This order cannot be confirmed.');
            }

            $this->orderService->updateOrderStatus($order, Order::ORDER_STATUS_CONFIRMED);

            return redirect()->route('admin.orders.show', $id)
                ->with('success', 'Order confirmed. Stock has been deducted.');
        } catch (\Exception $e) {
            Log::error('Order confirmation failed: ' . $e->getMessage());

            return redirect()->route('admin.orders.show', $id)
                ->with('error', 'Failed to confirm order.');
        }
    }

    public function shipOrder(string $id)
    {
        try {
            $order = Order::findOrFail($id);

            if (!$order->canShip()) {
                return redirect()->route('admin.orders.show', $id)
                    ->with('error', 'This order cannot be shipped.');
            }

            $this->orderService->updateOrderStatus($order, Order::ORDER_STATUS_SHIPPED);

            return redirect()->route('admin.orders.show', $id)
                ->with('success', 'Order marked as shipped.');
        } catch (\Exception $e) {
            Log::error('Order shipping failed: ' . $e->getMessage());

            return redirect()->route('admin.orders.show', $id)
                ->with('error', 'Failed to ship order.');
        }
    }

    public function deliverOrder(string $id)
    {
        try {
            $order = Order::findOrFail($id);

            if (!$order->canDeliver()) {
                return redirect()->route('admin.orders.show', $id)
                    ->with('error', 'This order cannot be marked as delivered.');
            }

            $this->orderService->updateOrderStatus($order, Order::ORDER_STATUS_DELIVERED);

            return redirect()->route('admin.orders.show', $id)
                ->with('success', 'Order marked as delivered.');
        } catch (\Exception $e) {
            Log::error('Order delivery failed: ' . $e->getMessage());

            return redirect()->route('admin.orders.show', $id)
                ->with('error', 'Failed to mark order as delivered.');
        }
    }

    public function cancelOrder(string $id)
    {
        try {
            $order = Order::findOrFail($id);

            if (!$order->canCancel()) {
                return redirect()->route('admin.orders.show', $id)
                    ->with('error', 'This order cannot be cancelled.');
            }

            $this->orderService->updateOrderStatus($order, Order::ORDER_STATUS_CANCELLED);

            return redirect()->route('admin.orders.show', $id)
                ->with('success', 'Order cancelled. Stock has been restored.');
        } catch (\Exception $e) {
            Log::error('Order cancellation failed: ' . $e->getMessage());

            return redirect()->route('admin.orders.show', $id)
                ->with('error', 'Failed to cancel order.');
        }
    }

    public function verifyPayment(string $id)
    {
        try {
            $order = Order::findOrFail($id);

            if (!$order->canVerifyPayment()) {
                return redirect()->route('admin.orders.show', $id)
                    ->with('error', 'This payment cannot be verified.');
            }

            $this->orderService->updatePaymentStatus($order, Order::PAYMENT_STATUS_VERIFIED);

            return redirect()->route('admin.orders.show', $id)
                ->with('success', 'Payment verified successfully.');
        } catch (\Exception $e) {
            Log::error('Payment verification failed: ' . $e->getMessage());

            return redirect()->route('admin.orders.show', $id)
                ->with('error', 'Failed to verify payment.');
        }
    }

    public function rejectPayment(string $id)
    {
        try {
            $order = Order::findOrFail($id);

            if (!$order->canVerifyPayment()) {
                return redirect()->route('admin.orders.show', $id)
                    ->with('error', 'This payment cannot be rejected.');
            }

            $this->orderService->updatePaymentStatus($order, Order::PAYMENT_STATUS_REJECTED);

            return redirect()->route('admin.orders.show', $id)
                ->with('success', 'Payment rejected.');
        } catch (\Exception $e) {
            Log::error('Payment rejection failed: ' . $e->getMessage());

            return redirect()->route('admin.orders.show', $id)
                ->with('error', 'Failed to reject payment.');
        }
    }

    public function markAsPaid(Request $request, string $id)
    {
        $request->validate([
            'paid_amount' => 'nullable|numeric|min:0',
        ]);

        try {
            $order = Order::findOrFail($id);
            Log::info('MarkAsPaid debug - Order ID: ' . $id . ', payment_status: ' . $order->payment_status);
            Log::info('canMarkAsPaid result: ' . ($order->canMarkAsPaid() ? 'true' : 'false'));

            if (!$order->canMarkAsPaid()) {
                return redirect()->route('admin.orders.show', $id)
                    ->with('error', 'This order cannot be marked as paid. Current status: ' . $order->payment_status);
            }

            $paidAmount = $request->paid_amount ?? $order->total_amount;

            $order->update([
                'payment_status' => Order::PAYMENT_STATUS_PAID,
                'paid_amount' => $paidAmount,
            ]);

            return redirect()->route('admin.orders.show', $id)
                ->with('success', 'Order marked as paid.');
        } catch (\Exception $e) {
            Log::error('Mark as paid failed: ' . $e->getMessage() . '\n' . $e->getTraceAsString());

            return redirect()->route('admin.orders.show', $id)
                ->with('error', 'Failed to mark as paid. Error: ' . $e->getMessage());
        }
    }

    public function search(Request $request)
    {
        return $this->index($request);
    }

    public function destroy(string $id)
    {
        $order = Order::findOrFail($id);

        if ($order->order_status !== Order::ORDER_STATUS_CANCELLED) {
            return redirect()->route('admin.orders.index')
                ->with('error', 'Only cancelled orders can be deleted.');
        }

        $order->delete();

        return redirect()->route('admin.orders.index')
            ->with('success', 'Order deleted successfully.');
    }
}
