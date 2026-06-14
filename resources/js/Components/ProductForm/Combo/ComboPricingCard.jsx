import { DollarSign, TrendingUp, Percent } from 'lucide-react';

export default function ComboPricingCard({ price, onPriceChange, estimatedCost, error }) {
    const profit = (parseFloat(price) || 0) - estimatedCost;
    const margin = estimatedCost > 0 ? ((profit / (parseFloat(price) || 0)) * 100) : 0;

    return (
        <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div className="px-5 py-4 border-b border-gray-100">
                <h3 className="text-base font-semibold text-gray-900">Pricing</h3>
                <p className="text-xs text-gray-500 mt-0.5">Set your bundle selling price and view profitability</p>
            </div>

            <div className="p-5 space-y-5">
                <div className="max-w-xs">
                    <label className="block text-sm font-medium text-gray-700 mb-1.5">
                        Bundle Sale Price <span className="text-red-500">*</span>
                    </label>
                    <input
                        type="number"
                        value={price}
                        onChange={(e) => onPriceChange(e.target.value)}
                        placeholder="0.00"
                        step="0.01"
                        min="0"
                        className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    />
                    {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div className="bg-emerald-50 rounded-xl p-4 border border-emerald-100">
                        <div className="flex items-center gap-2 text-xs text-emerald-600 mb-2">
                            <TrendingUp className="w-4 h-4" />
                            Expected Profit
                        </div>
                        <p className={`text-2xl font-bold ${profit >= 0 ? 'text-emerald-700' : 'text-red-600'}`}>
                            ${profit.toFixed(2)}
                        </p>
                        <p className="text-[11px] text-emerald-500 mt-1">
                            {profit >= 0
                                ? `Bundle Price ($${(parseFloat(price) || 0).toFixed(2)}) - Estimated Cost ($${estimatedCost.toFixed(2)})`
                                : 'Loss — price is below cost'
                            }
                        </p>
                    </div>

                    <div className={`rounded-xl p-4 border ${profit >= 0 ? 'bg-blue-50 border-blue-100' : 'bg-red-50 border-red-100'}`}>
                        <div className="flex items-center gap-2 text-xs mb-2">
                            <Percent className={`w-4 h-4 ${profit >= 0 ? 'text-blue-600' : 'text-red-600'}`} />
                            <span className={`${profit >= 0 ? 'text-blue-600' : 'text-red-600'}`}>Profit Margin</span>
                        </div>
                        <p className={`text-2xl font-bold ${profit >= 0 ? 'text-blue-700' : 'text-red-600'}`}>
                            {estimatedCost > 0 ? margin.toFixed(1) : 0}%
                        </p>
                        <p className="text-[11px] text-gray-400 mt-1">
                            {estimatedCost > 0
                                ? `Based on bundle sale price and estimated cost`
                                : 'Add components to see margin'}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
}
