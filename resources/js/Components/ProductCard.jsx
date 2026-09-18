import { useState, useEffect, useMemo, memo, useCallback, useRef } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import { Heart, Zap } from 'lucide-react';
import { useWishlist } from '@/Hooks/useWishlist';
import { useProductDisplay, formatUnits } from '@/Hooks/useProductDisplay';
import { useBuyNow, buyNowKey } from '@/Hooks/useBuyNow';
import { formatCurrency, getCurrencyConfig } from '@/Utils/currency';
import ProductImagePlaceholder from '@/Components/ProductImagePlaceholder';

function getStockStatus(product, threshold = 10) {
    const stock = getEffectiveStock(product);
    if (stock <= 0) return 'out_of_stock';
    if (stock <= threshold) return 'low_stock';
    return 'in_stock';
}

function safeNumber(value) {
    const number = Number(value);
    return Number.isFinite(number) ? number : null;
}

function getVariablePrice(product) {
    const summaryPrice = safeNumber(product.display_price_summary?.min);
    if (summaryPrice !== null) return summaryPrice;

    const rangePrice = safeNumber(product.price_range?.[0]);
    if (rangePrice !== null) return rangePrice;

    const variantPrices = (product.variants || [])
        .map((variant) => safeNumber(variant.price))
        .filter((price) => price !== null);
    if (variantPrices.length > 0) return Math.min(...variantPrices);

    return safeNumber(product.price);
}

function getEffectiveStock(product) {
    return product.effective_stock ?? product.stock ?? 0;
}

function getDisplayPrice(product) {
    if (product.is_flash_sale && product.flash_sale_price !== null && product.flash_sale_price !== undefined) {
        const effPrice = product.flash_sale_price;
        const original = product.flash_sale_original_price ?? getBasePrice(product);
        return {
            display: Number(effPrice).toLocaleString(),
            original: Number(original).toLocaleString(),
            savings: Math.round(original - effPrice),
            isFlashSale: true,
            flashSaleDiscount: product.flash_sale_discount_percentage,
            flashSaleEndsAt: product.flash_sale_ends_at,
            flashSaleName: product.flash_sale_name,
        };
    }

    const hasPromotion = product.promotion_price !== undefined
        && product.promotion_price !== null
        && product.promotion_price < getBasePrice(product);

    if (hasPromotion) {
        if (product.is_variable) {
            const minPrice = safeNumber(product.promotion_price);
            const maxPrice = safeNumber(product.promotion_price_max);
            const originalMin = safeNumber(product.display_price_summary?.min) ?? safeNumber(product.price_range?.[0]) ?? getVariablePrice(product);
            const originalMax = safeNumber(product.display_price_summary?.max) ?? safeNumber(product.price_range?.[1]) ?? originalMin;
            const label = minPrice !== null && maxPrice !== null && minPrice !== maxPrice ? 'From' : '';
            return {
                display: minPrice,
                displayMax: maxPrice,
                original: originalMin,
                originalMax: originalMax,
                savings: originalMin !== null ? Math.round(originalMin - minPrice) : null,
                label,
                hasPromotion: true,
                promotionBadge: product.promotion_badge,
            };
        }
        const effPrice = product.promotion_price;
        return {
            display: Number(effPrice).toLocaleString(),
            original: Number(getBasePrice(product)).toLocaleString(),
            savings: Math.round(getBasePrice(product) - effPrice),
            hasPromotion: true,
            promotionBadge: product.promotion_badge,
        };
    }

    if (product.is_variable) {
        const price = getVariablePrice(product);
        const maxPrice = safeNumber(product.price_range?.[1]);
        const label = price !== null && maxPrice !== null && price !== maxPrice ? 'From' : '';
        return { display: price, label };
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
        return getVariablePrice(product);
    }
    if (product.is_combo) {
        const summary = product.display_price_summary;
        if (summary) return summary.price ?? product.price ?? 0;
        return product.price ?? 0;
    }
    return product.price ?? 0;
}

const StockBadge = memo(function StockBadge({ status, labels = {} }) {
    if (status === 'out_of_stock') {
        return (
            <div className="absolute inset-0 bg-black/50 flex items-center justify-center z-10">
                <span className="px-3 py-1.5 bg-red-500 text-white text-xs font-semibold rounded-full shadow-lg">
                    {labels.out_of_stock || 'Out of Stock'}
                </span>
            </div>
        );
    }
    return null;
});

const ProductTypeBadge = memo(function ProductTypeBadge({ isVariable, isCombo }) {
    if (!isVariable && !isCombo) return null;
    const label = isVariable ? 'Multiple Options' : 'Bundle';
    return (
        <span
            className="inline-block px-1.5 py-0.5 text-[9px] font-medium rounded"
            style={{
                backgroundColor: 'var(--storefront-color-surface-muted, #F1F5F9)',
                color: 'var(--storefront-color-muted, #6B7280)',
            }}
        >
            {label}
        </span>
    );
});

const PriceDisplay = memo(function PriceDisplay({ product, displayPrice, pd }) {
    const { display, displayMax, original, originalMax, savings, label, isFlashSale, flashSaleDiscount, flashSaleEndsAt, flashSaleName, hasPromotion, promotionBadge } = displayPrice || {};
    const cc = getCurrencyConfig(usePage().props.platform_setting, usePage().props.website_info);
    const showOriginal = pd?.pricing?.show_original_price !== false;
    const showSavings = pd?.pricing?.show_savings !== false;

    if (isFlashSale) {
        return (
            <div className="mt-1.5">
                <div className="flex items-baseline gap-1 flex-wrap">
                    <span
                        className="text-[17px] font-extrabold leading-tight"
                        style={{ color: '#EA580C' }}
                    >
                        {formatCurrency(display, cc)}
                    </span>
                    <span className="text-[10px] font-medium" style={{ color: 'var(--storefront-color-muted, #6B7280)' }}>
                        {cc.code}
                    </span>
                    {showOriginal && original && (
                        <span
                            className="text-xs line-through w-full sm:w-auto block leading-tight"
                            style={{ color: 'var(--storefront-color-muted, #6B7280)' }}
                        >
                            {original} <span className="text-[10px]">{cc.code}</span>
                        </span>
                    )}
                </div>
                {showSavings && savings > 0 && (
                    <p className="text-[10px] font-semibold flex items-center gap-1 leading-tight" style={{ color: '#EA580C' }}>
                        <Zap className="w-3 h-3 fill-current" />
                        Save {formatCurrency(savings, cc)}
                    </p>
                )}
            </div>
        );
    }

    if (hasPromotion && product.is_variable) {
        const hasRange = displayMax !== null && displayMax !== undefined && Number(display) !== Number(displayMax);
        const hasOriginalRange = originalMax !== null && originalMax !== undefined && Number(original) !== Number(originalMax);
        return (
            <div className="mt-1.5">
                <div className="flex items-baseline gap-1 flex-wrap">
                    {label && (
                        <span
                            className="text-[10px] font-medium uppercase tracking-wide"
                            style={{ color: 'var(--storefront-color-muted, #6B7280)' }}
                        >
                            {label}
                        </span>
                    )}
                    <span
                        className="text-[17px] font-extrabold leading-tight"
                        style={{ color: 'var(--storefront-color-success, #16A34A)' }}
                    >
                        {formatCurrency(display, cc)}
                        {hasRange && <span className="text-[12px] font-semibold"> - {formatCurrency(displayMax, cc)}</span>}
                    </span>
                    <span className="text-[10px] font-medium" style={{ color: 'var(--storefront-color-muted, #6B7280)' }}>
                        {cc.code}
                    </span>
                </div>
                {showOriginal && (original || originalMax) && (
                    <div className="flex items-center gap-1.5 flex-wrap mt-0.5">
                        <span
                            className="text-[11px] line-through leading-tight"
                            style={{ color: 'var(--storefront-color-muted, #6B7280)' }}
                        >
                            {hasOriginalRange
                                ? <>{formatCurrency(original, cc)} - {formatCurrency(originalMax, cc)}</>
                                : <>{formatCurrency(original, cc)}</>
                            }
                            <span className="text-[9px]"> {cc.code}</span>
                        </span>
                        {showSavings && savings > 0 && (
                            <span className="text-[10px] font-medium leading-tight" style={{ color: 'var(--storefront-color-success, #16A34A)' }}>
                                Save {formatCurrency(savings, cc)}
                            </span>
                        )}
                    </div>
                )}
            </div>
        );
    }

    if (product.is_variable) {
        if (display === null || display === undefined || !Number.isFinite(Number(display))) {
            return (
                <div className="mt-1.5">
                    <span className="text-[13px] font-semibold" style={{ color: 'var(--theme-color, #3B82F6)' }}>
                        Select Options
                    </span>
                </div>
            );
        }
        return (
            <div className="mt-1.5">
                <div className="flex items-baseline gap-1 flex-wrap">
                    {label && (
                        <span
                            className="text-[10px] font-medium uppercase tracking-wide"
                            style={{ color: 'var(--storefront-color-muted, #6B7280)' }}
                        >
                            {label}
                        </span>
                    )}
                    <span
                        className="text-[17px] font-extrabold leading-tight"
                        style={{ color: 'var(--storefront-color-text, #111827)' }}
                    >
                        {formatCurrency(display, cc)}
                    </span>
                    <span className="text-[10px] font-medium" style={{ color: 'var(--storefront-color-muted, #6B7280)' }}>
                        {cc.code}
                    </span>
                </div>
                {showOriginal && original && (
                    <span
                        className="text-xs line-through w-full sm:w-auto block leading-tight"
                        style={{ color: 'var(--storefront-color-muted, #6B7280)' }}
                    >
                        {original} <span className="text-[10px]">{cc.code}</span>
                    </span>
                )}
                {showSavings && savings > 0 && (
                    <p className="text-[10px] font-semibold flex items-center gap-1 leading-tight" style={{ color: 'var(--storefront-color-success, #16A34A)' }}>
                        <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Save {formatCurrency(savings, cc)}
                    </p>
                )}
            </div>
        );
    }

    if (product.is_combo) {
        return (
            <div className="mt-1.5">
                <div className="flex items-baseline gap-1 flex-wrap">
                    <span
                        className="text-[17px] font-extrabold leading-tight"
                        style={{ color: 'var(--storefront-color-text, #111827)' }}
                    >
                        {formatCurrency(display || Number(product.price ?? 0), cc)}
                    </span>
                    <span className="text-[10px] font-medium" style={{ color: 'var(--storefront-color-muted, #6B7280)' }}>
                        {cc.code}
                    </span>
                    {showOriginal && product.display_price_summary?.base_price > 0 && (
                        <span
                            className="text-xs line-through w-full sm:w-auto leading-tight"
                            style={{ color: 'var(--storefront-color-muted, #6B7280)' }}
                        >
                            {Number(product.display_price_summary.base_price).toLocaleString()} <span className="text-[10px]">{cc.code}</span>
                        </span>
                    )}
                </div>
                {showSavings && product.display_price_summary?.savings > 0 && (
                    <p className="text-[10px] font-semibold flex items-center gap-1 leading-tight" style={{ color: 'var(--storefront-color-success, #16A34A)' }}>
                        <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Bundle Save {formatCurrency(product.display_price_summary.savings, cc)}
                    </p>
                )}
                {showSavings && savings > 0 && (
                    <p className="text-[10px] font-semibold flex items-center gap-1 leading-tight" style={{ color: 'var(--storefront-color-success, #16A34A)' }}>
                        <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Save {formatCurrency(savings, cc)}
                    </p>
                )}
            </div>
        );
    }

    return (
        <div className="mt-1.5">
            <div className="flex items-baseline gap-1 flex-wrap">
                    <span
                        className="text-[17px] font-extrabold leading-tight"
                        style={{ color: hasPromotion ? 'var(--storefront-color-success, #16A34A)' : 'var(--storefront-color-text, #111827)' }}
                    >
                        {display || Number(product.price ?? 0).toLocaleString()}
                    </span>
                <span className="text-[10px] font-medium" style={{ color: 'var(--storefront-color-muted, #6B7280)' }}>
                    {cc.code}
                </span>
            </div>
            {showOriginal && hasPromotion && original && (
                <div className="flex items-center gap-1.5 flex-wrap mt-0.5">
                    <span
                        className="text-[11px] line-through leading-tight"
                        style={{ color: 'var(--storefront-color-muted, #6B7280)' }}
                    >
                        {original} <span className="text-[9px]">{cc.code}</span>
                    </span>
                    {showSavings && savings > 0 && (
                        <span className="text-[10px] font-medium leading-tight" style={{ color: 'var(--storefront-color-success, #16A34A)' }}>
                            Save {formatCurrency(savings, cc)}
                        </span>
                    )}
                </div>
            )}
        </div>
    );
});

const ProductCard = memo(function ProductCard({ product, variant = null, onAddToCart, onSelectVariant, addingId = null }) {
    const { props } = usePage();
    const { auth, website_info, storefront, wishlisted_ids = [], tenant } = props;
    const labels = storefront?.content?.labels || {};
    const tokens = storefront?.design || {};
    const buttonStyle = tokens.buttons?.primary_style || 'solid';
    const productVariant = variant || tokens.product_cards?.variant || 'standard';
    const cc = getCurrencyConfig(props.platform_setting, props.website_info);
    const wishlistEnabled = website_info?.enable_wishlist !== false;
    const { toggleWishlist } = useWishlist();
    const { buyNow, buyingKey } = useBuyNow();
    const productUrl = tenant?.slug
        ? `/store/${tenant.slug}/products/${product.id}`
        : `/client/product/${product.id}`;

    const [imageLoaded, setImageLoaded] = useState(false);
    const [imageError, setImageError] = useState(false);
    const [isAdding, setIsAdding] = useState(false);
    const [justAdded, setJustAdded] = useState(false);
    const [wishlistAnim, setWishlistAnim] = useState(false);
    const [optimisticWishlisted, setOptimisticWishlisted] = useState(
        wishlisted_ids.includes(product.id)
    );
    const [carouselIndex, setCarouselIndex] = useState(0);
    const [carouselHovered, setCarouselHovered] = useState(false);
    const touchStartX = useRef(0);

    const productImages = useMemo(() => {
        const seen = new Set();
        const imgs = [];
        const add = (url) => {
            if (url && !seen.has(url)) {
                seen.add(url);
                imgs.push(url);
            }
        };
        add(product.photo1_url);
        add(product.photo2_url);
        if (Array.isArray(product.gallery_images_url)) {
            product.gallery_images_url.forEach(add);
        }
        return imgs;
    }, [product.photo1_url, product.photo2_url, product.gallery_images_url]);

    const hasMultipleImages = productImages.length > 1;

    const handleCarouselPrev = useCallback((e) => {
        e.preventDefault();
        e.stopPropagation();
        setCarouselIndex((i) => (i === 0 ? productImages.length - 1 : i - 1));
    }, [productImages.length]);

    const handleCarouselNext = useCallback((e) => {
        e.preventDefault();
        e.stopPropagation();
        setCarouselIndex((i) => (i === productImages.length - 1 ? 0 : i + 1));
    }, [productImages.length]);

    const handleTouchStart = useCallback((e) => {
        touchStartX.current = e.touches[0].clientX;
    }, []);

    const handleTouchEnd = useCallback((e) => {
        const diff = touchStartX.current - e.changedTouches[0].clientX;
        if (Math.abs(diff) > 40) {
            if (diff > 0) {
                setCarouselIndex((i) => (i === productImages.length - 1 ? 0 : i + 1));
            } else {
                setCarouselIndex((i) => (i === 0 ? productImages.length - 1 : i - 1));
            }
        }
    }, [productImages.length]);

    useEffect(() => {
        setOptimisticWishlisted(wishlisted_ids.includes(product.id));
    }, [wishlisted_ids, product.id]);

    const pd = useProductDisplay();
    const stockThreshold = pd.stock.low_stock_threshold;
    const stockStatus = getStockStatus(product, stockThreshold);
    const stockUnits = getEffectiveStock(product);
    const showStockBlock = pd.stock.display_mode !== 'hidden';
    const showOos = pd.stock.show_out_of_stock;
    const isOutOfStock = stockStatus === 'out_of_stock';
    const displayPrice = getDisplayPrice(product);
    const isFlashSale = displayPrice?.isFlashSale;
    const hasPromotion = !isFlashSale && displayPrice?.hasPromotion === true;

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
            await onAddToCart(product.id, { name: product.name, image: product.photo1_url || null });
        }
        setIsAdding(false);
        setJustAdded(true);
        setTimeout(() => setJustAdded(false), 2000);
    };

    const handleBuyNow = async (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (isOutOfStock) return;

        if (product.is_variable) {
            if (onSelectVariant) {
                onSelectVariant(product);
            }
            return;
        }

        buyNow({ productId: product.id, quantity: 1 });
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
        <div
            className="group relative min-w-0 flex flex-col overflow-hidden transition-all duration-300"
            style={{
                borderRadius: 'var(--storefront-radius-card, 0.75rem)',
                boxShadow: 'var(--storefront-shadow-card, 0 1px 3px rgb(0 0 0 / .08))',
                backgroundColor: 'var(--storefront-color-surface, #FFFFFF)',
                border: '1px solid var(--storefront-color-border, #E5E7EB)',
            }}
            onMouseEnter={(e) => {
                e.currentTarget.style.boxShadow = '0 4px 12px -2px rgb(0 0 0 / .1), 0 0 0 1px var(--theme-color, #3B82F6)';
                e.currentTarget.style.borderColor = 'var(--theme-color, #3B82F6)';
            }}
            onMouseLeave={(e) => {
                e.currentTarget.style.boxShadow = 'var(--storefront-shadow-card, 0 1px 3px rgb(0 0 0 / .08))';
                e.currentTarget.style.borderColor = 'var(--storefront-color-border, #E5E7EB)';
            }}
        >
            <Link href={productUrl} className="block">
                <div
                    className={`relative ${productVariant === 'compact' ? 'h-[160px] sm:h-[136px] lg:h-[150px]' : productVariant === 'image-focused' ? 'h-[240px] sm:h-[200px] lg:h-[230px]' : 'h-[200px] sm:h-[160px] lg:h-[180px]'} overflow-hidden`}
                    style={{ backgroundColor: 'var(--storefront-color-surface-muted, #F1F5F9)' }}
                    onMouseEnter={hasMultipleImages ? () => setCarouselHovered(true) : undefined}
                    onMouseLeave={hasMultipleImages ? () => setCarouselHovered(false) : undefined}
                    onTouchStart={hasMultipleImages ? handleTouchStart : undefined}
                    onTouchEnd={hasMultipleImages ? handleTouchEnd : undefined}
                >
                    {productImages.length > 0 ? (
                        <>
                            {productImages.map((img, idx) => (
                                <img
                                    key={img}
                                    src={img}
                                    alt={product.name}
                                    width="360"
                                    height="360"
                                    className={`absolute inset-0 w-full h-full object-cover transition-all duration-500 ${
                                        idx === carouselIndex
                                            ? 'opacity-100 scale-100 z-10'
                                            : 'opacity-0 scale-105 z-0 pointer-events-none'
                                    }`}
                                    onLoad={() => { if (idx === 0) setImageLoaded(true); }}
                                    onError={() => { if (idx === 0) setImageError(true); }}
                                />
                            ))}
                            {hasMultipleImages && (
                                <>
                                    <button
                                        onClick={handleCarouselPrev}
                                        className={`absolute left-1 top-1/2 -translate-y-1/2 z-20 w-6 h-6 rounded-full bg-black/30 backdrop-blur-sm text-white flex items-center justify-center text-xs transition-opacity duration-200 ${carouselHovered ? 'opacity-100' : 'opacity-0'} hover:bg-black/50`}
                                        aria-label="Previous image"
                                    >
                                        &#8249;
                                    </button>
                                    <button
                                        onClick={handleCarouselNext}
                                        className={`absolute right-1 top-1/2 -translate-y-1/2 z-20 w-6 h-6 rounded-full bg-black/30 backdrop-blur-sm text-white flex items-center justify-center text-xs transition-opacity duration-200 ${carouselHovered ? 'opacity-100' : 'opacity-0'} hover:bg-black/50`}
                                        aria-label="Next image"
                                    >
                                        &#8250;
                                    </button>
                                    <div className="absolute bottom-1.5 left-0 right-0 flex justify-center gap-1 z-20 pointer-events-none">
                                        {productImages.map((_, idx) => (
                                            <span
                                                key={idx}
                                                className={`block rounded-full transition-all duration-200 ${
                                                    idx === carouselIndex
                                                        ? 'w-3.5 h-1.5 bg-white'
                                                        : 'w-1.5 h-1.5 bg-white/50'
                                                }`}
                                            />
                                        ))}
                                    </div>
                                </>
                            )}
                        </>
                    ) : (
                        <ProductImagePlaceholder className="absolute inset-0" />
                    )}

                    {showStockBlock && showOos && <StockBadge status={stockStatus} labels={labels} />}

                    <div className="absolute top-2 right-2 z-10 flex items-center gap-1.5">
                        {isFlashSale && (
                            <div className="px-2 py-0.5 bg-orange-500 text-white text-[10px] font-bold rounded-full shadow-sm flex items-center gap-0.5">
                                <Zap className="w-2.5 h-2.5 fill-current" />
                                {pd.pricing.show_discount_percentage && displayPrice.flashSaleDiscount > 0 ? `-${displayPrice.flashSaleDiscount}%` : 'Flash'}
                            </div>
                        )}

                        {hasPromotion && (pd.pricing.show_discount_percentage || !/%/.test(displayPrice.promotionBadge || product.promotion_badge || '')) && (
                            <div className="px-2 py-0.5 bg-red-500 text-white text-[10px] font-bold rounded-full shadow-sm">
                                {displayPrice.promotionBadge || product.promotion_badge || 'Sale'}
                            </div>
                        )}

                        {pd.pricing.show_discount_percentage && !hasPromotion && !isFlashSale && Number(product.discount_percentage ?? 0) > 0 && (
                            <div className="px-2 py-0.5 bg-red-500 text-white text-[10px] font-bold rounded-full shadow-sm">
                                -{product.discount_percentage}%
                            </div>
                        )}

                        {wishlistEnabled && pd.actions.show_wishlist && (
                            <button
                                onClick={handleWishlistToggle}
                                className={`w-7 h-7 rounded-full flex items-center justify-center transition-all duration-200 ${
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
                </div>
            </Link>

            <div className="p-3 flex flex-col gap-0">
                <Link href={productUrl}>
                    {pd.product_info.show_category && product.category?.name && (
                        <span
                            className="inline-block max-w-[8rem] truncate px-2 py-0.5 text-[10px] font-medium rounded-full mb-1.5"
                            style={{
                                backgroundColor: 'var(--storefront-color-surface-muted, #F1F5F9)',
                                color: 'var(--storefront-color-muted, #6B7280)',
                            }}
                        >
                            {product.category.name}
                        </span>
                    )}
                    <h3
                        className="text-[13px] font-semibold leading-snug line-clamp-2 transition-colors"
                        style={{ color: 'var(--storefront-color-text, #111827)' }}
                    >
                        {product.name}
                    </h3>
                    {pd.product_info.show_brand && product.brand?.name && (
                        <p
                            className="text-[11px] mt-0.5 leading-tight"
                            style={{ color: 'var(--storefront-color-muted, #6B7280)' }}
                        >
                            {product.brand.name}
                        </p>
                    )}
                </Link>

                {pd.product_info.show_product_type && (
                <div className="flex items-center gap-1.5 mt-1">
                    <ProductTypeBadge isVariable={product.is_variable} isCombo={product.is_combo} />
                </div>
                )}

                <div className="flex items-center gap-1 mt-1 min-h-[14px]">
                    {/* Rating placeholder — ready for future reviews */}
                </div>

                <PriceDisplay product={product} displayPrice={displayPrice} pd={pd} />

                {showStockBlock && !isOutOfStock && (
                    <div className="flex items-center gap-1 mt-1.5">
                        {pd.stock.display_mode !== 'quantity' && (
                        <span
                            className="inline-block w-1.5 h-1.5 rounded-full shrink-0"
                            style={{
                                backgroundColor: stockStatus === 'low_stock'
                                    ? 'var(--storefront-color-warning, #D97706)'
                                    : 'var(--storefront-color-success, #16A34A)',
                            }}
                        />
                        )}
                        <p
                            className="text-xs font-medium leading-tight"
                            style={{
                                color: stockStatus === 'low_stock'
                                    ? 'var(--storefront-color-warning, #D97706)'
                                    : 'var(--storefront-color-success, #16A34A)',
                            }}
                        >
                            {pd.stock.display_mode === 'quantity'
                                ? (stockStatus === 'low_stock' ? `Only ${formatUnits(stockUnits, product)} left` : `${formatUnits(stockUnits, product)} available`)
                                : (stockStatus === 'low_stock' ? `Low Stock${pd.stock.display_mode === 'status_quantity' ? ` · ${formatUnits(stockUnits, product)}` : ''}` : `In Stock${pd.stock.display_mode === 'status_quantity' ? ` · ${formatUnits(stockUnits, product)}` : ''}`)}
                        </p>
                    </div>
                )}

                <div className="mt-3 space-y-1.5">
                    {isOutOfStock ? (
                        showOos ? (
                        <button
                            disabled
                            className="w-full flex items-center justify-center gap-2 px-3 py-2.5 rounded-lg text-xs font-medium cursor-not-allowed"
                            style={{
                                backgroundColor: 'var(--storefront-color-surface-muted, #F1F5F9)',
                                color: 'var(--storefront-color-muted, #6B7280)',
                                borderRadius: 'var(--storefront-radius-button, 0.5rem)',
                            }}
                        >
                            <i className="bi bi-x-circle text-xs"></i>
                            {labels.out_of_stock || 'Out of Stock'}
                        </button>
                        ) : null
                    ) : (
                        pd.actions.show_add_to_cart ? (
                        <button
                            onClick={handleAddToCart}
                            disabled={addingId === product.id || isAdding}
                            className={`w-full flex items-center justify-center gap-1.5 px-2 sm:px-3 py-2.5 rounded-lg text-xs sm:text-sm font-semibold whitespace-nowrap active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200`}
                            style={{
                                backgroundColor: buttonStyle === 'outline' || buttonStyle === 'ghost' ? 'transparent' : 'var(--theme-color, #3B82F6)',
                                color: buttonStyle === 'outline' || buttonStyle === 'ghost' ? 'var(--theme-color, #3B82F6)' : '#fff',
                                border: buttonStyle === 'outline' ? '1px solid var(--theme-color, #3B82F6)' : '1px solid transparent',
                                borderRadius: 'var(--storefront-radius-button, 0.5rem)',
                                boxShadow: buttonStyle === 'outline' || buttonStyle === 'ghost' ? 'none' : '0 1px 3px rgb(0 0 0 / .1)',
                            }}
                            onMouseEnter={(e) => {
                                e.currentTarget.style.opacity = '0.9';
                                e.currentTarget.style.boxShadow = '0 2px 8px rgb(0 0 0 / .15)';
                            }}
                            onMouseLeave={(e) => {
                                e.currentTarget.style.opacity = '1';
                                e.currentTarget.style.boxShadow = buttonStyle === 'outline' || buttonStyle === 'ghost' ? 'none' : '0 1px 3px rgb(0 0 0 / .1)';
                            }}
                        >
            {addingId === product.id || isAdding ? (
                <>
                    <svg className="animate-spin h-3.5 w-3.5" fill="none" viewBox="0 0 24 24">
                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Adding...
                </>
            ) : justAdded ? (
                <>
                    <i className="bi bi-check-lg text-xs"></i>
                    Added
                </>
            ) : (
                <>
                    <i className="bi bi-cart-plus text-xs"></i>
                    {labels.add_to_cart || 'Add to Cart'}
                </>
            )}
                        </button>
                        ) : null
                    )}
                    {!isOutOfStock && pd.actions.show_buy_now && (
                        <button
                            onClick={handleBuyNow}
                            disabled={buyingKey === buyNowKey(product.id, null)}
                            className="w-full flex items-center justify-center gap-2 px-3 py-2.5 border text-xs font-semibold transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                            style={{
                                borderColor: 'var(--theme-color, #3B82F6)',
                                color: 'var(--theme-color, #3B82F6)',
                                borderRadius: 'var(--storefront-radius-button, 0.5rem)',
                            }}
                        >
                            {buyingKey === buyNowKey(product.id, null) ? (
                                <svg className="animate-spin h-3.5 w-3.5" fill="none" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" /><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                            ) : (
                                <i className="bi bi-lightning-charge text-xs"></i>
                            )}
                            {labels.buy_now || 'Buy Now'}
                        </button>
                    )}
                    {pd.actions.show_view_product && (
                    <Link
                        href={productUrl}
                        className="w-full flex items-center justify-center gap-2 px-3 py-2.5 border text-xs font-semibold transition-all duration-200"
                        style={{
                            borderColor: 'var(--storefront-color-border, #E5E7EB)',
                            color: 'var(--theme-color, #3B82F6)',
                            borderRadius: 'var(--storefront-radius-button, 0.5rem)',
                        }}
                        onMouseEnter={(e) => {
                            e.currentTarget.style.borderColor = 'var(--theme-color, #3B82F6)';
                            e.currentTarget.style.backgroundColor = 'color-mix(in srgb, var(--theme-color, #3B82F6) 5%, transparent)';
                        }}
                        onMouseLeave={(e) => {
                            e.currentTarget.style.borderColor = 'var(--storefront-color-border, #E5E7EB)';
                            e.currentTarget.style.backgroundColor = '';
                        }}
                    >
                        <i className="bi bi-eye text-xs"></i>
                        {labels.view_product || 'View Product'}
                    </Link>
                    )}
                </div>
            </div>
        </div>
    );
});

export default ProductCard;
