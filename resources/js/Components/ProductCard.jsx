import { useState, useEffect, memo } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import { Heart } from 'lucide-react';
import { useWishlist } from '@/Hooks/useWishlist';

const LOW_STOCK_THRESHOLD = 10;

function getEffectiveStock(product) {
    return product.effective_stock ?? product.stock ?? 0;
}

function getStockStatus(product) {
    const stock = getEffectiveStock(product);
    if (stock <= 0) return 'out_of_stock';
    if (stock < LOW_STOCK_THRESHOLD) return 'low_stock';
    return 'in_stock';
}

function getDisplayPrice(product) {
    if (product.promotion_price !== undefined && product.promotion_price !== null) {
        const effPrice = product.promotion_price;
        return {
            display: Number(effPrice).toLocaleString(),
            original: Number(getBasePrice(product)).toLocaleString(),
            savings: Math.round(getBasePrice(product) - effPrice),
        };
    }

    if (product.is_variable) {
        const summary = product.display_price_summary;
        if (summary) {
            return { display: summary.display, label: summary.label };
        }
        const priceRange = product.price_range;
        if (priceRange && priceRange.length >= 2) {
            const min = Number(priceRange[0]).toLocaleString();
            const max = Number(priceRange[1]).toLocaleString();
            if (priceRange[0] === priceRange[1]) {
                return { display: `${min}`, label: '' };
            }
            return { display: `${min}`, label: 'From' };
        }
        return { display: Number(product.price ?? 0).toLocaleString(), label: 'From' };
    }

    if (product.is_combo) {
        const summary = product.display_price_summary;
        if (summary) {
            return { display: summary.display, savings: summary.savings > 0 ? summary.savings : null };
        }
        return { display: Number(product.price ?? 0).toLocaleString() };
    }

    const price = Number(product.price ?? 0).toLocaleString();
    return { display: price };
}

function getBasePrice(product) {
    if (product.is_variable) {
        const summary = product.display_price_summary;
        if (summary) return summary.min ?? product.price ?? 0;
        const range = product.price_range;
        if (range && range.length >= 2) return range[0];
        return product.price ?? 0;
    }
    if (product.is_combo) {
        const summary = product.display_price_summary;
        if (summary) return summary.price ?? product.price ?? 0;
        return product.price ?? 0;
    }
    return product.price ?? 0;
}

const StockBadge = memo(function StockBadge({ status }) {
    if (status === 'out_of_stock') {
        return (
            <div className="absolute inset-0 bg-black/50 flex items-center justify-center z-10">
                <span className="px-3 py-1.5 bg-red-500 text-white text-xs font-semibold rounded-full shadow-lg">
                    Out of Stock
                </span>
            </div>
        );
    }
    return null;
});

const ProductTypeBadge = memo(function ProductTypeBadge({ isVariable, isCombo }) {
    let label;
    if (isVariable) {
        label = 'Multiple Options';
    } else if (isCombo) {
        label = 'Bundle';
    } else {
        label = 'Single';
    }
    return (
        <div className="absolute top-2 left-2 px-2 py-0.5 bg-white/90 text-gray-700 text-[10px] font-medium rounded-full shadow-sm z-10">
            {label}
        </div>
    );
});

const PriceDisplay = memo(function PriceDisplay({ product, displayPrice }) {
    const { display, original, savings, label } = displayPrice || {};

    if (product.is_variable) {
        return (
            <div className="mt-1.5">
                <div className="flex items-baseline gap-1 flex-wrap">
                    {label && (
                        <span className="text-[10px] text-gray-500 font-medium uppercase tracking-wide">{label}</span>
                    )}
                    <span className="text-[17px] font-extrabold text-gray-900 leading-tight">
                        {display || Number(product.price ?? 0).toLocaleString()}
                    </span>
                    <span className="text-[10px] text-gray-400 font-medium">MMK</span>
                </div>
                {original && (
                    <span className="text-xs text-gray-400 line-through w-full sm:w-auto block leading-tight">
                        {original} MMK
                    </span>
                )}
                {savings > 0 && (
                    <p className="text-[10px] text-green-600 font-semibold flex items-center gap-1 leading-tight">
                        <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Save {Number(savings).toLocaleString()} MMK
                    </p>
                )}
            </div>
        );
    }

    if (product.is_combo) {
        return (
            <div className="mt-1.5">
                <div className="flex items-baseline gap-1 flex-wrap">
                    <span className="text-[17px] font-extrabold text-gray-900 leading-tight">
                        {display || Number(product.price ?? 0).toLocaleString()}
                    </span>
                    <span className="text-[10px] text-gray-400 font-medium">MMK</span>
                    {product.display_price_summary?.base_price > 0 && (
                        <span className="text-xs text-gray-400 line-through w-full sm:w-auto leading-tight">
                            {Number(product.display_price_summary.base_price).toLocaleString()} MMK
                        </span>
                    )}
                </div>
                {product.display_price_summary?.savings > 0 && (
                    <p className="text-[10px] text-green-600 font-semibold flex items-center gap-1 leading-tight">
                        <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Bundle Save {Number(product.display_price_summary.savings).toLocaleString()} MMK
                    </p>
                )}
                {savings > 0 && (
                    <p className="text-[10px] text-green-600 font-semibold flex items-center gap-1 leading-tight">
                        <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Save {Number(savings).toLocaleString()} MMK
                    </p>
                )}
            </div>
        );
    }

    return (
        <div className="mt-1.5">
            <div className="flex items-baseline gap-1 flex-wrap">
                <span className="text-[17px] font-extrabold text-gray-900 leading-tight">
                    {display || Number(product.price ?? 0).toLocaleString()}
                </span>
                <span className="text-[10px] text-gray-400 font-medium">MMK</span>
                {original && (
                    <span className="text-xs text-gray-400 line-through w-full sm:w-auto leading-tight">
                        {original} MMK
                    </span>
                )}
            </div>
            {savings > 0 && (
                <p className="text-[10px] text-green-600 font-semibold flex items-center gap-1 leading-tight">
                    <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Save {Number(savings).toLocaleString()} MMK
                </p>
            )}
        </div>
    );
});

const ProductCard = memo(function ProductCard({ product, onAddToCart, onSelectVariant, addingId = null }) {
    const { props } = usePage();
    const { auth, website_info, wishlisted_ids = [], tenant } = props;
    const wishlistEnabled = website_info?.enable_wishlist !== false;
    const { toggleWishlist } = useWishlist();
    const productUrl = tenant?.slug
        ? `/store/${tenant.slug}/products/${product.id}`
        : `/client/product/${product.id}`;

    const [imageLoaded, setImageLoaded] = useState(false);
    const [isAdding, setIsAdding] = useState(false);
    const [wishlistAnim, setWishlistAnim] = useState(false);
    const [optimisticWishlisted, setOptimisticWishlisted] = useState(
        wishlisted_ids.includes(product.id)
    );

    useEffect(() => {
        setOptimisticWishlisted(wishlisted_ids.includes(product.id));
    }, [wishlisted_ids, product.id]);

    const stockStatus = getStockStatus(product);
    const isOutOfStock = stockStatus === 'out_of_stock';
    const displayPrice = getDisplayPrice(product);
    const hasPromotion = product.promotion_price !== undefined
        && product.promotion_price !== null
        && product.promotion_price < getBasePrice(product);

    const handleAddToCart = async (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (addingId === product.id) return;

        if (product.is_variable) {
            if (onSelectVariant) {
                onSelectVariant(product);
            }
            return;
        }

        setIsAdding(true);
        if (onAddToCart) {
            await onAddToCart(product.id);
        }
        setIsAdding(false);
    };

    const handleWishlistToggle = (e) => {
        e.preventDefault();
        e.stopPropagation();

        if (!auth?.user) {
            router.visit(tenant?.slug ? `/store/${tenant.slug}/login` : '/login');
            return;
        }

        setOptimisticWishlisted((prev) => !prev);
        setWishlistAnim(true);
        setTimeout(() => setWishlistAnim(false), 400);

        toggleWishlist(product.id, optimisticWishlisted);
    };

    return (
        <div className="group relative bg-white rounded-xl border border-gray-100/80 shadow-sm hover:shadow-md hover:border-gray-200 transition-all duration-300 overflow-hidden flex flex-col"
             onMouseEnter={(e) => { e.currentTarget.style.borderColor = 'var(--theme-color, #3B82F6)'; }}
             onMouseLeave={(e) => { e.currentTarget.style.borderColor = ''; }}
        >
            <Link href={productUrl} className="block">
                <div className="relative h-[140px] sm:h-[160px] lg:h-[180px] bg-gray-100 overflow-hidden">
                    {product.photo1_url ? (
                        <>
                            <img
                                src={product.photo1_url}
                                alt={product.name}
                                className={`w-full h-full object-cover transition-transform duration-500 group-hover:scale-105 ${
                                    imageLoaded ? 'opacity-100' : 'opacity-0'
                                }`}
                                onLoad={() => setImageLoaded(true)}
                            />
                            {!imageLoaded && (
                                <div className="absolute inset-0 flex items-center justify-center">
                                    <div className="w-6 h-6 border-2 border-gray-200 rounded-full animate-spin" style={{ borderTopColor: 'var(--theme-color, #3B82F6)' }}></div>
                                </div>
                            )}
                        </>
                    ) : (
                        <div className="absolute inset-0 flex items-center justify-center">
                            <div className="text-center">
                                <i className="bi bi-image text-2xl text-gray-300"></i>
                                <p className="mt-1 text-xs text-gray-400">No Image</p>
                            </div>
                        </div>
                    )}

                    <StockBadge status={stockStatus} />

                    {!isOutOfStock && (
                        <ProductTypeBadge isVariable={product.is_variable} isCombo={product.is_combo} />
                    )}

                    {hasPromotion && (
                        <div className="absolute top-11 right-2 px-2 py-0.5 bg-red-500 text-white text-[10px] font-bold rounded-full shadow-sm z-10">
                            {product.promotion_badge}
                        </div>
                    )}

                    {!hasPromotion && Number(product.discount_percentage ?? 0) > 0 && (
                        <div className="absolute top-11 right-2 px-2 py-0.5 bg-red-500 text-white text-[10px] font-bold rounded-full shadow-sm z-10">
                            -{product.discount_percentage}%
                        </div>
                    )}

                    {wishlistEnabled && (
                        <button
                            onClick={handleWishlistToggle}
                            className={`absolute top-2 right-2 z-10 w-7 h-7 rounded-full flex items-center justify-center transition-all duration-200 ${
                                optimisticWishlisted
                                    ? 'shadow-sm'
                                    : 'bg-white/80 backdrop-blur-sm shadow-sm hover:shadow'
                            } ${wishlistAnim ? 'scale-110' : 'scale-100'}`}
                            style={optimisticWishlisted ? { backgroundColor: 'rgba(var(--theme-color-rgb, 59, 130, 246), 0.1)' } : {}}
                            aria-label={optimisticWishlisted ? 'Remove from wishlist' : 'Add to wishlist'}
                        >
                            <Heart
                                className={`w-[14px] h-[14px] transition-all duration-300 ${
                                    optimisticWishlisted
                                        ? 'scale-110'
                                        : 'fill-none hover:text-red-400'
                                }`}
                                style={optimisticWishlisted ? { fill: 'var(--theme-color, #3B82F6)', color: 'var(--theme-color, #3B82F6)' } : { color: 'var(--theme-color, #3B82F6)' }}
                            />
                        </button>
                    )}
                </div>
            </Link>

            <div className="p-3 flex flex-col gap-0">
                <Link href={productUrl}>
                    {product.category?.name && (
                        <span className="inline-block max-w-[8rem] truncate px-2 py-0.5 bg-gray-100 text-gray-500 text-[10px] font-medium rounded-full mb-1">
                            {product.category.name}
                        </span>
                    )}
                    <h3 className="text-sm font-semibold text-gray-900 line-clamp-2 leading-snug group-hover:text-theme transition-colors">
                        {product.name}
                    </h3>
                    {product.brand?.name && (
                        <p className="text-xs text-gray-400 mt-0.5">
                            {product.brand.name}
                        </p>
                    )}
                </Link>

                <PriceDisplay product={product} displayPrice={displayPrice} />

                {!isOutOfStock && (
                    <p className={`text-[11px] font-medium mt-1 leading-tight ${
                        stockStatus === 'low_stock' ? 'text-orange-600' : 'text-green-600'
                    }`}>
                        {stockStatus === 'low_stock' ? 'Low Stock' : 'In Stock'}
                    </p>
                )}

                <div className="mt-2.5 space-y-1.5">
                    {isOutOfStock ? (
                        <button
                            disabled
                            className="w-full flex items-center justify-center gap-2 px-3 py-2 bg-gray-100 text-gray-400 rounded-lg text-sm font-medium cursor-not-allowed"
                        >
                            <i className="bi bi-x-circle text-xs"></i>
                            Out of Stock
                        </button>
                    ) : (
                        <button
                            onClick={handleAddToCart}
                            disabled={addingId === product.id || isAdding}
                            className="w-full flex items-center justify-center gap-2 px-3 py-2 text-white rounded-lg text-sm font-semibold active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200 shadow-sm hover:shadow"
                            style={{ backgroundColor: 'var(--theme-color, #3B82F6)' }}
                            onMouseEnter={(e) => e.currentTarget.style.opacity = '0.9'}
                            onMouseLeave={(e) => e.currentTarget.style.opacity = '1'}
                        >
                            {addingId === product.id || isAdding ? (
                                <>
                                    <svg className="animate-spin h-3.5 w-3.5" fill="none" viewBox="0 0 24 24">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                    Adding...
                                </>
                            ) : (
                                <>
                                    <i className="bi bi-cart-plus text-xs"></i>
                                    Add to Cart
                                </>
                            )}
                        </button>
                    )}
                    <Link
                        href={productUrl}
                        className="w-full flex items-center justify-center gap-2 px-3 py-2 border rounded-lg text-xs font-semibold transition-all duration-200"
                        style={{ borderColor: 'rgba(var(--theme-color-rgb, 59, 130, 246), 0.25)', color: 'var(--theme-color, #3B82F6)' }}
                        onMouseEnter={(e) => { e.currentTarget.style.borderColor = 'var(--theme-color, #3B82F6)'; e.currentTarget.style.backgroundColor = 'rgba(var(--theme-color-rgb, 59, 130, 246), 0.06)'; }}
                        onMouseLeave={(e) => { e.currentTarget.style.borderColor = 'rgba(var(--theme-color-rgb, 59, 130, 246), 0.25)'; e.currentTarget.style.backgroundColor = ''; }}
                    >
                        <i className="bi bi-eye text-xs"></i>
                        View Details
                    </Link>
                </div>
            </div>
        </div>
    );
});

export default ProductCard;
