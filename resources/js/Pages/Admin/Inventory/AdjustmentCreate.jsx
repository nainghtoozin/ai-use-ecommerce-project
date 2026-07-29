import { useState, useEffect, useRef } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { ArrowLeft, ArrowUpRight, ArrowDownRight, Search, Package, CheckCircle, AlertTriangle, XCircle, Building2 } from 'lucide-react';
import { adminUrl } from '@/Utils/adminUrl';

const REASON_OTHER = 'other';

const stockBadge = (stock, threshold = 5) => {
    if (stock <= 0) return { label: 'Out of Stock', class: 'bg-red-50 text-red-700 border-red-200', icon: XCircle };
    if (stock <= threshold) return { label: 'Low Stock', class: 'bg-amber-50 text-amber-700 border-amber-200', icon: AlertTriangle };
    return { label: 'In Stock', class: 'bg-green-50 text-green-700 border-green-200', icon: CheckCircle };
};

export default function AdjustmentCreate({ products = [], warehouses = [], reasons = {} }) {
    const { errors } = usePage().props;

    const defaultWarehouse = warehouses.find(w => w.is_default) || warehouses[0];
    const hasMultipleLocations = warehouses.length > 1;

    const [form, setForm] = useState({
        product_id: '',
        variant_id: '',
        warehouse_id: defaultWarehouse?.id?.toString() || '',
        type: 'increase',
        quantity: '',
        reason: '',
        custom_reason: '',
        reference: '',
        notes: '',
    });

    const [productSearch, setProductSearch] = useState('');
    const [showProductDropdown, setShowProductDropdown] = useState(false);
    const [currentStock, setCurrentStock] = useState(null);
    const [processing, setProcessing] = useState(false);
    const [showConfirm, setShowConfirm] = useState(false);

    const searchRef = useRef(null);
    const selectedProduct = products.find(p => p.id.toString() === form.product_id);

    const filteredProducts = productSearch.length > 0
        ? products.filter(p =>
            p.name.toLowerCase().includes(productSearch.toLowerCase()) ||
            (p.sku && p.sku.toLowerCase().includes(productSearch.toLowerCase()))
        ).slice(0, 20)
        : [];

    useEffect(() => {
        const handleClickOutside = (e) => {
            if (searchRef.current && !searchRef.current.contains(e.target)) {
                setShowProductDropdown(false);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const handleProductSelect = (product) => {
        setForm({ ...form, product_id: String(product.id), variant_id: '' });
        setProductSearch(product.name);
        setShowProductDropdown(false);

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        fetch(adminUrl('/admin/inventory/adjustments/preview'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            },
            body: JSON.stringify({
                product_id: String(product.id),
                type: 'increase',
                quantity: 1,
            }),
        })
            .then(res => res.json())
            .then(data => {
                setCurrentStock(data.current_stock ?? 0);
            })
            .catch(() => {
                setCurrentStock(0);
            });
    };

    const handleTypeChange = (type) => {
        setForm({ ...form, type });
    };

    const handleQuantityChange = (value) => {
        setForm({ ...form, quantity: value });
    };

    const parsedQuantity = parseFloat(form.quantity) || 0;
    const computedNewStock = currentStock !== null
        ? max(0, form.type === 'increase'
            ? currentStock + parsedQuantity
            : currentStock - parsedQuantity)
        : null;

    const canSubmit = form.product_id && form.quantity && parsedQuantity > 0 && form.reason && (form.reason !== REASON_OTHER || form.custom_reason.trim());

    const handleSubmit = () => {
        if (!canSubmit) return;
        setShowConfirm(true);
    };

    const confirmSubmit = () => {
        setProcessing(true);
        setShowConfirm(false);

        const payload = { ...form };
        if (form.reason === REASON_OTHER) {
            payload.reason = 'other';
            payload.notes = form.custom_reason + (form.notes ? '\n' + form.notes : '');
        }

        router.post(adminUrl('/admin/inventory/adjustments'), payload, {
            onFinish: () => setProcessing(false),
        });
    };

    const badge = selectedProduct && currentStock !== null ? stockBadge(currentStock) : null;

    return (
        <AdminLayout>
            <Head title="New Stock Adjustment" />

            <div className="py-6">
                <div className="px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center justify-between mb-6">
                        <div className="flex items-center gap-3">
                            <Link href={adminUrl('/admin/inventory/adjustments')} className="text-gray-400 hover:text-gray-600 dark:text-gray-400">
                                <ArrowLeft className="w-5 h-5" />
                            </Link>
                            <div>
                                <h1 className="text-2xl font-semibold text-gray-900 dark:text-gray-100">New Stock Adjustment</h1>
                                <p className="text-sm text-gray-500 dark:text-gray-400">Correct stock levels by increasing or decreasing inventory.</p>
                            </div>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
                        <div className="xl:col-span-2 space-y-6">
                            {/* Product Selection */}
                            <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6">
                                <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4">Product</h2>

                                <div className="relative" ref={searchRef}>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                        Search Product <span className="text-red-500">*</span>
                                    </label>
                                    <div className="relative">
                                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" />
                                        <input
                                            type="text"
                                            value={productSearch}
                                            onChange={(e) => {
                                                setProductSearch(e.target.value);
                                                setShowProductDropdown(true);
                                                if (!e.target.value) {
                                                    setForm({ ...form, product_id: '', variant_id: '' });
                                                    setCurrentStock(null);
                                                }
                                            }}
                                            onFocus={() => setShowProductDropdown(true)}
                                            placeholder="Search by product name or SKU..."
                                            className="w-full pl-10 pr-4 py-2.5 border border-gray-300 dark:border-gray-700 rounded-lg text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                        />
                                    </div>

                                    {showProductDropdown && filteredProducts.length > 0 && (
                                        <div className="absolute z-20 mt-1 w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg shadow-lg max-h-64 overflow-y-auto">
                                            {filteredProducts.map((p) => (
                                                <button
                                                    key={p.id}
                                                    type="button"
                                                    onClick={() => handleProductSelect(p)}
                                                    className="w-full px-4 py-2.5 text-left hover:bg-gray-50 dark:bg-gray-950 flex items-center justify-between border-b border-gray-100 dark:border-gray-800 last:border-0"
                                                >
                                                    <div>
                                                        <div className="text-sm font-medium text-gray-900 dark:text-gray-100">{p.name}</div>
                                                        {p.sku && <div className="text-xs text-gray-400 dark:text-gray-500 font-mono">{p.sku}</div>}
                                                    </div>
                                                    <span className="text-xs text-gray-400 dark:text-gray-500 capitalize">{p.type}</span>
                                                </button>
                                            ))}
                                        </div>
                                    )}
                                    {errors.product_id && <p className="mt-1 text-xs text-red-600">{errors.product_id}</p>}
                                </div>

                                {/* Product Preview */}
                                {selectedProduct && currentStock !== null && (
                                    <div className="mt-4 p-4 bg-gray-50 dark:bg-gray-950 rounded-lg border border-gray-200 dark:border-gray-800">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-3">
                                                <div className="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
                                                    <Package className="w-5 h-5 text-blue-600" />
                                                </div>
                                                <div>
                                                    <div className="text-sm font-semibold text-gray-900 dark:text-gray-100">{selectedProduct.name}</div>
                                                    <div className="text-xs text-gray-500 dark:text-gray-400 font-mono">{selectedProduct.sku || 'No SKU'}</div>
                                                </div>
                                            </div>
                                            <div className="text-right">
                                                <div className="text-2xl font-bold text-gray-900 dark:text-gray-100">{Math.round(currentStock)}</div>
                                                <div className="text-xs text-gray-500 dark:text-gray-400">current stock</div>
                                            </div>
                                        </div>
                                        {badge && (
                                            <div className="mt-3 flex items-center justify-between">
                                                <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium border ${badge.class}`}>
                                                    <badge.icon className="w-3 h-3" />
                                                    {badge.label}
                                                </span>
                                                {defaultWarehouse && (
                                                    <span className="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
                                                        <Building2 className="w-3 h-3" />
                                                        {defaultWarehouse.name}
                                                    </span>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                )}
                            </div>

                            {/* Adjustment Details */}
                            <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6">
                                <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4">Adjustment</h2>

                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Type <span className="text-red-500">*</span></label>
                                        <div className="grid grid-cols-2 gap-2">
                                            <button
                                                type="button"
                                                onClick={() => handleTypeChange('increase')}
                                                className={`flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg border text-sm font-medium transition-colors ${
                                                    form.type === 'increase'
                                                        ? 'bg-green-50 border-green-300 text-green-700'
                                                        : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'
                                                }`}
                                            >
                                                <ArrowUpRight className="w-4 h-4" />
                                                Increase
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => handleTypeChange('decrease')}
                                                className={`flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg border text-sm font-medium transition-colors ${
                                                    form.type === 'decrease'
                                                        ? 'bg-red-50 border-red-300 text-red-700'
                                                        : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'
                                                }`}
                                            >
                                                <ArrowDownRight className="w-4 h-4" />
                                                Decrease
                                            </button>
                                        </div>
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Quantity <span className="text-red-500">*</span></label>
                                        <input
                                            type="number"
                                            value={form.quantity}
                                            onChange={(e) => handleQuantityChange(e.target.value)}
                                            placeholder="0"
                                            min="0.01"
                                            step="0.01"
                                            className="w-full rounded-lg border border-gray-300 dark:border-gray-700 px-3 py-2.5 text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                        />
                                        {errors.quantity && <p className="mt-1 text-xs text-red-600">{errors.quantity}</p>}
                                    </div>
                                </div>

                                {/* Inventory Location */}
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Inventory Location</label>
                                    {hasMultipleLocations ? (
                                        <select
                                            value={form.warehouse_id}
                                            onChange={(e) => setForm({ ...form, warehouse_id: e.target.value })}
                                            className="w-full rounded-lg border border-gray-300 dark:border-gray-700 px-3 py-2.5 text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                        >
                                            {warehouses.map((wh) => (
                                                <option key={wh.id} value={wh.id}>
                                                    {wh.name}{wh.code ? ` (${wh.code})` : ''}{wh.is_default ? ' — Primary' : ''}
                                                </option>
                                            ))}
                                        </select>
                                    ) : (
                                        <div>
                                            <div className="w-full rounded-lg border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 px-3 py-2.5 text-sm text-gray-700 dark:text-gray-300">
                                                {defaultWarehouse?.name || 'Primary Store'}
                                            </div>
                                            <input type="hidden" name="warehouse_id" value={form.warehouse_id || defaultWarehouse?.id || ''} />
                                            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">Opening stock will be assigned to your primary store.</p>
                                        </div>
                                    )}
                                </div>

                                {/* Stock Preview — computed client-side */}
                                {currentStock !== null && parsedQuantity > 0 && (
                                    <div className="bg-gray-50 dark:bg-gray-950 rounded-lg p-4 grid grid-cols-3 gap-4 text-center border border-gray-200 dark:border-gray-800">
                                        <div>
                                            <div className="text-xs text-gray-500 dark:text-gray-400 mb-1">Current</div>
                                            <div className="text-xl font-bold text-gray-900 dark:text-gray-100">{Math.round(currentStock)}</div>
                                        </div>
                                        <div>
                                            <div className="text-xs text-gray-500 dark:text-gray-400 mb-1">Change</div>
                                            <div className={`text-xl font-bold ${form.type === 'increase' ? 'text-green-600' : 'text-red-600'}`}>
                                                {form.type === 'increase' ? '+' : '-'}{form.quantity}
                                            </div>
                                        </div>
                                        <div>
                                            <div className="text-xs text-gray-500 dark:text-gray-400 mb-1">New Stock</div>
                                            <div className="text-xl font-bold text-gray-900 dark:text-gray-100">{Math.round(computedNewStock)}</div>
                                        </div>
                                    </div>
                                )}
                            </div>

                            {/* Reason & Details */}
                            <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6">
                                <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4">Reason & Details</h2>

                                <div className="space-y-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Reason <span className="text-red-500">*</span></label>
                                        <select
                                            value={form.reason}
                                            onChange={(e) => setForm({ ...form, reason: e.target.value, custom_reason: '' })}
                                            className="w-full rounded-lg border border-gray-300 dark:border-gray-700 px-3 py-2.5 text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                        >
                                            <option value="">Select reason</option>
                                            {Object.entries(reasons).map(([key, label]) => (
                                                <option key={key} value={key}>{label}</option>
                                            ))}
                                        </select>
                                        {errors.reason && <p className="mt-1 text-xs text-red-600">{errors.reason}</p>}
                                    </div>

                                    {form.reason === REASON_OTHER && (
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Custom Reason <span className="text-red-500">*</span></label>
                                            <input
                                                type="text"
                                                value={form.custom_reason}
                                                onChange={(e) => setForm({ ...form, custom_reason: e.target.value })}
                                                placeholder="Enter custom reason..."
                                                className="w-full rounded-lg border border-gray-300 dark:border-gray-700 px-3 py-2.5 text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                            />
                                        </div>
                                    )}

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Reference</label>
                                        <input
                                            type="text"
                                            value={form.reference}
                                            onChange={(e) => setForm({ ...form, reference: e.target.value })}
                                            placeholder="Invoice number, PO number, etc."
                                            className="w-full rounded-lg border border-gray-300 dark:border-gray-700 px-3 py-2.5 text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Notes</label>
                                        <textarea
                                            value={form.notes}
                                            onChange={(e) => setForm({ ...form, notes: e.target.value })}
                                            placeholder="Additional details about this adjustment..."
                                            rows={3}
                                            className="w-full rounded-lg border border-gray-300 dark:border-gray-700 px-3 py-2.5 text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Sidebar Summary */}
                        <div className="xl:col-span-1">
                            <div className="sticky top-4 space-y-6">
                                <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6">
                                    <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4">Adjustment Summary</h2>

                                    {!selectedProduct ? (
                                        <div className="text-center py-8 text-gray-400 dark:text-gray-500">
                                            <Package className="w-8 h-8 mx-auto mb-2 text-gray-300" />
                                            <p className="text-sm">Select a product to begin</p>
                                        </div>
                                    ) : (
                                        <div className="space-y-4">
                                            <div>
                                                <div className="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Product</div>
                                                <div className="text-sm font-medium text-gray-900 dark:text-gray-100">{selectedProduct.name}</div>
                                                {selectedProduct.sku && <div className="text-xs text-gray-400 dark:text-gray-500 font-mono">{selectedProduct.sku}</div>}
                                            </div>

                                            <div className="border-t border-gray-100 dark:border-gray-800 pt-3">
                                                <div className="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Type</div>
                                                <div className={`text-sm font-medium flex items-center gap-1 ${form.type === 'increase' ? 'text-green-600' : 'text-red-600'}`}>
                                                    {form.type === 'increase' ? <ArrowUpRight className="w-3.5 h-3.5" /> : <ArrowDownRight className="w-3.5 h-3.5" />}
                                                    {form.type === 'increase' ? 'Increase' : 'Decrease'}
                                                </div>
                                            </div>

                                            {form.quantity && (
                                                <div className="border-t border-gray-100 dark:border-gray-800 pt-3">
                                                    <div className="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Quantity</div>
                                                    <div className="text-sm font-medium text-gray-900 dark:text-gray-100">{form.quantity} units</div>
                                                </div>
                                            )}

                                            <div className="border-t border-gray-100 dark:border-gray-800 pt-3">
                                                <div className="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Location</div>
                                                <div className="text-sm font-medium text-gray-900 dark:text-gray-100">{defaultWarehouse?.name || 'Primary Store'}</div>
                                            </div>

                                            {currentStock !== null && parsedQuantity > 0 && (
                                                <div className="border-t border-gray-100 dark:border-gray-800 pt-3">
                                                    <div className="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Stock Change</div>
                                                    <div className="flex items-center gap-2">
                                                        <span className="text-sm text-gray-700 dark:text-gray-300">{Math.round(currentStock)}</span>
                                                        <span className="text-gray-400 dark:text-gray-500">→</span>
                                                        <span className="text-sm font-semibold text-gray-900 dark:text-gray-100">{Math.round(computedNewStock)}</span>
                                                    </div>
                                                </div>
                                            )}

                                            {form.reason && (
                                                <div className="border-t border-gray-100 dark:border-gray-800 pt-3">
                                                    <div className="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Reason</div>
                                                    <div className="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                        {form.reason === REASON_OTHER ? form.custom_reason || 'Other' : reasons[form.reason]}
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    )}
                                </div>

                                <div className="space-y-3">
                                    <button
                                        type="button"
                                        onClick={handleSubmit}
                                        disabled={!canSubmit || processing}
                                        className="w-full flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        {processing ? 'Saving...' : 'Save Adjustment'}
                                    </button>
                                    <Link
                                        href={adminUrl('/admin/inventory/adjustments')}
                                        className="w-full flex items-center justify-center px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg hover:bg-gray-50"
                                    >
                                        Cancel
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Confirmation Dialog */}
            {showConfirm && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                    <div className="bg-white dark:bg-gray-900 rounded-xl shadow-xl max-w-md w-full mx-4 p-6">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Confirm Adjustment</h3>

                        <div className="space-y-3 mb-6">
                            <div className="flex justify-between text-sm">
                                <span className="text-gray-500 dark:text-gray-400">Product</span>
                                <span className="font-medium text-gray-900 dark:text-gray-100">{selectedProduct?.name}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-gray-500 dark:text-gray-400">Type</span>
                                <span className={`font-medium ${form.type === 'increase' ? 'text-green-600' : 'text-red-600'}`}>
                                    {form.type === 'increase' ? 'Increase' : 'Decrease'}
                                </span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-gray-500 dark:text-gray-400">Quantity</span>
                                <span className="font-medium text-gray-900 dark:text-gray-100">{form.quantity} units</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-gray-500 dark:text-gray-400">Location</span>
                                <span className="font-medium text-gray-900 dark:text-gray-100">{defaultWarehouse?.name || 'Primary Store'}</span>
                            </div>
                            {currentStock !== null && (
                                <div className="flex justify-between text-sm">
                                    <span className="text-gray-500 dark:text-gray-400">Stock</span>
                                    <span className="font-medium text-gray-900 dark:text-gray-100">
                                        {Math.round(currentStock)} → {Math.round(computedNewStock)}
                                    </span>
                                </div>
                            )}
                            <div className="flex justify-between text-sm">
                                <span className="text-gray-500 dark:text-gray-400">Reason</span>
                                <span className="font-medium text-gray-900 dark:text-gray-100">
                                    {form.reason === REASON_OTHER ? form.custom_reason : reasons[form.reason]}
                                </span>
                            </div>
                        </div>

                        <div className="flex items-center gap-3">
                            <button
                                type="button"
                                onClick={() => setShowConfirm(false)}
                                className="flex-1 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg hover:bg-gray-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                onClick={confirmSubmit}
                                disabled={processing}
                                className="flex-1 px-4 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50"
                            >
                                {processing ? 'Saving...' : 'Confirm'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}

function max(a, b) {
    return a > b ? a : b;
}
