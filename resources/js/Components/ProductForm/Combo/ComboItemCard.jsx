import { useState } from 'react';
import { X, Package, Layers, AlertTriangle, CheckCircle, AlertCircle } from 'lucide-react';

export default function ComboItemCard({ item, onRemove, onQuantityChange, index, isBottleneck = false, maxCombos = 0 }) {
    const isVariant = !!item.variant_id;
    const [showConfirm, setShowConfirm] = useState(false);

    const required = item.quantity || 1;
    const available = item.stock_available || 0;
    const canMake = Math.floor(available / required);

    const status = available <= 0
        ? 'out_of_stock'
        : canMake < 5
            ? 'low_stock'
            : 'ok';

    const statusConfig = {
        out_of_stock: {
            color: 'text-red-600 bg-red-50 border-red-200',
            icon: AlertTriangle,
            label: 'Out of Stock',
        },
        low_stock: {
            color: 'text-amber-600 bg-amber-50 border-amber-200',
            icon: AlertCircle,
            label: 'Low Stock',
        },
        ok: {
            color: 'text-emerald-600 bg-emerald-50 border-emerald-200',
            icon: CheckCircle,
            label: 'OK',
        },
    };

    const statusInfo = statusConfig[status];
    const StatusIcon = statusInfo.icon;

    const handleRemove = () => {
        if (item.combo_item_id) {
            setShowConfirm(true);
        } else {
            onRemove(item.id);
        }
    };

    const confirmRemove = () => {
        setShowConfirm(false);
        onRemove(item.id);
    };

    return (
        <div className={`flex gap-4 p-4 bg-white dark:bg-gray-900 rounded-xl border-2 transition-colors ${
            isBottleneck ? 'border-orange-400 dark:border-orange-600 bg-orange-50/30 dark:bg-orange-950/20' : 'border-gray-200 dark:border-gray-800 hover:border-gray-300'
        }`}>
            {/* Bottleneck indicator */}
            {isBottleneck && maxCombos > 0 && (
                <div className="absolute -top-2 -right-2 bg-orange-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full">
                    LIMITS BUNDLES
                </div>
            )}

            {/* Image */}
            <div className="relative flex-shrink-0 w-16 h-16 rounded-xl overflow-hidden bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-800">
                {item.photo1_url ? (
                    <img src={item.photo1_url} alt={item.product_name} className="w-full h-full object-cover" />
                ) : (
                    <div className="w-full h-full flex items-center justify-center">
                        <Package className="w-6 h-6 text-gray-300" />
                    </div>
                )}
                {isBottleneck && (
                    <div className="absolute inset-0 bg-orange-500/20 flex items-center justify-center">
                        <AlertTriangle className="w-5 h-5 text-orange-600" />
                    </div>
                )}
            </div>

            {/* Info */}
            <div className="flex-1 min-w-0 space-y-2">
                <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0">
                        <p className="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">{item.product_name}</p>
                        {isVariant && (
                            <span className="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[11px] font-medium bg-purple-50 text-purple-700 mt-0.5">
                                <Layers className="w-3 h-3" />
                                {item.variant_label}
                            </span>
                        )}
                    </div>
                    {showConfirm ? (
                        <div className="flex items-center gap-1">
                            <button
                                type="button"
                                onClick={confirmRemove}
                                className="px-2 py-1 rounded-lg text-xs font-medium bg-red-500 text-white hover:bg-red-600"
                            >
                                Remove
                            </button>
                            <button
                                type="button"
                                onClick={() => setShowConfirm(false)}
                                className="px-2 py-1 rounded-lg text-xs font-medium bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300"
                            >
                                Cancel
                            </button>
                        </div>
                    ) : (
                        <button
                            type="button"
                            onClick={handleRemove}
                            className="p-1 rounded-lg text-gray-400 dark:text-gray-500 hover:text-red-500 hover:bg-red-50 transition-colors flex-shrink-0"
                        >
                            <X className="w-4 h-4" />
                        </button>
                    )}
                </div>

                {/* Stock info row */}
                <div className="flex flex-wrap items-center gap-2 text-xs">
                    <span className={`inline-flex items-center gap-1 px-2 py-1 rounded-full border ${statusInfo.color}`}>
                        <StatusIcon className="w-3 h-3" />
                        {statusInfo.label}
                    </span>
                    <span className="text-gray-500 dark:text-gray-400">
                        {available} available
                    </span>
                    {required > 1 && (
                        <span className="text-gray-400 dark:text-gray-500">
                            · {available}/{required} = {canMake} bundles
                        </span>
                    )}
                </div>

                {/* Quantity + Cost row */}
                <div className="flex items-center gap-4 pt-1">
                    <div className="flex items-center gap-2">
                        <label className="text-xs text-gray-500 dark:text-gray-400 font-medium whitespace-nowrap">Bundle Qty:</label>
                        <input
                            type="number"
                            value={item.quantity}
                            onChange={(e) => onQuantityChange(item.id, Math.max(1, parseInt(e.target.value) || 1))}
                            className="w-16 rounded-lg border border-gray-300 dark:border-gray-700 px-2.5 py-1.5 text-sm text-center font-medium focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            min="1"
                        />
                    </div>
                    <span className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                        ${(item.unit_price || 0).toFixed(2)} ea
                    </span>
                    <span className="text-sm text-gray-500 dark:text-gray-400">
                        Subtotal: <span className="font-semibold text-gray-900 dark:text-gray-200">${((item.unit_price || 0) * (item.quantity || 1)).toFixed(2)}</span>
                    </span>
                </div>
            </div>
        </div>
    );
}
