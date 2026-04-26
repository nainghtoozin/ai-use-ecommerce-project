@extends('Admin.layouts.admin')

@section('title', 'Order Details')
@section('page-title', 'Order #' . $order->id)

@section('content')
<div class="space-y-6">
    @if (session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-md">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-md">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-md shadow p-6">
                <h3 class="text-lg font-semibold mb-4">Customer Information</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <span class="text-gray-500 text-sm">Name</span>
                        <p class="font-medium">{{ $order->first_name }} {{ $order->last_name }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500 text-sm">Phone</span>
                        <p class="font-medium">{{ $order->phone }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500 text-sm">Email</span>
                        <p class="font-medium">{{ $order->email ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500 text-sm">User Account</span>
                        <p class="font-medium">{{ $order->user ? $order->user->name : 'Guest' }}</p>
                    </div>
                </div>

                <div class="mt-4">
                    <span class="text-gray-500 text-sm">Delivery Address</span>
                    <p class="font-medium">{{ $order->address }}</p>
                    @php
                        $cityName = $order->city->name ?? 'N/A';
                        $townshipName = $order->township->name ?? null;
                    @endphp
                    @if($cityName || $townshipName)
                        <p class="text-sm text-gray-600">{{ $cityName }}{{ $townshipName ? ', ' . $townshipName : '' }}</p>
                    @endif
                    @if($order->postal_code)
                        <p class="text-sm text-gray-600">Postal Code: {{ $order->postal_code }}</p>
                    @endif
                </div>

                @if($order->notes)
                    <div class="mt-4">
                        <span class="text-gray-500 text-sm">Notes</span>
                        <p class="font-medium">{{ $order->notes }}</p>
                    </div>
                @endif
            </div>

            <div class="bg-white rounded-md shadow p-6">
                <h3 class="text-lg font-semibold mb-4">Order Items</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="border-b">
                                <th class="text-left py-2">Product</th>
                                <th class="text-right py-2">Price</th>
                                <th class="text-right py-2">Qty</th>
                                <th class="text-right py-2">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($order->items as $item)
                            <tr class="border-b">
                                <td class="py-3">
                                    <div class="font-medium">{{ $item->product->name ?? 'Product #' . $item->product_id }}</div>
                                    @if($item->product)
                                        <div class="text-sm text-gray-500">SKU: {{ $item->product->id }}</div>
                                    @endif
                                </td>
                                <td class="text-right py-3">{{ number_format($item->price, 2) }}</td>
                                <td class="text-right py-3">{{ $item->quantity }}</td>
                                <td class="text-right py-3 font-medium">{{ number_format($item->price * $item->quantity, 2) }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center py-4 text-gray-500">No items found</td>
                            </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-right py-2">Subtotal:</td>
                                <td class="text-right py-2">{{ number_format($order->items_total, 2) }}</td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-right py-2">Delivery Fee:</td>
                                <td class="text-right py-2">{{ number_format($order->delivery_fee, 2) }}</td>
                            </tr>
                            <tr class="font-bold">
                                <td colspan="3" class="text-right py-2">Total:</td>
                                <td class="text-right py-2">{{ number_format($order->total_amount, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-white rounded-md shadow p-6">
                <h3 class="text-lg font-semibold mb-4">Order Status</h3>
                
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Order Status:</span>
                        <span class="px-3 py-1 rounded-full text-sm font-semibold 
                            @if($order->order_status == 'pending') bg-yellow-100 text-yellow-800
                            @elseif($order->order_status == 'confirmed') bg-blue-100 text-blue-800
                            @elseif($order->order_status == 'shipped') bg-purple-100 text-purple-800
                            @elseif($order->order_status == 'delivered') bg-green-100 text-green-800
                            @else bg-red-100 text-red-800 @endif">
                            {{ ucfirst($order->order_status) }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Payment Status:</span>
                        <span class="px-3 py-1 rounded-full text-sm font-semibold 
                            @if($order->payment_status == 'unpaid') bg-gray-100 text-gray-800
                            @elseif($order->payment_status == 'paid') bg-orange-100 text-orange-800
                            @elseif($order->payment_status == 'verified') bg-green-100 text-green-800
                            @else bg-red-100 text-red-800 @endif">
                            {{ ucfirst($order->payment_status) }}
                        </span>
                    </div>

                    <div class="border-t pt-3">
                        <span class="text-gray-600 text-sm">Order Date:</span>
                        <p class="font-medium">{{ $order->created_at->format('Y-m-d H:i:s') }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-md shadow p-6">
                <h3 class="text-lg font-semibold mb-4">Payment Information</h3>
                
                <div class="space-y-3">
                    <div>
                        <span class="text-gray-600 text-sm">Payment Method:</span>
                        <p class="font-medium">{{ $order->paymentMethod->name ?? 'N/A' }}</p>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Total Payable:</span>
                        <span class="font-bold text-lg">{{ number_format($order->total_payable, 2) }}</span>
                    </div>

                    @if($order->paid_amount)
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Paid Amount:</span>
                        <span class="font-medium {{ $order->isPaymentAmountCorrect() ? 'text-green-600' : 'text-red-600' }}">
                            {{ number_format($order->paid_amount, 2) }}
                            @if(!$order->isPaymentAmountCorrect())
                                <span class="text-xs text-red-500">(Short payment)</span>
                            @endif
                        </span>
                    </div>
                    @endif

                    @if($order->transaction_id)
                    <div>
                        <span class="text-gray-600 text-sm">Transaction ID:</span>
                        <p class="font-medium">{{ $order->transaction_id }}</p>
                    </div>
                    @endif

                    @if($order->payment_proof)
                    <div>
                        <span class="text-gray-600 text-sm">Payment Proof:</span>
                        <div class="mt-2">
                            <a href="{{ asset('storage/' . $order->payment_proof) }}" target="_blank" class="block">
                                <img src="{{ asset('storage/' . $order->payment_proof) }}" alt="Payment Proof" class="max-w-full h-auto rounded-md border" style="max-height: 200px;">
                            </a>
                            <a href="{{ asset('storage/' . $order->payment_proof) }}" target="_blank" class="text-blue-500 text-sm hover:underline mt-1 block">
                                View Full Image
                            </a>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <div class="bg-white rounded-md shadow p-6">
                <h3 class="text-lg font-semibold mb-4">Actions</h3>
                <div class="space-y-3">
                    @if($order->canMarkAsPaid())
                        <button type="button" 
                                onclick="document.getElementById('markAsPaidModal').classList.remove('hidden')"
                                class="w-full bg-orange-500 text-white px-4 py-2 rounded-md hover:bg-orange-600">
                            <i class="fa-solid fa-money-bill-wave"></i> Mark as Paid
                        </button>
                    @endif

                    @if($order->canConfirm())
                        <form action="{{ route('admin.orders.confirm', $order->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="w-full bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600">
                                <i class="fa-solid fa-check"></i> Confirm Order
                            </button>
                        </form>
                    @endif

                    @if($order->canShip())
                        <form action="{{ route('admin.orders.ship', $order->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="w-full bg-purple-500 text-white px-4 py-2 rounded-md hover:bg-purple-600">
                                <i class="fa-solid fa-truck"></i> Mark as Shipped
                            </button>
                        </form>
                    @endif

                    @if($order->canDeliver())
                        <form action="{{ route('admin.orders.deliver', $order->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="w-full bg-green-500 text-white px-4 py-2 rounded-md hover:bg-green-600">
                                <i class="fa-solid fa-check-circle"></i> Mark as Delivered
                            </button>
                        </form>
                    @endif

                    @if($order->canVerifyPayment() || $order->canApprovePayment())
                        <form action="{{ route('admin.orders.approve-payment', $order->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="w-full bg-green-500 text-white px-4 py-2 rounded-md hover:bg-green-600">
                                <i class="fa-solid fa-check"></i> Approve Payment
                            </button>
                        </form>
                        <form action="{{ route('admin.orders.reject-payment', $order->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="w-full bg-red-500 text-white px-4 py-2 rounded-md hover:bg-red-600">
                                <i class="fa-solid fa-times"></i> Reject Payment
                            </button>
                        </form>
                    @endif

                    @if($order->canCancel())
                        <form action="{{ route('admin.orders.cancel', $order->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="w-full bg-red-500 text-white px-4 py-2 rounded-md hover:bg-red-600" onclick="return confirm('Are you sure you want to cancel this order? Stock will be restored.')">
                                <i class="fa-solid fa-ban"></i> Cancel Order
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="flex justify-between items-center">
        <a href="{{ route('admin.orders.index') }}" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600">
            <i class="fa-solid fa-arrow-left"></i> Back to Orders
        </a>
    </div>
</div>

<!-- Mark as Paid Modal -->
<div id="markAsPaidModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-lg p-6 w-96">
        <h3 class="text-lg font-semibold mb-4">Mark as Paid</h3>
        
        <div class="mb-4 p-3 bg-gray-50 rounded-md">
            <p class="text-sm text-gray-600">Payment Method:</p>
            <p class="font-medium">{{ $order->paymentMethod->name ?? 'N/A' }}</p>
            @if($order->paymentMethod)
                @if($order->paymentMethod->account_number)
                    <p class="text-sm text-gray-500 mt-1">Account: {{ $order->paymentMethod->account_number }}</p>
                @endif
                @if($order->paymentMethod->account_name)
                    <p class="text-sm text-gray-500">Name: {{ $order->paymentMethod->account_name }}</p>
                @endif
            @endif
        </div>

        <form action="{{ route('admin.orders.mark-as-paid', $order->id) }}" method="POST">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Amount Received</label>
                <input type="number" 
                       name="paid_amount" 
                       step="0.01" 
                       min="0" 
                       value="{{ $order->total_payable }}"
                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-400"
                       required>
                <p class="text-sm text-gray-500 mt-1">Total payable: {{ number_format($order->total_payable, 2) }}</p>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" 
                        onclick="document.getElementById('markAsPaidModal').classList.add('hidden')"
                        class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600">
                    Cancel
                </button>
                <button type="submit" class="bg-orange-500 text-white px-4 py-2 rounded-md hover:bg-orange-600">
                    Confirm Payment
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
