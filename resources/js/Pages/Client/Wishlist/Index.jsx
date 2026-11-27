import { useState, useCallback } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { Heart, ShoppingCart, Trash2, Zap } from 'lucide-react';
import ShopLayout from '@/Layouts/ShopLayout';
import BackToTopButton from '@/Components/BackToTopButton';
import { useCart } from '@/Hooks/useCart';
import { useWishlist } from '@/Hooks/useWishlist';
import { useBuyNow, buyNowKey } from '@/Hooks/useBuyNow';
import VariantSelectModal from '@/Components/VariantSelectModal';
import { formatCurrency, getCurrencyConfig } from '@/Utils/currency';

const LOW_STOCK_THRESHOLD = 10;

/* ============================================================
 * DESKTOP WISHLIST CARD (unchanged desktop design)
 * ============================================================ */
function DesktopWishlistCard({ item, onRemove, onAddToCart, onSelectVariant, addingId = null, processingId = null }) {
    const cc = getCurrencyConfig(usePage().props.platform_setting, usePage().props.website_info);
    const [added, setAdded] = useState(false);
    const product = item.product;
    const storeSlug = usePage().props.tenant?.slug;
    const productUrl = storeSlug ? `/store/${storeSlug}/products/${product.id}` : `/client/product/${product.id}`;
    const { buyNow, buyingKey } = useBuyNow();

    if (!product) return null;

    const effectiveStock = product.effective_stock ?? product.stock ?? 0;
    const isOutOfStock = effectiveStock <= 0;
    const isLowStock = !isOutOfStock && effectiveStock < LOW_STOCK_THRESHOLD;
    const hasPromotion = product.promotion_price && product.promotion_price < product.price;
    const displayPrice = hasPromotion ? product.promotion_price : product.price;
    const originalPrice = hasPromotion ? product.price : null;

    const handleAddToCart = async () => {
        setAdded(true);
        await onAddToCart(product.id);
        setTimeout(() => setAdded(false), 2000);
    };

    const handleBuyNow = (e) => {
        if (e) { e.preventDefault(); e.stopPropagation(); }
        if (isOutOfStock) return;
        if (product.is_variable) {
            if (onSelectVariant) onSelectVariant(product);
            return;
        }
        buyNow({ productId: product.id, quantity: 1 });
    };

    return (
        <div className="group relative bg-white dark:bg-gray-900 rounded-xl border border-gray-100 dark:border-gray-800/80 shadow-sm hover:shadow-md hover:border-gray-200 transition-all duration-300 overflow-hidden flex flex-col">
            <Link href={productUrl} className="block">
                <div className="relative h-[140px] sm:h-[160px] lg:h-[180px] bg-gray-100 dark:bg-gray-800 overflow-hidden">
                    {product.photo1_url ? (
                        <img
                            src={product.photo1_url}
                            alt={product.name}
                            className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                        />
                    ) : (
                        <div className="absolute inset-0 flex items-center justify-center">
                            <i className="bi bi-image text-2xl text-gray-300"></i>
                        </div>
                    )}

                    {isOutOfStock ? (
                        <div className="absolute inset-0 bg-black/50 flex items-center justify-center">
                            <span className="px-3 py-1.5 bg-red-500 text-white text-xs font-semibold rounded-full">
                                Out of Stock
                            </span>
                        </div>
                    ) : (
                        <div className="absolute top-2 left-2 px-2 py-0.5 bg-white dark:bg-gray-900/90 text-gray-700 dark:text-gray-300 text-[10px] font-medium rounded-full shadow-sm z-10">
                            {product.is_variable ? 'Multiple Options' : product.is_combo ? 'Bundle' : 'Single'}
                        </div>
                    )}

                    {product.promotion_price && product.promotion_price < product.price && (
                        <div className="absolute top-2 right-2 px-2 py-0.5 bg-red-500 text-white text-[10px] font-bold rounded-full shadow-sm">
                            {product.promotion_badge}
                        </div>
                    )}
                </div>
            </Link>

            <div className="p-3 flex flex-col gap-0">
                <Link href={productUrl}>
                    {product.category?.name && (
                        <span className="inline-block max-w-[8rem] truncate px-2 py-0.5 bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 text-[10px] font-medium rounded-full mb-1">
                            {product.category.name}
                        </span>
                    )}
                    <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100 line-clamp-2 leading-snug group-hover:text-blue-600 transition-colors">
                        {product.name}
                    </h3>
                    {product.brand?.name && (
                        <p className="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                            {product.brand.name}
                        </p>
                    )}
                </Link>

                <div className="mt-1.5">
                    <div className="flex items-baseline gap-1 flex-wrap">
                        <span className="text-[17px] font-extrabold text-gray-900 dark:text-gray-100 leading-tight">
                            {Number(displayPrice).toLocaleString()}
                        </span>
                        <span className="text-[10px] text-gray-400 dark:text-gray-500 font-medium">{cc.code}</span>
                        {originalPrice && (
                            <span className="text-xs text-gray-400 dark:text-gray-500 line-through w-full sm:w-auto leading-tight">
                                {formatCurrency(originalPrice, cc)}
                            </span>
                        )}
                    </div>
                </div>

                {!isOutOfStock && (
                    <p className={`text-[11px] font-medium mt-1 leading-tight ${
                        isLowStock ? 'text-orange-600' : 'text-green-600'
                    }`}>
                        {isLowStock ? 'Low Stock' : 'In Stock'}
                    </p>
                )}

                <div className="mt-2.5 space-y-1.5">
                    {isOutOfStock ? (
                        <button
                            disabled
                            className="w-full flex items-center justify-center gap-2 px-3 py-2 bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-500 rounded-lg text-sm font-medium cursor-not-allowed"
                        >
                            <ShoppingCart className="w-3.5 h-3.5" />
                            Out of Stock
                        </button>
                    ) : (
                        <button
                            onClick={handleAddToCart}
                            disabled={addingId === product.id}
                            className={`w-full flex items-center justify-center gap-2 px-3 py-2 rounded-lg text-sm font-semibold transition-all duration-200 ${
                                added
                                    ? 'bg-green-600 text-white'
                                    : 'bg-blue-600 text-white hover:bg-blue-700 active:scale-[0.98] shadow-sm hover:shadow'
                            }`}
                        >
                            {addingId === product.id ? (
                                <>
                                    <svg className="animate-spin h-3.5 w-3.5" fill="none" viewBox="0 0 24 24">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                    </svg>
                                    Adding...
                                </>
                            ) : added ? (
                                <>
                                    <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                    </svg>
                                    Added to Cart
                                </>
                            ) : (
                                <>
                                    <ShoppingCart className="w-3.5 h-3.5" />
                                    Add to Cart
                                </>
                            )}
                        </button>
                    )}

                    {!isOutOfStock && (
                        <button
                            onClick={handleBuyNow}
                            disabled={buyingKey === buyNowKey(product.id, null)}
                            className="w-full flex items-center justify-center gap-1.5 px-3 py-2 border border-blue-200 dark:border-blue-800 text-blue-600 dark:text-blue-400 rounded-lg text-xs sm:text-sm font-semibold hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-all duration-200 disabled:opacity-50"
                        >
                            {buyingKey === buyNowKey(product.id, null) ? (
                                <svg className="animate-spin h-3.5 w-3.5" fill="none" viewBox="0 0 24 24">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                </svg>
                            ) : (
                                <Zap className="w-3.5 h-3.5" />
                            )}
                            {buyingKey === buyNowKey(product.id, null) ? 'Processing...' : 'Buy Now'}
                        </button>
                    )}

                    <button
                        onClick={() => onRemove(item.product_id)}
                        disabled={processingId === product.id}
                        className="w-full flex items-center justify-center gap-2 px-3 py-2 border border-gray-200 dark:border-gray-800 text-gray-500 dark:text-gray-400 rounded-lg text-xs font-medium hover:bg-red-50 hover:text-red-600 hover:border-red-200 transition-all duration-200 disabled:opacity-50"
                    >
                        {processingId === product.id ? (
                            <svg className="animate-spin h-3.5 w-3.5" fill="none" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                        ) : (
                            <Trash2 className="w-3.5 h-3.5" />
                        )}
                        Remove
                    </button>
                </div>
            </div>
        </div>
    );
}

/* ============================================================
 * MOBILE WISHLIST CARD (dedicated mobile experience)
 * ============================================================ */
function MobileWishlistCard({ item, onRemove, onAddToCart, onSelectVariant, addingId = null, processingId = null }) {
    const cc = getCurrencyConfig(usePage().props.platform_setting, usePage().props.website_info);
    const [added, setAdded] = useState(false);
    const product = item.product;
    const storeSlug = usePage().props.tenant?.slug;
    const productUrl = storeSlug ? `/store/${storeSlug}/products/${product.id}` : `/client/product/${product.id}`;
    const { buyNow, buyingKey } = useBuyNow();

    if (!product) return null;

    const effectiveStock = product.effective_stock ?? product.stock ?? 0;
    const isOutOfStock = effectiveStock <= 0;
    const isLowStock = !isOutOfStock && effectiveStock < LOW_STOCK_THRESHOLD;

    const isVariable = !!product.is_variable;
    const variantPrices = (product.variants || [])
        .map((v) => Number(v.price))
        .filter((p) => Number.isFinite(p));
    const minVariantPrice = variantPrices.length ? Math.min(...variantPrices) : null;
    const hasPromotion = product.promotion_price && product.promotion_price < (isVariable ? (minVariantPrice ?? product.price) : product.price);

    let displayPrice;
    let priceLabel = '';
    if (isVariable) {
        displayPrice = hasPromotion ? product.promotion_price : (minVariantPrice ?? product.price);
        const maxPrice = variantPrices.length ? Math.max(...variantPrices) : null;
        if (displayPrice !== null && maxPrice !== null && displayPrice !== maxPrice) priceLabel = 'From';
    } else {
        displayPrice = hasPromotion ? product.promotion_price : product.price;
    }

    const handleAddToCart = async () => {
        setAdded(true);
        await onAddToCart(product.id);
        setTimeout(() => setAdded(false), 2000);
    };

    const handleBuyNow = (e) => {
        if (e) { e.preventDefault(); e.stopPropagation(); }
        if (isOutOfStock) return;
        if (product.is_variable) {
            if (onSelectVariant) onSelectVariant(product);
            return;
        }
        buyNow({ productId: product.id, quantity: 1 });
    };

    return (
        <div className="bg-white dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800 shadow-sm overflow-hidden">
            <div className="flex gap-3 p-3">
                <Link href={productUrl} className="shrink-0">
                    <div className="w-[88px] h-[88px] rounded-xl bg-gray-100 dark:bg-gray-800 overflow-hidden flex items-center justify-center">
                        {product.photo1_url ? (
                            <img
                                src={product.photo1_url}
                                alt={product.name}
                                className="w-full h-full object-contain"
                            />
                        ) : (
                            <i className="bi bi-image text-2xl text-gray-300"></i>
                        )}
                    </div>
                </Link>

                <div className="flex-1 min-w-0 flex flex-col">
                    <div className="flex items-start justify-between gap-2">
                        <div className="min-w-0 flex-1">
                            {product.category?.name && (
                                <span className="inline-block max-w-full truncate px-2 py-0.5 bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 text-[10px] font-medium rounded-full mb-1">
                                    {product.category.name}
                                </span>
                            )}
                            <Link href={productUrl}>
                                <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100 line-clamp-2 leading-snug">
                                    {product.name}
                                </h3>
                            </Link>
                            {product.brand?.name && (
                                <p className="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5 truncate">
                                    {product.brand.name}
                                </p>
                            )}
                        </div>
                        <button
                            onClick={() => onRemove(item.product_id)}
                            disabled={processingId === product.id}
                            className="shrink-0 p-1.5 text-gray-400 hover:text-red-500 transition-colors disabled:opacity-50"
                            aria-label="Remove from wishlist"
                        >
                            {processingId === product.id ? (
                                <svg className="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                </svg>
                            ) : (
                                <Trash2 className="w-4 h-4" />
                            )}
                        </button>
                    </div>

                    <div className="mt-1 flex items-baseline gap-1 flex-wrap">
                        <span className="text-base font-extrabold text-gray-900 dark:text-gray-100 leading-tight">
                            {priceLabel ? `${priceLabel} ` : ''}{Number(displayPrice).toLocaleString()}
                        </span>
                        <span className="text-[10px] text-gray-400 dark:text-gray-500 font-medium">{cc.code}</span>
                        {product.is_variable && (
                            <span className="ml-1 px-1.5 py-0.5 bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 text-[10px] font-medium rounded">
                                Multiple Options
                            </span>
                        )}
                    </div>

                    {!isOutOfStock && (
                        <p className={`text-[11px] font-medium mt-0.5 ${isLowStock ? 'text-orange-600' : 'text-green-600'}`}>
                            {isLowStock ? 'Low Stock' : 'In Stock'}
                        </p>
                    )}
                    {isOutOfStock && (
                        <p className="text-[11px] font-medium mt-0.5 text-red-500">Out of Stock</p>
                    )}

                    <div className="mt-2 flex gap-2 min-w-0">
                        {isOutOfStock ? (
                            <button
                                disabled
                                className="flex-1 h-10 flex items-center justify-center gap-1.5 px-2 bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-500 rounded-lg text-xs font-medium cursor-not-allowed whitespace-nowrap"
                            >
                                <ShoppingCart className="w-3.5 h-3.5 shrink-0" />
                                Out of Stock
                            </button>
                        ) : (
                            <>
                                <button
                                    onClick={handleAddToCart}
                                    disabled={addingId === product.id}
                                    className={`flex-1 h-10 flex items-center justify-center gap-1.5 px-2 rounded-lg text-xs font-semibold transition-all duration-200 whitespace-nowrap ${
                                        added
                                            ? 'bg-green-600 text-white'
                                            : 'bg-blue-600 text-white hover:bg-blue-700 active:scale-[0.98]'
                                    } disabled:opacity-50`}
                                >
                                    {addingId === product.id ? (
                                        <svg className="animate-spin h-3.5 w-3.5" fill="none" viewBox="0 0 24 24">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                        </svg>
                                    ) : (
                                        <ShoppingCart className="w-3.5 h-3.5" />
                                    )}
                                    {addingId === product.id ? 'Adding...' : added ? 'Added' : 'Add to Cart'}
                                </button>

                                <button
                                    onClick={handleBuyNow}
                                    disabled={buyingKey === buyNowKey(product.id, null)}
                                    className="flex-1 h-10 flex items-center justify-center gap-1.5 px-2 border border-blue-200 dark:border-blue-800 text-blue-600 dark:text-blue-400 rounded-lg text-xs font-semibold hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-all duration-200 disabled:opacity-50 whitespace-nowrap"
                                >
                                    {buyingKey === buyNowKey(product.id, null) ? (
                                        <svg className="animate-spin h-3.5 w-3.5" fill="none" viewBox="0 0 24 24">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                        </svg>
                                    ) : (
                                        <Zap className="w-3.5 h-3.5" />
                                    )}
                                    {buyingKey === buyNowKey(product.id, null) ? 'Processing...' : 'Buy Now'}
                                </button>
                            </>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}

export default function WishlistIndex({ wishlistItems = [] }) {
    const { addToCart, addingId } = useCart();
    const { removeFromWishlist, moveAllToCart, clearWishlist, processingId } = useWishlist();
    const [items, setItems] = useState(wishlistItems);
    const [movingAll, setMovingAll] = useState(false);
    const [allMoved, setAllMoved] = useState(false);
    const [clearing, setClearing] = useState(false);
    const [variableProduct, setVariableProduct] = useState(null);

    const handleAddToCart = useCallback(async (productId, meta) => {
        await addToCart(productId, 1, undefined, meta || null);
    }, [addToCart]);

    const handleSelectVariant = useCallback((product) => {
        setVariableProduct(product);
    }, []);

    const handleModalAddToCart = useCallback(async (variantId, quantity, meta) => {
        if (!variableProduct) return { error: 'No product selected.' };
        return addToCart(variableProduct.id, quantity, variantId, { name: variableProduct.name, ...(meta || {}) });
    }, [variableProduct, addToCart]);

    const handleRemove = useCallback(async (productId) => {
        const result = await removeFromWishlist(productId);
        if (result && result.success !== false && !result.error) {
            setItems(prev => prev.filter(i => (i.product_id ?? i.product?.id) !== productId));
        } else {
            alert(result?.error || 'Failed to remove item. Please try again.');
        }
    }, [removeFromWishlist]);

    const handleMoveAllToCart = async () => {
        setMovingAll(true);
        const result = await moveAllToCart();
        if (result && result.success) {
            setAllMoved(true);
            setTimeout(() => setAllMoved(false), 3000);
            setItems([]);
        }
        setMovingAll(false);
    };

    const handleClear = async () => {
        if (!window.confirm('Clear all items from your wishlist?')) return;
        setClearing(true);
        const result = await clearWishlist();
        if (result && result.success !== false && !result.error) {
            setItems([]);
        } else {
            alert(result?.error || 'Failed to clear wishlist. Please try again.');
        }
        setClearing(false);
    };

    return (
        <ShopLayout>
            <Head title="My Wishlist" />

            {/* ===== MOBILE LAYOUT (md:hidden) ===== */}
            <div className="md:hidden">
                <div className="px-4 pt-4 pb-24">
                    <div className="flex items-center justify-between gap-3 mb-3">
                        <div className="flex items-center gap-2">
                            <Heart className="w-5 h-5 text-red-500 fill-red-500" />
                            <h1 className="text-lg font-bold text-gray-900 dark:text-gray-100">My Wishlist</h1>
                            <span className="px-2 py-0.5 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 text-xs font-semibold rounded-full">
                                {items.length}
                            </span>
                        </div>
                        {items.length > 0 && (
                            <div className="flex items-center gap-2">
                                <button
                                    onClick={handleMoveAllToCart}
                                    disabled={movingAll}
                                    className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all duration-200 ${
                                        allMoved
                                            ? 'bg-green-600 text-white'
                                            : 'bg-blue-600 text-white hover:bg-blue-700 shadow-sm disabled:opacity-50'
                                    }`}
                                >
                                    {movingAll ? (
                                        <svg className="animate-spin h-3.5 w-3.5" fill="none" viewBox="0 0 24 24">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                        </svg>
                                    ) : (
                                        <ShoppingCart className="w-3.5 h-3.5" />
                                    )}
                                    {allMoved ? 'Added' : 'Move All'}
                                </button>
                                <button
                                    onClick={handleClear}
                                    disabled={clearing}
                                    className="flex items-center gap-1.5 px-3 py-1.5 border border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-400 rounded-lg text-xs font-semibold hover:bg-red-50 hover:text-red-600 hover:border-red-200 transition-all duration-200 disabled:opacity-50"
                                >
                                    {clearing ? (
                                        <svg className="animate-spin h-3.5 w-3.5" fill="none" viewBox="0 0 24 24">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                        </svg>
                                    ) : (
                                        <Trash2 className="w-3.5 h-3.5" />
                                    )}
                                    Clear
                                </button>
                            </div>
                        )}
                    </div>

                    {items.length === 0 ? (
                        <div className="text-center py-12 bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm">
                            <Heart className="w-10 h-10 mx-auto text-gray-300" />
                            <h3 className="mt-3 text-base font-semibold text-gray-900 dark:text-gray-100">No items in wishlist</h3>
                            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">Your wishlist will appear here once you save products.</p>
                            <Link href="/products" className="mt-4 inline-block px-5 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-medium">Browse Products</Link>
                        </div>
                    ) : (
                        <div className="space-y-3">
                            {items.map((item) => (
                                <MobileWishlistCard
                                    key={item.id}
                                    item={item}
                                    onRemove={handleRemove}
                                    onAddToCart={handleAddToCart}
                                    onSelectVariant={handleSelectVariant}
                                    addingId={addingId}
                                    processingId={processingId}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </div>

            {/* ===== DESKTOP LAYOUT (hidden on mobile) ===== */}
            <div className="hidden md:block">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                                <Heart className="w-6 h-6 text-red-500 fill-red-500" />
                                My Wishlist
                            </h1>
                            <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                {items.length} {items.length === 1 ? 'item' : 'items'}
                            </p>
                        </div>

                        {items.length > 0 && (
                            <div className="flex items-center gap-3">
                                <button
                                    onClick={handleMoveAllToCart}
                                    disabled={movingAll}
                                    className={`flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold transition-all duration-200 ${
                                        allMoved
                                            ? 'bg-green-600 text-white'
                                            : 'bg-blue-600 text-white hover:bg-blue-700 shadow-sm disabled:opacity-50'
                                    }`}
                                >
                                    {movingAll ? (
                                        <svg className="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                        </svg>
                                    ) : allMoved ? (
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                        </svg>
                                    ) : (
                                        <ShoppingCart className="w-4 h-4" />
                                    )}
                                    {allMoved ? 'Added to Cart' : 'Move All to Cart'}
                                </button>

                                <button
                                    onClick={handleClear}
                                    disabled={clearing}
                                    className="flex items-center gap-2 px-4 py-2.5 border border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-400 rounded-xl text-sm font-semibold hover:bg-red-50 hover:text-red-600 hover:border-red-200 transition-all duration-200 disabled:opacity-50"
                                >
                                    {clearing ? (
                                        <svg className="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                        </svg>
                                    ) : (
                                        <Trash2 className="w-4 h-4" />
                                    )}
                                    Clear All
                                </button>
                            </div>
                        )}
                    </div>

                    {items.length === 0 ? (
                        <div className="text-center py-12 bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm">
                            <Heart className="w-10 h-10 mx-auto text-gray-300" />
                            <h3 className="mt-3 text-base font-semibold text-gray-900 dark:text-gray-100">No items in wishlist</h3>
                            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">Your wishlist will appear here once you save products.</p>
                            <Link href="/products" className="mt-4 inline-block px-5 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-medium">Browse Products</Link>
                        </div>
                    ) : (
                        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4">
                            {items.map((item) => (
                                <DesktopWishlistCard
                                    key={item.id}
                                    item={item}
                                    onRemove={handleRemove}
                                    onAddToCart={handleAddToCart}
                                    onSelectVariant={handleSelectVariant}
                                    addingId={addingId}
                                    processingId={processingId}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </div>

            <BackToTopButton />

            {variableProduct && (
                <VariantSelectModal
                    product={variableProduct}
                    onClose={() => setVariableProduct(null)}
                    onAddToCart={handleModalAddToCart}
                />
            )}
        </ShopLayout>
    );
}
