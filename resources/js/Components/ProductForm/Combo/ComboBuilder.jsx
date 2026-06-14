import { useState, useEffect } from 'react';
import { Gift, Package, AlertCircle } from 'lucide-react';
import ComboSelector from './ComboSelector';
import ComboItemCard from './ComboItemCard';
import ComboSummary from './ComboSummary';

export default function ComboBuilder({
    items = [],
    setItems,
    selectableProducts = [],
    comboPrice = 0,
    existingComboItems = [],
    hideSummary = false,
}) {
    const [localItems, setLocalItems] = useState([]);

    useEffect(() => {
        if (existingComboItems.length > 0 && localItems.length === 0) {
            const mapped = existingComboItems.map((item) => ({
                id: `existing_${item.id}`,
                combo_item_id: item.id,
                product_id: item.combo_product_id,
                product_name: item.combo_product?.name || 'Unknown',
                photo1_url: item.combo_product?.photo1_url,
                type: item.combo_product?.type || 'single',
                variant_id: item.linked_variant_id || null,
                variant_label: item.linked_variant?.label || null,
                quantity: item.quantity,
                unit_price: item.linked_variant
                    ? item.linked_variant.price || item.combo_product?.price || 0
                    : item.combo_product?.price || 0,
                stock_available: item.linked_variant
                    ? item.linked_variant.stock || 0
                    : item.combo_product?.effective_stock || item.combo_product?.stock || 0,
            }));
            setLocalItems(mapped);
        }
    }, [existingComboItems]);

    useEffect(() => {
        setItems(localItems);
    }, [localItems, setItems]);

    function handleSelect(product, variant) {
        const existingKey = variant
            ? `${product.id}_${variant.id}`
            : `${product.id}_product`;

        const alreadyExists = localItems.some((item) => {
            const itemKey = item.variant_id
                ? `${item.product_id}_${item.variant_id}`
                : `${item.product_id}_product`;
            return itemKey === existingKey;
        });

        if (alreadyExists) return;

        const newItem = {
            id: `new_${Date.now()}`,
            combo_item_id: null,
            product_id: product.id,
            product_name: product.name,
            photo1_url: product.photo1_url,
            type: product.type,
            variant_id: variant?.id || null,
            variant_label: variant?.label || null,
            quantity: 1,
            unit_price: variant ? (variant.price || product.price) : product.price,
            stock_available: variant ? (variant.stock || 0) : (product.stock || 0),
        };

        setLocalItems([...localItems, newItem]);
    }

    function handleRemove(id) {
        setLocalItems(localItems.filter((item) => item.id !== id));
    }

    function handleQuantityChange(id, quantity) {
        setLocalItems(localItems.map((item) =>
            item.id === id ? { ...item, quantity } : item
        ));
    }

    const getSubtotal = (item) => (item.unit_price || 0) * (item.quantity || 1);

    const itemsWithSubtotals = localItems.map((item) => ({
        ...item,
        subtotal: getSubtotal(item),
    }));

    const excludedIds = localItems
        .filter((item) => !item.variant_id)
        .map((item) => item.product_id);

    return (
        <div className="space-y-4">
            {/* Header */}
            <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div className="px-5 py-4 border-b border-gray-100 bg-gradient-to-r from-orange-50/50 to-amber-50/50">
                    <div className="flex items-center gap-3">
                        <div className="flex-shrink-0 w-8 h-8 rounded-lg bg-orange-100 flex items-center justify-center">
                            <Gift className="w-4 h-4 text-orange-600" />
                        </div>
                        <div>
                            <h3 className="text-base font-semibold text-gray-900">Combo Builder</h3>
                            <p className="text-xs text-gray-500 mt-0.5">Select existing products or specific variants to build your bundle</p>
                        </div>
                    </div>
                </div>

                <div className="p-5 space-y-4">
                    {/* Selector */}
                    <ComboSelector
                        products={selectableProducts}
                        onSelect={handleSelect}
                        excludeIds={excludedIds}
                    />

                    {/* Empty state */}
                    {localItems.length === 0 && (
                        <div className="text-center py-10">
                            <div className="w-14 h-14 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-3">
                                <Package className="w-7 h-7 text-gray-300" />
                            </div>
                            <p className="text-sm font-medium text-gray-600">No components yet</p>
                            <p className="text-xs text-gray-400 mt-1 max-w-xs mx-auto">
                                Search and add products or specific variants above to start building your combo
                            </p>
                        </div>
                    )}

                    {/* Items list */}
                    {localItems.length > 0 && (
                        <div className="space-y-2">
                            <div className="flex items-center justify-between">
                                <p className="text-xs font-medium text-gray-500">
                                    {localItems.length} component{localItems.length !== 1 ? 's' : ''}
                                </p>
                            </div>
                            {itemsWithSubtotals.map((item, index) => (
                                <ComboItemCard
                                    key={item.id}
                                    item={item}
                                    index={index}
                                    onRemove={handleRemove}
                                    onQuantityChange={handleQuantityChange}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </div>

            {/* Summary */}
            {!hideSummary && itemsWithSubtotals.length > 0 && (
                <ComboSummary
                    items={itemsWithSubtotals}
                    comboPrice={comboPrice}
                />
            )}

            {/* Validation warning */}
            {localItems.length === 0 && (
                <div className="bg-amber-50 border border-amber-200 rounded-lg p-3 flex items-start gap-2">
                    <AlertCircle className="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" />
                    <div>
                        <p className="text-xs font-medium text-amber-700">No components selected</p>
                        <p className="text-[11px] text-amber-600 mt-0.5">A combo product requires at least one component to be valid</p>
                    </div>
                </div>
            )}
        </div>
    );
}
