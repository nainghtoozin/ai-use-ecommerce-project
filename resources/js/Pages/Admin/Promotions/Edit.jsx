import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

function toDatetimeLocal(value) {
    if (!value) return '';
    const d = new Date(value);
    if (isNaN(d.getTime())) return '';
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

export default function PromotionEdit({ promotion, products, categories }) {
    const { data, setData, put, processing, errors } = useForm({
        name: promotion.name || '',
        code: promotion.code || '',
        description: promotion.description || '',
        type: promotion.type || 'percentage',
        value: promotion.value ?? 0,
        max_discount_amount: promotion.max_discount_amount ?? '',
        minimum_order_amount: promotion.minimum_order_amount ?? '',
        applies_to: promotion.applies_to || 'all',
        product_ids: (promotion.products || []).map(p => p.id),
        category_ids: (promotion.categories || []).map(c => c.id),
        starts_at: toDatetimeLocal(promotion.starts_at),
        ends_at: toDatetimeLocal(promotion.ends_at),
        usage_limit: promotion.usage_limit ?? '',
        per_customer_limit: promotion.per_customer_limit ?? '',
        priority: promotion.priority ?? 0,
        is_automatic: promotion.is_automatic ?? false,
        stackable: promotion.stackable ?? false,
        is_active: promotion.is_active ?? true,
    });

    const [productSearch, setProductSearch] = useState('');
    const [categorySearch, setCategorySearch] = useState('');

    const suffix = data.type === 'percentage' ? '%' : data.type === 'fixed' ? 'Fixed' : 'Free Shipping';

    const filteredProducts = productSearch
        ? products.filter(p => p.name.toLowerCase().includes(productSearch.toLowerCase()))
        : products;

    const filteredCategories = categorySearch
        ? categories.filter(c => c.name.toLowerCase().includes(categorySearch.toLowerCase()))
        : categories;

    function generateCode() {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        let result = '';
        for (let i = 0; i < 8; i++) {
            result += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        setData('code', result);
    }

    function handleSubmit(e) {
        e.preventDefault();
        put(`/admin/promotions/${promotion.id}`);
    }

    function toggleProduct(id) {
        setData('product_ids',
            data.product_ids.includes(id)
                ? data.product_ids.filter(pid => pid !== id)
                : [...data.product_ids, id]
        );
    }

    function toggleCategory(id) {
        setData('category_ids',
            data.category_ids.includes(id)
                ? data.category_ids.filter(cid => cid !== id)
                : [...data.category_ids, id]
        );
    }

    function inputClass(field) {
        return `w-full border ${errors[field] ? 'border-red-300' : 'border-gray-300'} rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors`;
    }

    return (
        <AdminLayout>
            <Head title={`Edit ${promotion.name}`} />
            <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div className="mb-6">
                    <Link href="/admin/promotions" className="text-sm text-blue-600 hover:underline">&larr; Back to Promotions</Link>
                    <h1 className="text-2xl font-bold text-gray-900 mt-2">Edit Promotion</h1>
                    <p className="text-sm text-gray-500 mt-1">Editing: <span className="font-medium">{promotion.name}</span></p>
                </div>

                {Object.keys(errors).length > 0 && (
                    <div className="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                        <p className="font-semibold text-sm mb-1">Please fix the following errors:</p>
                        <ul className="list-disc list-inside text-sm space-y-0.5">
                            {Object.values(errors).flat().map((msg, i) => (
                                <li key={i}>{msg}</li>
                            ))}
                        </ul>
                    </div>
                )}

                <form onSubmit={handleSubmit} className="space-y-6">

                    {/* Section 1: Basic Information */}
                    <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                        <div className="px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
                            <h3 className="text-base font-bold text-gray-800 flex items-center gap-2">
                                <svg className="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                Basic Information
                            </h3>
                        </div>
                        <div className="p-6 space-y-5">
                            <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
                                <div>
                                    <label htmlFor="name" className="block text-sm font-semibold text-gray-700 mb-1.5">
                                        Promotion Name <span className="text-red-500">*</span>
                                    </label>
                                    <input id="name" type="text" value={data.name} onChange={e => setData('name', e.target.value)}
                                        className={inputClass('name')} placeholder="e.g. Summer Sale 2026" required />
                                    {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
                                </div>
                                <div>
                                    <label htmlFor="code" className="block text-sm font-semibold text-gray-700 mb-1.5">
                                        Coupon Code
                                    </label>
                                    <div className="flex gap-2">
                                        <input id="code" type="text" value={data.code} onChange={e => setData('code', e.target.value)}
                                            className={`flex-1 ${inputClass('code')}`} placeholder="e.g. SUMMER20" />
                                        <button type="button" onClick={generateCode}
                                            className="px-4 py-2.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 transition-colors border border-gray-300 whitespace-nowrap">
                                            <svg className="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                                            Generate
                                        </button>
                                    </div>
                                    {errors.code && <p className="mt-1 text-xs text-red-600">{errors.code}</p>}
                                    <p className="text-xs text-gray-400 mt-1.5">Leave empty for automatic/no-code promotions</p>
                                </div>
                            </div>
                            <div>
                                <label htmlFor="description" className="block text-sm font-semibold text-gray-700 mb-1.5">Description</label>
                                <textarea id="description" value={data.description} onChange={e => setData('description', e.target.value)}
                                    rows={2} className={inputClass('description')} placeholder="Optional internal description" />
                                {errors.description && <p className="mt-1 text-xs text-red-600">{errors.description}</p>}
                            </div>
                        </div>
                    </div>

                    {/* Section 2: Discount Configuration */}
                    <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                        <div className="px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
                            <h3 className="text-base font-bold text-gray-800 flex items-center gap-2">
                                <svg className="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                Discount Configuration
                            </h3>
                        </div>
                        <div className="p-6 space-y-5">
                            <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
                                <div>
                                    <label htmlFor="type" className="block text-sm font-semibold text-gray-700 mb-1.5">
                                        Discount Type <span className="text-red-500">*</span>
                                    </label>
                                    <select id="type" value={data.type} onChange={e => setData('type', e.target.value)}
                                        className={inputClass('type')}>
                                        <option value="percentage">Percentage (%)</option>
                                        <option value="fixed">Fixed Amount</option>
                                        <option value="free_shipping">Free Shipping</option>
                                    </select>
                                    {errors.type && <p className="mt-1 text-xs text-red-600">{errors.type}</p>}
                                </div>
                                <div>
                                    <label htmlFor="value" className="block text-sm font-semibold text-gray-700 mb-1.5">
                                        Discount Value <span className="text-red-500">*</span>
                                    </label>
                                    <div className="relative">
                                        <input id="value" type="number" step="0.01" min="0" value={data.value}
                                            onChange={e => setData('value', e.target.value)}
                                            className={`${inputClass('value')} pr-16`} />
                                        <span className="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-gray-400 font-medium">{suffix}</span>
                                    </div>
                                    {errors.value && <p className="mt-1 text-xs text-red-600">{errors.value}</p>}
                                </div>
                                {data.type === 'percentage' && (
                                    <div>
                                        <label htmlFor="max_discount_amount" className="block text-sm font-semibold text-gray-700 mb-1.5">Max Discount Cap</label>
                                        <input id="max_discount_amount" type="number" step="0.01" min="0"
                                            value={data.max_discount_amount} onChange={e => setData('max_discount_amount', e.target.value)}
                                            className={inputClass('max_discount_amount')} placeholder="No cap" />
                                        {errors.max_discount_amount && <p className="mt-1 text-xs text-red-600">{errors.max_discount_amount}</p>}
                                    </div>
                                )}
                            </div>
                            <div>
                                <label htmlFor="minimum_order_amount" className="block text-sm font-semibold text-gray-700 mb-1.5">Minimum Order Amount</label>
                                <input id="minimum_order_amount" type="number" step="0.01" min="0"
                                    value={data.minimum_order_amount} onChange={e => setData('minimum_order_amount', e.target.value)}
                                    className={`max-w-xs ${inputClass('minimum_order_amount')}`} placeholder="No minimum" />
                                {errors.minimum_order_amount && <p className="mt-1 text-xs text-red-600">{errors.minimum_order_amount}</p>}
                                <p className="text-xs text-gray-400 mt-1">Cart subtotal must be at least this amount for the promotion to apply</p>
                            </div>
                        </div>
                    </div>

                    {/* Section 3: Applicability */}
                    <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                        <div className="px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
                            <h3 className="text-base font-bold text-gray-800 flex items-center gap-2">
                                <svg className="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                Applicability
                            </h3>
                        </div>
                        <div className="p-6 space-y-5">
                            <div>
                                <label htmlFor="applies_to" className="block text-sm font-semibold text-gray-700 mb-1.5">Applies To <span className="text-red-500">*</span></label>
                                <select id="applies_to" value={data.applies_to} onChange={e => setData('applies_to', e.target.value)}
                                    className={`max-w-xs ${inputClass('applies_to')}`}>
                                    <option value="all">All Products</option>
                                    <option value="products">Specific Products</option>
                                    <option value="categories">Specific Categories</option>
                                </select>
                                {errors.applies_to && <p className="mt-1 text-xs text-red-600">{errors.applies_to}</p>}
                            </div>

                            {data.applies_to === 'products' && (
                                <div>
                                    <label className="block text-sm font-semibold text-gray-700 mb-2">Select Products</label>
                                    <div className="border border-gray-200 rounded-lg overflow-hidden">
                                        <div className="p-2 border-b border-gray-100 bg-gray-50">
                                            <div className="relative">
                                                <svg className="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                                                <input type="text" value={productSearch} onChange={e => setProductSearch(e.target.value)}
                                                    className="w-full pl-8 pr-3 py-2 border border-gray-200 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                    placeholder="Search products..." />
                                            </div>
                                        </div>
                                        {data.product_ids.length > 0 && (
                                            <div className="p-2 border-b border-gray-100 bg-white">
                                                <div className="flex flex-wrap gap-1.5">
                                                    {data.product_ids.map(pid => {
                                                        const p = products.find(p => p.id === pid);
                                                        return (
                                                            <span key={pid} className="inline-flex items-center gap-1 px-2.5 py-1 bg-blue-50 text-blue-700 rounded-full text-xs font-medium">
                                                                {p?.name || pid}
                                                                <button type="button" onClick={() => toggleProduct(pid)} className="text-blue-400 hover:text-blue-700">&times;</button>
                                                            </span>
                                                        );
                                                    })}
                                                </div>
                                            </div>
                                        )}
                                        <div className="max-h-48 overflow-y-auto divide-y divide-gray-50">
                                            {filteredProducts.length === 0 ? (
                                                <p className="px-4 py-3 text-sm text-gray-400 text-center">No products found</p>
                                            ) : filteredProducts.map(product => (
                                                <label key={product.id} className="flex items-center gap-3 px-4 py-2.5 hover:bg-blue-50/50 cursor-pointer transition-colors">
                                                    <input type="checkbox" checked={data.product_ids.includes(product.id)}
                                                        onChange={() => toggleProduct(product.id)}
                                                        className="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500" />
                                                    <span className="text-sm text-gray-700">{product.name}</span>
                                                </label>
                                            ))}
                                        </div>
                                    </div>
                                    <p className="text-xs text-gray-400 mt-1.5">{data.product_ids.length} product(s) selected</p>
                                </div>
                            )}

                            {data.applies_to === 'categories' && (
                                <div>
                                    <label className="block text-sm font-semibold text-gray-700 mb-2">Select Categories</label>
                                    <div className="border border-gray-200 rounded-lg overflow-hidden">
                                        <div className="p-2 border-b border-gray-100 bg-gray-50">
                                            <div className="relative">
                                                <svg className="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                                                <input type="text" value={categorySearch} onChange={e => setCategorySearch(e.target.value)}
                                                    className="w-full pl-8 pr-3 py-2 border border-gray-200 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                    placeholder="Search categories..." />
                                            </div>
                                        </div>
                                        {data.category_ids.length > 0 && (
                                            <div className="p-2 border-b border-gray-100 bg-white">
                                                <div className="flex flex-wrap gap-1.5">
                                                    {data.category_ids.map(cid => {
                                                        const c = categories.find(c => c.id === cid);
                                                        return (
                                                            <span key={cid} className="inline-flex items-center gap-1 px-2.5 py-1 bg-purple-50 text-purple-700 rounded-full text-xs font-medium">
                                                                {c?.name || cid}
                                                                <button type="button" onClick={() => toggleCategory(cid)} className="text-purple-400 hover:text-purple-700">&times;</button>
                                                            </span>
                                                        );
                                                    })}
                                                </div>
                                            </div>
                                        )}
                                        <div className="max-h-48 overflow-y-auto divide-y divide-gray-50">
                                            {filteredCategories.length === 0 ? (
                                                <p className="px-4 py-3 text-sm text-gray-400 text-center">No categories found</p>
                                            ) : filteredCategories.map(category => (
                                                <label key={category.id} className="flex items-center gap-3 px-4 py-2.5 hover:bg-purple-50/50 cursor-pointer transition-colors">
                                                    <input type="checkbox" checked={data.category_ids.includes(category.id)}
                                                        onChange={() => toggleCategory(category.id)}
                                                        className="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500" />
                                                    <span className="text-sm text-gray-700">{category.name}</span>
                                                </label>
                                            ))}
                                        </div>
                                    </div>
                                    <p className="text-xs text-gray-400 mt-1.5">{data.category_ids.length} category(ies) selected</p>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Section 4: Schedule */}
                    <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                        <div className="px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
                            <h3 className="text-base font-bold text-gray-800 flex items-center gap-2">
                                <svg className="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                Schedule
                            </h3>
                        </div>
                        <div className="p-6">
                            <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
                                <div>
                                    <label htmlFor="starts_at" className="block text-sm font-semibold text-gray-700 mb-1.5">Start Date</label>
                                    <input id="starts_at" type="datetime-local" value={data.starts_at}
                                        onChange={e => setData('starts_at', e.target.value)} className={inputClass('starts_at')} />
                                    {errors.starts_at && <p className="mt-1 text-xs text-red-600">{errors.starts_at}</p>}
                                </div>
                                <div>
                                    <label htmlFor="ends_at" className="block text-sm font-semibold text-gray-700 mb-1.5">End Date</label>
                                    <input id="ends_at" type="datetime-local" value={data.ends_at}
                                        onChange={e => setData('ends_at', e.target.value)} className={inputClass('ends_at')} />
                                    {errors.ends_at && <p className="mt-1 text-xs text-red-600">{errors.ends_at}</p>}
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Section 5: Usage Limits */}
                    <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                        <div className="px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
                            <h3 className="text-base font-bold text-gray-800 flex items-center gap-2">
                                <svg className="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                                Usage Limits
                            </h3>
                        </div>
                        <div className="p-6">
                            <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
                                <div>
                                    <label htmlFor="usage_limit" className="block text-sm font-semibold text-gray-700 mb-1.5">Total Usage Limit</label>
                                    <input id="usage_limit" type="number" min="1" value={data.usage_limit}
                                        onChange={e => setData('usage_limit', e.target.value)}
                                        className={inputClass('usage_limit')} placeholder="Unlimited" />
                                    {errors.usage_limit && <p className="mt-1 text-xs text-red-600">{errors.usage_limit}</p>}
                                    <p className="text-xs text-gray-400 mt-1">Maximum number of times this promotion can be used</p>
                                </div>
                                <div>
                                    <label htmlFor="per_customer_limit" className="block text-sm font-semibold text-gray-700 mb-1.5">Per-Customer Limit</label>
                                    <input id="per_customer_limit" type="number" min="1" value={data.per_customer_limit}
                                        onChange={e => setData('per_customer_limit', e.target.value)}
                                        className={inputClass('per_customer_limit')} placeholder="Unlimited" />
                                    {errors.per_customer_limit && <p className="mt-1 text-xs text-red-600">{errors.per_customer_limit}</p>}
                                    <p className="text-xs text-gray-400 mt-1">How many times a single customer can use this</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Section 6: Advanced Settings */}
                    <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                        <div className="px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
                            <h3 className="text-base font-bold text-gray-800 flex items-center gap-2">
                                <svg className="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                Advanced Settings
                            </h3>
                        </div>
                        <div className="p-6 space-y-5">
                            <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
                                <div>
                                    <label htmlFor="priority" className="block text-sm font-semibold text-gray-700 mb-1.5">Priority</label>
                                    <input id="priority" type="number" min="0" value={data.priority}
                                        onChange={e => setData('priority', e.target.value)}
                                        className={`max-w-xs ${inputClass('priority')}`} />
                                    {errors.priority && <p className="mt-1 text-xs text-red-600">{errors.priority}</p>}
                                    <p className="text-xs text-gray-400 mt-1">Higher values are evaluated first. Only applies to automatic promotions.</p>
                                </div>
                                <div className="flex flex-col justify-end gap-3">
                                    <label className="flex items-center gap-3 cursor-pointer">
                                        <div className="relative">
                                            <input type="checkbox" checked={data.is_automatic} onChange={e => setData('is_automatic', e.target.checked)}
                                                className="sr-only peer" />
                                            <div className="w-9 h-5 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                                        </div>
                                        <div>
                                            <span className="text-sm font-semibold text-gray-700">Automatic Promotion</span>
                                            <p className="text-xs text-gray-400">Applied automatically without requiring a code</p>
                                        </div>
                                    </label>
                                    <label className="flex items-center gap-3 cursor-pointer">
                                        <div className="relative">
                                            <input type="checkbox" checked={data.stackable} onChange={e => setData('stackable', e.target.checked)}
                                                className="sr-only peer" />
                                            <div className="w-9 h-5 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                                        </div>
                                        <div>
                                            <span className="text-sm font-semibold text-gray-700">Stackable</span>
                                            <p className="text-xs text-gray-400">Can be combined with other promotions</p>
                                        </div>
                                    </label>
                                    <label className="flex items-center gap-3 cursor-pointer">
                                        <div className="relative">
                                            <input type="checkbox" checked={data.is_active} onChange={e => setData('is_active', e.target.checked)}
                                                className="sr-only peer" />
                                            <div className="w-9 h-5 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-500"></div>
                                        </div>
                                        <div>
                                            <span className="text-sm font-semibold text-gray-700">Active</span>
                                            <p className="text-xs text-gray-400">Promotion is live and can be used</p>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Form Actions */}
                    <div className="bg-white rounded-xl border border-gray-200 p-4 sm:p-6 flex flex-col sm:flex-row justify-between items-center gap-4">
                        <Link href="/admin/promotions"
                            className="w-full sm:w-auto px-5 py-2.5 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors text-center">
                            <svg className="w-4 h-4 inline mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                            Back to List
                        </Link>
                        <button type="submit" disabled={processing}
                            className="w-full sm:w-auto px-6 py-2.5 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition-colors shadow-sm disabled:opacity-50">
                            {processing ? (
                                <span className="flex items-center gap-2 justify-center">
                                    <svg className="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" /><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" /></svg>
                                    Updating...
                                </span>
                            ) : (
                                <span><svg className="w-4 h-4 inline mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" /></svg> Update Promotion</span>
                            )}
                        </button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}
