@extends('admin.layouts.admin')

@section('title', 'Edit Promotion')
@section('page-title', 'Edit Promotion')

@section('content')
<div class="max-w-3xl mx-auto bg-white p-6 rounded-md shadow-md">
    <h3 class="text-xl font-semibold text-gray-800 mb-4">Edit Promotion</h3>

    @if ($errors->any())
        <div class="bg-red-100 text-red-600 px-4 py-2 rounded mb-4">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>- {{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.promotions.update', $promotion->id) }}" method="POST" class="space-y-4">
        @csrf
        @method('PUT')

        <div>
            <label for="name" class="block text-gray-700 font-medium">Promotion Name</label>
            <input type="text" name="name" id="name" value="{{ old('name', $promotion->name) }}"
                   class="w-full border border-gray-300 rounded-md px-3 py-2 mt-1 focus:outline-none focus:ring focus:border-blue-400"
                   placeholder="e.g. Summer Sale 2026" required>
        </div>

        <div>
            <label for="description" class="block text-gray-700 font-medium">Description</label>
            <textarea name="description" id="description" rows="2"
                      class="w-full border border-gray-300 rounded-md px-3 py-2 mt-1 focus:outline-none focus:ring focus:border-blue-400"
                      placeholder="Optional description">{{ old('description', $promotion->description) }}</textarea>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="code" class="block text-gray-700 font-medium">Promotion Code</label>
                <input type="text" name="code" id="code" value="{{ old('code', $promotion->code) }}"
                       class="w-full border border-gray-300 rounded-md px-3 py-2 mt-1 focus:outline-none focus:ring focus:border-blue-400"
                       placeholder="e.g. SUMMER20">
                <p class="text-xs text-gray-500 mt-1">Leave empty for automatic/no-code promotions</p>
            </div>

            <div>
                <label for="type" class="block text-gray-700 font-medium">Discount Type</label>
                <select name="type" id="type"
                        class="w-full border border-gray-300 rounded-md px-3 py-2 mt-1 focus:outline-none focus:ring focus:border-blue-400">
                    <option value="percentage" {{ old('type', $promotion->type) === 'percentage' ? 'selected' : '' }}>Percentage (%)</option>
                    <option value="fixed" {{ old('type', $promotion->type) === 'fixed' ? 'selected' : '' }}>Fixed Amount</option>
                    <option value="free_shipping" {{ old('type', $promotion->type) === 'free_shipping' ? 'selected' : '' }}>Free Shipping</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="value" class="block text-gray-700 font-medium">Discount Value</label>
                <div class="flex items-center gap-2 mt-1">
                    <input type="number" step="0.01" min="0" name="value" id="value" value="{{ old('value', $promotion->value) }}"
                           class="flex-1 border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring focus:border-blue-400">
                    <span class="text-sm text-gray-500 type-suffix">{{ $promotion->type === 'percentage' ? '%' : ($promotion->type === 'free_shipping' ? 'free shipping' : 'fixed') }}</span>
                </div>
            </div>

            <div id="maxDiscountField" class="{{ $promotion->type === 'percentage' ? '' : 'hidden' }}">
                <label for="max_discount_amount" class="block text-gray-700 font-medium">Max Discount Cap</label>
                <input type="number" step="0.01" min="0" name="max_discount_amount" id="max_discount_amount" value="{{ old('max_discount_amount', $promotion->max_discount_amount) }}"
                       class="w-full border border-gray-300 rounded-md px-3 py-2 mt-1 focus:outline-none focus:ring focus:border-blue-400"
                       placeholder="Leave empty for no cap">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="minimum_order_amount" class="block text-gray-700 font-medium">Minimum Order Amount</label>
                <input type="number" step="0.01" min="0" name="minimum_order_amount" id="minimum_order_amount" value="{{ old('minimum_order_amount', $promotion->minimum_order_amount) }}"
                       class="w-full border border-gray-300 rounded-md px-3 py-2 mt-1 focus:outline-none focus:ring focus:border-blue-400"
                       placeholder="Leave empty for no minimum">
            </div>

            <div>
                <label for="applies_to" class="block text-gray-700 font-medium">Applies To</label>
                <select name="applies_to" id="applies_to"
                        class="w-full border border-gray-300 rounded-md px-3 py-2 mt-1 focus:outline-none focus:ring focus:border-blue-400">
                    <option value="all" {{ old('applies_to', $promotion->applies_to) === 'all' ? 'selected' : '' }}>All Products</option>
                    <option value="products" {{ old('applies_to', $promotion->applies_to) === 'products' ? 'selected' : '' }}>Specific Products</option>
                    <option value="categories" {{ old('applies_to', $promotion->applies_to) === 'categories' ? 'selected' : '' }}>Specific Categories</option>
                </select>
            </div>
        </div>

        <div id="productsField" class="{{ $promotion->applies_to === 'products' ? '' : 'hidden' }}">
            <label for="product_ids" class="block text-gray-700 font-medium">Select Products</label>
            <select name="product_ids[]" id="product_ids" multiple
                    class="w-full border border-gray-300 rounded-md px-3 py-2 mt-1 focus:outline-none focus:ring focus:border-blue-400"
                    size="5">
                @foreach($products as $product)
                    <option value="{{ $product->id }}" {{ $promotion->products->contains($product->id) ? 'selected' : '' }}>
                        {{ $product->name }}
                    </option>
                @endforeach
            </select>
            <p class="text-xs text-gray-500 mt-1">Ctrl+Click to select multiple</p>
        </div>

        <div id="categoriesField" class="{{ $promotion->applies_to === 'categories' ? '' : 'hidden' }}">
            <label for="category_ids" class="block text-gray-700 font-medium">Select Categories</label>
            <select name="category_ids[]" id="category_ids" multiple
                    class="w-full border border-gray-300 rounded-md px-3 py-2 mt-1 focus:outline-none focus:ring focus:border-blue-400"
                    size="5">
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" {{ $promotion->categories->contains($category->id) ? 'selected' : '' }}>
                        {{ $category->name }}
                    </option>
                @endforeach
            </select>
            <p class="text-xs text-gray-500 mt-1">Ctrl+Click to select multiple</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="starts_at" class="block text-gray-700 font-medium">Start Date</label>
                <input type="datetime-local" name="starts_at" id="starts_at" value="{{ old('starts_at', $promotion->starts_at ? $promotion->starts_at->format('Y-m-d\TH:i') : '') }}"
                       class="w-full border border-gray-300 rounded-md px-3 py-2 mt-1 focus:outline-none focus:ring focus:border-blue-400">
            </div>
            <div>
                <label for="ends_at" class="block text-gray-700 font-medium">End Date</label>
                <input type="datetime-local" name="ends_at" id="ends_at" value="{{ old('ends_at', $promotion->ends_at ? $promotion->ends_at->format('Y-m-d\TH:i') : '') }}"
                       class="w-full border border-gray-300 rounded-md px-3 py-2 mt-1 focus:outline-none focus:ring focus:border-blue-400">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="usage_limit" class="block text-gray-700 font-medium">Usage Limit (total)</label>
                <input type="number" min="1" name="usage_limit" id="usage_limit" value="{{ old('usage_limit', $promotion->usage_limit) }}"
                       class="w-full border border-gray-300 rounded-md px-3 py-2 mt-1 focus:outline-none focus:ring focus:border-blue-400"
                       placeholder="Leave empty for unlimited">
            </div>
            <div>
                <label for="per_customer_limit" class="block text-gray-700 font-medium">Per-Customer Limit</label>
                <input type="number" min="1" name="per_customer_limit" id="per_customer_limit" value="{{ old('per_customer_limit', $promotion->per_customer_limit) }}"
                       class="w-full border border-gray-300 rounded-md px-3 py-2 mt-1 focus:outline-none focus:ring focus:border-blue-400"
                       placeholder="Leave empty for unlimited">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="priority" class="block text-gray-700 font-medium">Priority</label>
                <input type="number" min="0" name="priority" id="priority" value="{{ old('priority', $promotion->priority) }}"
                       class="w-full border border-gray-300 rounded-md px-3 py-2 mt-1 focus:outline-none focus:ring focus:border-blue-400">
                <p class="text-xs text-gray-500 mt-1">Higher values = higher priority</p>
            </div>
            <div class="flex items-center gap-6 pt-7">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="is_automatic" value="1"
                           class="w-4 h-4 text-blue-500 border-gray-300 rounded focus:ring-blue-400"
                           {{ old('is_automatic', $promotion->is_automatic) ? 'checked' : '' }}>
                    <span class="text-gray-700 font-medium">Auto-apply</span>
                </label>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="stackable" value="1"
                           class="w-4 h-4 text-blue-500 border-gray-300 rounded focus:ring-blue-400"
                           {{ old('stackable', $promotion->stackable) ? 'checked' : '' }}>
                    <span class="text-gray-700 font-medium">Stackable</span>
                </label>
                <input type="hidden" name="is_active" value="0">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1"
                           class="w-4 h-4 text-blue-500 border-gray-300 rounded focus:ring-blue-400"
                           {{ old('is_active', $promotion->is_active) ? 'checked' : '' }}>
                    <span class="text-gray-700 font-medium">Active</span>
                </label>
            </div>
        </div>

        <div class="flex justify-end gap-3 pt-2">
            <a href="{{ route('admin.promotions.index') }}"
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

<script>
document.getElementById('type').addEventListener('change', function() {
    const suffix = document.querySelector('.type-suffix');
    const maxField = document.getElementById('maxDiscountField');
    if (this.value === 'percentage') {
        suffix.textContent = '%';
        maxField.classList.remove('hidden');
    } else if (this.value === 'fixed') {
        suffix.textContent = 'fixed';
        maxField.classList.add('hidden');
    } else {
        suffix.textContent = 'free shipping';
        maxField.classList.add('hidden');
    }
});

document.getElementById('applies_to').addEventListener('change', function() {
    document.getElementById('productsField').classList.toggle('hidden', this.value !== 'products');
    document.getElementById('categoriesField').classList.toggle('hidden', this.value !== 'categories');
});

if (document.getElementById('applies_to').value === 'products') {
    document.getElementById('productsField').classList.remove('hidden');
} else if (document.getElementById('applies_to').value === 'categories') {
    document.getElementById('categoriesField').classList.remove('hidden');
}
</script>
@endsection
