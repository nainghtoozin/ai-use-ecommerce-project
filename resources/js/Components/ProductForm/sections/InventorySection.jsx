import { usePage } from '@inertiajs/react';
import FormInput from '../FormInput';

export default function InventorySection({ data, setData, errors, isEdit = false }) {
    const { featureStatus = {}, warehouses = [] } = usePage().props;
    const inventoryEnabled = featureStatus.inventory_management?.enabled !== false;
    const warehouseEnabled = featureStatus.warehouse_management?.enabled !== false;

    if (!inventoryEnabled) {
        return null;
    }

    const isVariable = data.product_type === 'variable';
    const isCombo = data.product_type === 'combo';

    if (isVariable || isCombo) {
        return null;
    }

    const stockValue = parseInt(data.stock) || 0;
    const lowStockThreshold = parseInt(data.low_stock_alert) || 5;
    const isLowStock = stockValue > 0 && stockValue <= lowStockThreshold;
    const isOutOfStock = stockValue === 0;

    return (
        <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div className="px-6 py-4 border-b border-gray-100">
                <div className="flex items-center justify-between">
                    <div>
                        <h3 className="text-base font-semibold text-gray-900">Inventory</h3>
                        <p className="text-xs text-gray-500 mt-0.5">Stock tracking and warehouse assignment</p>
                    </div>
                    <span className={`inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium ${
                        isOutOfStock
                            ? 'bg-red-100 text-red-700'
                            : isLowStock
                                ? 'bg-amber-100 text-amber-700'
                                : 'bg-green-100 text-green-700'
                    }`}>
                        <span className={`w-1.5 h-1.5 rounded-full ${
                            isOutOfStock ? 'bg-red-500' : isLowStock ? 'bg-amber-500' : 'bg-green-500'
                        }`} />
                        {isOutOfStock ? 'Out of stock' : isLowStock ? 'Low stock' : `${stockValue} in stock`}
                    </span>
                </div>
            </div>

            <div className="px-6 py-6 space-y-4">
                {isEdit ? (
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1.5">
                            Current Stock
                        </label>
                        <div className="flex items-center gap-3">
                            <span className="text-2xl font-semibold text-gray-900">{stockValue}</span>
                            <span className="text-sm text-gray-500">units</span>
                        </div>
                        <p className="mt-1 text-xs text-gray-500">
                            Manage stock through Stock Movements to maintain a complete audit trail.
                        </p>
                    </div>
                ) : (
                    <FormInput
                        label="Opening Stock"
                        name="stock"
                        type="number"
                        value={data.stock ?? 0}
                        onChange={(e) => setData('stock', e.target.value)}
                        placeholder="0"
                        error={errors.stock}
                        min="0"
                        helpText="Initial stock quantity recorded as opening stock"
                    />
                )}

                {warehouseEnabled && (
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1.5">
                            Default Warehouse
                        </label>
                        <select
                            name="warehouse_id"
                            value={data.warehouse_id || ''}
                            onChange={(e) => setData('warehouse_id', e.target.value)}
                            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 bg-white"
                        >
                            <option value="">No warehouse</option>
                            {warehouses.map((wh) => (
                                <option key={wh.id} value={wh.id}>
                                    {wh.name}{wh.code ? ` (${wh.code})` : ''}
                                </option>
                            ))}
                        </select>
                        {errors.warehouse_id && <p className="mt-1 text-xs text-red-600">{errors.warehouse_id}</p>}
                        <p className="mt-1 text-xs text-gray-500">
                            Assign stock to a specific warehouse location
                        </p>
                    </div>
                )}

                <FormInput
                    label="Low Stock Alert Threshold"
                    name="low_stock_alert"
                    type="number"
                    value={data.low_stock_alert ?? 5}
                    onChange={(e) => setData('low_stock_alert', e.target.value)}
                    placeholder="5"
                    error={errors.low_stock_alert}
                    min="0"
                    helpText="Receive alert when stock drops below this number"
                />

                {(isLowStock || isOutOfStock) && (
                    <div className={`rounded-lg border px-4 py-3 ${
                        isOutOfStock ? 'bg-red-50 border-red-200' : 'bg-amber-50 border-amber-200'
                    }`}>
                        <div className="flex items-start gap-3">
                            <svg className={`w-5 h-5 flex-shrink-0 mt-0.5 ${
                                isOutOfStock ? 'text-red-500' : 'text-amber-500'
                            }`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <div>
                                <p className={`text-sm font-medium ${isOutOfStock ? 'text-red-800' : 'text-amber-800'}`}>
                                    {isOutOfStock
                                        ? 'This product is out of stock'
                                        : `Only ${stockValue} item${stockValue !== 1 ? 's' : ''} remaining`
                                    }
                                </p>
                                <p className={`text-xs mt-0.5 ${isOutOfStock ? 'text-red-600' : 'text-amber-600'}`}>
                                    {isOutOfStock
                                        ? 'Customers cannot purchase this product until restocked'
                                        : 'Restock soon to avoid losing sales'
                                    }
                                </p>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
