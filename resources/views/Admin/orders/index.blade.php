@extends('Admin.layouts.admin')

@section('title', 'Orders')
@section('page-title', 'Orders')

@section('content')
<div x-data="orderFilter()">
    @if (session('success'))
        <div x-show="true" x-transition class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-md mb-6">
            <strong class="font-bold">Success! </strong>
            <span class="block sm:inline">{{ session('success') }}</span>
        </div>
    @endif

    @if (session('error'))
        <div x-show="true" x-transition class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-md mb-6">
            <strong class="font-bold">Error! </strong>
            <span class="block sm:inline">{{ session('error') }}</span>
        </div>
    @endif

    <div class="flex justify-between items-center mb-6">
        <h3 class="text-xl font-semibold text-gray-800">Order List</h3>
    </div>

    <form action="{{ route('admin.orders.index') }}" method="GET" class="mb-6">
        <div class="flex flex-wrap gap-3 items-end">
            <div class="w-40">
                <label class="block text-sm font-medium text-gray-700 mb-1">Order Status</label>
                <select name="order_status" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-400">
                    <option value="">All</option>
                    <option value="pending" {{ request('order_status') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="confirmed" {{ request('order_status') === 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                    <option value="shipped" {{ request('order_status') === 'shipped' ? 'selected' : '' }}>Shipped</option>
                    <option value="delivered" {{ request('order_status') === 'delivered' ? 'selected' : '' }}>Delivered</option>
                    <option value="cancelled" {{ request('order_status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                </select>
            </div>

            <div class="w-40">
                <label class="block text-sm font-medium text-gray-700 mb-1">Payment Status</label>
                <select name="payment_status" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-400">
                    <option value="">All</option>
                    <option value="unpaid" {{ request('payment_status') === 'unpaid' ? 'selected' : '' }}>Unpaid</option>
                    <option value="paid" {{ request('payment_status') === 'paid' ? 'selected' : '' }}>Paid</option>
                    <option value="verified" {{ request('payment_status') === 'verified' ? 'selected' : '' }}>Verified</option>
                    <option value="rejected" {{ request('payment_status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                </select>
            </div>

            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <input 
                    type="text" 
                    name="search" 
                    value="{{ request('search') }}" 
                    class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-400"
                    placeholder="Search by name, phone, order ID..."
                >
            </div>

            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600">
                <i class="fa-solid fa-filter"></i> Filter
            </button>

            @if(request()->anyFilled(['order_status', 'payment_status', 'search']))
                <a href="{{ route('admin.orders.index') }}" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600">
                    Clear
                </a>
            @endif
        </div>
    </form>

    <div class="overflow-x-auto bg-white rounded-md shadow-md">
        <table class="min-w-full text-left border-collapse">
            <thead class="bg-gray-200 text-gray-700 uppercase text-sm">
                <tr>
                    <th class="px-4 py-3 border-b">#</th>
                    <th class="px-4 py-3 border-b">Customer</th>
                    <th class="px-4 py-3 border-b">Products</th>
                    <th class="px-4 py-3 border-b">Total</th>
                    <th class="px-4 py-3 border-b">Payment Method</th>
                    <th class="px-4 py-3 border-b">City</th>
                    <th class="px-4 py-3 border-b">Order Status</th>
                    <th class="px-4 py-3 border-b">Payment</th>
                    <th class="px-4 py-3 border-b">Date</th>
                    <th class="px-4 py-3 border-b text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="text-gray-700">
                @php
                    $orderStatusColors = [
                        'pending' => 'bg-yellow-100 text-yellow-800',
                        'confirmed' => 'bg-blue-100 text-blue-800',
                        'shipped' => 'bg-purple-100 text-purple-800',
                        'delivered' => 'bg-green-100 text-green-800',
                        'cancelled' => 'bg-red-100 text-red-800',
                    ];
                    $paymentStatusColors = [
                        'unpaid' => 'bg-gray-100 text-gray-800',
                        'paid' => 'bg-orange-100 text-orange-800',
                        'verified' => 'bg-green-100 text-green-800',
                        'rejected' => 'bg-red-100 text-red-800',
                    ];
                @endphp

                @forelse($orders as $order)
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-4 py-3 border-b">{{ $order->id }}</td>

                    <td class="px-4 py-3 border-b">
                        @if($order->user)
                            <div class="font-medium">{{ $order->first_name }} {{ $order->last_name }}</div>
                            <div class="text-sm text-gray-500">{{ $order->phone }}</div>
                        @else
                            <div class="font-medium">{{ $order->first_name }} {{ $order->last_name }}</div>
                            <div class="text-sm text-gray-500">{{ $order->phone }}</div>
                        @endif
                    </td>

                    <td class="px-4 py-3 border-b text-sm">
                        @forelse($order->items as $item)
                            <div>{{ $item->product->name ?? 'N/A' }} (x{{ $item->quantity }})</div>
                        @empty
                            <span class="text-gray-500">No items</span>
                        @endforelse
                    </td>

                    <td class="px-4 py-3 border-b">
                        <div class="font-medium">{{ number_format($order->total_amount, 2) }}</div>
                        @if($order->delivery_fee > 0)
                            <div class="text-xs text-gray-500">+{{ number_format($order->delivery_fee, 2) }} shipping</div>
                        @endif
                    </td>

                    <td class="px-4 py-3 border-b">
                        <span class="text-sm">
                            {{ $order->paymentMethod->name ?? 'N/A' }}
                        </span>
                    </td>

                    <td class="px-4 py-3 border-b">
                        <span class="text-sm">
                            {{ $order->city->name ?? 'N/A' }}
                        </span>
                    </td>

                    <td class="px-4 py-3 border-b">
                        <span class="px-2 py-1 rounded-full text-xs font-semibold {{ $orderStatusColors[$order->order_status] ?? 'bg-gray-100 text-gray-800' }}">
                            {{ ucfirst($order->order_status) }}
                        </span>
                    </td>

                    <td class="px-4 py-3 border-b">
                        <span class="px-2 py-1 rounded-full text-xs font-semibold {{ $paymentStatusColors[$order->payment_status] ?? 'bg-gray-100 text-gray-800' }}">
                            {{ ucfirst($order->payment_status) }}
                        </span>
                        @if($order->payment_proof)
                            <i class="fa-solid fa-check-circle text-green-500 ml-1"></i>
                        @endif
                    </td>

                    <td class="px-4 py-3 border-b text-sm">
                        {{ $order->created_at->format('Y-m-d') }}
                        <div class="text-xs text-gray-500">{{ $order->created_at->format('H:i') }}</div>
                    </td>

                    <td class="px-4 py-3 border-b text-center">
                        <a href="{{ route('admin.orders.show', $order->id) }}" class="bg-blue-500 text-white px-3 py-1 rounded-md hover:bg-blue-600 text-sm">
                            <i class="fa-solid fa-eye"></i> View
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" class="text-center py-6 text-gray-500">No orders found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $orders->links('pagination::tailwind') }}
    </div>
</div>

<script>
function orderFilter() {
    return {
        init() {
            // Initialize if needed
        }
    }
}
</script>
@endsection