import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';

function formatCurrency(amount) {
    return Number(amount).toFixed(2);
}

export default function FlashSaleCreate({ products }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        description: '',
        discount_type: 'percentage',
        discount_value: 0,
        max_discount_amount: '',
        starts_at: '',
        ends_at: '',
        is_active: true,
        priority: 0,
        usage_limit: '',
        products: [],
    });

    const [productSearch, setProductSearch] = useState('');

    const filteredProducts = productSearch
        ? products.filter(p => p.name.toLowerCase().includes(productSearch.toLowerCase()))
        : products;

    const suffix = data.discount_type === 'percentage' ? '%' : 'Fixed';

    function addProduct(product) {
        if (data.products.some(p => p.product_id === product.id)) return;
        setData('products', [...data.products, {
            product_id: product.id,
            product_name: product.name,
            original_price: product.price,
            variant_id: null,
            flash_price: product.price,
            quantity_limit: '',
        }]);
    }

    function removeProduct(productId) {
        setData('products', data.products.filter(p => p.product_id !== productId));
    }

    function updateProduct(productId, field, value) {
        setData('products', data.products.map(p =>
            p.product_id === productId ? { ...p, [field]: value } : p
        ));
    }

    function inputClass(field) {
        return `w-full border ${errors[field] ? 'border-red-300' : 'border-gray-300'} rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors`;
    }

    function handleSubmit(e) {
        e.preventDefault();
        post(adminUrl('/admin/flash-sales'));
    }

    return (
        <AdminLayout>
            <Head title="Create Flash Sale" />
            <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div className="mb-6">
                    <Link href={adminUrl('/admin/flash-sales')} className="text-sm text-blue-600 hover:underline">&larr; Back to Flash Sales</Link>
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-2">Create Flash Sale</h1>
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
                    <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden">
                        <div className="px-6 py-4 border-b border-gray-100 dark:border-gray-800 bg-gradient-to-r from-gray-50 to-white">
                            <h3 className="text-base font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2">
                                <svg className="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                Basic Information
                            </h3>
                        </div>
                        <div className="p-6 space-y-5">
                            <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
                                <div>
                                    <label htmlFor="name" className="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                                        Flash Sale Name <span className="text-red-500">*</span>
                                    </label>
                                    <input id="name" type="text" value={data.name} onChange={e => setData('name', e.target.value)}
                                        className={inputClass('name')} placeholder="e.g. Weekend Flash Sale" required />
                                </div>
                                <div>
                                    <label htmlFor="discount_type" className="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                                        Discount Type <span className="text-red-500">*</span>
                                    </label>
                                    <select id="discount_type" value={data.discount_type} onChange={e => setData('discount_type', e.target.value)}
                                        className={inputClass('discount_type')}>
                                        <option value="percentage">Percentage (%)</option>
                                        <option value="fixed">Fixed Amount</option>
                                    </select>
                                </div>
                            </div>
                            <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
                                <div>
                                    <label htmlFor="discount_value" className="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                                        Discount Value <span className="text-red-500">*</span>
                                    </label>
                                    <div className="relative">
                                        <input id="discount_value" type="number" step="0.01" min="0" value={data.discount_value}
                                            onChange={e => setData('discount_value', e.target.value)}
                                            className={`${inputClass('discount_value')} pr-16`} />
                                        <span className="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-gray-400 font-medium">{suffix}</span>
                                    </div>
                                </div>
                                {data.discount_type === 'percentage' && (
                                    <div>
                                        <label htmlFor="max_discount_amount" className="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Max Discount Cap</label>
                                        <input id="max_discount_amount" type="number" step="0.01" min="0"
                                            value={data.max_discount_amount} onChange={e => setData('max_discount_amount', e.target.value)}
                                            className={inputClass('max_discount_amount')} placeholder="No cap" />
                                    </div>
                                )}
                            </div>
                            <div>
                                <label htmlFor="description" className="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Description</label>
                                <textarea id="description" value={data.description} onChange={e => setData('description', e.target.value)}
                                    rows={2} className={inputClass('description')} placeholder="Optional description" />
                            </div>
                        </div>
                    </div>

                    <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden">
                        <div className="px-6 py-4 border-b border-gray-100 dark:border-gray-800 bg-gradient-to-r from-gray-50 to-white">
                            <h3 className="text-base font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2">
                                <svg className="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                Schedule &amp; Limits
                            </h3>
                        </div>
                        <div className="p-6 space-y-5">
                            <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
                                <div>
                                    <label htmlFor="starts_at" className="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Start Date</label>
                                    <input id="starts_at" type="datetime-local" value={data.starts_at}
                                        onChange={e => setData('starts_at', e.target.value)} className={inputClass('starts_at')} />
                                </div>
                                <div>
                                    <label htmlFor="ends_at" className="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">End Date</label>
                                    <input id="ends_at" type="datetime-local" value={data.ends_at}
                                        onChange={e => setData('ends_at', e.target.value)} className={inputClass('ends_at')} />
                                </div>
                            </div>
                            <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
                                <div>
                                    <label htmlFor="usage_limit" className="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Total Usage Limit</label>
                                    <input id="usage_limit" type="number" min="1" value={data.usage_limit}
                                        onChange={e => setData('usage_limit', e.target.value)}
                                        className={inputClass('usage_limit')} placeholder="Unlimited" />
                                </div>
                                <div>
                                    <label htmlFor="priority" className="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Priority</label>
                                    <input id="priority" type="number" min="0" value={data.priority}
                                        onChange={e => setData('priority', e.target.value)}
                                        className={inputClass('priority')} />
                                </div>
                                <div className="flex items-end">
                                    <label className="flex items-center gap-3 cursor-pointer">
                                        <div className="relative">
                                            <input type="checkbox" checked={data.is_active} onChange={e => setData('is_active', e.target.checked)}
                                                className="sr-only peer" />
                                            <div className="w-9 h-5 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-500"></div>
                                        </div>
                                        <span className="text-sm font-semibold text-gray-700 dark:text-gray-300">Active</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden">
                        <div className="px-6 py-4 border-b border-gray-100 dark:border-gray-800 bg-gradient-to-r from-gray-50 to-white">
                            <h3 className="text-base font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2">
                                <svg className="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                                Products ({data.products.length})
                            </h3>
                        </div>
                        <div className="p-6 space-y-4">
                            <div>
                                <label className="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Add Products</label>
                                <div className="border border-gray-200 dark:border-gray-800 rounded-lg overflow-hidden">
                                    <div className="p-2 border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-950">
                                        <div className="relative">
                                            <svg className="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                                            <input type="text" value={productSearch} onChange={e => setProductSearch(e.target.value)}
                                                className="w-full pl-8 pr-3 py-2 border border-gray-200 dark:border-gray-800 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                placeholder="Search products to add..." />
                                        </div>
                                    </div>
                                    <div className="max-h-48 overflow-y-auto divide-y divide-gray-50">
                                        {filteredProducts.length === 0 ? (
                                            <p className="px-4 py-3 text-sm text-gray-400 text-center">No products found</p>
                                        ) : filteredProducts.map(product => {
                                            const added = data.products.some(p => p.product_id === product.id);
                                            return (
                                                <button key={product.id} type="button" onClick={() => addProduct(product)}
                                                    disabled={added}
                                                    className={`w-full flex items-center justify-between px-4 py-2.5 text-left transition-colors ${added ? 'bg-gray-50 dark:bg-gray-950 opacity-50 cursor-not-allowed' : 'hover:bg-purple-50/50 cursor-pointer'}`}>
                                                    <span className="text-sm text-gray-700 dark:text-gray-300">{product.name}</span>
                                                    <span className="text-xs text-gray-400">{formatCurrency(product.price)}</span>
                                                </button>
                                            );
                                        })}
                                    </div>
                                </div>
                            </div>

                            {data.products.length > 0 && (
                                <div className="space-y-3">
                                    {data.products.map((item) => (
                                        <div key={item.product_id} className="flex flex-col sm:flex-row items-start sm:items-center gap-3 p-4 bg-gray-50 dark:bg-gray-950 rounded-lg border border-gray-100 dark:border-gray-800">
                                            <div className="flex-1 min-w-0">
                                                <p className="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">{item.product_name}</p>
                                                <p className="text-xs text-gray-400">Original: {formatCurrency(item.original_price)}</p>
                                            </div>
                                            <div className="flex items-center gap-3 flex-wrap">
                                                <div>
                                                    <label className="block text-xs text-gray-500 mb-0.5">Flash Price</label>
                                                    <input type="number" step="0.01" min="0" value={item.flash_price}
                                                        onChange={e => updateProduct(item.product_id, 'flash_price', e.target.value)}
                                                        className="w-28 border border-gray-300 dark:border-gray-700 rounded-md px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500" />
                                                </div>
                                                <div>
                                                    <label className="block text-xs text-gray-500 mb-0.5">Qty Limit</label>
                                                    <input type="number" min="1" value={item.quantity_limit}
                                                        onChange={e => updateProduct(item.product_id, 'quantity_limit', e.target.value)}
                                                        className="w-24 border border-gray-300 dark:border-gray-700 rounded-md px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500"
                                                        placeholder="None" />
                                                </div>
                                                <button type="button" onClick={() => removeProduct(item.product_id)}
                                                    className="mt-4 sm:mt-0 p-1.5 text-gray-400 hover:text-red-600 rounded-md hover:bg-red-50 transition-colors">
                                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>
                                                </button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-4 sm:p-6 flex flex-col sm:flex-row justify-between items-center gap-4">
                        <Link href={adminUrl('/admin/flash-sales')}
                            className="w-full sm:w-auto px-5 py-2.5 border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors text-center">
                            <svg className="w-4 h-4 inline mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                            Back to List
                        </Link>
                        <button type="submit" disabled={processing || data.products.length === 0}
                            className="w-full sm:w-auto px-6 py-2.5 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition-colors shadow-sm disabled:opacity-50">
                            {processing ? 'Creating...' : 'Create Flash Sale'}
                        </button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}
