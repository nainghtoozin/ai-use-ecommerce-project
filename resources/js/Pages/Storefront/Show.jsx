import { useState, useMemo } from 'react';
import { Head, Link } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';
import ComboViewDetail from '@/Components/ProductView/ComboViewDetail';
import { useCart } from '@/Hooks/useCart';
import { assetUrl } from '@/Utils/helpers';

const OPTION_STYLES = [
    'bg-blue-100 text-blue-800 border-blue-300 hover:border-blue-500 hover:bg-blue-200',
    'bg-purple-100 text-purple-800 border-purple-300 hover:border-purple-500 hover:bg-purple-200',
    'bg-emerald-100 text-emerald-800 border-emerald-300 hover:border-emerald-500 hover:bg-emerald-200',
    'bg-amber-100 text-amber-800 border-amber-300 hover:border-amber-500 hover:bg-amber-200',
    'bg-pink-100 text-pink-800 border-pink-300 hover:border-pink-500 hover:bg-pink-200',
    'bg-cyan-100 text-cyan-800 border-cyan-300 hover:border-cyan-500 hover:bg-cyan-200',
];

function safeNum(val) {
    const n = Number(val);
    return Number.isFinite(n) ? n : 0;
}

export default function StoreShow({ tenant, product, promotion, detail }) {
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

    const images = [product.photo1, ...(product.gallery_images_url || product.gallery_images || [])].filter(Boolean);

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

    const currency = ' MMK';

    const renderStockBadge = (compact = false) => {
        if (isVariable && !selectedVariant && optionKeys.length > 0) {
            return (
                <span className="inline-flex items-center px-2.5 py-1 bg-gray-100 text-gray-500 rounded-full text-xs font-medium">
                    Select options
                </span>
            );
        }
        if (availableStock <= 0) {
            return (
                <span className={`inline-flex items-center ${compact ? 'px-2 py-0.5 text-xs' : 'px-3 py-1 text-sm'} bg-red-100 text-red-700 rounded-full font-medium`}>
                    <svg className="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    Out of Stock
                </span>
            );
        }
        if (availableStock < 10) {
            return (
                <span className={`inline-flex items-center ${compact ? 'px-2 py-0.5 text-xs' : 'px-3 py-1 text-sm'} bg-yellow-100 text-yellow-700 rounded-full font-medium`}>
                    <svg className="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    Only {availableStock} left
                </span>
            );
        }
        return (
            <span className={`inline-flex items-center ${compact ? 'px-2 py-0.5 text-xs' : 'px-3 py-1 text-sm'} bg-green-100 text-green-700 rounded-full font-medium`}>
                <svg className="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" /></svg>
                In Stock
            </span>
        );
    };

    const renderQuantityAndCart = (sticky = false) => (
        <div className={`flex items-center gap-3 ${sticky ? '' : 'lg:flex-row flex-col sm:flex-row'}`}>
            {availableStock > 0 && (
                <div className="flex items-center border border-gray-300 rounded-xl overflow-hidden shrink-0">
                    <button
                        onClick={() => setQuantity(q => Math.max(1, q - 1))}
                        className="w-10 h-10 sm:w-12 sm:h-12 flex items-center justify-center text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors active:bg-gray-200"
                    >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20 12H4" /></svg>
                    </button>
                    <span className="w-12 sm:w-14 h-10 sm:h-12 flex items-center justify-center text-sm sm:text-base font-semibold text-gray-900 border-x border-gray-300 bg-white">
                        {quantity}
                    </span>
                    <button
                        onClick={() => setQuantity(q => Math.min(availableStock, q + 1))}
                        className="w-10 h-10 sm:w-12 sm:h-12 flex items-center justify-center text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors active:bg-gray-200"
                    >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>
                    </button>
                </div>
            )}
            <button
                onClick={handleAddToCart}
                disabled={!allOptionsSelected || availableStock <= 0 || addingId === product.id}
                className={`flex-1 min-w-0 flex items-center justify-center gap-2 rounded-xl font-semibold transition-all active:scale-[0.98] ${
                    sticky ? 'px-4 py-3 text-sm' : 'px-6 py-3 sm:py-3.5 text-sm sm:text-base'
                } ${
                    !allOptionsSelected || availableStock <= 0
                        ? 'bg-gray-200 text-gray-400 cursor-not-allowed'
                        : 'bg-indigo-600 text-white hover:bg-indigo-700 shadow-md hover:shadow-lg disabled:opacity-50'
                }`}
            >
                {!allOptionsSelected ? (
                    <>
                        <svg className="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        Select options
                    </>
                ) : availableStock <= 0 ? (
                    'Out of Stock'
                ) : added ? (
                    <>
                        <svg className="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" /></svg>
                        Added!
                    </>
                ) : addingId === product.id ? (
                    <>
                        <svg className="animate-spin h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" /><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                        Adding...
                    </>
                ) : (
                    <>
                        <svg className="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z" /></svg>
                        Add to Cart
                    </>
                )}
            </button>
        </div>
    );

    return (
        <ShopLayout>
            <Head title={`${product.seo_title || product.name} - ${tenant.name}`}>
                <meta name="description" content={product.seo_description || product.short_description || ''} />
                {product.seo_keywords && <meta name="keywords" content={product.seo_keywords} />}
                <meta property="og:title" content={`${product.seo_title || product.name} - ${tenant.name}`} />
                <meta property="og:description" content={product.seo_description || product.short_description || ''} />
                <meta property="og:image" content={assetUrl(product.seo_image || product.photo1_url)} />
                <meta name="twitter:image" content={assetUrl(product.seo_image || product.photo1_url)} />
            </Head>

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-6 lg:py-8 pb-24 lg:pb-8">
                <nav className="flex flex-wrap items-center text-xs sm:text-sm text-gray-500 mb-6 lg:mb-8 gap-1">
                    <Link href={`/store/${tenant.slug}`} className="hover:text-indigo-600 font-medium">{tenant.name}</Link>
                    <svg className="w-3 h-3 mx-1 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg>
                    {product.category && (
                        <>
                            <Link href={`/store/${tenant.slug}/products?category=${product.category.id}`} className="hover:text-indigo-600">{product.category.name}</Link>
                            <svg className="w-3 h-3 mx-1 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg>
                        </>
                    )}
                    <span className="text-gray-900 truncate max-w-[200px]">{product.name}</span>
                </nav>

                <div className="grid grid-cols-1 lg:grid-cols-[45%_55%] gap-6 lg:gap-10">
                    <div>
                        <div className="relative bg-gray-100 rounded-2xl overflow-hidden border border-gray-200 min-h-[200px] max-h-[280px] md:aspect-square md:max-h-[450px]">
                            {mainImage ? (
                                <img
                                    src={assetUrl(mainImage)}
                                    alt={product.name}
                                    className="w-full h-full object-contain"
                                />
                            ) : (
                                <div className="flex items-center justify-center h-full">
                                    <div className="text-center">
                                        <svg className="w-16 h-16 sm:w-24 sm:h-24 mx-auto text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <p className="mt-2 text-sm text-gray-400">No Image</p>
                                    </div>
                                </div>
                            )}

                            {isCombo && (
                                <div className="absolute top-3 left-3 px-3 py-1.5 bg-purple-600 text-white text-xs sm:text-sm font-bold rounded-full shadow-lg z-10">
                                    Bundle
                                </div>
                            )}
                            {isVariable && (
                                <div className="absolute top-3 left-3 px-3 py-1.5 bg-blue-600 text-white text-xs sm:text-sm font-bold rounded-full shadow-lg z-10">
                                    Multiple Options
                                </div>
                            )}
                            {discountPercent > 0 && (
                                <div className="absolute top-3 right-3 px-3 py-1.5 bg-red-500 text-white text-xs sm:text-sm font-bold rounded-full shadow-lg z-10">
                                    -{discountPercent}%
                                </div>
                            )}
                        </div>

                        {images.length > 1 && (
                            <div className="flex gap-2 sm:gap-3 mt-4">
                                {images.map((img, idx) => (
                                    <button
                                        key={idx}
                                        onClick={() => setActiveImage(idx)}
                                        className={`w-16 h-16 sm:w-20 sm:h-20 rounded-xl overflow-hidden border-2 transition-all ${
                                            activeImage === idx
                                                ? 'border-indigo-600 ring-2 ring-indigo-200'
                                                : 'border-gray-200 hover:border-gray-400'
                                        }`}
                                    >
                                        <img src={assetUrl(img)} alt="" className="w-full h-full object-cover" />
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>

                    <div className="flex flex-col">
                        {/* Product Information Card */}
                        <div className="bg-white rounded-xl border border-gray-200 p-4 space-y-2">
                            <h1 className="text-lg sm:text-xl lg:text-2xl font-bold text-gray-900 leading-tight">
                                {product.name}
                            </h1>

                            <div className="flex items-center gap-2 text-sm text-gray-500">
                                <span className="px-2 py-0.5 bg-gray-100 text-gray-500 text-[11px] font-medium rounded-full">
                                    {product.category?.name || 'Uncategorized'}
                                </span>
                                <span>{product.brand?.name || 'Generic Brand'}</span>
                            </div>

                            <div className="flex items-center justify-between text-xs text-gray-500">
                                <span>
                                    Type: <span className="font-semibold text-gray-700">{isCombo ? 'Bundle' : isVariable ? 'Variable' : 'Single'}</span>
                                </span>
                                {!isVariable && (
                                    <span>
                                        SKU: <span className="font-medium text-gray-700">{product.sku || 'SKU not available'}</span>
                                    </span>
                                )}
                            </div>

                            <div className="pt-2 border-t border-gray-100">
                                <div className="flex items-center justify-between gap-3">
                                    <div>
                                        {isVariable && !allOptionsSelected ? (
                                            <span className="text-xl sm:text-2xl text-gray-300 font-medium">Select options</span>
                                        ) : (
                                            <div className="flex items-baseline gap-2 flex-wrap">
                                                <span className="text-2xl sm:text-3xl font-extrabold text-gray-900">
                                                    {priceDisplay}{currency}
                                                </span>
                                                {originalPrice > 0 && originalPrice > currentPrice && (
                                                    <>
                                                        <span className="text-base sm:text-lg text-gray-400 line-through">
                                                            {Number(originalPrice).toLocaleString()}{currency}
                                                        </span>
                                                        <span className="px-2 py-1 bg-red-100 text-red-700 rounded-lg text-xs font-bold">
                                                            Save {discountPercent}%
                                                        </span>
                                                    </>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                    <div className="flex-shrink-0">
                                        {renderStockBadge()}
                                    </div>
                                </div>
                            </div>

                            <p className="text-xs text-gray-500 pt-1 border-t border-gray-100">
                                Unit: <span className="font-medium text-gray-700">{product.unit?.name || 'Standard Unit'}</span>
                            </p>
                        </div>

                        {/* Short Description */}
                        <div className="mt-4">
                            <p className="text-sm text-gray-500 leading-relaxed line-clamp-3">
                                {product.short_description || 'No short description available for this product.'}
                            </p>
                        </div>

                        <div className="mt-4 border-t border-gray-200 pt-4 space-y-4">
                            {isVariable && optionKeys.length > 0 && (
                                <div className="space-y-3">
                                    {optionKeys.map((key, keyIdx) => (
                                        <div key={key}>
                                            <label className="text-sm font-semibold text-gray-900 capitalize block mb-2.5">
                                                {optionNames[key] || key.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}
                                            </label>
                                            <div className="flex flex-wrap gap-2">
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
                                                            className={`px-4 py-2.5 rounded-xl border-2 text-sm font-semibold transition-all ${
                                                                isSel
                                                                    ? 'border-indigo-600 bg-indigo-50 text-indigo-700 ring-2 ring-indigo-200 shadow-sm'
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
                                        <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                                            <span className="text-gray-600">
                                                Selected: <span className="font-semibold text-gray-900">{selectedVariant.label}</span>
                                            </span>
                                            {selectedVariant.sku && (
                                                <span className="text-gray-400">
                                                    SKU: <span className="font-mono">{selectedVariant.sku}</span>
                                                </span>
                                            )}
                                            {selectedVariant.price > 0 && (
                                                <span className="text-gray-600">
                                                    Price: <span className="font-semibold text-gray-900">{Number(selectedVariant.price).toLocaleString()}{currency}</span>
                                                </span>
                                            )}
                                        </div>
                                    )}
                                </div>
                            )}

                            {isCombo && detail?.combo_summary && (
                                <div className="space-y-2">
                                    <h3 className="text-sm font-semibold text-gray-900">What's Included</h3>
                                    <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                        {(detail.combo_summary.items || []).map((item, idx) => (
                                            <div key={idx} className="flex items-center gap-2.5 bg-gray-50 rounded-xl p-3 border border-gray-100">
                                                <div className="w-10 h-10 rounded-lg bg-gray-200 overflow-hidden shrink-0">
                                                    {item.image ? (
                                                        <img src={assetUrl(item.image)} alt={item.name} className="w-full h-full object-cover" />
                                                    ) : (
                                                        <div className="w-full h-full flex items-center justify-center text-gray-400">
                                                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                                                        </div>
                                                    )}
                                                </div>
                                                <div className="min-w-0">
                                                    <p className="text-xs font-medium text-gray-900 truncate">{item.name}</p>
                                                    <p className="text-xs text-gray-500">x{item.quantity || 1}</p>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}

                            <div className="border-t border-gray-100 pt-4">
                                {renderQuantityAndCart()}
                                <div className="flex flex-wrap items-center gap-4 mt-4 text-xs sm:text-sm text-gray-500">
                                    <span className="flex items-center gap-1.5">
                                        <svg className="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        Secure checkout
                                    </span>
                                    <span className="flex items-center gap-1.5">
                                        <svg className="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                                        Fast delivery
                                    </span>
                                    <span className="flex items-center gap-1.5">
                                        <svg className="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                                        7-day returns
                                    </span>
                                </div>
                            </div>
                        </div>

                        {/* Description Section */}
                        <div className="mt-4">
                            <h2 className="text-base font-bold text-gray-900 mb-2">Product Description</h2>
                            <div className="text-sm text-gray-500 leading-relaxed whitespace-pre-line">
                                {truncatedDescription}
                            </div>
                            {isLongDescription && (
                                <button onClick={() => setShowDescriptionModal(true)} className="mt-2 text-sm font-semibold text-indigo-600 hover:text-indigo-700 transition-colors">
                                    Read More
                                </button>
                            )}
                        </div>

                        {/* Product Specifications Card */}
                        <div className="bg-white rounded-xl border border-gray-200 p-4 mt-4">
                            <h3 className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Product Specifications</h3>
                            <div className="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-gray-500">Category</span>
                                    <span className="font-medium text-gray-800">{product.category?.name || 'Uncategorized'}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-gray-500">SKU</span>
                                    <span className="font-medium text-gray-800">{product.sku || 'SKU not available'}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-gray-500">Brand</span>
                                    <span className="font-medium text-gray-800">{product.brand?.name || 'Generic Brand'}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-gray-500">Unit</span>
                                    <span className="font-medium text-gray-800">{product.unit?.name || 'Standard Unit'}</span>
                                </div>
                                <div className="flex justify-between col-span-2">
                                    <span className="text-gray-500">Type</span>
                                    <span className="font-medium text-gray-800">{isCombo ? 'Bundle' : isVariable ? 'Variable' : 'Single'}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {isCombo && detail?.combo_summary && (
                    <div className="mt-12 lg:mt-16">
                        <h2 className="text-xl sm:text-2xl font-bold text-gray-900 mb-6">Bundle Details</h2>
                        <ComboViewDetail product={{
                            ...product,
                            combo_items: detail.combo_summary.items || [],
                            combo_summary: detail.combo_summary,
                            combo_availability: detail.combo_availability,
                        }} />
                    </div>
                )}
            </div>

            {/* Description Modal */}
            {showDescriptionModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" onClick={() => setShowDescriptionModal(false)}>
                    <div className="relative w-full max-w-4xl bg-white rounded-xl shadow-2xl overflow-hidden" onClick={e => e.stopPropagation()}>
                        <div className="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                            <h3 className="text-lg font-bold text-gray-900">Product Description</h3>
                            <button onClick={() => setShowDescriptionModal(false)} className="p-1.5 hover:bg-gray-100 rounded-lg transition-colors">
                                <svg className="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div className="px-6 py-4 overflow-y-auto max-h-[80vh] text-sm text-gray-600 leading-relaxed whitespace-pre-line">
                            {product.description}
                        </div>
                    </div>
                </div>
            )}

            <div className="fixed bottom-0 left-0 right-0 z-40 lg:hidden bg-white border-t border-gray-200 px-4 py-3 shadow-[0_-4px_20px_rgba(0,0,0,0.08)]">
                <div className="flex items-center justify-between gap-3">
                    <div className="min-w-0">
                        <div className="text-lg font-bold text-gray-900">
                            {priceDisplay ? `${priceDisplay}${currency}` : '—'}
                        </div>
                        <div className="text-xs text-gray-500 mt-0.5">
                            {renderStockBadge(true)}
                        </div>
                    </div>
                    <div className="flex-1 min-w-0">
                        {renderQuantityAndCart(true)}
                    </div>
                </div>
            </div>
        </ShopLayout>
    );
}
