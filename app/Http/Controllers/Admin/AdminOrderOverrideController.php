<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderOverrideService;
use Illuminate\Http\Request;

class AdminOrderOverrideController extends Controller
{
    public function __construct(
        private readonly OrderOverrideService $orderOverrideService
    ) {}

    public function overrideOrderStatus(Request $request, string $id)
    {
        if (!auth()->user()->can('orders.override-status')) {
            abort(403, 'Unauthorized for order status override.');
        }

        $request->validate([
            'new_status' => 'required|string',
            'reason' => 'required|string|max:1000',
        ]);

        try {
            $order = Order::with('user', 'paymentMethod')->findOrFail($id);

            $this->orderOverrideService->overrideOrderStatus(
                $order,
                $request->new_status,
                $request->reason
            );

            return redirect()->route('admin.orders.show', $id)
                ->with('success', 'Order status overridden successfully.');
        } catch (\Exception $e) {
            return redirect()->route('admin.orders.show', $id)
                ->with('error', $e->getMessage());
        }
    }

    public function overridePaymentStatus(Request $request, string $id)
    {
        if (!auth()->user()->can('orders.override-payment')) {
            abort(403, 'Unauthorized for payment status override.');
        }

        $request->validate([
            'new_status' => 'required|string',
            'reason' => 'required|string|max:1000',
        ]);

        try {
            $order = Order::with('user', 'paymentMethod')->findOrFail($id);

            $this->orderOverrideService->overridePaymentStatus(
                $order,
                $request->new_status,
                $request->reason
            );

            return redirect()->route('admin.orders.show', $id)
                ->with('success', 'Payment status overridden successfully.');
        } catch (\Exception $e) {
            return redirect()->route('admin.orders.show', $id)
                ->with('error', $e->getMessage());
        }
    }
}
