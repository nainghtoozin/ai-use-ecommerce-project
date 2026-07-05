import { Package, Layers, AlertTriangle, ArrowDown, TrendingDown } from 'lucide-react';
import { formatCurrency } from '@/Utils/currency';

function safeNum(val) {
    const n = Number(val);
    return Number.isFinite(n) ? n : 0;
}

function formatPrice(price) {
    return formatCurrency(safeNum(price));
}

function getStockStatus(stock) {
    if (stock <= 0) return { label: 'Out of Stock', bg: 'bg-red-50', text: 'text-red-700', ring: 'ring-red-600/20', dot: 'bg-red-500' };
    if (stock <= 5) return { label: 'Low Stock', bg: 'bg-amber-50', text: 'text-amber-700', ring: 'ring-amber-600/20', dot: 'bg-amber-500' };
    return { label: 'In Stock', bg: 'bg-emerald-50', text: 'text-emerald-700', ring: 'ring-emerald-600/20', dot: 'bg-emerald-500' };
}

function resolveComboItem(rawItem) {
    const isVariant = !!rawItem.linked_variant_id;
    const variant = rawItem.linkedVariant || null;
    const comboProduct = rawItem.combo_product || rawItem.comboProduct || null;

    let productName = 'Unknown Product';
    let variantLabel = null;
    let unitPrice = 0;
    let stockAvailable = 0;
    let photoUrl = null;

    if (isVariant && variant) {
        productName = variant.product?.name || comboProduct?.name || 'Unknown Product';
        variantLabel = variant.label || variant.name || null;
        unitPrice = safeNum(variant.price ?? comboProduct?.price ?? 0);
        stockAvailable = safeNum(variant.stock ?? 0);
        photoUrl = comboProduct?.photo1_url || comboProduct?.photo1 || comboProduct?.photo2_url || comboProduct?.photo2 || null;
    } else if (comboProduct) {
        productName = comboProduct.name || 'Unknown Product';
        unitPrice = safeNum(comboProduct.price ?? 0);
        stockAvailable = safeNum(comboProduct.stock ?? comboProduct.effective_stock ?? 0);
        photoUrl = comboProduct.photo1_url || comboProduct.photo1 || comboProduct.photo2_url || comboProduct.photo2 || null;
    }

    const quantity = safeNum(rawItem.quantity);
    const subtotal = unitPrice * quantity;

    return {
        id: rawItem.id,
        combo_product_id: rawItem.combo_product_id,
        linked_variant_id: rawItem.linked_variant_id,
        product_name: productName,
        variant_label: variantLabel,
        link_type: isVariant ? 'variant' : 'product',
        quantity,
        unit_price: unitPrice,
        subtotal,
        stock_available: stockAvailable,
        photo_url: photoUrl,
    };
}

function ComboItemRow({ item, isBottleneck, index }) {
    const stock = safeNum(item.stock_available);
    const status = getStockStatus(stock);
    const qty = Math.max(1, safeNum(item.quantity));
    const possibleCombos = Math.floor(stock / qty);
    const photoUrl = item.photo_url || null;

    return (
        <div className={`group relative flex items-start gap-4 p-4 rounded-xl border transition-all ${isBottleneck ? 'border-orange-300 bg-orange-50/50 ring-1 ring-orange-200' : 'border-gray-100 bg-white hover:border-gray-200 hover:shadow-sm'}`}>
            <div className="flex-shrink-0 w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center text-xs font-bold text-gray-500">
                {index + 1}
            </div>

            {photoUrl ? (
                <img src={photoUrl} alt={item.product_name} className="flex-shrink-0 w-14 h-14 rounded-lg object-cover border border-gray-100" />
            ) : (
                <div className="flex-shrink-0 w-14 h-14 rounded-lg bg-gray-50 border border-gray-100 flex items-center justify-center">
                    <Package className="w-6 h-6 text-gray-300" />
                </div>
            )}

            <div className="flex-1 min-w-0">
                <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                        <div className="flex items-center gap-2">
                            <p className="text-sm font-semibold text-gray-900 truncate">{item.product_name}</p>
                            {isBottleneck && (
                                <span className="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-700">
                                    <ArrowDown className="w-3 h-3" />
                                    Bottleneck
                                </span>
                            )}
                        </div>
                        {item.variant_label && (
                            <div className="mt-1 flex flex-wrap gap-1">
                                <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-50 text-indigo-700">
                                    <Layers className="w-3 h-3 mr-1" />
                                    {item.variant_label}
                                </span>
                            </div>
                        )}
                        <div className="mt-1 flex items-center gap-2 text-xs text-gray-500">
                            <span>{item.link_type === 'variant' ? 'Specific Variant' : 'Base Product'}</span>
                            <span className="text-gray-300">·</span>
                            <span>Stock: {stock}</span>
                        </div>
                    </div>

                    <div className="flex-shrink-0 text-right">
                        <p className="text-sm font-semibold text-gray-900">{formatPrice(item.unit_price)}</p>
                        <p className="text-xs text-gray-500">{formatPrice(item.subtotal)} total</p>
                    </div>
                </div>

                <div className="mt-3 flex items-center gap-3">
                    <div className="flex items-center gap-1.5">
                        <span className="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold bg-gray-900 text-white">
                            ×{item.quantity}
                        </span>
                        <span className="text-xs text-gray-500">per bundle</span>
                    </div>

                    <div className="h-4 w-px bg-gray-200" />

                    <div className="flex items-center gap-1.5">
                        <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium ring-1 ${status.bg} ${status.text} ${status.ring}`}>
                            <span className={`w-1.5 h-1.5 rounded-full ${status.dot}`} />
                            {stock} available
                        </span>
                    </div>

                    <div className="h-4 w-px bg-gray-200" />

                    <div className="flex items-center gap-1.5">
                        <span className="text-xs text-gray-500">→</span>
                        <span className={`text-xs font-semibold ${possibleCombos <= 0 ? 'text-red-600' : possibleCombos <= 5 ? 'text-amber-600' : 'text-emerald-600'}`}>
                            {possibleCombos} bundles possible
                        </span>
                    </div>
                </div>
            </div>
        </div>
    );
}

export default function ComboViewDetail({ product }) {
    if (!product) return null;

    const rawComboItems = Array.isArray(product.combo_items) ? product.combo_items : [];
    const comboAvailability = product.combo_availability || null;
    const comboSummary = product.combo_summary || null;

    const hasSummaryItems = Array.isArray(comboSummary?.items) && comboSummary.items.length > 0;

    const resolvedItems = hasSummaryItems
        ? comboSummary.items.map((item) => ({
            id: item.id,
            combo_product_id: item.product_id,
            linked_variant_id: item.variant_id,
            product_name: item.product_name || 'Unknown Product',
            variant_label: item.variant_label || null,
            link_type: item.link_type || (item.variant_id ? 'variant' : 'product'),
            quantity: safeNum(item.quantity),
            unit_price: safeNum(item.unit_price),
            subtotal: safeNum(item.subtotal),
            stock_available: safeNum(item.stock_available),
            photo_url: item.combo_product?.photo1_url || item.combo_product?.photo1 || null,
        }))
        : rawComboItems.map(resolveComboItem);

    const itemCount = resolvedItems.length;
    const availableStock = comboAvailability?.available_stock ?? 0;

    const basePrice = hasSummaryItems
        ? safeNum(comboSummary.base_price)
        : resolvedItems.reduce((sum, item) => sum + safeNum(item.subtotal), 0);
    const comboPrice = safeNum(comboSummary?.combo_price ?? product.price ?? 0);
    const savings = hasSummaryItems ? safeNum(comboSummary.savings) : Math.max(0, basePrice - comboPrice);
    const savingsPct = hasSummaryItems
        ? safeNum(comboSummary.savings_percentage)
        : (basePrice > 0 ? Math.round(((basePrice - comboPrice) / basePrice) * 100) : 0);

    const bottleneck = comboAvailability?.bottleneck;
    const outOfStockItems = Array.isArray(comboAvailability?.out_of_stock_items) ? comboAvailability.out_of_stock_items : [];
    const lowStockItems = Array.isArray(comboAvailability?.low_stock_items) ? comboAvailability.low_stock_items : [];
    const stockStatus = getStockStatus(availableStock);

    return (
        <>
            {/* Bundle Overview Card */}
            <div className="bg-gradient-to-br from-orange-50 to-amber-50 rounded-xl border border-orange-100 overflow-hidden">
                <div className="px-5 py-4 border-b border-orange-100/60">
                    <div className="flex items-center gap-2">
                        <div className="w-8 h-8 rounded-lg bg-orange-100 flex items-center justify-center">
                            <Package className="w-4 h-4 text-orange-600" />
                        </div>
                        <h3 className="text-sm font-semibold text-gray-900">Bundle Overview</h3>
                    </div>
                </div>
                <div className="px-5 py-5">
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div className="p-3 rounded-lg bg-white/80 border border-orange-100/60">
                            <p className="text-xs text-gray-500 mb-1">Components</p>
                            <p className="text-2xl font-bold text-gray-900">{itemCount}</p>
                        </div>
                        <div className="p-3 rounded-lg bg-white/80 border border-orange-100/60">
                            <p className="text-xs text-gray-500 mb-1">Bundle Stock</p>
                            <p className={`text-2xl font-bold ${availableStock <= 0 ? 'text-red-600' : availableStock <= 5 ? 'text-amber-600' : 'text-emerald-600'}`}>
                                {availableStock}
                            </p>
                        </div>
                        <div className="p-3 rounded-lg bg-white/80 border border-orange-100/60">
                            <p className="text-xs text-gray-500 mb-1">Bundle Price</p>
                            <p className="text-lg font-bold text-gray-900">{formatPrice(comboPrice)}</p>
                        </div>
                        <div className="p-3 rounded-lg bg-white/80 border border-orange-100/60">
                            <p className="text-xs text-gray-500 mb-1">Value if Separate</p>
                            <p className="text-lg font-semibold text-gray-500">{formatPrice(basePrice)}</p>
                        </div>
                    </div>

                    {savings > 0 && (
                        <div className="mt-4 flex items-center gap-4">
                            <div className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                <TrendingDown className="w-3.5 h-3.5" />
                                Save {formatPrice(savings)} ({savingsPct}%)
                            </div>
                            <div className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium ring-1 ${stockStatus.bg} ${stockStatus.text} ${stockStatus.ring}`}>
                                <span className={`w-1.5 h-1.5 rounded-full ${stockStatus.dot}`} />
                                {stockStatus.label}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Bottleneck Warning */}
            {bottleneck && availableStock <= 5 && (
                <div className="bg-amber-50 rounded-xl border border-amber-200 overflow-hidden">
                    <div className="px-5 py-4 flex items-start gap-3">
                        <div className="flex-shrink-0 w-8 h-8 rounded-lg bg-amber-100 flex items-center justify-center">
                            <AlertTriangle className="w-4 h-4 text-amber-600" />
                        </div>
                        <div className="flex-1">
                            <p className="text-sm font-semibold text-amber-800">Stock Limited by Component</p>
                            <p className="text-xs text-amber-600 mt-1">
                                Bundle stock is constrained by <span className="font-semibold">{bottleneck.product_name}</span>
                                {bottleneck.variant_label ? ` (${bottleneck.variant_label})` : ''}
                                — only {safeNum(bottleneck.possible_combos)} bundle{safeNum(bottleneck.possible_combos) !== 1 ? 's' : ''} can be assembled.
                            </p>
                        </div>
                    </div>
                </div>
            )}

            {/* Out of Stock Warnings */}
            {outOfStockItems.length > 0 && (
                <div className="bg-red-50 rounded-xl border border-red-200 overflow-hidden">
                    <div className="px-5 py-4">
                        <div className="flex items-center gap-2 mb-2">
                            <AlertTriangle className="w-4 h-4 text-red-600" />
                            <p className="text-sm font-semibold text-red-800">Out of Stock Components</p>
                        </div>
                        <div className="space-y-1">
                            {outOfStockItems.map((item, i) => (
                                <p key={i} className="text-xs text-red-600">
                                    • {item.product_name || 'Unknown'}{item.variant_label ? ` (${item.variant_label})` : ''} — {safeNum(item.stock)} in stock, needs {safeNum(item.required)}
                                </p>
                            ))}
                        </div>
                    </div>
                </div>
            )}

            {/* Low Stock Warnings */}
            {lowStockItems.length > 0 && (
                <div className="bg-orange-50 rounded-xl border border-orange-200 overflow-hidden">
                    <div className="px-5 py-4">
                        <div className="flex items-center gap-2 mb-2">
                            <AlertTriangle className="w-4 h-4 text-orange-600" />
                            <p className="text-sm font-semibold text-orange-800">Low Stock Components</p>
                        </div>
                        <div className="space-y-1">
                            {lowStockItems.map((item, i) => (
                                <p key={i} className="text-xs text-orange-600">
                                    • {item.product_name || 'Unknown'}{item.variant_label ? ` (${item.variant_label})` : ''} — only {safeNum(item.possible_combos)} bundle{safeNum(item.possible_combos) !== 1 ? 's' : ''} possible
                                </p>
                            ))}
                        </div>
                    </div>
                </div>
            )}

            {/* Bundle Includes Section */}
            <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div className="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <Package className="w-4 h-4 text-gray-400" />
                        <h3 className="text-sm font-semibold text-gray-900">Bundle Includes</h3>
                    </div>
                    <span className="text-xs text-gray-500">{itemCount} item{itemCount !== 1 ? 's' : ''}</span>
                </div>
                <div className="px-5 py-4 space-y-3">
                    {resolvedItems.map((item, index) => {
                        const isBottleneck = bottleneck && (
                            (item.combo_product_id === bottleneck.product_id && item.linked_variant_id === bottleneck.variant_id) ||
                            (item.combo_product_id === bottleneck.product_id && !item.linked_variant_id && !bottleneck.variant_id)
                        );
                        return (
                            <ComboItemRow
                                key={item.id}
                                item={item}
                                isBottleneck={!!isBottleneck}
                                index={index}
                            />
                        );
                    })}
                </div>
            </div>

            {/* Pricing Breakdown */}
            <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div className="px-5 py-4 border-b border-gray-100">
                    <div className="flex items-center gap-2">
                        <TrendingDown className="w-4 h-4 text-gray-400" />
                        <h3 className="text-sm font-semibold text-gray-900">Pricing Breakdown</h3>
                    </div>
                </div>
                <div className="px-5 py-5">
                    <div className="space-y-3">
                        {resolvedItems.map((item) => (
                            <div key={item.id} className="flex items-center justify-between text-sm">
                                <span className="text-gray-600">
                                    {item.product_name}{item.variant_label ? ` (${item.variant_label})` : ''} × {item.quantity}
                                </span>
                                <span className="font-medium text-gray-900">{formatPrice(item.subtotal)}</span>
                            </div>
                        ))}
                        <hr className="border-gray-100" />
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-gray-500">Individual Total</span>
                            <span className="font-medium text-gray-500">{formatPrice(basePrice)}</span>
                        </div>
                        {savings > 0 && (
                            <div className="flex items-center justify-between text-sm">
                                <span className="text-green-600 font-medium">Bundle Discount</span>
                                <span className="text-green-600 font-semibold">−{formatPrice(savings)} ({savingsPct}%)</span>
                            </div>
                        )}
                        <hr className="border-gray-100" />
                        <div className="flex items-center justify-between">
                            <span className="text-base font-semibold text-gray-900">Bundle Price</span>
                            <span className="text-lg font-bold text-gray-900">{formatPrice(comboPrice)}</span>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
