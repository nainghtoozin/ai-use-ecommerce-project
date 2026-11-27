import { useState } from 'react';
import { Link } from '@inertiajs/react';
import { ChevronDown, ShoppingBag, Trash2 } from 'lucide-react';
import { formatCurrency } from '@/Utils/currency';

function discountPercent(original, current) {
    const orig = Number(original);
    const curr = Number(current);
    if (!orig || !(orig > curr)) return null;
    return Math.round(((orig - curr) / orig) * 100);
}

function ItemPrice({ item, cc }) {
    const hasDiscount = item.original_price != null && Number(item.original_price) > Number(item.price);

    if (item.is_flash_sale) {
        const pct = discountPercent(item.original_price, item.price);
        return (
            <div className="flex flex-col gap-0.5 items-start">
                <span className="text-[11px] text-gray-400 line-through">{formatCurrency(item.original_price, cc)}</span>
                <span className="text-sm font-bold text-orange-600">{formatCurrency(item.price, cc)}</span>
                {pct !== null && (
                    <span className="inline-block px-1.5 py-0.5 bg-orange-500 text-white text-[10px] font-bold rounded-full">-{pct}%</span>
                )}
            </div>
        );
    }

    if (hasDiscount) {
        const pct = discountPercent(item.original_price, item.price);
        return (
            <div className="flex flex-col gap-0.5 items-start">
                <span className="text-[11px] text-gray-400 line-through">{formatCurrency(item.original_price, cc)}</span>
                <span className="text-sm font-bold text-gray-900 dark:text-gray-100">{formatCurrency(item.price, cc)}</span>
                {pct !== null && (
                    <span className="inline-block px-1.5 py-0.5 bg-red-500 text-white text-[10px] font-bold rounded-full">-{pct}%</span>
                )}
            </div>
        );
    }

    return <span className="text-sm font-bold text-gray-900 dark:text-gray-100">{formatCurrency(item.price, cc)}</span>;
}

export default function MobileCartView({
    tenant,
    cartItems,
    subtotal,
    appliedPromotion,
    appliedCoupon,
    totalDiscount,
    clearing,
    quantityDrafts,
    cc,
    labels,
    finalTotal,
    originalSubtotal,
    promoSavings,
    primaryActionStyle,
    onClearCart,
    onStepperClick,
    onDraftChange,
    onCommitDraft,
    onRemoveItem,
}) {
    const [summaryOpen, setSummaryOpen] = useState(true);
    const itemCount = (cartItems || []).reduce((s, i) => s + Number(i.quantity || 0), 0);

    if (!cartItems || cartItems.length === 0) {
        return (
            <div className="px-4 pt-6 pb-28 flex flex-col items-center justify-center text-center min-h-[60vh]">
                <div className="w-24 h-24 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center mb-5">
                    <ShoppingBag className="w-10 h-10 text-gray-400 dark:text-gray-500" />
                </div>
                <h2 className="text-lg font-bold text-gray-900 dark:text-gray-100">Your cart is empty</h2>
                <p className="mt-2 text-sm text-gray-500 dark:text-gray-400 max-w-[260px]">
                    Browse products from {tenant.name} and add items to your cart.
                </p>
                <Link
                    href={`/store/${tenant.slug}`}
                    style={primaryActionStyle}
                    className="mt-6 inline-flex items-center justify-center px-6 py-3 text-white font-semibold text-sm shadow-sm"
                >
                    {labels.continue_shopping || 'Continue Shopping'}
                </Link>
            </div>
        );
    }

    return (
        <div className="px-4 pt-3 pb-28">
            {/* Header */}
            <div className="flex items-center justify-between mb-4">
                <div className="flex items-center gap-3 min-w-0">
                    <Link
                        href={`/store/${tenant.slug}`}
                        className="w-9 h-9 flex items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 flex-shrink-0"
                        aria-label="Back to store"
                    >
                        <span className="text-lg leading-none">←</span>
                    </Link>
                    <div className="min-w-0">
                        <h1 className="text-lg font-bold text-gray-900 dark:text-gray-100 leading-tight">My Cart</h1>
                        <p className="text-xs text-gray-500 dark:text-gray-400">{itemCount} {itemCount === 1 ? 'item' : 'items'}</p>
                    </div>
                </div>
                <button
                    onClick={onClearCart}
                    disabled={clearing}
                    aria-label={labels.clear_cart || 'Clear Cart'}
                    className="flex items-center gap-1.5 px-3 py-2 text-xs font-medium text-red-600 bg-red-50 border border-red-200 rounded-full hover:bg-red-100 disabled:opacity-50 transition-colors flex-shrink-0"
                >
                    <Trash2 className="w-3.5 h-3.5" />
                    <span>{clearing ? 'Clearing...' : (labels.clear_cart || 'Clear All')}</span>
                </button>
            </div>

            {/* Items */}
            <div className="space-y-3">
                {cartItems.map((item) => {
                    const lineSubtotal = item.line_total ?? Number(item.price) * Number(item.quantity);
                    return (
                        <div
                            key={item.cart_key}
                            className="flex gap-3 bg-white dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800 shadow-sm p-3"
                        >
                            <div className="w-[72px] h-[72px] flex-shrink-0 rounded-xl bg-gray-50 dark:bg-gray-800 flex items-center justify-center overflow-hidden">
                                {item.photo1_url ? (
                                    <img src={item.photo1_url} alt={item.name} className="w-full h-full object-contain" />
                                ) : (
                                    <ShoppingBag className="w-6 h-6 text-gray-300 dark:text-gray-600" />
                                )}
                            </div>
                            <div className="flex-1 min-w-0">
                                <div className="flex items-start justify-between gap-2">
                                    <p className="text-sm font-semibold leading-snug text-gray-900 dark:text-gray-100 line-clamp-2">{item.name}</p>
                                    <button
                                        onClick={() => onRemoveItem(item.cart_key)}
                                        aria-label="Remove item"
                                        className="flex-shrink-0 -mr-1 p-1 text-gray-300 hover:text-red-500 transition-colors disabled:opacity-50"
                                    >
                                        <Trash2 className="w-4 h-4" />
                                    </button>
                                </div>
                                {item.variant_name && (
                                    <p className="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5 truncate">{item.variant_name}</p>
                                )}
                                <div className="mt-2 flex items-center justify-between gap-2">
                                    <ItemPrice item={item} cc={cc} />
                                    <div className="flex items-center bg-gray-100 dark:bg-gray-800 rounded-full flex-shrink-0">
                                        <button
                                            onClick={() => onStepperClick(item.cart_key, -1)}
                                            className="w-8 h-8 flex items-center justify-center text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 transition-colors"
                                        >
                                            <span className="text-base leading-none">−</span>
                                        </button>
                                        <input
                                            aria-label={`Quantity for ${item.name}`}
                                            type="text"
                                            inputMode="numeric"
                                            value={quantityDrafts[item.cart_key] ?? item.quantity}
                                            onChange={(e) => onDraftChange(item.cart_key, e.target.value)}
                                            onBlur={() => onCommitDraft(item.cart_key)}
                                            onKeyDown={(e) => { if (e.key === 'Enter') e.target.blur(); }}
                                            className="w-9 text-center text-sm font-semibold text-gray-900 dark:text-gray-100 bg-transparent border-0 outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                        />
                                        <button
                                            onClick={() => onStepperClick(item.cart_key, 1)}
                                            className="w-8 h-8 flex items-center justify-center text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 transition-colors"
                                        >
                                            <span className="text-base leading-none">+</span>
                                        </button>
                                    </div>
                                </div>
                                <div className="mt-2 flex justify-between items-center pt-2 border-t border-gray-50 dark:border-gray-800">
                                    <span className="text-[11px] text-gray-500 dark:text-gray-400">Subtotal</span>
                                    <span className="text-sm font-bold text-gray-900 dark:text-gray-100">{formatCurrency(lineSubtotal, cc)}</span>
                                </div>
                            </div>
                        </div>
                    );
                })}
            </div>

            {/* Order Summary */}
            <div className="mt-4 bg-white dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800 shadow-sm overflow-hidden">
                <button
                    type="button"
                    onClick={() => setSummaryOpen((v) => !v)}
                    className="w-full flex items-center justify-between px-4 py-3.5"
                >
                    <div className="flex items-center gap-2">
                        <span className="text-sm font-semibold text-gray-900 dark:text-gray-100">Order Summary</span>
                        <ChevronDown className={`w-4 h-4 text-gray-400 transition-transform ${summaryOpen ? 'rotate-180' : ''}`} />
                    </div>
                    <span className="text-base font-extrabold text-gray-900 dark:text-gray-100">{formatCurrency(finalTotal, cc)}</span>
                </button>
                {summaryOpen && (
                    <div className="px-4 pb-4 pt-1 space-y-2 text-sm border-t border-gray-50 dark:border-gray-800">
                        {promoSavings > 0 && (
                            <>
                                <div className="flex justify-between text-gray-500 dark:text-gray-400">
                                    <span>Original Subtotal ({itemCount} items)</span>
                                    <span>{formatCurrency(originalSubtotal, cc)}</span>
                                </div>
                                <div className="flex justify-between text-emerald-600 dark:text-emerald-400">
                                    <span>Promotion Discount</span>
                                    <span className="font-medium">-{formatCurrency(promoSavings, cc)}</span>
                                </div>
                            </>
                        )}
                        <div className="flex justify-between text-gray-600 dark:text-gray-400">
                            <span>Subtotal ({itemCount} items)</span>
                            <span>{formatCurrency(subtotal, cc)}</span>
                        </div>
                        {(Number(appliedCoupon?.discount) || 0) > 0 && (
                            <div className="flex justify-between text-emerald-600 dark:text-emerald-400">
                                <span>Coupon Discount</span>
                                <span className="font-medium">-{formatCurrency(appliedCoupon.discount, cc)}</span>
                            </div>
                        )}
                        {(Number(appliedPromotion?.discount) || 0) > 0 && (
                            <div className="flex justify-between text-emerald-600 dark:text-emerald-400">
                                <span>Promotion Discount</span>
                                <span className="font-medium">-{formatCurrency(appliedPromotion.discount, cc)}</span>
                            </div>
                        )}
                        <div className="flex justify-between items-center pt-2 border-t border-gray-100 dark:border-gray-800">
                            <span className="text-base font-bold text-gray-900 dark:text-gray-100">Total</span>
                            <span className="text-lg font-extrabold text-gray-900 dark:text-gray-100">{formatCurrency(finalTotal, cc)}</span>
                        </div>
                    </div>
                )}
            </div>

            {/* Sticky checkout bar */}
            <div className="fixed bottom-[calc(4rem+env(safe-area-inset-bottom))] left-0 right-0 z-40 bg-white/95 dark:bg-gray-900/95 backdrop-blur-md border-t border-gray-100 dark:border-gray-800 px-4 py-3 shadow-[0_-4px_20px_rgba(0,0,0,0.08)]">
                <div className="flex items-center justify-between gap-3">
                    <div className="min-w-0">
                        <p className="text-[11px] text-gray-500 dark:text-gray-400 leading-tight">Total Payment</p>
                        <p className="text-xl font-extrabold text-gray-900 dark:text-gray-100 leading-tight">{formatCurrency(finalTotal, cc)}</p>
                    </div>
                    <Link
                        href={`/store/${tenant.slug}/checkout`}
                        style={primaryActionStyle}
                        className="flex-1 max-w-[62%] block text-center py-3.5 text-white font-semibold text-sm shadow-sm rounded-xl"
                    >
                        {labels.checkout || 'Checkout'}
                    </Link>
                </div>
            </div>
        </div>
    );
}