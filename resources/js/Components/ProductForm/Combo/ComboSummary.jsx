import { Package, DollarSign, TrendingUp, Percent, AlertTriangle, CheckCircle } from 'lucide-react';

export default function ComboSummary({ items, comboPrice, availability = null }) {
    if (items.length === 0) return null;

    const estimatedCost = items.reduce((sum, item) => sum + (item.subtotal || 0), 0);
    const salePrice = parseFloat(comboPrice) || 0;
    const profit = salePrice - estimatedCost;
    const margin = salePrice > 0 ? (profit / salePrice) * 100 : 0;

    const hasLowStock = items.some((item) => {
        const required = item.quantity || 1;
        const available = item.stock_available || 0;
        const canMake = Math.floor(available / required);
        return canMake < 5;
    });

    const isOutOfStock = items.some((item) => {
        const available = item.stock_available || 0;
        return available <= 0;
    });

    return (
        <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden">
            <div className="px-5 py-4 border-b border-gray-100 dark:border-gray-800">
                <div className="flex items-center justify-between">
                    <div>
                        <h3 className="text-base font-semibold text-gray-900 dark:text-gray-100">Bundle Summary</h3>
                        <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Cost, pricing, and profitability overview</p>
                    </div>
                    {availability && (
                        <div className={`flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-medium ${
                            availability.maxCombos === 0
                                ? 'bg-red-100 text-red-700'
                                : availability.maxCombos < 5
                                    ? 'bg-amber-100 text-amber-700'
                                    : 'bg-emerald-100 text-emerald-700'
                        }`}>
                            {availability.maxCombos === 0 ? (
                                <AlertTriangle className="w-3.5 h-3.5" />
                            ) : (
                                <CheckCircle className="w-3.5 h-3.5" />
                            )}
                            {availability.maxCombos} bundles available
                        </div>
                    )}
                </div>
            </div>

            <div className="p-5">
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                    <div className="bg-gray-50 dark:bg-gray-950 rounded-xl p-4">
                        <div className="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400 mb-2">
                            <Package className="w-4 h-4" />
                            Total Components
                        </div>
                        <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">{items.length}</p>
                    </div>

                    <div className="bg-gray-50 dark:bg-gray-950 rounded-xl p-4">
                        <div className="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400 mb-2">
                            <DollarSign className="w-4 h-4" />
                            Estimated Cost
                        </div>
                        <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">${estimatedCost.toFixed(2)}</p>
                    </div>

                    <div className="bg-gray-50 dark:bg-gray-950 rounded-xl p-4">
                        <div className="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400 mb-2">
                            <DollarSign className="w-4 h-4" />
                            Bundle Sale Price
                        </div>
                        <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">${salePrice.toFixed(2)}</p>
                    </div>

                    <div className={`rounded-xl p-4 ${profit >= 0 ? 'bg-emerald-50' : 'bg-red-50'}`}>
                        <div className={`flex items-center gap-2 text-xs mb-2 ${profit >= 0 ? 'text-emerald-600' : 'text-red-600'}`}>
                            <TrendingUp className="w-4 h-4" />
                            Estimated Profit
                        </div>
                        <p className={`text-2xl font-bold ${profit >= 0 ? 'text-emerald-700' : 'text-red-600'}`}>
                            ${profit.toFixed(2)}
                        </p>
                    </div>

                    <div className={`rounded-xl p-4 ${profit >= 0 ? 'bg-blue-50' : 'bg-red-50'}`}>
                        <div className={`flex items-center gap-2 text-xs mb-2 ${profit >= 0 ? 'text-blue-600' : 'text-red-600'}`}>
                            <Percent className="w-4 h-4" />
                            Profit Margin
                        </div>
                        <p className={`text-2xl font-bold ${profit >= 0 ? 'text-blue-700' : 'text-red-600'}`}>
                            {salePrice > 0 ? `${margin.toFixed(1)}%` : '—'}
                        </p>
                    </div>
                </div>

                {/* Savings highlight */}
                {profit > 0 && estimatedCost > 0 && (
                    <div className="mt-4 p-3 bg-emerald-50 border border-emerald-200 rounded-lg">
                        <p className="text-sm text-emerald-700">
                            <span className="font-semibold">Bundle Savings:</span> Customers save ${profit.toFixed(2)} ({(margin).toFixed(1)}% off individual prices)
                        </p>
                    </div>
                )}

                {/* Stock warning */}
                {(isOutOfStock || hasLowStock) && (
                    <div className={`mt-4 p-3 rounded-lg ${
                        isOutOfStock ? 'bg-red-50 border border-red-200' : 'bg-amber-50 border border-amber-200'
                    }`}>
                        <div className="flex items-start gap-2">
                            <AlertTriangle className={`w-4 h-4 flex-shrink-0 mt-0.5 ${isOutOfStock ? 'text-red-600' : 'text-amber-600'}`} />
                            <div className="text-sm">
                                {isOutOfStock ? (
                                    <p className="text-red-700 font-medium">Some components are out of stock</p>
                                ) : (
                                    <p className="text-amber-700 font-medium">Some components have limited stock</p>
                                )}
                                <p className="text-xs mt-1 text-gray-600 dark:text-gray-400">
                                    Bundle availability is limited by component stock. Consider restocking before publishing.
                                </p>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
