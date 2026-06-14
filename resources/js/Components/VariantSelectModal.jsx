import { useState, useMemo } from 'react';

export default function VariantSelectModal({ product, onClose, onAddToCart }) {
    const [selectedVariantId, setSelectedVariantId] = useState(null);
    const [quantity, setQuantity] = useState(1);

    const variants = useMemo(() => {
        return (product.variants || []).filter(v => v.status === 'active');
    }, [product.variants]);

    const selectedVariant = useMemo(() => {
        if (!selectedVariantId) return null;
        return variants.find(v => v.id === selectedVariantId) || null;
    }, [variants, selectedVariantId]);

    const displayPrice = useMemo(() => {
        if (selectedVariant) {
            return selectedVariant.price != null ? Number(selectedVariant.price).toLocaleString() : '—';
        }
        return null;
    }, [selectedVariant]);

    function getStatusLabel(stock, threshold) {
        if (stock <= 0) return { label: 'Out of Stock', color: 'text-red-500' };
        if (stock <= threshold) return { label: 'Low Stock', color: 'text-orange-500' };
        return { label: 'In Stock', color: 'text-green-600' };
    }

    const selectedStatus = useMemo(() => {
        if (!selectedVariant) return null;
        return getStatusLabel(Number(selectedVariant.stock ?? 0), selectedVariant.low_stock_threshold ?? 5);
    }, [selectedVariant]);

    const maxQuantity = selectedVariant ? Number(selectedVariant.stock ?? 0) : 1;
    const canAddToCart = selectedVariant && Number(selectedVariant.stock ?? 0) > 0;

    const handleAdd = () => {
        if (!canAddToCart) return;
        onAddToCart(selectedVariant.id, quantity);
    };

    const optionKeys = useMemo(() => {
        const keys = new Set();
        variants.forEach(v => {
            if (v.attributes && typeof v.attributes === 'object') {
                Object.keys(v.attributes).forEach(k => keys.add(k));
            }
        });
        return Array.from(keys);
    }, [variants]);

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            onClick={onClose}
        >
            <div
                className="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 max-h-[90vh] overflow-y-auto"
                onClick={e => e.stopPropagation()}
            >
                <div className="flex items-center justify-between p-4 border-b border-gray-200">
                    <h2 className="text-lg font-semibold text-gray-900 truncate pr-2">
                        {product.name}
                    </h2>
                    <button
                        onClick={onClose}
                        className="text-gray-400 hover:text-gray-600 text-xl leading-none p-1"
                    >
                        &times;
                    </button>
                </div>

                <div className="p-4 space-y-4">
                    {variants.length === 0 ? (
                        <p className="text-sm text-gray-500 text-center py-4">
                            No variants available for this product.
                        </p>
                    ) : (
                        <>
                            {optionKeys.length > 0 && (
                                <div>
                                    <p className="text-sm font-medium text-gray-700 mb-2">
                                        Options
                                    </p>
                                    <div className="space-y-2">
                                        {variants.map(v => {
                                            const label = v.label || `Variant #${v.id}`;
                                            const inStock = Number(v.stock ?? 0) > 0;
                                            return (
                                                <label
                                                    key={v.id}
                                                    className={`flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-colors ${
                                                        selectedVariantId === v.id
                                                            ? 'border-blue-500 bg-blue-50'
                                                            : 'border-gray-200 hover:border-gray-300'
                                                    } ${!inStock ? 'opacity-50' : ''}`}
                                                >
                                                    <input
                                                        type="radio"
                                                        name="variant"
                                                        value={v.id}
                                                        checked={selectedVariantId === v.id}
                                                        onChange={() => {
                                                            setSelectedVariantId(v.id);
                                                            setQuantity(1);
                                                        }}
                                                        disabled={!inStock}
                                                        className="accent-blue-600"
                                                    />
                                                    <div className="flex-1 min-w-0">
                                                        <span className="text-sm font-medium text-gray-900 block truncate">
                                                            {label}
                                                        </span>
                                                        {v.sku && (
                                                            <span className="text-xs text-gray-400">
                                                                SKU: {v.sku}
                                                            </span>
                                                        )}
                                                    </div>
                                                    <div className="text-right flex-shrink-0">
                                                        <span className="text-sm font-semibold text-gray-900 block">
                                                            {v.price != null ? Number(v.price).toLocaleString() : '—'} MMK
                                                        </span>
                                                        <span className={`text-[10px] font-medium ${inStock ? (Number(v.stock) <= (v.low_stock_threshold ?? 5) ? 'text-orange-500' : 'text-green-600') : 'text-red-500'}`}>
                                                            {inStock ? (Number(v.stock) <= (v.low_stock_threshold ?? 5) ? 'Low Stock' : 'In Stock') : 'Out of Stock'}
                                                        </span>
                                                    </div>
                                                </label>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}
                        </>
                    )}

                    {selectedVariant ? (
                        <div className="border-t border-gray-200 pt-4 space-y-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-xs text-gray-500">Price</p>
                                    <p className="text-xl font-bold text-gray-900">
                                        {displayPrice} MMK
                                    </p>
                                </div>
                                <div className="text-right">
                                    <p className="text-xs text-gray-500">Stock</p>
                                    <p className={`text-sm font-medium ${selectedStatus.color}`}>
                                        {selectedStatus.label}
                                    </p>
                                </div>
                            </div>

                            <div>
                                <label className="text-sm font-medium text-gray-700 block mb-1">
                                    Quantity
                                </label>
                                <div className="flex items-center gap-3">
                                    <button
                                        type="button"
                                        onClick={() => setQuantity(q => Math.max(1, q - 1))}
                                        disabled={quantity <= 1}
                                        className="w-9 h-9 rounded-lg border border-gray-300 flex items-center justify-center text-gray-600 hover:bg-gray-100 disabled:opacity-30 disabled:cursor-not-allowed"
                                    >
                                        &minus;
                                    </button>
                                    <span className="w-12 text-center text-lg font-semibold text-gray-900">
                                        {quantity}
                                    </span>
                                    <button
                                        type="button"
                                        onClick={() => setQuantity(q => Math.min(maxQuantity, q + 1))}
                                        disabled={quantity >= maxQuantity}
                                        className="w-9 h-9 rounded-lg border border-gray-300 flex items-center justify-center text-gray-600 hover:bg-gray-100 disabled:opacity-30 disabled:cursor-not-allowed"
                                    >
                                        +
                                    </button>
                                </div>
                            </div>

                            <button
                                onClick={handleAdd}
                                disabled={!canAddToCart}
                                className="w-full py-3 bg-blue-600 text-white rounded-lg font-semibold text-sm hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                            >
                                Add to Cart
                            </button>
                        </div>
                    ) : (
                        <p className="text-sm text-center text-amber-600 py-2">
                            Please select a variant.
                        </p>
                    )}
                </div>
            </div>
        </div>
    );
}
