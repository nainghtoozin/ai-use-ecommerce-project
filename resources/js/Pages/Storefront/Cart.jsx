import { useState, useEffect, useRef } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Zap } from 'lucide-react';
import ShopLayout from '@/Layouts/ShopLayout';
import { useCart } from '@/Hooks/useCart';
import { formatCurrency, getCurrencyConfig } from '@/Utils/currency';

function discountPercent(original, current) {
    const orig = Number(original);
    const curr = Number(current);
    if (!orig || !(orig > curr)) return null;
    return Math.round(((orig - curr) / orig) * 100);
}

function ItemPrice({ item, cc, align = 'end' }) {
    const alignClass = align === 'center' ? 'items-center' : align === 'start' ? 'items-start' : 'items-end';
    const hasDiscount = item.original_price != null && Number(item.original_price) > Number(item.price);

    if (item.is_flash_sale) {
        const pct = discountPercent(item.original_price, item.price);
        return (
            <div className={`flex flex-col gap-0.5 ${alignClass}`}>
                <span className="text-xs text-gray-400 line-through">{formatCurrency(item.original_price, cc)}</span>
                <span className="text-[15px] font-bold text-orange-600">{formatCurrency(item.price, cc)}</span>
                {pct !== null && (
                    <span className="inline-block px-1.5 py-0.5 bg-orange-500 text-white text-[10px] font-bold rounded-full">-{pct}%</span>
                )}
            </div>
        );
    }

    if (hasDiscount) {
        const pct = discountPercent(item.original_price, item.price);
        const saveAmount = (Number(item.original_price) - Number(item.price)) * Number(item.quantity || 1);
        return (
            <div className={`flex flex-col gap-0.5 ${alignClass}`}>
                <span className="text-xs text-gray-400 line-through">{formatCurrency(item.original_price, cc)}</span>
                <span className="text-[15px] font-bold text-gray-900 dark:text-gray-100">{formatCurrency(item.price, cc)}</span>
                <span className="flex items-center gap-1 flex-wrap">
                    {item.promotion_badge ? (
                        <span className="inline-block px-1.5 py-0.5 bg-red-500 text-white text-[10px] font-bold rounded-full">{item.promotion_badge}</span>
                    ) : pct !== null ? (
                        <span className="inline-block px-1.5 py-0.5 bg-red-500 text-white text-[10px] font-bold rounded-full">-{pct}%</span>
                    ) : null}
                    <span className="text-[11px] font-medium text-emerald-600 dark:text-emerald-400">Save {formatCurrency(saveAmount, cc)}</span>
                </span>
            </div>
        );
    }

    return <span className="text-sm font-medium text-gray-800 dark:text-gray-200">{formatCurrency(item.price, cc)}</span>;
}

export default function StorefrontCart({ tenant, cartItems: initialCartItems, subtotal: initialSubtotal, appliedPromotion: initialPromotion, appliedCoupon: initialCoupon, totalDiscount: initialDiscount }) {
    const { storefront } = usePage().props;
    const labels = storefront?.content?.labels || {};
    const primaryActionStyle = { backgroundColor: 'var(--theme-color, #3B82F6)', borderRadius: 'var(--storefront-radius-button, 0.5rem)' };
    const cc = getCurrencyConfig(usePage().props.platform_setting, usePage().props.website_info);
    const { updateQuantity, removeItem, clearCart } = useCart();
    const [cartItems, setCartItems] = useState(initialCartItems || []);
    const [subtotal, setSubtotal] = useState(initialSubtotal || 0);
    const [clearing, setClearing] = useState(false);
    const [quantityDrafts, setQuantityDrafts] = useState({});
    const cartRef = useRef(cartItems);
    const subtotalRef = useRef(subtotal);
    const pendingOps = useRef({});
    cartRef.current = cartItems;
    subtotalRef.current = subtotal;

    const [appliedPromotion, setAppliedPromotion] = useState(initialPromotion || null);
    const [appliedCoupon, setAppliedCoupon] = useState(initialCoupon || null);
    const [totalDiscount, setTotalDiscount] = useState(initialDiscount || 0);


    useEffect(() => {
        setCartItems(initialCartItems || []);
        setSubtotal(initialSubtotal || 0);
    }, [initialCartItems, initialSubtotal]);

    useEffect(() => {
        setAppliedPromotion(initialPromotion || null);
        setAppliedCoupon(initialCoupon || null);
        setTotalDiscount(initialDiscount || 0);
    }, [initialPromotion, initialCoupon, initialDiscount]);

    function syncCartState(data) {
        if (!data) return;
        if (data.cartItems) setCartItems(data.cartItems);
        if (data.subtotal !== undefined) setSubtotal(data.subtotal);
        if ('appliedPromotion' in data) setAppliedPromotion(data.appliedPromotion || null);
        if ('appliedCoupon' in data) setAppliedCoupon(data.appliedCoupon || null);
        if (data.totalDiscount !== undefined) setTotalDiscount(data.totalDiscount);
    }

    const originalSubtotal = cartItems.reduce((s, i) => s + Number(i.original_price ?? i.price) * Number(i.quantity), 0);
    const promoSavings = Math.max(0, originalSubtotal - Number(subtotal));

    const finalTotal = Math.max(0, Number(subtotal) - Number(totalDiscount));

    async function handleUpdateQuantity(cartKey, newQty) {
        if (newQty < 1) newQty = 1;
        const prevItems = cartRef.current;
        const prevSubtotal = subtotalRef.current;
        const item = prevItems.find(i => i.cart_key === cartKey);
        if (!item) return;

        const optimisticItems = prevItems.map(i =>
            i.cart_key === cartKey ? { ...i, quantity: newQty } : i
        );
        const optimisticSubtotal = optimisticItems.reduce((sum, i) => sum + Number(i.price) * Number(i.quantity), 0);

        const opId = Date.now() + Math.random();
        pendingOps.current[cartKey] = opId;

        setCartItems(optimisticItems);
        setSubtotal(optimisticSubtotal);

        const result = await updateQuantity(cartKey, newQty, {
            message: `${item.name} quantity ${newQty > item.quantity ? 'increased' : 'decreased'} to ${newQty}.`,
        });

        if (pendingOps.current[cartKey] !== opId) {
            return;
        }

        if (result.error) {
            setCartItems(prevItems);
            setSubtotal(prevSubtotal);
            return;
        }

        syncCartState(result);
    }

    function handleDraftChange(cartKey, value) {
        if (value === '' || /^\d+$/.test(value)) {
            setQuantityDrafts(prev => ({ ...prev, [cartKey]: value }));
        }
    }

    function commitDraft(cartKey) {
        const draft = quantityDrafts[cartKey];
        if (draft === undefined) return;

        setQuantityDrafts(prev => {
            const next = { ...prev };
            delete next[cartKey];
            return next;
        });

        const num = parseInt(draft, 10);
        if (isNaN(num) || num < 1) return;

        const item = cartItems.find(i => i.cart_key === cartKey);
        if (item && num !== item.quantity) {
            handleUpdateQuantity(cartKey, num);
        }
    }

    function handleStepperClick(cartKey, delta) {
        const item = cartItems.find(i => i.cart_key === cartKey);
        if (!item) return;

        const draft = quantityDrafts[cartKey];
        const base = draft !== undefined ? parseInt(draft, 10) : item.quantity;
        const newQty = Math.max(1, (isNaN(base) ? 1 : base) + delta);

        setQuantityDrafts(prev => {
            const next = { ...prev };
            delete next[cartKey];
            return next;
        });

        if (newQty !== item.quantity) {
            handleUpdateQuantity(cartKey, newQty);
        }
    }

    async function handleRemoveItem(cartKey) {
        const prevItems = cartRef.current;
        const prevSubtotal = subtotalRef.current;

        const optimisticItems = prevItems.filter(i => i.cart_key !== cartKey);
        const optimisticSubtotal = optimisticItems.reduce((sum, i) => sum + Number(i.price) * Number(i.quantity), 0);

        setCartItems(optimisticItems);
        setSubtotal(optimisticSubtotal);

        const removedName = prevItems.find(i => i.cart_key === cartKey)?.name;
        const result = await removeItem(cartKey, removedName ? {
            message: `${removedName} was removed from your cart.`,
        } : null);

        if (result.error) {
            setCartItems(prevItems);
            setSubtotal(prevSubtotal);
            return;
        }

        syncCartState(result);
    }

    async function handleClearCart() {
        if (!window.confirm('Are you sure you want to clear your cart?')) return;
        setClearing(true);
        const prevItems = cartRef.current;
        const prevSubtotal = subtotalRef.current;
        const prevPromotion = appliedPromotion;
        const prevCoupon = appliedCoupon;
        const prevDiscount = totalDiscount;

        setCartItems([]);
        setSubtotal(0);
        setAppliedPromotion(null);
        setAppliedCoupon(null);
        setTotalDiscount(0);

        const result = await clearCart();

        setClearing(false);

        if (result.error) {
            setCartItems(prevItems);
            setSubtotal(prevSubtotal);
            setAppliedPromotion(prevPromotion);
            setAppliedCoupon(prevCoupon);
            setTotalDiscount(prevDiscount);
        } else {
            syncCartState(result);
        }
    }

    return (
        <ShopLayout>
            <Head title="Shopping Cart" />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="flex justify-between items-center mb-6 sm:mb-8">
                    <div>
                        <Link href={`/store/${tenant.slug}`} className="text-sm text-blue-600 hover:text-blue-800 mb-1 inline-block">
                            &larr; Back to {tenant.name}
                        </Link>
                        <h1 className="text-xl sm:text-2xl font-bold text-gray-900 dark:text-gray-100">Shopping Cart</h1>
                    </div>
                    {cartItems?.length > 0 && (
                        <button
                            onClick={handleClearCart}
                            disabled={clearing}
                            className="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-red-600 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 hover:text-red-700 disabled:opacity-50 transition-colors"
                        >
                            <i className="bi bi-trash3"></i>
                            {clearing ? 'Clearing...' : (labels.clear_cart || 'Clear Cart')}
                        </button>
                    )}
                </div>

                {!cartItems?.length ? (
                    <div className="text-center py-12 sm:py-16 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800">
                        <i className="bi bi-cart-x text-5xl text-gray-300"></i>
                        <h3 className="mt-4 text-lg font-medium text-gray-900 dark:text-gray-100">Your cart is empty</h3>
                        <p className="mt-2 text-gray-500 dark:text-gray-400">Browse products from {tenant.name} and add items to your cart.</p>
                        <Link
                            href={`/store/${tenant.slug}`}
                            style={primaryActionStyle}
                            className="mt-6 inline-block px-6 py-2.5 text-white transition-colors"
                        >
                            {labels.continue_shopping || 'Continue Shopping'}
                        </Link>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        <div className="lg:col-span-2 min-w-0">
                            <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden">
                                <div className="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-800">
                                    <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Cart Items ({cartItems.reduce((s, i) => s + Number(i.quantity || 0), 0)})</h2>
                                </div>
                                <div className="max-h-[55vh] lg:max-h-[calc(100vh-15rem)] overflow-y-auto overscroll-contain divide-y divide-gray-100 dark:divide-gray-800 [scrollbar-width:thin] [scrollbar-color:var(--tw-scroll-thumb,#D1D5DB)_transparent] [&::-webkit-scrollbar]:w-1 [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-gray-300/70 hover:[&::-webkit-scrollbar-thumb]:bg-gray-400/70 dark:[&::-webkit-scrollbar-thumb]:bg-gray-700/60 dark:hover:[&::-webkit-scrollbar-thumb]:bg-gray-600/70">
                            <div className="hidden md:grid md:grid-cols-[3.5rem_1fr_8rem_7rem_7rem_2.5rem] gap-4 px-4 py-2.5 sticky top-0 z-10 bg-white dark:bg-gray-900 border-b border-gray-100 dark:border-gray-800 text-[11px] font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">
                                <div></div>
                                <div>Product</div>
                                <div className="text-center">Unit Price</div>
                                <div className="text-center">Quantity</div>
                                <div className="text-right">Subtotal</div>
                                <div></div>
                            </div>

                            {cartItems.map((item) => {
                                const lineSubtotal = item.line_total ?? Number(item.price) * Number(item.quantity);
                                return (
                                    <div key={item.cart_key} className="px-4 py-3.5">
                                        <div className="md:hidden flex gap-3">
                                            <div className="w-11 h-11 flex-shrink-0 bg-gray-50 dark:bg-gray-800 rounded-lg overflow-hidden border border-gray-100 dark:border-gray-700">
                                                {item.photo1_url ? (
                                                    <img src={item.photo1_url} alt={item.name} className="w-full h-full object-cover" />
                                                ) : (
                                                    <div className="flex items-center justify-center h-full">
                                                        <i className="bi bi-image text-gray-400 dark:text-gray-500"></i>
                                                    </div>
                                                )}
                                            </div>
                                            <div className="flex-1 min-w-0">
                                                <p className="text-[13px] font-medium leading-snug text-gray-900 dark:text-gray-100 line-clamp-2">{item.name}</p>
                                                {item.variant_name && (
                                                    <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">{item.variant_name}</p>
                                                )}
                                                <div className="mt-2 space-y-1.5 text-sm">
                                                    <div className="flex justify-between gap-3">
                                                        <span className="text-gray-500 dark:text-gray-400">Unit Price</span>
                                                        <div className="text-right flex-shrink-0">
                                                            <ItemPrice item={item} cc={cc} align="end" />
                                                        </div>
                                                    </div>
                                                    <div className="flex items-center justify-between gap-2">
                                                        <span className="text-gray-500 dark:text-gray-400">Qty</span>
                                                        <div className="flex items-center border border-gray-300 dark:border-gray-700 rounded-lg">
                                                            <button onClick={() => handleStepperClick(item.cart_key, -1)} 
                                                                className="px-2 py-1 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:bg-gray-800 rounded-l-lg disabled:opacity-50 transition-colors">
                                                                <i className="bi bi-dash"></i>
                                                            </button>
                                                             <input aria-label={`Quantity for ${item.name}`} type="text" inputMode="numeric"
                                                                value={quantityDrafts[item.cart_key] ?? item.quantity}
                                                                onChange={(e) => handleDraftChange(item.cart_key, e.target.value)}
                                                                onBlur={() => commitDraft(item.cart_key)}
                                                                onKeyDown={(e) => { if (e.key === 'Enter') e.target.blur(); }}
                                                                
                                                                className="w-12 sm:w-14 text-center text-sm font-semibold text-gray-900 dark:text-gray-100 bg-transparent border-0 outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none disabled:opacity-50" />
                                                            <button onClick={() => handleStepperClick(item.cart_key, 1)} 
                                                                className="px-2 py-1 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:bg-gray-800 rounded-r-lg disabled:opacity-50 transition-colors">
                                                                <i className="bi bi-plus"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <div className="flex justify-between border-t border-gray-100 dark:border-gray-800 pt-1.5">
                                                        <span className="text-gray-700 dark:text-gray-300 font-semibold">Subtotal</span>
                                                        <span className="text-gray-900 dark:text-gray-100 font-bold">{formatCurrency(lineSubtotal, cc)}</span>
                                                    </div>
                                                </div>
                                                <button onClick={() => handleRemoveItem(item.cart_key)} 
                                                    className="mt-2 text-xs text-red-500 hover:text-red-700 disabled:opacity-50 flex items-center gap-1">
                                                    <i className="bi bi-trash"></i> Remove
                                                </button>
                                            </div>
                                        </div>

                                        <div className="hidden md:grid md:grid-cols-[3.5rem_1fr_8rem_7rem_7rem_2.5rem] gap-4 items-center">
                                            <div className="w-11 h-11 bg-gray-50 dark:bg-gray-800 rounded-lg overflow-hidden border border-gray-100 dark:border-gray-700">
                                                {item.photo1_url ? (
                                                    <img src={item.photo1_url} alt={item.name} className="w-full h-full object-cover" />
                                                ) : (
                                                    <div className="flex items-center justify-center h-full">
                                                        <i className="bi bi-image text-gray-400 dark:text-gray-500"></i>
                                                    </div>
                                                )}
                                            </div>
                                            <div className="min-w-0">
                                                <p className="text-[13px] font-medium leading-snug text-gray-900 dark:text-gray-100 line-clamp-2">{item.name}</p>
                                                {item.variant_name && (
                                                    <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">{item.variant_name}</p>
                                                )}
                                            </div>
                                            <div className="text-center">
                                                <ItemPrice item={item} cc={cc} align="center" />
                                            </div>
                                            <div className="flex justify-center">
                                                <div className="flex items-center border border-gray-300 dark:border-gray-700 rounded-lg">
                                                    <button onClick={() => handleStepperClick(item.cart_key, -1)} 
                                                        className="px-2.5 py-1.5 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:bg-gray-800 rounded-l-lg disabled:opacity-50 transition-colors">
                                                        <i className="bi bi-dash"></i>
                                                    </button>
                                                     <input aria-label={`Quantity for ${item.name}`} type="text" inputMode="numeric"
                                                        value={quantityDrafts[item.cart_key] ?? item.quantity}
                                                        onChange={(e) => handleDraftChange(item.cart_key, e.target.value)}
                                                        onBlur={() => commitDraft(item.cart_key)}
                                                        onKeyDown={(e) => { if (e.key === 'Enter') e.target.blur(); }}
                                                        
                                                        className="w-14 text-center text-sm font-semibold text-gray-900 dark:text-gray-100 bg-transparent border-0 outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none disabled:opacity-50" />
                                                    <button onClick={() => handleStepperClick(item.cart_key, 1)} 
                                                        className="px-2.5 py-1.5 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:bg-gray-800 rounded-r-lg disabled:opacity-50 transition-colors">
                                                        <i className="bi bi-plus"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div className="text-right">
                                                <span className="text-sm font-bold text-gray-900 dark:text-gray-100">{formatCurrency(lineSubtotal, cc)}</span>
                                            </div>
                                            <div className="flex justify-center">
                                                <button onClick={() => handleRemoveItem(item.cart_key)} 
                                                    className="text-gray-300 hover:text-red-500 transition-colors p-1 disabled:opacity-50" title="Remove item">
                                                    <i className="bi bi-x-lg text-sm"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                                </div>
                            </div>
                        </div>

                        <div className="lg:col-span-1">
                            <div className="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800 p-4 sm:p-6 sticky top-24">
                                <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Order Summary</h2>

                                <div className="space-y-3 text-sm">
                                    {promoSavings > 0 && (
                                        <>
                                            <div className="flex justify-between text-gray-600 dark:text-gray-400">
                                                <span>Original Subtotal ({cartItems.reduce((s, i) => s + i.quantity, 0)} items)</span>
                                                <span>{formatCurrency(originalSubtotal, cc)}</span>
                                            </div>
                                            <div className="flex justify-between text-emerald-600 dark:text-emerald-400">
                                                <div className="flex items-center gap-1.5">
                                                    <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    <span>Promotion Discount</span>
                                                </div>
                                                <span className="font-medium">-{formatCurrency(promoSavings, cc)}</span>
                                            </div>
                                        </>
                                    )}
                                    <div className="flex justify-between text-gray-600 dark:text-gray-400">
                                        <span>Subtotal ({cartItems.reduce((s, i) => s + i.quantity, 0)} items)</span>
                                        <span>{formatCurrency(subtotal, cc)}</span>
                                    </div>

                                    {(Number(appliedCoupon?.discount) || 0) > 0 && (
                                        <div className="flex justify-between text-emerald-600 dark:text-emerald-400">
                                            <div className="flex items-center gap-1.5">
                                                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <span>Coupon Discount</span>
                                            </div>
                                            <span className="font-medium">-{formatCurrency(appliedCoupon.discount, cc)}</span>
                                        </div>
                                    )}

                                    {(Number(appliedPromotion?.discount) || 0) > 0 && (
                                        <div className="flex justify-between text-emerald-600 dark:text-emerald-400">
                                            <div className="flex items-center gap-1.5">
                                                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <span>Promotion Discount</span>
                                            </div>
                                            <span className="font-medium">-{formatCurrency(appliedPromotion.discount, cc)}</span>
                                        </div>
                                    )}

                                <div className="border-t border-gray-200 dark:border-gray-800 pt-3 flex justify-between items-center text-gray-900 dark:text-gray-100">
                                    <span className="text-base font-bold">Total</span>
                                    <span className="text-lg font-extrabold">{formatCurrency(finalTotal, cc)}</span>
                                </div>
                                </div>

                                <Link href={`/store/${tenant.slug}/checkout`}
                                    style={primaryActionStyle}
                                    className="mt-5 w-full block text-center py-3 text-white font-medium transition-colors">
                                     {labels.checkout || 'Proceed to Checkout'}
                                </Link>

                                <Link href={`/store/${tenant.slug}`}
                                    className="mt-3 w-full block text-center py-2 text-[var(--theme-color,#3B82F6)] hover:opacity-80 font-medium text-sm">
                                     &larr; {labels.continue_shopping || 'Continue Shopping'}
                                </Link>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </ShopLayout>
    );
}
