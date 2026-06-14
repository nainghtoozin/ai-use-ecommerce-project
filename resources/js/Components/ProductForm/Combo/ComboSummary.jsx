import { Package, DollarSign, TrendingUp, Percent } from 'lucide-react';

export default function ComboSummary({ items, comboPrice }) {
    if (items.length === 0) return null;

    const estimatedCost = items.reduce((sum, item) => sum + (item.subtotal || 0), 0);
    const salePrice = parseFloat(comboPrice) || 0;
    const profit = salePrice - estimatedCost;
    const margin = salePrice > 0 ? (profit / salePrice) * 100 : 0;

    return (
        <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div className="px-5 py-4 border-b border-gray-100">
                <h3 className="text-base font-semibold text-gray-900">Bundle Summary</h3>
                <p className="text-xs text-gray-500 mt-0.5">Cost, pricing, and profitability overview</p>
            </div>

            <div className="p-5">
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                    <div className="bg-gray-50 rounded-xl p-4">
                        <div className="flex items-center gap-2 text-xs text-gray-500 mb-2">
                            <Package className="w-4 h-4" />
                            Total Components
                        </div>
                        <p className="text-2xl font-bold text-gray-900">{items.length}</p>
                    </div>

                    <div className="bg-gray-50 rounded-xl p-4">
                        <div className="flex items-center gap-2 text-xs text-gray-500 mb-2">
                            <DollarSign className="w-4 h-4" />
                            Estimated Cost
                        </div>
                        <p className="text-2xl font-bold text-gray-900">${estimatedCost.toFixed(2)}</p>
                    </div>

                    <div className="bg-gray-50 rounded-xl p-4">
                        <div className="flex items-center gap-2 text-xs text-gray-500 mb-2">
                            <DollarSign className="w-4 h-4" />
                            Bundle Sale Price
                        </div>
                        <p className="text-2xl font-bold text-gray-900">${salePrice.toFixed(2)}</p>
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
            </div>
        </div>
    );
}
