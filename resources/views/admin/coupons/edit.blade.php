@extends('admin.layouts.admin')

@section('title', 'Edit Coupon')
@section('page-title', 'Edit Coupon')

@section('content')
<div class="max-w-3xl mx-auto bg-white p-6 rounded-md shadow-md">
    <h3 class="text-xl font-semibold text-gray-800 mb-4">Edit Coupon: {{ $coupon->name }}</h3>

    @if ($errors->any())
        <div class="bg-red-100 text-red-600 px-4 py-2 rounded mb-4">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>- {{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.coupons.update', $coupon->id) }}" method="POST" class="space-y-4">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="name" class="block text-gray-700 font-medium">Coupon Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" id="name" value="{{ old('name', $coupon->name) }}"
                       class="w-full border border-gray-300 rounded-md px-3 py-2 mt-1 focus:outline-none focus:ring focus:border-blue-400" required>
            </div>

            <div>
                <label for="type" class="block text-gray-700 font-medium">Discount Type <span class="text-red-500">*</span></label>
                <select name="type" id="type"
                        class="w-full border border-gray-300 rounded-md px-3 py-2 mt-1 focus:outline-none focus:ring focus:border-blue-400">
                    <option value="percentage" {{ old('type', $coupon->type) === 'percentage' ? 'selected' : '' }}>Percentage (%)</option>
                    <option value="fixed_amount" {{ old('type', $coupon->type) === 'fixed_amount' ? 'selected' : '' }}>Fixed Amount</option>
                    <option value="free_shipping" {{ old('type', $coupon->type) === 'free_shipping' ? 'selected' : '' }}>Free Shipping</option>
                </select>
            </div>
        </div>

        <div>
            <label for="description" class="block text-gray-700 font-medium">Description</label>
            <textarea name="description" id="description" rows="2"
                      class="w-full border border-gray-300 rounded-md px-3 py-2 mt-1 focus:outline-none focus:ring focus:border-blue-400">{{ old('description', $coupon->description) }}</textarea>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="code" class="block text-gray-700 font-medium">Coupon Code</label>
                <input type="text" name="code" id="code" value="{{ old('code', $coupon->code) }}"
                       class="w-full border border-gray-300 rounded-md px-3 py-2 mt-1 focus:outline-none focus:ring focus:border-blue-400"
                       placeholder="SUMMER20">
                <p class="text-xs text-gray-400 mt-1">Leave empty for auto-apply promotions</p>
            </div>

            <div>
                <label for="discount_value" class="block text-gray-700 font-medium">Discount Value <span class="text-red-500">*</span></label>
                <input type="number" step="0.01" min="0" name="discount_value" id="discount_value" value="{{ old('discount_value', $coupon->discount_value) }}"
                       class="w-full border border-gray-300 rounded-md px-3 py-2 mt-1 focus:outline-none focus:ring focus:border-blue-400">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label for="min_order_amount" class="block text-gray-700 font-medium">Min Order Amount</label>
                <input type="number" step="0.01" min="0" name="min_order_amount" id="min_order_amount" value="{{ old('min_order_amount', $coupon->min_order_amount) }}"
                       class="w-full border border-gray-300 rounded-md px-3 py-2 mt-1 focus:outline-none focus:ring focus:border-blue-400">
            </div>

            <div>
                <label for="discount_cap" class="block text-gray-700 font-medium">Discount Cap</label>
                <input type="number" step="0.01" min="0" name="discount_cap" id="discount_cap" value="{{ old('discount_cap', $coupon->discount_cap) }}"
                       class="w-full border border-gray-300 rounded-md px-3 py-2 mt-1 focus:outline-none focus:ring focus:border-blue-400">
            </div>

            <div>
                <label for="priority" class="block text-gray-700 font-medium">Priority</label>
                <input type="number" min="0" name="priority" id="priority" value="{{ old('priority', $coupon->priority) }}"
                       class="w-full border border-gray-300 rounded-md px-3 py-2 mt-1 focus:outline-none focus:ring focus:border-blue-400">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="usage_limit" class="block text-gray-700 font-medium">Usage Limit (Total)</label>
                <input type="number" min="1" name="usage_limit" id="usage_limit" value="{{ old('usage_limit', $coupon->usage_limit) }}"
                       class="w-full border border-gray-300 rounded-md px-3 py-2 mt-1 focus:outline-none focus:ring focus:border-blue-400">
            </div>

            <div>
                <label for="per_customer_limit" class="block text-gray-700 font-medium">Per-Customer Limit</label>
                <input type="number" min="1" name="per_customer_limit" id="per_customer_limit" value="{{ old('per_customer_limit', $coupon->per_customer_limit) }}"
                       class="w-full border border-gray-300 rounded-md px-3 py-2 mt-1 focus:outline-none focus:ring focus:border-blue-400">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="starts_at" class="block text-gray-700 font-medium">Start Date</label>
                <input type="datetime-local" name="starts_at" id="starts_at" value="{{ old('starts_at', $coupon->starts_at ? $coupon->starts_at->format('Y-m-d\TH:i') : '') }}"
                       class="w-full border border-gray-300 rounded-md px-3 py-2 mt-1 focus:outline-none focus:ring focus:border-blue-400">
            </div>

            <div>
                <label for="expires_at" class="block text-gray-700 font-medium">Expiry Date</label>
                <input type="datetime-local" name="expires_at" id="expires_at" value="{{ old('expires_at', $coupon->expires_at ? $coupon->expires_at->format('Y-m-d\TH:i') : '') }}"
                       class="w-full border border-gray-300 rounded-md px-3 py-2 mt-1 focus:outline-none focus:ring focus:border-blue-400">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-start">
            <div class="flex items-center gap-2">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" id="is_active" value="1"
                       class="w-4 h-4 text-blue-500 border-gray-300 rounded focus:ring-blue-400"
                       {{ old('is_active', $coupon->is_active) ? 'checked' : '' }}>
                <label for="is_active" class="text-gray-700 font-medium">Active</label>
            </div>

            <div class="flex items-center gap-2">
                <input type="hidden" name="is_stackable" value="0">
                <input type="checkbox" name="is_stackable" id="is_stackable" value="1"
                       class="w-4 h-4 text-blue-500 border-gray-300 rounded focus:ring-blue-400"
                       {{ old('is_stackable', $coupon->is_stackable) ? 'checked' : '' }}>
                <label for="is_stackable" class="text-gray-700 font-medium">Stackable</label>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="category_ids" class="block text-gray-700 font-medium">Restrict to Categories</label>
                <select name="category_ids[]" id="category_ids" multiple
                        class="w-full border border-gray-300 rounded-md px-3 py-2 mt-1 focus:outline-none focus:ring focus:border-blue-400"
                        style="min-height: 100px;">
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ $coupon->categories->contains($category->id) ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="product_ids" class="block text-gray-700 font-medium">Restrict to Products</label>
                <select name="product_ids[]" id="product_ids" multiple
                        class="w-full border border-gray-300 rounded-md px-3 py-2 mt-1 focus:outline-none focus:ring focus:border-blue-400"
                        style="min-height: 100px;">
                    @foreach($products as $product)
                        <option value="{{ $product->id }}" {{ $coupon->products->contains($product->id) ? 'selected' : '' }}>
                            {{ $product->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="flex justify-end gap-3 pt-2">
            <a href="{{ route('admin.coupons.index') }}"
               class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600">
                Cancel
            </a>
            <button type="submit"
                    class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600">
                <i class="fa-solid fa-save mr-1"></i> Update
            </button>
        </div>
    </form>
</div>
@endsection
