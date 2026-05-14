@extends('admin.layouts.admin')

@section('title', 'Create Promotion')
@section('page-title', 'Create New Promotion')

@section('content')
<div x-data="promotionForm()" class="max-w-4xl mx-auto">

    {{-- Validation Errors --}}
    @if ($errors->any())
    <div class="mb-6 bg-red-50 border-l-4 border-red-500 text-red-800 px-4 py-3 rounded-r-lg shadow-sm">
        <div class="flex items-start gap-2">
            <i class="fa-solid fa-circle-exclamation mt-0.5 text-red-500"></i>
            <div>
                <p class="font-semibold text-sm">Please fix the following errors:</p>
                <ul class="list-disc list-inside text-sm mt-1 space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
    @endif

    <form action="{{ route('admin.promotions.store') }}" method="POST">
        @csrf

        {{-- Section 1: Basic Information --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-info-circle text-blue-600"></i>
                    <h3 class="text-base font-bold text-gray-800">Basic Information</h3>
                </div>
            </div>
            <div class="p-6 space-y-5">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    <div>
                        <label for="name" class="block text-sm font-semibold text-gray-700 mb-1.5">
                            Promotion Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="name" id="name" value="{{ old('name') }}"
                               class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="e.g. Summer Sale 2026" required>
                    </div>
                    <div>
                        <label for="code" class="block text-sm font-semibold text-gray-700 mb-1.5">
                            Coupon Code
                        </label>
                        <div class="flex gap-2">
                            <input type="text" name="code" id="code" value="{{ old('code') }}"
                                   class="flex-1 px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm font-mono focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="e.g. SUMMER20" x-model="code">
                            <button type="button" @click="generateCode()"
                                    class="px-3.5 py-2.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 transition-colors border border-gray-300 whitespace-nowrap"
                                    title="Generate random code">
                                <i class="fa-solid fa-shuffle mr-1"></i> Generate
                            </button>
                        </div>
                        <p class="text-xs text-gray-400 mt-1.5">Leave empty for automatic/no-code promotions, or click Generate for a random code</p>
                    </div>
                </div>
                <div>
                    <label for="description" class="block text-sm font-semibold text-gray-700 mb-1.5">Description</label>
                    <textarea name="description" id="description" rows="2"
                              class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                              placeholder="Optional description for internal reference">{{ old('description') }}</textarea>
                </div>
            </div>
        </div>

        {{-- Section 2: Discount Configuration --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-percent text-emerald-600"></i>
                    <h3 class="text-base font-bold text-gray-800">Discount Configuration</h3>
                </div>
            </div>
            <div class="p-6 space-y-5">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                    <div>
                        <label for="type" class="block text-sm font-semibold text-gray-700 mb-1.5">
                            Discount Type <span class="text-red-500">*</span>
                        </label>
                        <select name="type" id="type" x-model="discountType"
                                class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="percentage">Percentage (%)</option>
                            <option value="fixed">Fixed Amount</option>
                            <option value="free_shipping">Free Shipping</option>
                        </select>
                    </div>
                    <div>
                        <label for="value" class="block text-sm font-semibold text-gray-700 mb-1.5">
                            Discount Value <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="number" step="0.01" min="0" name="value" id="value" value="{{ old('value', 0) }}"
                                   class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-gray-400 font-medium" x-text="suffix"></span>
                        </div>
                    </div>
                    <div x-show="discountType === 'percentage'" x-transition>
                        <label for="max_discount_amount" class="block text-sm font-semibold text-gray-700 mb-1.5">Maximum Discount Cap</label>
                        <input type="number" step="0.01" min="0" name="max_discount_amount" id="max_discount_amount" value="{{ old('max_discount_amount') }}"
                               class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="No cap">
                    </div>
                </div>
                <div>
                    <label for="minimum_order_amount" class="block text-sm font-semibold text-gray-700 mb-1.5">Minimum Order Amount</label>
                    <input type="number" step="0.01" min="0" name="minimum_order_amount" id="minimum_order_amount" value="{{ old('minimum_order_amount') }}"
                           class="w-full max-w-xs px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="No minimum">
                    <p class="text-xs text-gray-400 mt-1">Cart subtotal must be at least this amount for the promotion to apply</p>
                </div>
            </div>
        </div>

        {{-- Section 3: Applicability --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-bullseye text-purple-600"></i>
                    <h3 class="text-base font-bold text-gray-800">Applicability</h3>
                </div>
            </div>
            <div class="p-6 space-y-5">
                <div>
                    <label for="applies_to" class="block text-sm font-semibold text-gray-700 mb-1.5">Applies To <span class="text-red-500">*</span></label>
                    <select name="applies_to" id="applies_to" x-model="appliesTo"
                            class="w-full max-w-xs px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="all">All Products</option>
                        <option value="products">Specific Products</option>
                        <option value="categories">Specific Categories</option>
                    </select>
                </div>

                {{-- Products Multi-Select --}}
                <div x-show="appliesTo === 'products'" x-transition>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Select Products</label>
                    <div class="border border-gray-200 rounded-lg overflow-hidden">
                        <div class="p-2 border-b border-gray-100 bg-gray-50">
                            <div class="relative">
                                <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                                <input type="text" x-model="productSearch"
                                       class="w-full pl-8 pr-3 py-2 border border-gray-200 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="Search products...">
                            </div>
                        </div>
                        <div class="p-2 border-b border-gray-100 bg-white">
                            <template x-if="selectedProducts.length > 0">
                                <div class="flex flex-wrap gap-1.5 mb-2">
                                    <template x-for="pid in selectedProducts" :key="pid">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-blue-50 text-blue-700 rounded-full text-xs font-medium">
                                            <span x-text="products.find(p => p.id == pid)?.name || pid"></span>
                                            <button type="button" @click="selectedProducts = selectedProducts.filter(id => id != pid)" class="text-blue-400 hover:text-blue-700">&times;</button>
                                        </span>
                                    </template>
                                </div>
                            </template>
                        </div>
                        <div class="max-h-48 overflow-y-auto divide-y divide-gray-50">
                            <template x-for="product in filteredProducts" :key="product.id">
                                <label class="flex items-center gap-3 px-4 py-2.5 hover:bg-blue-50/50 cursor-pointer transition-colors">
                                    <input type="checkbox" name="product_ids[]" :value="product.id"
                                           x-model="selectedProducts"
                                           class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                    <span class="text-sm text-gray-700" x-text="product.name"></span>
                                </label>
                            </template>
                            <template x-if="filteredProducts.length === 0">
                                <p class="px-4 py-3 text-sm text-gray-400 text-center">No products found</p>
                            </template>
                        </div>
                    </div>
                    <p class="text-xs text-gray-400 mt-1.5" x-text="`${selectedProducts.length} product(s) selected`"></p>
                </div>

                {{-- Categories Multi-Select --}}
                <div x-show="appliesTo === 'categories'" x-transition>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Select Categories</label>
                    <div class="border border-gray-200 rounded-lg overflow-hidden">
                        <div class="p-2 border-b border-gray-100 bg-gray-50">
                            <div class="relative">
                                <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                                <input type="text" x-model="categorySearch"
                                       class="w-full pl-8 pr-3 py-2 border border-gray-200 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="Search categories...">
                            </div>
                        </div>
                        <div class="p-2 border-b border-gray-100 bg-white">
                            <template x-if="selectedCategories.length > 0">
                                <div class="flex flex-wrap gap-1.5 mb-2">
                                    <template x-for="cid in selectedCategories" :key="cid">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-purple-50 text-purple-700 rounded-full text-xs font-medium">
                                            <span x-text="categories.find(c => c.id == cid)?.name || cid"></span>
                                            <button type="button" @click="selectedCategories = selectedCategories.filter(id => id != cid)" class="text-purple-400 hover:text-purple-700">&times;</button>
                                        </span>
                                    </template>
                                </div>
                            </template>
                        </div>
                        <div class="max-h-48 overflow-y-auto divide-y divide-gray-50">
                            <template x-for="category in filteredCategories" :key="category.id">
                                <label class="flex items-center gap-3 px-4 py-2.5 hover:bg-purple-50/50 cursor-pointer transition-colors">
                                    <input type="checkbox" name="category_ids[]" :value="category.id"
                                           x-model="selectedCategories"
                                           class="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                                    <span class="text-sm text-gray-700" x-text="category.name"></span>
                                </label>
                            </template>
                            <template x-if="filteredCategories.length === 0">
                                <p class="px-4 py-3 text-sm text-gray-400 text-center">No categories found</p>
                            </template>
                        </div>
                    </div>
                    <p class="text-xs text-gray-400 mt-1.5" x-text="`${selectedCategories.length} category(ies) selected`"></p>
                </div>
            </div>
        </div>

        {{-- Section 4: Schedule --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-calendar-days text-amber-600"></i>
                    <h3 class="text-base font-bold text-gray-800">Schedule</h3>
                </div>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    <div>
                        <label for="starts_at" class="block text-sm font-semibold text-gray-700 mb-1.5">Start Date</label>
                        <input type="datetime-local" name="starts_at" id="starts_at" value="{{ old('starts_at') }}"
                               class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="ends_at" class="block text-sm font-semibold text-gray-700 mb-1.5">End Date</label>
                        <input type="datetime-local" name="ends_at" id="ends_at" value="{{ old('ends_at') }}"
                               class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
            </div>
        </div>

        {{-- Section 5: Usage Limits --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-gauge-high text-rose-600"></i>
                    <h3 class="text-base font-bold text-gray-800">Usage Limits</h3>
                </div>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    <div>
                        <label for="usage_limit" class="block text-sm font-semibold text-gray-700 mb-1.5">Total Usage Limit</label>
                        <input type="number" min="1" name="usage_limit" id="usage_limit" value="{{ old('usage_limit') }}"
                               class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Unlimited">
                        <p class="text-xs text-gray-400 mt-1">Maximum number of times this promotion can be used</p>
                    </div>
                    <div>
                        <label for="per_customer_limit" class="block text-sm font-semibold text-gray-700 mb-1.5">Per-Customer Limit</label>
                        <input type="number" min="1" name="per_customer_limit" id="per_customer_limit" value="{{ old('per_customer_limit', 1) }}"
                               class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Unlimited">
                        <p class="text-xs text-gray-400 mt-1">How many times a single customer can use this</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Section 6: Advanced Settings --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-sliders text-gray-600"></i>
                    <h3 class="text-base font-bold text-gray-800">Advanced Settings</h3>
                </div>
            </div>
            <div class="p-6 space-y-5">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    <div>
                        <label for="priority" class="block text-sm font-semibold text-gray-700 mb-1.5">Priority</label>
                        <input type="number" min="0" name="priority" id="priority" value="{{ old('priority', 0) }}"
                               class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <p class="text-xs text-gray-400 mt-1">Higher values are evaluated first. Only applies to automatic promotions.</p>
                    </div>
                    <div class="flex flex-col justify-end gap-3">
                        <div class="flex items-center gap-3">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="hidden" name="is_automatic" value="0">
                                <input type="checkbox" name="is_automatic" value="1" class="sr-only peer"
                                       {{ old('is_automatic') ? 'checked' : '' }}>
                                <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                            <div>
                                <span class="text-sm font-semibold text-gray-700">Automatic Promotion</span>
                                <p class="text-xs text-gray-400">Applied automatically without requiring a code</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="hidden" name="stackable" value="0">
                                <input type="checkbox" name="stackable" value="1" class="sr-only peer"
                                       {{ old('stackable') ? 'checked' : '' }}>
                                <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                            <div>
                                <span class="text-sm font-semibold text-gray-700">Stackable</span>
                                <p class="text-xs text-gray-400">Can be combined with other promotions</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" class="sr-only peer"
                                       {{ old('is_active', true) ? 'checked' : '' }}>
                                <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-500"></div>
                            </label>
                            <div>
                                <span class="text-sm font-semibold text-gray-700">Active</span>
                                <p class="text-xs text-gray-400">Promotion is live and can be used</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Form Actions --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6 flex flex-col sm:flex-row justify-between items-center gap-4">
            <a href="{{ route('admin.promotions.index') }}"
               class="w-full sm:w-auto px-5 py-2.5 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors text-center">
                <i class="fa-solid fa-arrow-left mr-1.5"></i> Back to List
            </a>
            <button type="submit"
                    class="w-full sm:w-auto px-6 py-2.5 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition-colors shadow-sm">
                <i class="fa-solid fa-check mr-1.5"></i> Create Promotion
            </button>
        </div>
    </form>
</div>

<script>
function promotionForm() {
    return {
        code: '{{ old('code') }}',
        discountType: 'percentage',
        appliesTo: 'all',
        productSearch: '',
        categorySearch: '',
        selectedProducts: @json(old('product_ids', [])),
        selectedCategories: @json(old('category_ids', [])),
        products: @json($products),
        categories: @json($categories),

        get suffix() {
            if (this.discountType === 'percentage') return '%';
            if (this.discountType === 'fixed') return 'fixed';
            return 'free shipping';
        },

        get filteredProducts() {
            if (!this.productSearch) return this.products;
            return this.products.filter(p =>
                p.name.toLowerCase().includes(this.productSearch.toLowerCase())
            );
        },

        get filteredCategories() {
            if (!this.categorySearch) return this.categories;
            return this.categories.filter(c =>
                c.name.toLowerCase().includes(this.categorySearch.toLowerCase())
            );
        },

        generateCode() {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            let result = '';
            for (let i = 0; i < 8; i++) {
                result += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            this.code = result;
            document.getElementById('code').value = result;
        }
    }
}
</script>
@endsection
