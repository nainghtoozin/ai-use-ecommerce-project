@extends('admin.layouts.admin')

@section('title', 'Coupons')
@section('page-title', 'Coupons & Discounts')

@section('content')
<div x-data="couponDelete()" x-cloak>

    @if (session('success'))
        <div x-show="true" x-transition
             class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-md mb-6">
            <strong class="font-bold">Success! </strong>
            <span class="block sm:inline">{{ session('success') }}</span>
        </div>
    @endif

    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
        <h3 class="text-xl font-semibold text-gray-800">Coupon List</h3>
        <a href="{{ route('admin.coupons.create') }}"
           class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600 flex items-center gap-2">
            <i class="fa-solid fa-plus"></i> Add Coupon
        </a>
    </div>

    <div class="mb-6">
        <form action="{{ route('admin.coupons.search') }}" method="GET" class="flex flex-col sm:flex-row gap-3 sm:gap-2">
            <input type="text" name="query" value="{{ request('query') }}"
                   class="flex-1 border border-gray-300 rounded-md px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-400"
                   placeholder="Search coupons by name, code or type..." required>
            <button type="submit"
                    class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600 flex items-center justify-center gap-2">
                <i class="fa-solid fa-search"></i> Search
            </button>
        </form>
    </div>

    <div class="overflow-x-auto bg-white rounded-md shadow-md">
        <table class="min-w-full text-left border-collapse">
            <thead class="bg-gray-100 text-gray-600 uppercase text-sm">
                <tr>
                    <th class="px-4 py-3 border-b">#</th>
                    <th class="px-4 py-3 border-b">Name</th>
                    <th class="px-4 py-3 border-b">Code</th>
                    <th class="px-4 py-3 border-b">Type</th>
                    <th class="px-4 py-3 border-b">Value</th>
                    <th class="px-4 py-3 border-b">Used</th>
                    <th class="px-4 py-3 border-b">Active</th>
                    <th class="px-4 py-3 border-b text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="text-gray-700">
                @forelse($coupons as $coupon)
                <tr class="hover:bg-gray-200/30 transition">
                    <td class="px-4 py-2 border-b">{{ $coupon->id }}</td>
                    <td class="px-4 py-2 border-b">
                        <div class="font-medium">{{ $coupon->name }}</div>
                        @if($coupon->description)
                            <div class="text-xs text-gray-500 mt-0.5">{{ Str::limit($coupon->description, 40) }}</div>
                        @endif
                    </td>
                    <td class="px-4 py-2 border-b">
                        @if($coupon->code)
                            <span class="font-mono bg-gray-100 px-2 py-0.5 rounded text-sm">{{ $coupon->code }}</span>
                        @else
                            <span class="text-xs text-gray-400 italic">Auto-apply</span>
                        @endif
                    </td>
                    <td class="px-4 py-2 border-b capitalize">{{ str_replace('_', ' ', $coupon->type) }}</td>
                    <td class="px-4 py-2 border-b">
                        @if($coupon->type === 'percentage')
                            {{ $coupon->discount_value }}%
                            @if($coupon->discount_cap)
                                <br><span class="text-xs text-gray-500">cap: {{ number_format($coupon->discount_cap, 2) }}</span>
                            @endif
                        @elseif($coupon->type === 'free_shipping')
                            <span class="text-gray-500">Free Shipping</span>
                        @else
                            {{ number_format($coupon->discount_value, 2) }}
                        @endif
                    </td>
                    <td class="px-4 py-2 border-b">
                        {{ $coupon->used_count }}
                        @if($coupon->usage_limit)
                            / {{ $coupon->usage_limit }}
                        @endif
                    </td>
                    <td class="px-4 py-2 border-b">
                        <span class="px-2 py-1 rounded-md text-sm font-medium
                            {{ $coupon->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600' }}">
                            {{ $coupon->is_active ? 'Active' : 'Inactive' }}
                        </span>
                        @if($coupon->expires_at && now()->gt($coupon->expires_at))
                            <br><span class="text-xs text-red-500">Expired</span>
                        @endif
                    </td>
                    <td class="px-4 py-2 border-b text-center">
                        <div class="flex justify-center gap-2">
                            <a href="{{ route('admin.coupons.edit', $coupon->id) }}"
                               class="bg-green-500 text-white px-3 py-1 rounded-md hover:bg-green-600 flex items-center gap-1">
                                <i class="fa-solid fa-pen-to-square"></i> Edit
                            </a>
                            <button @click="openModal({{ $coupon->id }}, '{{ addslashes($coupon->name) }}')"
                                    class="bg-red-500 text-white px-3 py-1 rounded-md hover:bg-red-600 flex items-center gap-1">
                                <i class="fa-solid fa-trash"></i> Delete
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center text-gray-500 py-4">No coupons found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div x-show="show" x-transition.opacity
         class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
         style="display: none;">
        <div @click.away="show = false" class="bg-white rounded-md shadow-lg p-6 w-96">
            <h3 class="text-lg font-semibold mb-4">Confirm Delete</h3>
            <p class="mb-4">Are you sure you want to delete <strong x-text="couponName"></strong>?</p>
            <div class="flex justify-end gap-3">
                <button @click="show = false" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600">
                    Cancel
                </button>
                <form :action="`/admin/coupons/${couponId}`" method="POST" class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="bg-red-500 text-white px-4 py-2 rounded-md hover:bg-red-600">
                        Delete
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="mt-4 flex justify-center">
        {{ $coupons->links() }}
    </div>
</div>

<script>
function couponDelete() {
    return {
        show: false,
        couponId: null,
        couponName: '',
        openModal(id, name) {
            this.couponId = id;
            this.couponName = name;
            this.show = true;
        }
    }
}
</script>
@endsection
