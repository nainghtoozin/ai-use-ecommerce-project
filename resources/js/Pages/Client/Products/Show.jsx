import { useState, useMemo } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';
import ComboViewDetail from '@/Components/ProductView/ComboViewDetail';
import { assetUrl } from '@/Utils/helpers';

function safeNum(val) {
    const n = Number(val);
    return Number.isFinite(n) ? n : 0;
}

function StockStatusBadge({ status, label }) {
    if (status === 'out_of_stock') {
        return (
            <span className="inline-flex items-center px-2.5 py-0.5 bg-red-100 text-red-700 rounded-full text-xs font-medium">
                Out of Stock
            </span>
        );
    }
    if (status === 'low_stock') {
        return (
            <span className="inline-flex items-center px-2.5 py-0.5 bg-yellow-100 text-yellow-700 rounded-full text-xs font-medium">
                {label || 'Low Stock'}
            </span>
        );
    }
    return (
        <span className="inline-flex items-center px-2.5 py-0.5 bg-green-100 text-green-700 rounded-full text-xs font-medium">
            {label || 'In Stock'}
        </span>
    );
}

export default function ProductShow({ product, promotion, detail }) {
    const [activeImage, setActiveImage] = useState(0);
    const [quantity, setQuantity] = useState(1);
    const [addedToCart, setAddedToCart] = useState(false);
    const [selectedOptions, setSelectedOptions] = useState({});

    const isVariable = !!product.is_variable;
    const isCombo = !!product.is_combo;

    const productType = isCombo ? 'Bundle' : isVariable ? 'Variable' : 'Single';

    const variants = detail?.variants ?? [];
    const optionKeys = detail?.option_keys ?? [];
    const optionValues = detail?.option_values ?? {};
    const optionNames = detail?.option_names ?? {};

    const effectiveStock = product.effective_stock ?? product.stock ?? 0;

    const selectedVariant = useMemo(() => {
        if (!isVariable || !variants.length || !optionKeys.length) return null;
        if (optionKeys.some(key => !selectedOptions[key])) return null;
        return variants.find(v => {
            const attrs = v.attributes ?? {};
            return optionKeys.every(key => attrs[key] === selectedOptions[key]);
        }) || null;
    }, [isVariable, variants, optionKeys, selectedOptions]);

    const displaySku = useMemo(() => {
        if (isVariable && selectedVariant?.sku) return selectedVariant.sku;
        return product.sku;
    }, [isVariable, selectedVariant, product.sku]);

    const unitName = product.unit?.name || 'Standard Unit';

    const availableStock = useMemo(() => {
        if (isVariable && selectedVariant) return safeNum(selectedVariant.stock);
        if (isVariable) return 0;
        return effectiveStock;
    }, [isVariable, selectedVariant, effectiveStock]);

    const displayPrice = useMemo(() => {
        if (isVariable && selectedVariant) return safeNum(selectedVariant.price);
        if (isVariable) return null;

        if (promotion && promotion.discount > 0) {
            const basePrice = detail?.price ?? safeNum(product.price);
            return Math.max(0, basePrice - promotion.discount);
        }

        if (isCombo) return detail?.price ?? safeNum(product.price);
        return detail?.price ?? safeNum(product.price);
    }, [isVariable, selectedVariant, promotion, detail, product.price]);

    const originalPrice = useMemo(() => {
        if (isVariable) return null;
        if (isCombo && detail?.price) {
            const summary = detail?.combo_summary;
            return summary?.base_price > summary?.combo_price ? summary.base_price : null;
        }
        if (promotion && promotion.discount > 0) {
            return detail?.price ?? safeNum(product.price);
        }
        return null;
    }, [isVariable, isCombo, promotion, detail, product.price]);

    const allOptionsSelected = !isVariable || !optionKeys.length || optionKeys.every(key => selectedOptions[key]);

    const isOptionAvailable = useMemo(() => {
        return (key, value) => {
            const testOptions = { ...selectedOptions, [key]: value };
            return variants.some(v => {
                const attrs = v.attributes ?? {};
                return optionKeys.every(k => !testOptions[k] || attrs[k] === testOptions[k]);
            });
        };
    }, [variants, optionKeys, selectedOptions]);

    const savings = useMemo(() => {
        if (promotion && promotion.discount > 0) return Math.round(promotion.discount);
        return 0;
    }, [promotion]);

    const images = useMemo(() => {
        const gallery = product.gallery_images_url || product.gallery_images || [];
        const base = [product.photo1, ...gallery].filter(Boolean);
        if (isVariable && selectedVariant?.image_url) {
            return [selectedVariant.image_url, ...base.filter(url => url !== selectedVariant.image_url)];
        }
        return base;
    }, [product.photo1, product.gallery_images, product.gallery_images_url, isVariable, selectedVariant]);

    const hasPromotion = !isVariable && promotion && promotion.discount > 0;

    function handleOptionChange(key, value) {
        setSelectedOptions(prev => ({ ...prev, [key]: value }));
    }

    function handleAddToCart() {
        if (!allOptionsSelected) return;

        const payload = {
            product_id: product.id,
            quantity: quantity,
        };

        if (isVariable && selectedVariant) {
            payload.variant_id = selectedVariant.id;
        }

        router.post('/cart/add', payload, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                setAddedToCart(true);
                setTimeout(() => setAddedToCart(false), 2000);
            },
        });
    }

    const stockStatus = availableStock <= 0 ? 'out_of_stock' : availableStock < 10 ? 'low_stock' : 'in_stock';

    let stockLabel = 'In Stock';
    if (isVariable && selectedVariant) {
        stockLabel = stockStatus === 'out_of_stock' ? 'Out of Stock'
            : stockStatus === 'low_stock' ? 'Low Stock'
            : 'In Stock';
    } else if (stockStatus === 'low_stock') {
        stockLabel = isCombo ? `Only ${availableStock} bundles available`
            : `Only ${availableStock} left in stock`;
    }

    const canAddToCart = allOptionsSelected && availableStock > 0;
    const showStickyBar = !isVariable || (isVariable && (selectedVariant || !optionKeys.length));

    return (
        <ShopLayout>
            <Head title={product.seo_title || product.name}>
                <meta name="description" content={product.seo_description || product.short_description || ''} />
                {product.seo_keywords && <meta name="keywords" content={product.seo_keywords} />}
                <meta property="og:title" content={product.seo_title || product.name} />
                <meta property="og:description" content={product.seo_description || product.short_description || ''} />
                <meta property="og:image" content={assetUrl(product.seo_image || product.photo1_url)} />
                <meta name="twitter:image" content={assetUrl(product.seo_image || product.photo1_url)} />
            </Head>

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
                <nav className="flex text-xs sm:text-sm text-gray-400 items-center gap-1 overflow-hidden">
                    <Link href="/" className="hover:text-gray-600 flex-shrink-0">Home</Link>
                    <span className="mx-1 text-gray-300">/</span>
                    <Link href={`/?category=${product.category_id}`} className="hover:text-gray-600 flex-shrink-0 truncate">
                        {product.category?.name || 'Products'}
                    </Link>
                    <span className="mx-1 text-gray-300">/</span>
                    <span className="text-gray-700 font-medium truncate text-xs sm:text-sm">{product.name}</span>
                </nav>
            </div>

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-32 sm:pb-16">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-10">
                    {/* Left: Product Gallery */}
                    {images.length > 0 && (
                        <div className="space-y-3 w-full md:max-w-[400px] lg:max-w-[500px]">
                            <div className="relative aspect-square bg-gray-50 rounded-xl overflow-hidden border border-gray-100 max-h-[500px]">
                                <img
                                    src={assetUrl(images[activeImage])}
                                    alt={product.name}
                                    className="w-full h-full object-cover"
                                />

                                {isCombo && (
                                    <div className="absolute top-3 left-3 px-2.5 py-1 bg-purple-600 text-white text-xs font-bold rounded-full shadow-sm">
                                        Bundle
                                    </div>
                                )}

                                {isVariable && (
                                    <div className="absolute top-3 left-3 px-2.5 py-1 bg-blue-600 text-white text-xs font-bold rounded-full shadow-sm">
                                        Multiple Options
                                    </div>
                                )}

                                {hasPromotion && (
                                    <div className="absolute top-3 right-3 px-2.5 py-1 bg-red-500 text-white text-xs font-bold rounded-full shadow-sm">
                                        {promotion.badge}
                                    </div>
                                )}
                            </div>

                            {images.length > 1 && (
                                <div className="flex gap-2 overflow-x-auto pb-1">
                                    {images.map((img, idx) => (
                                        <button
                                            key={idx}
                                            onClick={() => setActiveImage(idx)}
                                            className={`flex-shrink-0 w-16 h-16 sm:w-20 sm:h-20 rounded-lg overflow-hidden border-2 transition-colors ${
                                                activeImage === idx ? 'border-blue-600' : 'border-gray-200 hover:border-gray-400'
                                            }`}
                                        >
                                            <img src={assetUrl(img)} alt="" className="w-full h-full object-cover" />
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>
                    )}

                    {/* Right: Product Info */}
                    <div className={images.length > 0 ? 'flex flex-col gap-0' : 'md:col-span-2 flex flex-col gap-0'}>

                        {/* Product Information Card */}
                        <div className="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
                            <h1 className="text-xl sm:text-2xl lg:text-3xl font-bold text-gray-900 leading-tight">
                                {product.name}
                            </h1>

                            <div className="flex items-center gap-2 flex-wrap">
                                <span className="px-2.5 py-0.5 bg-gray-100 text-gray-500 text-[11px] font-medium rounded-full">
                                    {product.category?.name || 'Uncategorized'}
                                </span>
                                <span className="text-sm text-gray-400">{product.brand?.name || 'Generic Brand'}</span>
                            </div>

                            <div className="flex items-center justify-between gap-2">
                                <span className="text-xs text-gray-500">
                                    Type: <span className={`font-semibold ${isCombo ? 'text-purple-600' : isVariable ? 'text-blue-600' : 'text-gray-700'}`}>{productType}</span>
                                </span>
                                {!isVariable && (
                                    <span className="text-xs text-gray-500">
                                        SKU: <span className="font-medium text-gray-700">{displaySku || 'SKU not available'}</span>
                                    </span>
                                )}
                            </div>

                            {/* Price + Stock row */}
                            <div className="pt-2 border-t border-gray-100">
                                <div className="flex items-center justify-between gap-3">
                                    <div>
                                        {isVariable ? (
                                            !allOptionsSelected || !displayPrice ? (
                                                <span className="text-xl sm:text-2xl text-gray-300 font-medium">Select options</span>
                                            ) : (
                                                <div className="flex items-baseline gap-2">
                                                    <span className="text-2xl sm:text-3xl font-extrabold text-gray-900">
                                                        {safeNum(displayPrice).toLocaleString()} <span className="text-base sm:text-lg font-medium text-gray-500">MMK</span>
                                                    </span>
                                                </div>
                                            )
                                        ) : (
                                            <div className="flex items-baseline gap-2 flex-wrap">
                                                <span className="text-2xl sm:text-3xl font-extrabold text-gray-900">
                                                    {safeNum(displayPrice).toLocaleString()} <span className="text-base sm:text-lg font-medium text-gray-500">MMK</span>
                                                </span>
                                                {originalPrice > 0 && (
                                                    <span className="text-base sm:text-lg text-gray-400 line-through">
                                                        {safeNum(originalPrice).toLocaleString()} MMK
                                                    </span>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                    <div className="flex-shrink-0">
                                        {isVariable && !selectedVariant && optionKeys.length > 0 ? (
                                            <span className="text-xs text-gray-400">Select options to see availability</span>
                                        ) : (
                                            <StockStatusBadge status={stockStatus} label={stockLabel} />
                                        )}
                                    </div>
                                </div>
                                {isCombo && detail?.combo_summary?.savings > 0 && (
                                    <p className="text-xs text-green-600 font-semibold mt-1">
                                        Save {safeNum(detail.combo_summary.savings).toLocaleString()} MMK ({detail.combo_summary.savings_percentage}%)
                                    </p>
                                )}
                                {hasPromotion && savings > 0 && (
                                    <p className="text-xs text-green-600 font-semibold mt-1">You save {savings.toLocaleString()} MMK</p>
                                )}
                            </div>

                            <p className="text-xs text-gray-500 pt-1 border-t border-gray-100">
                                Unit: <span className="font-medium text-gray-700">{unitName}</span>
                            </p>
                        </div>

                        {/* Short Description */}
                        <div className="mt-4">
                            <p className="text-sm text-gray-500 leading-relaxed line-clamp-3">
                                {product.short_description || 'No short description available for this product.'}
                            </p>
                        </div>

                        {/* Product Details Card */}
                        <div className="bg-white rounded-xl border border-gray-200 p-5 mt-4 space-y-2.5">
                            <h3 className="text-xs font-semibold text-gray-500 uppercase tracking-wide">Product Details</h3>
                            <div className="space-y-1.5 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-gray-500">Category</span>
                                    <span className="font-medium text-gray-800 text-right">{product.category?.name || 'Uncategorized'}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-gray-500">Brand</span>
                                    <span className="font-medium text-gray-800 text-right">{product.brand?.name || 'Generic Brand'}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-gray-500">SKU</span>
                                    <span className="font-medium text-gray-800 text-right">{displaySku || 'SKU not available'}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-gray-500">Unit</span>
                                    <span className="font-medium text-gray-800 text-right">{unitName}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-gray-500">Type</span>
                                    <span className="font-medium text-gray-800 text-right">{productType}</span>
                                </div>
                            </div>
                        </div>

                        {/* Description Section */}
                        <div className="mt-6">
                            <h2 className="text-base font-bold text-gray-900 mb-2">Product Description</h2>
                            <div className="text-sm text-gray-500 leading-relaxed whitespace-pre-line">
                                {product.description || 'Detailed product information will be available soon.'}
                            </div>
                        </div>

                        {/* Variable: Available Options */}
                        {isVariable && optionKeys.length > 0 && (
                            <div className="mt-5 space-y-3">
                                <h3 className="text-xs font-semibold text-gray-500 uppercase tracking-wide">Available Options</h3>
                                <p className="text-[11px] text-gray-400 font-medium">Please select all options</p>
                                {optionKeys.map((key) => {
                                    const displayName = optionNames[key] || key.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
                                    return (
                                        <div key={key}>
                                            <label className="text-xs font-semibold text-gray-500 uppercase tracking-wide">{displayName}</label>
                                            <div className="flex flex-wrap gap-2 mt-1.5">
                                                {(optionValues[key] || []).map((value) => {
                                                    const isSelected = selectedOptions[key] === value;
                                                    const available = isOptionAvailable(key, value);
                                                    return (
                                                        <button
                                                            key={value}
                                                            onClick={() => available && handleOptionChange(key, value)}
                                                            className={`px-3.5 py-1.5 rounded-lg border text-sm font-medium transition-all ${
                                                                isSelected
                                                                    ? 'border-blue-600 bg-blue-50 text-blue-700 ring-2 ring-blue-200'
                                                                    : available
                                                                        ? 'border-gray-200 bg-white text-gray-700 hover:border-gray-400 hover:bg-gray-50'
                                                                        : 'border-gray-100 bg-gray-50 text-gray-300 cursor-not-allowed'
                                                            }`}
                                                        >
                                                            {value}
                                                        </button>
                                                    );
                                                })}
                                            </div>
                                        </div>
                                    );
                                })}
                                {selectedVariant && (
                                    <div className="text-xs text-gray-500 space-y-0.5 pt-1">
                                        <p>Selected: <span className="font-medium text-gray-700">{selectedVariant.label}</span></p>
                                        <p>SKU: <span className="font-medium text-gray-700">{selectedVariant.sku || 'SKU not available'}</span></p>
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Combo: Bundle Includes */}
                        {isCombo && detail?.combo_summary?.items?.length > 0 && (
                            <div className="mt-5 space-y-2">
                                <h3 className="text-xs font-semibold text-gray-500 uppercase tracking-wide">Bundle Includes</h3>
                                <div className="space-y-1.5">
                                    {detail.combo_summary.items.map((item, idx) => (
                                        <div key={idx} className="flex items-center gap-3 text-sm">
                                            <span className="flex-shrink-0 w-6 h-6 rounded bg-gray-100 flex items-center justify-center text-[10px] font-bold text-gray-500">
                                                {idx + 1}
                                            </span>
                                            <span className="text-gray-700 truncate">
                                                {item.product_name}
                                                {item.variant_label ? <span className="text-gray-400"> ({item.variant_label})</span> : ''}
                                            </span>
                                            <span className="flex-shrink-0 text-gray-400 ml-auto">×{item.quantity}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {/* Add to Cart Section */}
                        <div className="mt-6 pt-5 border-t border-gray-100">
                            {availableStock > 0 ? (
                                <div className="space-y-4">
                                    <div className="flex items-center gap-3">
                                        <label className="text-sm font-medium text-gray-700">Qty</label>
                                        <div className="flex items-center border border-gray-300 rounded-lg">
                                            <button
                                                onClick={() => setQuantity(Math.max(1, quantity - 1))}
                                                disabled={quantity <= 1}
                                                className="px-3 py-1.5 text-gray-500 hover:bg-gray-100 rounded-l-lg disabled:opacity-30 disabled:cursor-not-allowed text-sm"
                                            >
                                                &minus;
                                            </button>
                                            <span className="px-3 py-1.5 text-gray-900 font-medium min-w-[2.5rem] text-center text-sm">
                                                {quantity}
                                            </span>
                                            <button
                                                onClick={() => setQuantity(Math.min(availableStock, quantity + 1))}
                                                disabled={quantity >= availableStock}
                                                className="px-3 py-1.5 text-gray-500 hover:bg-gray-100 rounded-r-lg disabled:opacity-30 disabled:cursor-not-allowed text-sm"
                                            >
                                                +
                                            </button>
                                        </div>
                                        <span className="text-xs text-gray-400">{availableStock} available</span>
                                    </div>

                                    <button
                                        onClick={handleAddToCart}
                                        disabled={!canAddToCart}
                                        className={`w-full py-3 rounded-xl font-semibold text-sm transition-all flex items-center justify-center gap-2 disabled:cursor-not-allowed active:scale-[0.99] ${
                                            canAddToCart
                                                ? 'text-white shadow-sm hover:opacity-90'
                                                : 'bg-gray-200 text-gray-400'
                                        }`}
                                        style={canAddToCart ? { backgroundColor: 'var(--theme-color, #3B82F6)' } : {}}
                                    >
                                        {addedToCart ? (
                                            <>
                                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                                </svg>
                                                Added to Cart
                                            </>
                                        ) : !allOptionsSelected ? (
                                            'Please select all options'
                                        ) : (
                                            <>
                                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z" />
                                                </svg>
                                                Add to Cart
                                            </>
                                        )}
                                    </button>
                                </div>
                            ) : (
                                <button
                                    disabled
                                    className="w-full py-3 rounded-xl font-semibold text-sm bg-gray-100 text-gray-400 cursor-not-allowed flex items-center justify-center gap-2"
                                >
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z" />
                                    </svg>
                                    Out of Stock
                                </button>
                            )}
                        </div>
                    </div>
                </div>

                {/* Combo View Detail (full section) */}
                {isCombo && detail?.combo_summary && (
                    <div className="mt-10 space-y-6">
                        <h2 className="text-lg font-bold text-gray-900">Bundle Details</h2>
                        <ComboViewDetail product={{
                            ...product,
                            combo_items: detail.combo_summary.items || [],
                            combo_summary: detail.combo_summary,
                            combo_availability: detail.combo_availability,
                        }} />
                    </div>
                )}
            </div>

            {/* Sticky Mobile Add to Cart Bar */}
            {showStickyBar && (
                <div className="fixed bottom-0 left-0 right-0 z-40 bg-white border-t border-gray-200 px-4 py-3 md:hidden">
                    <div className="flex items-center gap-3">
                        <div className="flex-shrink-0">
                            {isVariable && displayPrice ? (
                                <span className="text-lg font-extrabold text-gray-900">
                                    {safeNum(displayPrice).toLocaleString()} <span className="text-xs font-medium text-gray-500">MMK</span>
                                </span>
                            ) : !isVariable ? (
                                <span className="text-lg font-extrabold text-gray-900">
                                    {safeNum(displayPrice).toLocaleString()} <span className="text-xs font-medium text-gray-500">MMK</span>
                                </span>
                            ) : (
                                <span className="text-sm text-gray-400">Select options</span>
                            )}
                            {availableStock > 0 && (
                                <p className="text-[10px] text-gray-400">{availableStock} available</p>
                            )}
                        </div>
                        <div className="flex-1 flex items-center gap-2">
                            <div className="flex items-center border border-gray-300 rounded-lg">
                                <button
                                    onClick={() => setQuantity(Math.max(1, quantity - 1))}
                                    disabled={quantity <= 1}
                                    className="px-2.5 py-1.5 text-gray-500 text-sm disabled:opacity-30"
                                >
                                    &minus;
                                </button>
                                <span className="px-2.5 py-1.5 text-gray-900 font-medium text-sm min-w-[2rem] text-center">
                                    {quantity}
                                </span>
                                <button
                                    onClick={() => setQuantity(Math.min(availableStock || 1, quantity + 1))}
                                    disabled={quantity >= (availableStock || 1)}
                                    className="px-2.5 py-1.5 text-gray-500 text-sm disabled:opacity-30"
                                >
                                    +
                                </button>
                            </div>
                            <button
                                onClick={handleAddToCart}
                                disabled={!canAddToCart}
                                className={`flex-1 py-2.5 rounded-lg font-semibold text-sm transition-all disabled:cursor-not-allowed active:scale-[0.99] ${
                                    canAddToCart ? 'text-white shadow-sm' : 'bg-gray-200 text-gray-400'
                                }`}
                                style={canAddToCart ? { backgroundColor: 'var(--theme-color, #3B82F6)' } : {}}
                            >
                                {addedToCart ? 'Added!' : !allOptionsSelected ? 'Select Options' : 'Add to Cart'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </ShopLayout>
    );
}
