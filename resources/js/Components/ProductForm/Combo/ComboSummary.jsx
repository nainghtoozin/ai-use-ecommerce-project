import { Package, DollarSign, Percent, AlertTriangle } from 'lucide-react';

export default function ComboSummary({ items, comboPrice }) {
    if (items.length === 0) return null;

    const basePrice = items.reduce((sum, item) => sum + (item.subtotal || 0), 0);
    const savings = basePrice - comboPrice;
    const savingsPercentage = basePrice > 0 ? ((savings / basePrice) * 100).toFixed(1) : 0;
    const effectiveStock = Math.min(...items.map((i) => Math.floor(i.stock_available / Math.max(1, i.quantity))));
    const hasLowStock = items.some((i) => i.stock_available <= 0);
    const hasLowComponent = items.some((i) => Math.floor(i.stock_available / Math.max(1, i.quantity)) < 5);

    return (
        <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div className="px-5 py-4 border-b border-gray-100 bg-gradient-to-r from-orange-50/50 to-amber-50/50">
                <div className="flex items-center gap-3">
                    <div className="flex-shrink-0 w-8 h-8 rounded-lg bg-orange-100 flex items-center justify-center">
                        <Package className="w-4 h-4 text-orange-600" />
                    </div>
                    <div>
                        <h3 className="text-base font-semibold text-gray-900">Combo Summary</h3>
                        <p className="text-xs text-gray-500 mt-0.5">{items.length} component{items.length !== 1 ? 's' : ''} · derived stock & pricing</p>
                    </div>
                </div>
            </div>

            <div className="p-5 space-y-4">
                <div className="grid grid-cols-2 gap-4">
                    <div className="bg-gray-50 rounded-lg p-3">
                        <div className="flex items-center gap-1.5 text-xs text-gray-500 mb-1">
                            <DollarSign className="w-3.5 h-3.5" />
                            Base Price
                        </div>
                        <p className="text-lg font-semibold text-gray-900">${basePrice.toFixed(2)}</p>
                        <p className="text-[10px] text-gray-400 mt-0.5">Sum of all components</p>
                    </div>

                    <div className="bg-gray-50 rounded-lg p-3">
                        <div className="flex items-center gap-1.5 text-xs text-gray-500 mb-1">
                            <Package className="w-3.5 h-3.5" />
                            Available Stock
                        </div>
                        <p className={`text-lg font-semibold ${effectiveStock <= 0 ? 'text-red-600' : 'text-emerald-600'}`}>
                            {effectiveStock}
                        </p>
                        <p className="text-[10px] text-gray-400 mt-0.5">Derived from components</p>
                    </div>
                </div>

                {savings > 0 && (
                    <div className="bg-emerald-50 rounded-lg p-3 border border-emerald-100">
                        <div className="flex items-center gap-1.5 text-xs text-emerald-600 mb-1">
                            <Percent className="w-3.5 h-3.5" />
                            Bundle Savings
                        </div>
                        <div className="flex items-baseline gap-2">
                            <p className="text-lg font-semibold text-emerald-700">${savings.toFixed(2)}</p>
                            <p className="text-sm font-medium text-emerald-600">({savingsPercentage}% off)</p>
                        </div>
                    </div>
                )}

                {hasLowStock && (
                    <div className="bg-red-50 rounded-lg p-3 border border-red-100">
                        <div className="flex items-center gap-2">
                            <AlertTriangle className="w-4 h-4 text-red-500 flex-shrink-0" />
                            <div>
                                <p className="text-xs font-medium text-red-700">Stock warning</p>
                                <p className="text-[11px] text-red-600 mt-0.5">One or more components are out of stock</p>
                            </div>
                        </div>
                    </div>
                )}

                {!hasLowStock && hasLowComponent && (
                    <div className="bg-amber-50 rounded-lg p-3 border border-amber-100">
                        <div className="flex items-center gap-2">
                            <AlertTriangle className="w-4 h-4 text-amber-500 flex-shrink-0" />
                            <div>
                                <p className="text-xs font-medium text-amber-700">Low stock alert</p>
                                <p className="text-[11px] text-amber-600 mt-0.5">Available bundle stock is under 5 units</p>
                            </div>
                        </div>
                    </div>
                )}

                <div className="space-y-1.5">
                    <p className="text-xs font-medium text-gray-700">Components breakdown</p>
                    {items.map((item, i) => (
                        <div key={item.id || i} className="flex items-center justify-between text-xs py-1.5 border-b border-gray-100 last:border-0">
                            <span className="text-gray-600 truncate flex-1">
                                {item.product_name}
                                {item.variant_label && (
                                    <span className="text-gray-400 ml-1">({item.variant_label})</span>
                                )}
                            </span>
                            <span className="text-gray-500 ml-2 whitespace-nowrap">
                                {item.quantity} × ${item.unit_price?.toFixed(2)}
                            </span>
                            <span className="font-medium text-gray-700 ml-2 w-16 text-right">
                                ${item.subtotal?.toFixed(2)}
                            </span>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}
