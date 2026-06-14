import { X, Package, Layers } from 'lucide-react';

export default function ComboItemCard({ item, onRemove, onQuantityChange, index }) {
    const isVariant = !!item.linked_variant_id;

    const stockColor = item.stock_available <= 0
        ? 'text-red-600'
        : item.stock_available < 10
            ? 'text-amber-600'
            : 'text-emerald-600';

    return (
        <div className="flex gap-4 p-4 bg-white rounded-xl border border-gray-200 hover:border-gray-300 transition-colors">
            {/* Image */}
            <div className="flex-shrink-0 w-16 h-16 rounded-xl overflow-hidden bg-gray-100 border border-gray-200">
                {item.photo1_url ? (
                    <img src={item.photo1_url} alt={item.product_name} className="w-full h-full object-cover" />
                ) : (
                    <div className="w-full h-full flex items-center justify-center">
                        <Package className="w-6 h-6 text-gray-300" />
                    </div>
                )}
            </div>

            {/* Info */}
            <div className="flex-1 min-w-0 space-y-1.5">
                <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0">
                        <p className="text-sm font-semibold text-gray-900 truncate">{item.product_name}</p>
                        {isVariant && (
                            <span className="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[11px] font-medium bg-purple-50 text-purple-700 mt-0.5">
                                <Layers className="w-3 h-3" />
                                {item.variant_label}
                            </span>
                        )}
                    </div>
                    <button
                        type="button"
                        onClick={() => onRemove(item.id)}
                        className="p-1 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors flex-shrink-0"
                    >
                        <X className="w-4 h-4" />
                    </button>
                </div>

                <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
                    <span className={`font-medium ${stockColor}`}>
                        Current Stock: {item.stock_available} pcs
                    </span>
                </div>

                {/* Quantity + Cost row */}
                <div className="flex items-center gap-4 pt-1">
                    <div className="flex items-center gap-2">
                        <label className="text-xs text-gray-500 font-medium whitespace-nowrap">Bundle Qty:</label>
                        <input
                            type="number"
                            value={item.quantity}
                            onChange={(e) => onQuantityChange(item.id, Math.max(1, parseInt(e.target.value) || 1))}
                            className="w-16 rounded-lg border border-gray-300 px-2.5 py-1.5 text-sm text-center font-medium focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            min="1"
                        />
                    </div>
                    <span className="text-sm font-semibold text-gray-900">
                        Cost: ${(item.unit_price || 0).toFixed(2)}
                    </span>
                    {item.quantity > 1 && (
                        <span className="text-xs text-gray-400">
                            (${((item.unit_price || 0) * (item.quantity || 1)).toFixed(2)} total)
                        </span>
                    )}
                </div>
            </div>
        </div>
    );
}
