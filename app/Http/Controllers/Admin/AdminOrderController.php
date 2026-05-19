<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessOrderStatusChange;
use App\Models\Order;
use App\Events\PaymentVerified;
use App\Events\PaymentRejected;
use App\Services\OrderService;
use App\Services\DashboardCacheService;
use App\Services\PerPageTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class AdminOrderController extends Controller
{
    use PerPageTrait;

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

        $resolved = $this->resolvePerPage($request);
        $perPage = $resolved['per_page'];
        $warning = $resolved['warning'];
        
        if ($resolved['should_paginate']) {
            $orders = $ordersQuery->latest()->paginate($perPage)->withQueryString();
            $showPagination = true;
        } else {
            $total = $ordersQuery->count();
            $items = $ordersQuery->latest()->get();
            
            $orders = new \Illuminate\Pagination\LengthAwarePaginator(
                $items,
                $total,
                $total,
                1,
                ['path' => $request->url(), 'query' => $request->query()]
            );
            $showPagination = false;
        }

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $orders,
            'showPagination' => $showPagination,
            'warning' => $warning,
            'filters' => $filters,
        ]);
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

        return Inertia::render('Admin/Orders/Show', [
            'order' => $order,
        ]);
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
            $order = Order::with('user')->findOrFail($id);

            if (!$order->canConfirm()) {
                return redirect()->route('admin.orders.show', $id)
                    ->with('error', 'This order cannot be confirmed.');
            }

            $this->orderService->updateOrderStatus($order, Order::ORDER_STATUS_CONFIRMED);

            ProcessOrderStatusChange::dispatch($order, 'confirmed', 'pending');

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
            $order = Order::with('user')->findOrFail($id);

            if (!$order->canShip()) {
                return redirect()->route('admin.orders.show', $id)
                    ->with('error', 'This order cannot be shipped.');
            }

            $this->orderService->updateOrderStatus($order, Order::ORDER_STATUS_SHIPPED);

            ProcessOrderStatusChange::dispatch($order, 'shipped');

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
            $order = Order::with('user')->findOrFail($id);

            if (!$order->canDeliver()) {
                return redirect()->route('admin.orders.show', $id)
                    ->with('error', 'This order cannot be marked as delivered.');
            }

            $this->orderService->updateOrderStatus($order, Order::ORDER_STATUS_DELIVERED);

            ProcessOrderStatusChange::dispatch($order, 'delivered');

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
            $order = Order::with('user')->findOrFail($id);

            if (!$order->canCancel()) {
                return redirect()->route('admin.orders.show', $id)
                    ->with('error', 'This order cannot be cancelled.');
            }

            $this->orderService->updateOrderStatus($order, Order::ORDER_STATUS_CANCELLED);

            ProcessOrderStatusChange::dispatch($order, 'cancelled_by_admin');

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
            $order = Order::with('user')->findOrFail($id);

            if (!$order->canApprovePayment()) {
                return redirect()->route('admin.orders.show', $id)
                    ->with('error', 'This payment cannot be verified.');
            }

            $order->update([
                'order_status' => Order::ORDER_STATUS_VERIFIED,
                'payment_status' => Order::PAYMENT_STATUS_VERIFIED,
                'payment_verified_at' => now(),
                'rejection_reason' => null,
            ]);

            ProcessOrderStatusChange::dispatch($order, 'payment_verified');

            event(new PaymentVerified($order));
            app(DashboardCacheService::class)->clearOrderRelatedCache();

            return redirect()->route('admin.orders.show', $id)
                ->with('success', 'Payment verified successfully.');
        } catch (\Exception $e) {
            Log::error('Payment verification failed: ' . $e->getMessage());

            return redirect()->route('admin.orders.show', $id)
                ->with('error', 'Failed to verify payment.');
        }
    }

    public function rejectPayment(Request $request, string $id)
    {
        try {
            $order = Order::with('user')->findOrFail($id);

            if (!$order->canRejectPayment()) {
                return redirect()->route('admin.orders.show', $id)
                    ->with('error', 'This payment cannot be rejected.');
            }

            $request->validate([
                'rejection_reason' => ['nullable', 'string', 'max:1000'],
            ]);

            $order->update([
                'order_status' => Order::ORDER_STATUS_REJECTED,
                'payment_status' => Order::PAYMENT_STATUS_REJECTED,
                'rejection_reason' => $request->rejection_reason,
            ]);

            ProcessOrderStatusChange::dispatch($order, 'payment_rejected', rejectionReason: $request->rejection_reason);

            event(new PaymentRejected($order));
            app(DashboardCacheService::class)->clearOrderRelatedCache();

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
            Log::error('Mark as paid failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString());

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
