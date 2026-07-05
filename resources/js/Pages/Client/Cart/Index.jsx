import { useState, useEffect } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';
import { assetUrl } from '@/Utils/helpers';
import { useCart } from '@/Hooks/useCart';
import axios from 'axios';
import { formatCurrency, getCurrencyConfig } from '@/Utils/currency';

export default function CartIndex({ cartItems: initialCartItems, subtotal: initialSubtotal, appliedPromotion: initialPromotion, appliedCoupon: initialCoupon, totalDiscount: initialDiscount }) {
    const cc = getCurrencyConfig(usePage().props.platform_setting, usePage().props.website_info);
    const { updateQuantity, removeItem, loading } = useCart();
    const [cartItems, setCartItems] = useState(initialCartItems || []);
    const [subtotal, setSubtotal] = useState(initialSubtotal || 0);
    const [updating, setUpdating] = useState(null);
    const [quantityDrafts, setQuantityDrafts] = useState({});

    // Promotion state
    const [appliedPromotion, setAppliedPromotion] = useState(initialPromotion || null);
    const [appliedCoupon, setAppliedCoupon] = useState(initialCoupon || null);
    const [totalDiscount, setTotalDiscount] = useState(initialDiscount || 0);
    const [promoCode, setPromoCode] = useState('');
    const [promoLoading, setPromoLoading] = useState(false);
    const [promoMessage, setPromoMessage] = useState(null);
    const [promoError, setPromoError] = useState(false);
    const [copiedId, setCopiedId] = useState(null);

    // Sync props to local state when Inertia updates the page (e.g., after clear cart)
    useEffect(() => {
        setCartItems(initialCartItems || []);
        setSubtotal(initialSubtotal || 0);
    }, [initialCartItems, initialSubtotal]);

    useEffect(() => {
        setAppliedPromotion(initialPromotion || null);
        setAppliedCoupon(initialCoupon || null);
        setTotalDiscount(initialDiscount || 0);
    }, [initialPromotion, initialCoupon, initialDiscount]);

    function copyToClipboard(text, id) {
        navigator.clipboard.writeText(text).then(() => {
            setCopiedId(id);
            setTimeout(() => setCopiedId(null), 2000);
        }).catch(() => {});
    }

    async function applyPromotion(code) {
        if (!code?.trim()) return;
        setPromoLoading(true);
        setPromoMessage(null);
        setPromoError(false);
        try {
            const res = await axios.post('/cart/apply-promotion', { code });
            if (res.data?.success) {
                setAppliedPromotion({
                    code: res.data.promotion_code,
                    name: res.data.promotion_name,
                    discount: res.data.discount,
                });
                setTotalDiscount(prev => Number(prev) + Number(res.data.discount));
                setPromoCode('');
                setPromoMessage(res.data.message || 'Promotion applied!');
            }
        } catch (err) {
            const msg = err.response?.data?.message || 'Failed to apply promotion.';
            setPromoMessage(msg);
            setPromoError(true);
        } finally {
            setPromoLoading(false);
            setTimeout(() => { setPromoMessage(null); setPromoError(false); }, 4000);
        }
    }

    async function removePromotion() {
        setPromoLoading(true);
        try {
            const res = await axios.post('/cart/remove-promotion');
            if (res.data?.success) {
                const removed = appliedPromotion?.discount || 0;
                setAppliedPromotion(null);
                setTotalDiscount(prev => Math.max(0, Number(prev) - Number(removed)));
                setPromoMessage(res.data.message || 'Promotion removed.');
            }
        } catch (err) {
            setPromoMessage('Failed to remove promotion.');
            setPromoError(true);
        } finally {
            setPromoLoading(false);
            setTimeout(() => { setPromoMessage(null); setPromoError(false); }, 4000);
        }
    }

    const finalTotal = Math.max(0, Number(subtotal) - Number(totalDiscount));

    async function handleUpdateQuantity(cartKey, newQty) {
        if (newQty < 1) newQty = 1;
        setUpdating(cartKey);
        
        const result = await updateQuantity(cartKey, newQty);
        
        if (result.cartItems) {
            setCartItems(result.cartItems);
            setSubtotal(result.subtotal);
        }
        
        if (result.success && newQty === 0) {
            setCartItems(prev => prev.filter(item => item.cart_key !== cartKey));
        }
        
        setUpdating(null);
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
        setUpdating(cartKey);
        
        const result = await removeItem(cartKey);
        
        if (result.cartItems) {
            setCartItems(result.cartItems);
            setSubtotal(result.subtotal);
        }
        
        setUpdating(null);
    }

    function handleClearCart() {
        setUpdating('clear');

        router.delete('/cart/clear', {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                window.dispatchEvent(new CustomEvent('cart-updated', { detail: { count: 0 } }));
            },
            onFinish: () => setUpdating(null),
        });
    }

    return (
        <ShopLayout>
            <Head title="Shopping Cart" />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="flex justify-between items-center mb-6 sm:mb-8">
                    <h1 className="text-xl sm:text-2xl font-bold text-gray-900">Shopping Cart</h1>
                    {cartItems?.length > 0 && (
                        <button
                            onClick={handleClearCart}
                            disabled={updating === 'clear'}
                            className="text-sm text-red-600 hover:text-red-800 disabled:opacity-50"
                        >
                            Clear Cart
                        </button>
                    )}
                </div>

                {!cartItems?.length ? (
                    <div className="text-center py-12 sm:py-16 bg-white rounded-xl border border-gray-200">
                        <i className="bi bi-cart-x text-5xl text-gray-300"></i>
                        <h3 className="mt-4 text-lg font-medium text-gray-900">Your cart is empty</h3>
                        <p className="mt-2 text-gray-500">Browse our products and add items to your cart.</p>
                        <Link
                            href="/"
                            className="mt-6 inline-block px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                        >
                            Continue Shopping
                        </Link>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        <div className="lg:col-span-2 space-y-4">
                            {/* Table header - hidden on mobile */}
                            <div className="hidden md:grid md:grid-cols-[5rem_1fr_8rem_7rem_7rem_2.5rem] gap-4 px-4 py-3 bg-gray-50 rounded-lg border border-gray-200 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                <div></div>
                                <div>Product</div>
                                <div className="text-center">Unit Price</div>
                                <div className="text-center">Quantity</div>
                                <div className="text-right">Subtotal</div>
                                <div></div>
                            </div>

                            {cartItems.map((item) => {
                                const lineSubtotal = Number(item.price) * Number(item.quantity);
                                return (
                                    <div key={item.cart_key} className="bg-white rounded-xl border border-gray-200 p-3 sm:p-4 hover:shadow-sm transition-shadow">
                                        {/* Mobile layout */}
                                        <div className="md:hidden flex gap-3">
                                            <Link
                                                href={`/client/product/${item.id}`}
                                                className="w-20 h-20 flex-shrink-0 bg-gray-100 rounded-lg overflow-hidden"
                                            >
                                                {item.photo1_url ? (
                                                    <img src={item.photo1_url} alt={item.name} className="w-full h-full object-cover" />
                                                ) : (
                                                    <div className="flex items-center justify-center h-full">
                                                        <i className="bi bi-image text-gray-400 text-xl"></i>
                                                    </div>
                                                )}
                                            </Link>
                                            <div className="flex-1 min-w-0">
                                                <Link href={`/client/product/${item.id}`} className="text-sm font-medium text-gray-900 hover:text-blue-600 line-clamp-2">
                                                    {item.name}
                                                </Link>
                                                {item.variant_name && (
                                                    <span className="inline-block mt-1 px-2 py-0.5 bg-purple-50 text-purple-700 border border-purple-200 rounded text-xs font-medium">
                                                        {item.variant_name}
                                                    </span>
                                                )}
                                                <div className="mt-2 space-y-1.5 text-sm">
                                                    <div className="flex justify-between">
                                                        <span className="text-gray-500">Unit Price</span>
                                                        <span className="text-gray-800 font-medium">{formatCurrency(item.price, cc)}</span>
                                                    </div>
                                                    <div className="flex items-center justify-between gap-2">
                                                        <span className="text-gray-500">Qty</span>
                                                        <div className="flex items-center border border-gray-300 rounded-lg">
                                                            <button
                                                                onClick={() => handleStepperClick(item.cart_key, -1)}
                                                                disabled={updating === item.cart_key}
                                                                className="px-2 py-1 text-gray-600 hover:bg-gray-100 rounded-l-lg disabled:opacity-50 transition-colors"
                                                            >
                                                                <i className="bi bi-dash"></i>
                                                            </button>
                                                            <input
                                                                type="text"
                                                                inputMode="numeric"
                                                                value={quantityDrafts[item.cart_key] ?? item.quantity}
                                                                onChange={(e) => handleDraftChange(item.cart_key, e.target.value)}
                                                                onBlur={() => commitDraft(item.cart_key)}
                                                                onKeyDown={(e) => { if (e.key === 'Enter') e.target.blur(); }}
                                                                disabled={updating === item.cart_key}
                                                                className="w-12 sm:w-14 text-center text-sm font-semibold text-gray-900 bg-transparent border-0 outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none disabled:opacity-50"
                                                            />
                                                            <button
                                                                onClick={() => handleStepperClick(item.cart_key, 1)}
                                                                disabled={updating === item.cart_key}
                                                                className="px-2 py-1 text-gray-600 hover:bg-gray-100 rounded-r-lg disabled:opacity-50 transition-colors"
                                                            >
                                                                <i className="bi bi-plus"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <div className="flex justify-between border-t border-gray-100 pt-1.5">
                                                        <span className="text-gray-700 font-semibold">Subtotal</span>
                                                        <span className="text-gray-900 font-bold">{formatCurrency(lineSubtotal, cc)}</span>
                                                    </div>
                                                </div>
                                                <button
                                                    onClick={() => handleRemoveItem(item.cart_key)}
                                                    disabled={updating === item.cart_key}
                                                    className="mt-2 text-xs text-red-500 hover:text-red-700 disabled:opacity-50 flex items-center gap-1"
                                                >
                                                    <i className="bi bi-trash"></i>
                                                    Remove
                                                </button>
                                            </div>
                                        </div>

                                        {/* Desktop layout */}
                                        <div className="hidden md:grid md:grid-cols-[5rem_1fr_8rem_7rem_7rem_2.5rem] gap-4 items-center">
                                            <Link
                                                href={`/client/product/${item.id}`}
                                                className="w-20 h-20 bg-gray-100 rounded-lg overflow-hidden"
                                            >
                                                {item.photo1_url ? (
                                                    <img src={item.photo1_url} alt={item.name} className="w-full h-full object-cover" />
                                                ) : (
                                                    <div className="flex items-center justify-center h-full">
                                                        <i className="bi bi-image text-gray-400 text-xl"></i>
                                                    </div>
                                                )}
                                            </Link>

                                            <div>
                                                <Link href={`/client/product/${item.id}`} className="text-sm font-medium text-gray-900 hover:text-blue-600 line-clamp-2">
                                                    {item.name}
                                                </Link>
                                                {item.variant_name && (
                                                    <span className="inline-block mt-1 px-2 py-0.5 bg-purple-50 text-purple-700 border border-purple-200 rounded text-xs font-medium">
                                                        {item.variant_name}
                                                    </span>
                                                )}
                                            </div>

                                            <div className="text-center">
                                                <span className="text-sm text-gray-800 font-medium">{formatCurrency(item.price, cc)}</span>
                                            </div>

                                            <div className="flex justify-center">
                                                <div className="flex items-center border border-gray-300 rounded-lg">
                                                    <button
                                                        onClick={() => handleStepperClick(item.cart_key, -1)}
                                                        disabled={updating === item.cart_key}
                                                        className="px-2.5 py-1.5 text-gray-600 hover:bg-gray-100 rounded-l-lg disabled:opacity-50 transition-colors"
                                                    >
                                                        <i className="bi bi-dash"></i>
                                                    </button>
                                                    <input
                                                        type="text"
                                                        inputMode="numeric"
                                                        value={quantityDrafts[item.cart_key] ?? item.quantity}
                                                        onChange={(e) => handleDraftChange(item.cart_key, e.target.value)}
                                                        onBlur={() => commitDraft(item.cart_key)}
                                                        onKeyDown={(e) => { if (e.key === 'Enter') e.target.blur(); }}
                                                        disabled={updating === item.cart_key}
                                                        className="w-14 text-center text-sm font-semibold text-gray-900 bg-transparent border-0 outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none disabled:opacity-50"
                                                    />
                                                    <button
                                                        onClick={() => handleStepperClick(item.cart_key, 1)}
                                                        disabled={updating === item.cart_key}
                                                        className="px-2.5 py-1.5 text-gray-600 hover:bg-gray-100 rounded-r-lg disabled:opacity-50 transition-colors"
                                                    >
                                                        <i className="bi bi-plus"></i>
                                                    </button>
                                                </div>
                                            </div>

                                            <div className="text-right">
                                                <span className="text-sm font-bold text-gray-900">{formatCurrency(lineSubtotal, cc)}</span>
                                            </div>

                                            <div className="flex justify-center">
                                                <button
                                                    onClick={() => handleRemoveItem(item.cart_key)}
                                                    disabled={updating === item.cart_key}
                                                    className="text-gray-300 hover:text-red-500 transition-colors p-1 disabled:opacity-50"
                                                    title="Remove item"
                                                >
                                                    <i className="bi bi-x-lg text-sm"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>

                        <div className="lg:col-span-1">
                            <div className="bg-white rounded-lg border border-gray-200 p-4 sm:p-6 sticky top-24">
                                <h2 className="text-lg font-semibold text-gray-900 mb-4">Order Summary</h2>

                                <div className="space-y-3 text-sm">
                                    <div className="flex justify-between text-gray-600">
                                        <span>Subtotal ({cartItems.reduce((s, i) => s + i.quantity, 0)} items)</span>
                                        <span>{formatCurrency(subtotal, cc)}</span>
                                    </div>

                                    {totalDiscount > 0 && (
                                        <div className="flex justify-between text-emerald-600">
                                            <div className="flex items-center gap-1.5">
                                                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <span>Discount</span>
                                            </div>
                                            <span className="font-medium">-{formatCurrency(totalDiscount, cc)}</span>
                                        </div>
                                    )}

                                    <div className="flex justify-between text-gray-600">
                                        <span>Shipping</span>
                                        <span className="text-green-600">Calculated at checkout</span>
                                    </div>

                                    <div className="border-t border-gray-200 pt-3 flex justify-between font-semibold text-gray-900">
                                        <span>Total</span>
                                        <span>{formatCurrency(finalTotal, cc)}</span>
                                    </div>
                                </div>

                                {/* Promo Code Section */}
                                <div className="mt-5 pt-4 border-t border-gray-200 space-y-3">
                                    {!appliedPromotion && !appliedCoupon && (
                                        <div>
                                            <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Have a promo code?</p>
                                            <div className="flex gap-2">
                                                <input
                                                    type="text"
                                                    value={promoCode}
                                                    onChange={e => setPromoCode(e.target.value)}
                                                    placeholder="Enter code"
                                                    maxLength={50}
                                                    className="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                    onKeyDown={e => e.key === 'Enter' && (e.preventDefault(), applyPromotion(promoCode))}
                                                />
                                                <button
                                                    type="button"
                                                    onClick={() => applyPromotion(promoCode)}
                                                    disabled={promoLoading || !promoCode.trim()}
                                                    className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                                >
                                                    {promoLoading ? (
                                                        <svg className="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                                        </svg>
                                                    ) : 'Apply'}
                                                </button>
                                            </div>
                                        </div>
                                    )}

                                    {appliedPromotion && (
                                        <div className="flex items-center justify-between bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-2.5">
                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-center gap-1.5">
                                                    <svg className="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    <p className="text-sm font-semibold text-emerald-800 truncate">{appliedPromotion.name}</p>
                                                </div>
                                                <div className="flex items-center gap-2 mt-0.5 ml-5">
                                                    <span className="text-xs font-mono font-medium text-emerald-700">{appliedPromotion.code}</span>
                                                    <button
                                                        type="button"
                                                        onClick={() => copyToClipboard(appliedPromotion.code, 'promo')}
                                                        className={`text-xs font-medium px-1.5 py-0.5 rounded transition-colors ${
                                                            copiedId === 'promo' ? 'text-green-700' : 'text-gray-400 hover:text-gray-600'
                                                        }`}
                                                        title="Copy code"
                                                    >
                                                        {copiedId === 'promo' ? (
                                                            <><svg className="w-3 h-3 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" /></svg> Copied</>
                                                        ) : (
                                                            <><svg className="w-3 h-3 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg> Copy</>
                                                        )}
                                                    </button>
                                                </div>
                                            </div>
                                            <button
                                                type="button"
                                                onClick={removePromotion}
                                                disabled={promoLoading}
                                                className="ml-2 px-2.5 py-1.5 text-xs font-medium text-red-600 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 disabled:opacity-50 transition-colors shrink-0"
                                            >
                                                Remove
                                            </button>
                                        </div>
                                    )}

                                    {promoMessage && (
                                        <div className={`text-xs px-3 py-2 rounded-lg flex items-center gap-1.5 ${
                                            promoError ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                                        }`}>
                                            <svg className="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                {promoError ? (
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                ) : (
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                )}
                                            </svg>
                                            {promoMessage}
                                        </div>
                                    )}
                                </div>

                                <Link
                                    href="/checkout"
                                    className="mt-5 w-full block text-center py-3 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition-colors"
                                >
                                    Proceed to Checkout
                                </Link>

                                <Link
                                    href="/"
                                    className="mt-3 w-full block text-center py-2 text-blue-600 hover:text-blue-800 font-medium text-sm"
                                >
                                    ← Continue Shopping
                                </Link>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </ShopLayout>
    );
}