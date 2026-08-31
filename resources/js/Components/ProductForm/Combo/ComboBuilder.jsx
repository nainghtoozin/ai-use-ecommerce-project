import { useState, useEffect, useMemo } from 'react';
import { Gift, Package, AlertCircle, AlertTriangle, CheckCircle } from 'lucide-react';
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

    const availability = useMemo(() => {
        if (localItems.length === 0) {
            return { maxCombos: 0, bottleneck: null, outOfStock: [], lowStock: [] };
        }

        let maxCombos = Infinity;
        let bottleneck = null;
        const outOfStock = [];
        const lowStock = [];

        localItems.forEach((item) => {
            const required = item.quantity || 1;
            const available = item.stock_available || 0;
            const canMake = Math.floor(available / required);

            if (canMake <= 0) {
                outOfStock.push(item);
            } else if (canMake < 5) {
                lowStock.push(item);
            }

            if (canMake < maxCombos) {
                maxCombos = canMake;
                bottleneck = item;
            }
        });

        return {
            maxCombos: maxCombos === Infinity ? 0 : maxCombos,
            bottleneck,
            outOfStock,
            lowStock,
        };
    }, [localItems]);

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
        isBottleneck: availability.bottleneck?.id === item.id,
        maxCombos: availability.maxCombos,
    }));

    const excludedIds = localItems
        .filter((item) => !item.variant_id)
        .map((item) => item.product_id);

    return (
        <div className="space-y-4">
            {/* Header */}
            <div className="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 overflow-hidden">
                <div className="px-5 py-4 border-b border-gray-100 dark:border-gray-800 bg-gradient-to-r from-orange-50/50 to-amber-50/50">
                    <div className="flex items-center gap-3">
                        <div className="flex-shrink-0 w-8 h-8 rounded-lg bg-orange-100 flex items-center justify-center">
                            <Gift className="w-4 h-4 text-orange-600" />
                        </div>
                        <div>
                            <h3 className="text-base font-semibold text-gray-900 dark:text-gray-100">Combo Builder</h3>
                            <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Select existing products or specific variants to build your bundle</p>
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
                            <div className="w-14 h-14 rounded-2xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center mx-auto mb-3">
                                <Package className="w-7 h-7 text-gray-300" />
                            </div>
                            <p className="text-sm font-medium text-gray-600 dark:text-gray-400">No components yet</p>
                            <p className="text-xs text-gray-400 dark:text-gray-500 mt-1 max-w-xs mx-auto">
                                Search and add products or specific variants above to start building your combo
                            </p>
                        </div>
                    )}

                    {/* Availability Summary */}
                    {localItems.length > 0 && (
                        <div className={`rounded-xl p-4 border-2 ${
                            availability.maxCombos === 0
                                ? 'bg-red-50 border-red-200'
                                : availability.maxCombos < 5
                                    ? 'bg-amber-50 border-amber-200'
                                    : 'bg-emerald-50 border-emerald-200'
                        }`}>
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    {availability.maxCombos === 0 ? (
                                        <AlertTriangle className="w-5 h-5 text-red-600" />
                                    ) : availability.maxCombos < 5 ? (
                                        <AlertCircle className="w-5 h-5 text-amber-600" />
                                    ) : (
                                        <CheckCircle className="w-5 h-5 text-emerald-600" />
                                    )}
                                    <div>
                                        <p className={`text-sm font-semibold ${
                                            availability.maxCombos === 0
                                                ? 'text-red-700'
                                                : availability.maxCombos < 5
                                                    ? 'text-amber-700'
                                                    : 'text-emerald-700'
                                        }`}>
                                            {availability.maxCombos === 0
                                                ? 'Out of Stock'
                                                : availability.maxCombos < 5
                                                    ? `Limited Availability (${availability.maxCombos} bundles)`
                                                    : `${availability.maxCombos} bundles available`
                                            }
                                        </p>
                                        {availability.bottleneck && (
                                            <p className="text-xs text-gray-600 dark:text-gray-400 mt-0.5">
                                                Limited by: <span className="font-medium">{availability.bottleneck.product_name}</span>
                                                {availability.bottleneck.variant_label && ` (${availability.bottleneck.variant_label})`}
                                            </p>
                                        )}
                                    </div>
                                </div>
                                <div className="text-right">
                                    <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                        {availability.maxCombos}
                                    </p>
                                    <p className="text-xs text-gray-500 dark:text-gray-400">max bundles</p>
                                </div>
                            </div>

                            {/* Component status list */}
                            {localItems.length > 1 && (
                                <div className="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 text-xs">
                                        {itemsWithSubtotals.map((item) => {
                                            const required = item.quantity || 1;
                                            const available = item.stock_available || 0;
                                            const canMake = Math.floor(available / required);
                                            const status = canMake <= 0 ? 'out' : canMake < 5 ? 'low' : 'ok';

                                            return (
                                                <div key={item.id} className={`flex items-center gap-2 px-2 py-1 rounded ${
                                                    status === 'out' ? 'bg-red-100 text-red-700' :
                                                    status === 'low' ? 'bg-amber-100 text-amber-700' :
                                                    'bg-emerald-100 text-emerald-700'
                                                }`}>
                                                    <span className="truncate flex-1">{item.product_name}</span>
                                                    <span className="font-medium whitespace-nowrap">
                                                        {available}/{required} = {canMake}
                                                    </span>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Items list */}
                    {localItems.length > 0 && (
                        <div className="space-y-2">
                            <div className="flex items-center justify-between">
                                <p className="text-xs font-medium text-gray-500 dark:text-gray-400">
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
                                    isBottleneck={availability.bottleneck?.id === item.id}
                                    maxCombos={availability.maxCombos}
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
