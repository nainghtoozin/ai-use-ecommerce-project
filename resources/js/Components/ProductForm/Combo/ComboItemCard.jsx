import { X, Package, Layers, ChevronDown, ChevronUp } from 'lucide-react';
import { useState } from 'react';

export default function ComboItemCard({ item, onRemove, onQuantityChange, index }) {
    const [expanded, setExpanded] = useState(false);

    const isVariant = !!item.linked_variant_id;
    const stockColor = item.stock_available <= 0
        ? 'text-red-600 bg-red-50'
        : item.stock_available < 10
            ? 'text-amber-600 bg-amber-50'
            : 'text-emerald-600 bg-emerald-50';

    return (
        <div className="group bg-white rounded-xl border border-gray-200 overflow-hidden hover:border-gray-300 transition-colors">
            <div className="flex items-center gap-3 px-4 py-3">
                <span className="flex-shrink-0 w-6 h-6 rounded-full bg-orange-100 text-orange-700 text-xs font-bold flex items-center justify-center">
                    {index + 1}
                </span>

                {item.photo1_url ? (
                    <img src={item.photo1_url} alt={item.product_name} className="w-10 h-10 rounded-lg object-cover border border-gray-200 flex-shrink-0" />
                ) : (
                    <div className="w-10 h-10 rounded-lg bg-gray-100 border border-gray-200 flex items-center justify-center flex-shrink-0">
                        <Package className="w-5 h-5 text-gray-300" />
                    </div>
                )}

                <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium text-gray-900 truncate">{item.product_name}</p>
                    <div className="flex items-center gap-2 mt-0.5">
                        {isVariant ? (
                            <span className="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-purple-50 text-purple-700">
                                <Layers className="w-2.5 h-2.5" />
                                {item.variant_label}
                            </span>
                        ) : (
                            <span className="text-xs text-gray-400">Single product</span>
                        )}
                        <span className={`inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium ${stockColor}`}>
                            {item.stock_available} in stock
                        </span>
                    </div>
                </div>

                <div className="flex items-center gap-2 flex-shrink-0">
                    <div className="flex items-center border border-gray-200 rounded-lg overflow-hidden">
                        <button
                            type="button"
                            onClick={() => onQuantityChange(item.id, Math.max(1, item.quantity - 1))}
                            className="px-2 py-1 text-gray-500 hover:bg-gray-100 transition-colors text-xs font-medium"
                        >
                            −
                        </button>
                        <input
                            type="number"
                            value={item.quantity}
                            onChange={(e) => onQuantityChange(item.id, Math.max(1, parseInt(e.target.value) || 1))}
                            className="w-10 text-center text-xs font-medium border-x border-gray-200 py-1 focus:outline-none"
                            min="1"
                        />
                        <button
                            type="button"
                            onClick={() => onQuantityChange(item.id, item.quantity + 1)}
                            className="px-2 py-1 text-gray-500 hover:bg-gray-100 transition-colors text-xs font-medium"
                        >
                            +
                        </button>
                    </div>

                    <button
                        type="button"
                        onClick={() => onRemove(item.id)}
                        className="p-1.5 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors"
                    >
                        <X className="w-4 h-4" />
                    </button>
                </div>
            </div>

            <button
                type="button"
                onClick={() => setExpanded(!expanded)}
                className="w-full flex items-center justify-between px-4 py-2 bg-gray-50/50 border-t border-gray-100 text-xs text-gray-500 hover:text-gray-700 transition-colors"
            >
                <span>
                    ${item.unit_price?.toFixed(2)} each · ${item.subtotal?.toFixed(2)} total
                </span>
                {expanded ? <ChevronUp className="w-3.5 h-3.5" /> : <ChevronDown className="w-3.5 h-3.5" />}
            </button>

            {expanded && (
                <div className="px-4 py-3 bg-gray-50/50 border-t border-gray-100 space-y-2 text-xs">
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <span className="text-gray-500">Product ID</span>
                            <p className="font-mono text-gray-700">#{item.product_id}</p>
                        </div>
                        {item.variant_id && (
                            <div>
                                <span className="text-gray-500">Variant ID</span>
                                <p className="font-mono text-gray-700">#{item.variant_id}</p>
                            </div>
                        )}
                        <div>
                            <span className="text-gray-500">Unit Price</span>
                            <p className="text-gray-700">${item.unit_price?.toFixed(2)}</p>
                        </div>
                        <div>
                            <span className="text-gray-500">Quantity</span>
                            <p className="text-gray-700">{item.quantity}</p>
                        </div>
                    </div>
                    <div className="pt-2 border-t border-gray-200">
                        <span className="text-gray-500">Available Bundle Stock</span>
                        <p className={`font-semibold mt-0.5 ${item.stock_available <= 0 ? 'text-red-600' : 'text-emerald-600'}`}>
                            {item.stock_available} combos possible
                        </p>
                    </div>
                </div>
            )}
        </div>
    );
}
