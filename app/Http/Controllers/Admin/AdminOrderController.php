<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessOrderStatusChange;
use App\Models\Order;
use App\Events\PaymentVerified;
use App\Events\PaymentRejected;
use App\Services\OrderService;
use App\Services\OrderWorkflow;
use App\Services\PerPageTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class AdminOrderController extends Controller
{
    use PerPageTrait;

    protected $orderService;
    protected $orderWorkflow;

    public function __construct(OrderService $orderService, OrderWorkflow $orderWorkflow)
    {
        $this->orderService = $orderService;
        $this->orderWorkflow = $orderWorkflow;
    }

    public function index(Request $request)
    {
        $filters = $request->only(['order_status', 'payment_status', 'search']);

        $ordersQuery = Order::query()->with([
            'user',
            'items.product',
            'items.variant',
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
            'items.variant',
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
            'order_status' => 'required|in:pending,confirmed,processing,shipped,delivered,cancelled',
        ]);

        try {
            $order = Order::findOrFail($id);
            $this->orderService->updateOrderStatus($order, $request->order_status);

            return admin_redirect('admin.orders.show', $id)
                ->with('success', 'Order status updated successfully.');
        } catch (\Exception $e) {
            Log::error('Order status update failed: ' . $e->getMessage());

            return admin_redirect('admin.orders.show', $id)
                ->with('error', 'Failed to update order status.');
        }
    }

    public function confirmOrder(string $id)
    {
        try {
            $order = Order::with('user', 'paymentMethod')->findOrFail($id);

            $this->orderWorkflow->assertCanConfirmOrder($order);

            $this->orderService->updateOrderStatus($order, Order::ORDER_STATUS_CONFIRMED);

            ProcessOrderStatusChange::dispatch($order, 'confirmed', 'pending');

            return admin_redirect('admin.orders.show', $id)
                ->with('success', 'Order confirmed. Stock has been deducted.');
        } catch (\Exception $e) {
            Log::error('Order confirmation failed: ' . $e->getMessage());

            return admin_redirect('admin.orders.show', $id)
                ->with('error', $e->getMessage());
        }
    }

    public function processOrder(string $id)
    {
        try {
            $order = Order::with('user')->findOrFail($id);

            $this->orderWorkflow->assertCanProcessOrder($order);

            $this->orderService->updateOrderStatus($order, Order::ORDER_STATUS_PROCESSING);

            ProcessOrderStatusChange::dispatch($order, 'processing');

            return admin_redirect('admin.orders.show', $id)
                ->with('success', 'Order is now being processed.');
        } catch (\Exception $e) {
            Log::error('Order processing failed: ' . $e->getMessage());

            return admin_redirect('admin.orders.show', $id)
                ->with('error', $e->getMessage());
        }
    }

    public function shipOrder(string $id)
    {
        try {
            $order = Order::with('user')->findOrFail($id);

            $this->orderWorkflow->assertCanShipOrder($order);

            $this->orderService->updateOrderStatus($order, Order::ORDER_STATUS_SHIPPED);

            ProcessOrderStatusChange::dispatch($order, 'shipped');

            return admin_redirect('admin.orders.show', $id)
                ->with('success', 'Order marked as shipped.');
        } catch (\Exception $e) {
            Log::error('Order shipping failed: ' . $e->getMessage());

            return admin_redirect('admin.orders.show', $id)
                ->with('error', $e->getMessage());
        }
    }

    public function deliverOrder(string $id)
    {
        try {
            $order = Order::with('user')->findOrFail($id);

            $this->orderWorkflow->assertCanDeliverOrder($order);

            $this->orderService->updateOrderStatus($order, Order::ORDER_STATUS_DELIVERED);

            ProcessOrderStatusChange::dispatch($order, 'delivered');

            return admin_redirect('admin.orders.show', $id)
                ->with('success', 'Order marked as delivered.');
        } catch (\Exception $e) {
            Log::error('Order delivery failed: ' . $e->getMessage());

            return admin_redirect('admin.orders.show', $id)
                ->with('error', $e->getMessage());
        }
    }

    public function cancelOrder(string $id)
    {
        try {
            $order = Order::with('user')->findOrFail($id);

            if (!$order->canCancel()) {
                return admin_redirect('admin.orders.show', $id)
                    ->with('error', 'This order cannot be cancelled.');
            }

            $this->orderService->updateOrderStatus($order, Order::ORDER_STATUS_CANCELLED);

            ProcessOrderStatusChange::dispatch($order, 'cancelled_by_admin');

            return admin_redirect('admin.orders.show', $id)
                ->with('success', 'Order cancelled. Stock has been restored.');
        } catch (\Exception $e) {
            Log::error('Order cancellation failed: ' . $e->getMessage());

            return admin_redirect('admin.orders.show', $id)
                ->with('error', 'Failed to cancel order.');
        }
    }

    public function verifyPayment(string $id)
    {
        try {
            $order = Order::with('user')->findOrFail($id);

            if (!$order->canApprovePayment()) {
                return admin_redirect('admin.orders.show', $id)
                    ->with('error', 'This payment cannot be verified.');
            }

            $order->update([
                'payment_status' => Order::PAYMENT_STATUS_PAID,
                'payment_verified_at' => now(),
                'rejection_reason' => null,
            ]);

            ProcessOrderStatusChange::dispatch($order, 'payment_verified');

            event(new PaymentVerified($order));

            return admin_redirect('admin.orders.show', $id)
                ->with('success', 'Payment verified successfully.');
        } catch (\Exception $e) {
            Log::error('Payment verification failed: ' . $e->getMessage());

            return admin_redirect('admin.orders.show', $id)
                ->with('error', 'Failed to verify payment.');
        }
    }

    public function rejectPayment(Request $request, string $id)
    {
        try {
            $order = Order::with('user')->findOrFail($id);

            if (!$order->canRejectPayment()) {
                return admin_redirect('admin.orders.show', $id)
                    ->with('error', 'This payment cannot be rejected.');
            }

            $request->validate([
                'rejection_reason' => ['nullable', 'string', 'max:1000'],
            ]);

            $order->update([
                'payment_status' => Order::PAYMENT_STATUS_FAILED,
                'rejection_reason' => $request->rejection_reason,
            ]);

            ProcessOrderStatusChange::dispatch($order, 'payment_rejected', rejectionReason: $request->rejection_reason);

            event(new PaymentRejected($order));

            return admin_redirect('admin.orders.show', $id)
                ->with('success', 'Payment rejected.');
        } catch (\Exception $e) {
            Log::error('Payment rejection failed: ' . $e->getMessage());

            return admin_redirect('admin.orders.show', $id)
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
                return admin_redirect('admin.orders.show', $id)
                    ->with('error', 'This order cannot be marked as paid. Current status: ' . $order->payment_status);
            }

            $paidAmount = $request->paid_amount ?? $order->total_amount;

            $order->update([
                'payment_status' => Order::PAYMENT_STATUS_PAID,
                'paid_amount' => $paidAmount,
            ]);

            return admin_redirect('admin.orders.show', $id)
                ->with('success', 'Order marked as paid.');
        } catch (\Exception $e) {
            Log::error('Mark as paid failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString());

            return admin_redirect('admin.orders.show', $id)
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
            return admin_redirect('admin.orders.index')
                ->with('error', 'Only cancelled orders can be deleted.');
        }

        $order->delete();

        return admin_redirect('admin.orders.index')
            ->with('success', 'Order deleted successfully.');
    }
}
