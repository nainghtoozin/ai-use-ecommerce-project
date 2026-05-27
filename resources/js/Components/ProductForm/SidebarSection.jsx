import { Lock, Eye, Trash2, Package, Layers, Gift } from 'lucide-react';
import FormSelect from './FormSelect';

const STATUS_CONFIG = {
    in_stock: {
        bg: 'bg-green-100',
        text: 'text-green-700',
        ring: 'ring-green-600/20',
        dot: 'bg-green-500',
        label: 'In Stock',
    },
    low_stock: {
        bg: 'bg-amber-100',
        text: 'text-amber-700',
        ring: 'ring-amber-600/20',
        dot: 'bg-amber-500',
        label: 'Low Stock',
    },
    out_of_stock: {
        bg: 'bg-red-100',
        text: 'text-red-700',
        ring: 'ring-red-600/20',
        dot: 'bg-red-500',
        label: 'Out of Stock',
    },
};

function computeInventorySummary(productType, stock, variants = [], comboItems = []) {
    if (productType === 'variable') {
        const totalStock = variants.reduce((sum, v) => sum + (parseInt(v.stock) || 0), 0);
        const variantCount = variants.length;
        const status = totalStock <= 0 ? 'out_of_stock' : totalStock <= 5 ? 'low_stock' : 'in_stock';
        return { type: 'variable', totalStock, variantCount, status };
    }

    if (productType === 'combo') {
        const itemCount = comboItems.length;
        let availableStock = 0;
        if (itemCount > 0) {
            availableStock = Math.min(...comboItems.map((item) => {
                const qty = Math.max(1, item.quantity || 1);
                return Math.floor((item.stock_available || 0) / qty);
            }));
        }
        const status = itemCount === 0 ? 'out_of_stock' : availableStock <= 0 ? 'out_of_stock' : availableStock <= 5 ? 'low_stock' : 'in_stock';
        return { type: 'combo', itemCount, availableStock, status };
    }

    const stockNum = parseInt(stock) || 0;
    const status = stockNum <= 0 ? 'out_of_stock' : stockNum <= 5 ? 'low_stock' : 'in_stock';
    return { type: 'single', stock: stockNum, status };
}

export default function SidebarSection({
    data,
    setData,
    errors,
    categories,
    processing,
    onSubmit,
    onCancel,
    isEdit = false,
    onDeleteUrl = null,
    variants = [],
    comboItems = [],
}) {
    const categoryOptions = categories.map((cat) => ({
        value: cat.id,
        label: cat.name,
    }));

    const productTypes = [
        { value: 'single', label: 'Single Product' },
        { value: 'variable', label: 'Variable Product' },
        { value: 'combo', label: 'Combo Product' },
    ];

    const inventory = computeInventorySummary(data.product_type, data.stock, variants, comboItems);
    const statusStyle = STATUS_CONFIG[inventory.status] || STATUS_CONFIG.in_stock;

    return (
        <div className="space-y-4">
            {/* Product Status */}
            <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div className="px-4 py-3 border-b border-gray-100">
                    <h3 className="text-sm font-semibold text-gray-900">Status</h3>
                </div>
                <div className="px-4 py-4">
                    <FormSelect
                        name="status"
                        value={data.status}
                        onChange={(e) => setData('status', e.target.value)}
                        options={[
                            { value: 'active', label: 'Active' },
                            { value: 'inactive', label: 'Inactive' },
                            { value: 'draft', label: 'Draft' },
                        ]}
                        placeholder="Select status"
                        error={errors.status}
                    />
                    {data.status === 'inactive' && (
                        <p className="mt-2 text-xs text-gray-500">
                            Inactive products won't be visible to customers.
                        </p>
                    )}
                    {data.status === 'draft' && (
                        <p className="mt-2 text-xs text-gray-500">
                            Draft products are not published yet.
                        </p>
                    )}
                </div>
            </div>

            {/* Product Type */}
            <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div className="px-4 py-3 border-b border-gray-100">
                    <h3 className="text-sm font-semibold text-gray-900">Product Type</h3>
                </div>
                <div className="px-4 py-4 space-y-2">
                    {productTypes.map((type) => (
                        <div
                            key={type.value}
                            className={`
                                flex items-center justify-between rounded-lg border px-3 py-2.5
                                ${data.product_type === type.value
                                    ? 'border-blue-500 bg-blue-50 ring-1 ring-blue-500'
                                    : 'border-gray-200 bg-white hover:border-gray-300 cursor-pointer'
                                }
                            `}
                            onClick={() => setData('product_type', type.value)}
                        >
                            <div className="flex items-center gap-2">
                                <div className={`
                                    w-4 h-4 rounded-full border-2 flex items-center justify-center
                                    ${data.product_type === type.value
                                        ? 'border-blue-500 bg-blue-500'
                                        : 'border-gray-300'
                                    }
                                `}>
                                    {data.product_type === type.value && (
                                        <div className="w-1.5 h-1.5 rounded-full bg-white" />
                                    )}
                                </div>
                                <span className="text-sm text-gray-700">
                                    {type.label}
                                </span>
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {/* Organization */}
            <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div className="px-4 py-3 border-b border-gray-100">
                    <h3 className="text-sm font-semibold text-gray-900">Organization</h3>
                </div>
                <div className="px-4 py-4 space-y-4">
                    <FormSelect
                        label="Category"
                        name="category_id"
                        value={data.category_id}
                        onChange={(e) => setData('category_id', e.target.value)}
                        options={categoryOptions}
                        placeholder="Select category"
                        error={errors.category_id}
                        required
                    />

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1.5">
                            Brand
                        </label>
                        <div className="rounded-lg border-2 border-dashed border-gray-200 bg-gray-50 px-3 py-2.5 text-center">
                            <span className="text-xs text-gray-400">
                                Brands available in Pro plan
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {/* Inventory Summary */}
            <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div className="px-4 py-3 border-b border-gray-100">
                    <h3 className="text-sm font-semibold text-gray-900">Inventory Summary</h3>
                </div>
                <div className="px-4 py-4">
                    {inventory.type === 'variable' ? (
                        <div className="space-y-3">
                            <div className="flex items-center gap-2.5">
                                <div className="flex-shrink-0 w-8 h-8 rounded-lg bg-purple-50 flex items-center justify-center">
                                    <Layers className="w-4 h-4 text-purple-600" />
                                </div>
                                <div className="flex-1 min-w-0">
                                    <p className="text-xs text-gray-500">Variants</p>
                                    <p className="text-sm font-semibold text-gray-900">{inventory.variantCount}</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-2.5">
                                <div className="flex-shrink-0 w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center">
                                    <Package className="w-4 h-4 text-blue-600" />
                                </div>
                                <div className="flex-1 min-w-0">
                                    <p className="text-xs text-gray-500">Total Units</p>
                                    <p className="text-sm font-semibold text-gray-900">{inventory.totalStock}</p>
                                </div>
                            </div>
                            <div className="pt-2 border-t border-gray-100">
                                <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium ring-1 ${statusStyle.bg} ${statusStyle.text} ${statusStyle.ring}`}>
                                    <span className={`w-1.5 h-1.5 rounded-full ${statusStyle.dot}`} />
                                    {statusStyle.label}
                                </span>
                            </div>
                        </div>
                    ) : inventory.type === 'combo' ? (
                        <div className="space-y-3">
                            <div className="flex items-center gap-2.5">
                                <div className="flex-shrink-0 w-8 h-8 rounded-lg bg-orange-50 flex items-center justify-center">
                                    <Gift className="w-4 h-4 text-orange-600" />
                                </div>
                                <div className="flex-1 min-w-0">
                                    <p className="text-xs text-gray-500">Components</p>
                                    <p className="text-sm font-semibold text-gray-900">{inventory.itemCount}</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-2.5">
                                <div className="flex-shrink-0 w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center">
                                    <Package className="w-4 h-4 text-blue-600" />
                                </div>
                                <div className="flex-1 min-w-0">
                                    <p className="text-xs text-gray-500">Bundle Stock</p>
                                    <p className={`text-sm font-semibold ${inventory.availableStock <= 0 ? 'text-red-600' : 'text-gray-900'}`}>
                                        {inventory.availableStock}
                                    </p>
                                </div>
                            </div>
                            <div className="pt-2 border-t border-gray-100">
                                <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium ring-1 ${statusStyle.bg} ${statusStyle.text} ${statusStyle.ring}`}>
                                    <span className={`w-1.5 h-1.5 rounded-full ${statusStyle.dot}`} />
                                    {statusStyle.label}
                                </span>
                            </div>
                            {inventory.itemCount === 0 && (
                                <p className="text-xs text-gray-400">Add components to calculate bundle stock.</p>
                            )}
                            {inventory.availableStock <= 5 && inventory.itemCount > 0 && (
                                <p className="text-xs text-amber-600">Bundle stock is limited by the lowest component.</p>
                            )}
                        </div>
                    ) : (
                        <div className="space-y-3">
                            <div className="flex items-center justify-between">
                                <span className="text-sm text-gray-600">Stock Level</span>
                                <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium ring-1 ${statusStyle.bg} ${statusStyle.text} ${statusStyle.ring}`}>
                                    <span className={`w-1.5 h-1.5 rounded-full ${statusStyle.dot}`} />
                                    {inventory.stock} units — {statusStyle.label}
                                </span>
                            </div>
                            {inventory.stock === 0 && (
                                <p className="text-xs text-gray-400">This product is currently out of stock.</p>
                            )}
                            {inventory.status === 'low_stock' && (
                                <p className="text-xs text-amber-600">Stock is running low. Consider restocking soon.</p>
                            )}
                        </div>
                    )}
                </div>
            </div>

            {/* Actions */}
            <div className="sticky bottom-4">
                <div className="bg-white rounded-xl shadow-lg border border-gray-200 p-4 space-y-3">
                    <button
                        type="button"
                        onClick={onSubmit}
                        disabled={processing}
                        className="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors shadow-sm"
                    >
                        {processing ? (
                            <>
                                <svg className="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                </svg>
                                {isEdit ? 'Updating...' : 'Saving...'}
                            </>
                        ) : (
                            <>
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                </svg>
                                {isEdit ? 'Update Product' : 'Save Product'}
                            </>
                        )}
                    </button>

                    {isEdit && onDeleteUrl && (
                        <a
                            href={onDeleteUrl}
                            data-Delete
                            method="delete"
                            as="button"
                            type="button"
                            className="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-white border border-red-300 text-red-600 text-sm font-medium rounded-lg hover:bg-red-50 transition-colors"
                        >
                            <Trash2 className="w-4 h-4" />
                            Delete Product
                        </a>
                    )}

                    <button
                        type="button"
                        onClick={onCancel}
                        className="w-full px-4 py-2.5 text-gray-700 bg-white border border-gray-300 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    );
}
