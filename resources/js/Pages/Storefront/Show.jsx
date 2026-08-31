import { useState, useMemo } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';
import ComboViewDetail from '@/Components/ProductView/ComboViewDetail';
import RelatedProducts from '@/Components/Storefront/RelatedProducts';
import { useCart } from '@/Hooks/useCart';
import { assetUrl } from '@/Utils/helpers';
import { formatCurrency, getCurrencyConfig } from '@/Utils/currency';
import { sanitizeStorefrontHtml } from '@/Utils/sanitizeStorefrontHtml';

const OPTION_STYLES = [
    'bg-blue-50 text-blue-700 border-blue-200 hover:border-blue-400 hover:bg-blue-100',
    'bg-purple-50 text-purple-700 border-purple-200 hover:border-purple-400 hover:bg-purple-100',
    'bg-emerald-50 text-emerald-700 border-emerald-200 hover:border-emerald-400 hover:bg-emerald-100',
    'bg-amber-50 text-amber-700 border-amber-200 hover:border-amber-400 hover:bg-amber-100',
    'bg-pink-50 text-pink-700 border-pink-200 hover:border-pink-400 hover:bg-pink-100',
    'bg-cyan-50 text-cyan-700 border-cyan-200 hover:border-cyan-400 hover:bg-cyan-100',
];

function safeNum(val) {
    const n = Number(val);
    return Number.isFinite(n) ? n : 0;
}

export default function StoreShow({ tenant, product, promotion, detail, relatedProducts = [] }) {
    const { storefront } = usePage().props;
    const themeTokens = storefront?.design || {};
    const labels = storefront?.content?.labels || {};
    const { addToCart, addingId } = useCart();
    const [selectedOptions, setSelectedOptions] = useState({});
    const [quantity, setQuantity] = useState(1);
    const [added, setAdded] = useState(false);
    const [activeImage, setActiveImage] = useState(0);
    const [showDescriptionModal, setShowDescriptionModal] = useState(false);

    const displayDescription = product.description || 'Detailed product information will be available soon.';
    const isLongDescription = product.description && product.description.length > 500;
    const truncatedDescription = isLongDescription ? product.description.slice(0, 500) + '...' : displayDescription;

    const isVariable = !!product.is_variable;
    const isCombo = !!product.is_combo;
    const variants = detail?.variants ?? [];
    const optionKeys = detail?.option_keys ?? [];
    const optionValues = detail?.option_values ?? {};
    const optionNames = detail?.option_names ?? {};

    const images = [product.photo1_url, ...(product.gallery_images_url || product.gallery_images || [])].filter(Boolean);

    const selectedVariant = useMemo(() => {
        if (!isVariable || !variants.length || !optionKeys.length) return null;
        if (optionKeys.some(key => !selectedOptions[key])) return null;
        return variants.find(v => {
            const attrs = v.attributes ?? {};
            return optionKeys.every(key => attrs[key] === selectedOptions[key]);
        }) || null;
    }, [isVariable, variants, optionKeys, selectedOptions]);

    const hasVariantImage = selectedVariant?.image_url;
    const mainImage = hasVariantImage ? selectedVariant.image_url : (images[activeImage] || null);

    const currentPrice = useMemo(() => {
        if (isVariable && selectedVariant) return safeNum(selectedVariant.price);
        if (isVariable) return null;
        return promotion?.promotion_price ?? product.price;
    }, [isVariable, selectedVariant, promotion, product.price]);

    const originalPrice = useMemo(() => {
        if (isVariable) return null;
        return promotion?.original_price ?? null;
    }, [isVariable, promotion]);

    const discountPercent = useMemo(() => {
        if (isVariable) return null;
        if (!promotion || !promotion.discount_value) return null;
        const base = promotion.original_price ?? product.price;
        if (base <= 0) return null;
        if (promotion.promotion_type === 'percentage') return promotion.discount_value;
        return Math.round((promotion.discount_value / base) * 100);
    }, [isVariable, promotion, product.price]);

    const availableStock = useMemo(() => {
        if (isVariable && selectedVariant) return safeNum(selectedVariant.stock);
        if (isVariable) return 0;
        return product.effective_stock ?? product.stock ?? 0;
    }, [isVariable, selectedVariant, product.effective_stock, product.stock]);

    const allOptionsSelected = !isVariable || !optionKeys.length || optionKeys.every(key => selectedOptions[key]);

    const priceDisplay = (() => {
        if (isVariable && !allOptionsSelected) return null;
        return Number(currentPrice).toLocaleString();
    })();

    function handleOptionChange(key, value) {
        setSelectedOptions(prev => ({ ...prev, [key]: value }));
    }

    const handleAddToCart = async () => {
        await addToCart(product.id, quantity, selectedVariant?.id || undefined);
        setAdded(true);
        setTimeout(() => setAdded(false), 2000);
    };

    const cc = getCurrencyConfig(usePage().props.platform_setting, usePage().props.website_info);

    const renderStockBadge = (compact = false) => {
        if (isVariable && !selectedVariant && optionKeys.length > 0) {
            return (
                <span className="inline-flex items-center px-2 py-0.5 bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 rounded-full text-[11px] font-medium">
                    {labels.select_options || 'Select options'}
                </span>
            );
        }
        if (availableStock <= 0) {
            return (
                <span className={`inline-flex items-center ${compact ? 'px-1.5 py-0.5 text-[11px]' : 'px-2 py-0.5 text-xs'} bg-red-50 text-red-600 rounded-full font-medium`}>
                    <svg className="w-3 h-3 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    {labels.out_of_stock || 'Out of Stock'}
                </span>
            );
        }
        if (availableStock < 10) {
            return (
                <span className={`inline-flex items-center ${compact ? 'px-1.5 py-0.5 text-[11px]' : 'px-2 py-0.5 text-xs'} bg-amber-50 text-amber-700 rounded-full font-medium`}>
                    <svg className="w-3 h-3 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    Only {availableStock} left
                </span>
            );
        }
        return (
            <span className={`inline-flex items-center ${compact ? 'px-1.5 py-0.5 text-[11px]' : 'px-2 py-0.5 text-xs'} bg-green-50 text-green-600 rounded-full font-medium`}>
                <svg className="w-3 h-3 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" /></svg>
                In Stock
            </span>
        );
    };

    const renderQuantityAndCart = (sticky = false) => (
        <div className={`flex items-center gap-2 ${sticky ? '' : ''}`}>
            {availableStock > 0 && (
                <div className="flex items-center border border-gray-200 dark:border-gray-800 rounded-lg overflow-hidden shrink-0">
                    <button
                        onClick={() => setQuantity(q => Math.max(1, q - 1))}
                        className="w-9 h-9 flex items-center justify-center text-gray-500 hover:bg-gray-50 dark:bg-gray-950 hover:text-gray-700 dark:text-gray-300 transition-colors active:bg-gray-100"
                    >
                        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20 12H4" /></svg>
                    </button>
                    <span className="w-10 h-9 flex items-center justify-center text-sm font-semibold text-gray-900 dark:text-gray-100 border-x border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
                        {quantity}
                    </span>
                    <button
                        onClick={() => setQuantity(q => Math.min(availableStock, q + 1))}
                        className="w-9 h-9 flex items-center justify-center text-gray-500 hover:bg-gray-50 dark:bg-gray-950 hover:text-gray-700 dark:text-gray-300 transition-colors active:bg-gray-100"
                    >
                        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>
                    </button>
                </div>
            )}
            <button
                onClick={handleAddToCart}
                disabled={!allOptionsSelected || availableStock <= 0 || addingId === product.id}
                className={`flex-1 min-w-0 flex items-center justify-center gap-2 rounded-lg font-semibold transition-all active:scale-[0.98] ${
                    sticky ? 'px-4 py-2.5 text-sm' : 'px-5 py-2.5 text-sm'
                } ${
                    !allOptionsSelected || availableStock <= 0
                        ? 'bg-gray-100 text-gray-400 cursor-not-allowed'
                        : 'text-white shadow-sm hover:shadow-md disabled:opacity-50'
                }`}
                style={{ backgroundColor: !allOptionsSelected || availableStock <= 0 ? undefined : 'var(--theme-color, #3B82F6)', borderRadius: `var(--storefront-radius-button, ${themeTokens.radius?.button || '0.5rem'})` }}
            >
                {!allOptionsSelected ? (
                    <>
                        <svg className="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        {labels.select_options || 'Select options'}
                    </>
                ) : availableStock <= 0 ? (
                    labels.out_of_stock || 'Out of Stock'
                ) : added ? (
                    <>
                        <svg className="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" /></svg>
                        Added!
                    </>
                ) : addingId === product.id ? (
                    <>
                        <svg className="animate-spin h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" /><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                        Adding...
                    </>
                ) : (
                    <>
                        <svg className="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z" /></svg>
                         {labels.add_to_cart || 'Add to Cart'}
                    </>
                )}
            </button>
        </div>
    );

    return (
        <ShopLayout>
            <Head title={`${product.seo_title || product.name} - ${storefront?.identity?.site_title || tenant.name}`}>
                <meta name="description" content={product.seo_description || product.short_description || ''} />
                {product.seo_keywords && <meta name="keywords" content={product.seo_keywords} />}
                <meta property="og:title" content={`${product.seo_title || product.name} - ${tenant.name}`} />
                <meta property="og:description" content={product.seo_description || product.short_description || ''} />
                <meta property="og:image" content={assetUrl(product.seo_image || product.photo1_url)} />
                <meta name="twitter:image" content={assetUrl(product.seo_image || product.photo1_url)} />
            </Head>

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 sm:py-4 lg:py-5 pb-24 lg:pb-6">
                <nav className="flex flex-wrap items-center text-xs text-gray-400 dark:text-gray-500 mb-3 sm:mb-4 gap-1">
                    <Link href={`/store/${tenant.slug}`} className="hover:text-indigo-600 font-medium transition-colors">{tenant.name}</Link>
                    <svg className="w-3 h-3 mx-0.5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg>
                    {product.category && (
                        <>
                            <Link href={`/store/${tenant.slug}/products?category=${product.category.id}`} className="hover:text-indigo-600 transition-colors">{product.category.name}</Link>
                            <svg className="w-3 h-3 mx-0.5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg>
                        </>
                    )}
                    <span className="text-gray-600 dark:text-gray-400 truncate max-w-[180px]">{product.name}</span>
                </nav>

                <div className="grid grid-cols-1 lg:grid-cols-[42%_58%] gap-4 lg:gap-6">
                    {/* Image Section */}
                    <div>
                        <div className="group relative bg-gray-50 dark:bg-gray-950 rounded-xl overflow-hidden aspect-[4/5] max-h-[520px]">
                            {mainImage ? (
                                <img
                                    src={assetUrl(mainImage)}
                                    alt={product.name}
                                    className="w-full h-full object-contain transition-transform duration-300 group-hover:scale-[1.03]"
                                />
                            ) : (
                                <div className="flex items-center justify-center h-full">
                                    <div className="text-center">
                                        <svg className="w-12 h-12 mx-auto text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <p className="mt-2 text-xs text-gray-400 dark:text-gray-500">No Image</p>
                                    </div>
                                </div>
                            )}

                            {isCombo && (
                                <div className="absolute top-3 left-3 px-2.5 py-1 bg-purple-600/90 backdrop-blur-sm text-white text-[11px] font-semibold rounded-md shadow-sm z-10">
                                    Bundle
                                </div>
                            )}
                            {isVariable && !isCombo && (
                                <div className="absolute top-3 left-3 px-2.5 py-1 bg-blue-600/90 backdrop-blur-sm text-white text-[11px] font-semibold rounded-md shadow-sm z-10">
                                    Multiple Options
                                </div>
                            )}
                            {discountPercent > 0 && (
                                <div className="absolute top-3 right-3 px-2.5 py-1 bg-red-500/90 backdrop-blur-sm text-white text-[11px] font-semibold rounded-md shadow-sm z-10">
                                    -{discountPercent}%
                                </div>
                            )}
                        </div>

                        {images.length > 1 && (
                            <div className="flex gap-2 mt-3 overflow-x-auto pb-0.5">
                                {images.map((img, idx) => (
                                    <button
                                        key={idx}
                                        onClick={() => setActiveImage(idx)}
                                        className={`w-16 h-16 rounded-lg overflow-hidden border-2 transition-all shrink-0 ${
                                            activeImage === idx
                                                ? 'border-indigo-500 shadow-sm'
                                                : 'border-transparent hover:border-gray-300 opacity-60 hover:opacity-100'
                                        }`}
                                    >
                                        <img
                                            src={assetUrl(img)}
                                            alt=""
                                            className="w-full h-full object-cover"
                                            onError={(e) => { e.target.style.display = 'none'; }}
                                        />
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Product Info Section */}
                    <div className="flex flex-col min-w-0">
                        {/* Product Info Card */}
                        <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-100 dark:border-gray-800 shadow-sm p-3 sm:p-4">
                            <h1 className="text-base sm:text-lg lg:text-xl font-bold text-gray-900 dark:text-gray-100 leading-snug">
                                {product.name}
                            </h1>

                            <div className="flex flex-wrap items-center gap-1.5 mt-1.5 text-[11px] text-gray-500 dark:text-gray-400">
                                <span className="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded font-medium">
                                    {product.category?.name || 'Uncategorized'}
                                </span>
                                <span className="text-gray-300">&middot;</span>
                                {product.brand ? (
                                    <Link
                                        href={`/store/${tenant.slug}/brands/${product.brand.id}`}
                                        className="hover:text-indigo-600 transition-colors"
                                    >
                                        {product.brand.name}
                                    </Link>
                                ) : (
                                    <span>Generic Brand</span>
                                )}
                                <span className="text-gray-300">&middot;</span>
                                <span>{isCombo ? 'Bundle' : isVariable ? 'Variable' : 'Single'}</span>
                                {!isVariable && product.sku && (
                                    <>
                                        <span className="text-gray-300">&middot;</span>
                                        <span>SKU: <span className="font-medium text-gray-700 dark:text-gray-300">{product.sku}</span></span>
                                    </>
                                )}
                                {product.unit?.name && (
                                    <>
                                        <span className="text-gray-300">&middot;</span>
                                        <span>{product.unit.name}</span>
                                    </>
                                )}
                            </div>

                            <div className="flex items-center justify-between gap-3 mt-2.5 pt-2.5 border-t border-gray-100 dark:border-gray-800">
                                <div>
                                    {isVariable && !allOptionsSelected ? (
                                        <span className="text-base text-gray-300 font-medium">{labels.select_options || 'Select options'}</span>
                                    ) : (
                                        <div className="flex items-baseline gap-2 flex-wrap">
                                            <span className="text-xl sm:text-2xl font-extrabold text-gray-900 dark:text-gray-100">
                                                {formatCurrency(currentPrice, cc)}
                                            </span>
                                            {originalPrice > 0 && originalPrice > currentPrice && (
                                                <>
                                                    <span className="text-sm text-gray-400 dark:text-gray-500 line-through">
                                                        {formatCurrency(originalPrice, cc)}
                                                    </span>
                                                    <span className="px-1.5 py-0.5 bg-red-50 text-red-600 rounded text-[11px] font-bold">
                                                        Save {discountPercent}%
                                                    </span>
                                                </>
                                            )}
                                        </div>
                                    )}
                                </div>
                                {renderStockBadge()}
                            </div>
                        </div>

                        {/* Short Description */}
                        {(product.short_description || !product.description) && (
                            <div className="text-xs text-gray-500 dark:text-gray-400 leading-relaxed mt-2 line-clamp-2" dangerouslySetInnerHTML={{ __html: sanitizeStorefrontHtml(product.short_description || 'No short description available.') }} />
                        )}

                        {/* Variant Options */}
                        {isVariable && optionKeys.length > 0 && (
                            <div className="mt-3 space-y-2.5">
                                {optionKeys.map((key, keyIdx) => (
                                    <div key={key}>
                                        <label className="text-xs font-semibold text-gray-700 dark:text-gray-300 capitalize block mb-1.5">
                                            {optionNames[key] || key.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}
                                        </label>
                                        <div className="flex flex-wrap gap-1.5">
                                            {(optionValues[key] || []).map((value) => {
                                                const isSel = selectedOptions[key] === value;
                                                const hasCombination = variants.some(v => {
                                                    const attrs = v.attributes ?? {};
                                                    return Object.entries({ ...selectedOptions, [key]: value }).every(
                                                        ([k, val]) => attrs[k] === val
                                                    );
                                                });
                                                return (
                                                    <button
                                                        key={value}
                                                        onClick={() => handleOptionChange(key, value)}
                                                        disabled={!hasCombination}
                                                        style={isSel ? { borderColor: 'var(--theme-color, #3B82F6)', backgroundColor: 'color-mix(in srgb, var(--theme-color, #3B82F6) 10%, transparent)', color: 'var(--theme-color, #3B82F6)' } : undefined}
                                                        className={`px-3 py-1.5 border text-xs font-medium transition-all ${
                                                            isSel
                                                                ? 'ring-1 ring-[var(--theme-color,#3B82F6)]'
                                                                : hasCombination
                                                                    ? OPTION_STYLES[(keyIdx + (optionValues[key] || []).indexOf(value)) % OPTION_STYLES.length]
                                                                    : 'border-gray-200 bg-gray-50 text-gray-300 cursor-not-allowed'
                                                        }`}
                                                    >
                                                        {value}
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    </div>
                                ))}
                                {selectedVariant && (
                                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                                        <span className="text-gray-600 dark:text-gray-400">
                                            Selected: <span className="font-semibold text-gray-900 dark:text-gray-100">{selectedVariant.label}</span>
                                        </span>
                                        {selectedVariant.sku && (
                                            <span className="text-gray-400 dark:text-gray-500">
                                                SKU: <span className="font-mono">{selectedVariant.sku}</span>
                                            </span>
                                        )}
                                        {selectedVariant.price > 0 && (
                                            <span className="text-gray-600 dark:text-gray-400">
                                                Price: <span className="font-semibold text-gray-900 dark:text-gray-100">{formatCurrency(selectedVariant.price, cc)}</span>
                                            </span>
                                        )}
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Combo Items */}
                        {isCombo && detail?.combo_summary && (
                            <div className="mt-3 space-y-2">
                                <h3 className="text-xs font-semibold text-gray-700 dark:text-gray-300">What's Included</h3>
                                <div className="grid grid-cols-2 sm:grid-cols-3 gap-1.5">
                                    {(detail.combo_summary.items || []).map((item, idx) => (
                                        <div key={idx} className="flex items-center gap-2 bg-gray-50 dark:bg-gray-950 rounded-lg p-2 border border-gray-100 dark:border-gray-800">
                                            <div className="w-8 h-8 rounded bg-gray-200 overflow-hidden shrink-0">
                                                {item.image ? (
                                                    <img src={assetUrl(item.image)} alt={item.name} className="w-full h-full object-cover" />
                                                ) : (
                                                    <div className="w-full h-full flex items-center justify-center text-gray-400 dark:text-gray-500">
                                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                                                    </div>
                                                )}
                                            </div>
                                            <div className="min-w-0">
                                                <p className="text-[11px] font-medium text-gray-900 dark:text-gray-100 truncate">{item.name}</p>
                                                <p className="text-[11px] text-gray-500 dark:text-gray-400">x{item.quantity || 1}</p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {/* Purchase Section */}
                        <div className="mt-3 pt-3 border-t border-gray-100 dark:border-gray-800">
                            {renderQuantityAndCart()}
                        </div>

                        {/* Description */}
                        <div className="mt-3">
                            <h2 className="text-sm font-bold text-gray-900 dark:text-gray-100 mb-1">{labels.product_description || 'Product Description'}</h2>
                            <div className="text-xs text-gray-500 dark:text-gray-400 leading-relaxed prose prose-xs max-w-none dark:prose-invert" dangerouslySetInnerHTML={{ __html: sanitizeStorefrontHtml(truncatedDescription) }} />
                            {isLongDescription && (
                                <button onClick={() => setShowDescriptionModal(true)} className="mt-1.5 text-xs font-semibold text-indigo-600 hover:text-indigo-700 transition-colors">
                                    Read More
                                </button>
                            )}
                        </div>

                        {/* Bundle Details (below purchase for combo) */}
                        {isCombo && detail?.combo_summary && (
                            <div className="mt-6 lg:mt-8">
                                <h2 className="text-base sm:text-lg font-bold text-gray-900 dark:text-gray-100 mb-3">{labels.bundle_details || 'Bundle Details'}</h2>
                                <ComboViewDetail product={{
                                    ...product,
                                    combo_items: detail.combo_summary.items || [],
                                    combo_summary: detail.combo_summary,
                                    combo_availability: detail.combo_availability,
                                }} />
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* Description Modal */}
            {showDescriptionModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" onClick={() => setShowDescriptionModal(false)}>
                    <div className="relative w-full max-w-3xl bg-white dark:bg-gray-900 rounded-xl shadow-2xl overflow-hidden" onClick={e => e.stopPropagation()}>
                        <div className="flex items-center justify-between px-5 py-3 border-b border-gray-200 dark:border-gray-800">
                            <h3 className="text-sm font-bold text-gray-900 dark:text-gray-100">{labels.product_description || 'Product Description'}</h3>
                            <button onClick={() => setShowDescriptionModal(false)} className="p-1 hover:bg-gray-100 dark:bg-gray-800 rounded-md transition-colors">
                                <svg className="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div className="px-5 py-4 overflow-y-auto max-h-[75vh] text-xs text-gray-600 dark:text-gray-400 leading-relaxed prose prose-xs max-w-none dark:prose-invert" dangerouslySetInnerHTML={{ __html: sanitizeStorefrontHtml(product.description) }} />
                    </div>
                </div>
            )}

            {/* Mobile Sticky Bar */}
            <div className="fixed bottom-0 left-0 right-0 z-40 lg:hidden bg-white dark:bg-gray-900/95 backdrop-blur-sm border-t border-gray-200 dark:border-gray-800 px-4 py-2.5 shadow-[0_-2px_12px_rgba(0,0,0,0.06)]">
                <div className="flex items-center justify-between gap-3">
                    <div className="min-w-0 shrink-0">
                        <div className="text-base font-bold text-gray-900 dark:text-gray-100">
                            {currentPrice ? formatCurrency(currentPrice, cc) : '\u2014'}
                        </div>
                        <div className="mt-0.5">
                            {renderStockBadge(true)}
                        </div>
                    </div>
                    <div className="flex-1 min-w-0">
                        {renderQuantityAndCart(true)}
                    </div>
                </div>
            </div>

            {/* Related Products */}
            <RelatedProducts products={relatedProducts} title={labels.related_products || 'Related Products'} />
        </ShopLayout>
    );
}
