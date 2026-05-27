import { useState, useMemo } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';
import ComboViewDetail from '@/Components/ProductView/ComboViewDetail';
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

function StockStatusBadge({ status, label }) {
    if (status === 'out_of_stock') {
        return (
            <span className="inline-flex items-center px-3 py-1 bg-red-100 text-red-700 rounded-full text-sm font-medium">
                Out of Stock
            </span>
        );
    }
    if (status === 'low_stock') {
        return (
            <span className="inline-flex items-center px-3 py-1 bg-yellow-100 text-yellow-700 rounded-full text-sm font-medium">
                {label || 'Low Stock'}
            </span>
        );
    }
    return (
        <span className="inline-flex items-center px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm font-medium">
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
    const isSingle = !!product.is_single;

    const variants = detail?.variants ?? [];
    const optionKeys = detail?.option_keys ?? [];
    const optionValues = detail?.option_values ?? {};

    const effectiveStock = product.effective_stock ?? product.stock ?? 0;

    const selectedVariant = useMemo(() => {
        if (!isVariable || !variants.length || !optionKeys.length) return null;
        if (optionKeys.some(key => !selectedOptions[key])) return null;
        return variants.find(v => {
            const attrs = v.attributes ?? {};
            return optionKeys.every(key => attrs[key] === selectedOptions[key]);
        }) || null;
    }, [isVariable, variants, optionKeys, selectedOptions]);

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

    const savings = useMemo(() => {
        if (promotion && promotion.discount > 0) return Math.round(promotion.discount);
        return 0;
    }, [promotion]);

    const images = [product.photo1, product.photo2].filter(Boolean);
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
        stockLabel = stockStatus === 'out_of_stock' ? `${selectedVariant.label} out of stock`
            : stockStatus === 'low_stock' ? `Only ${availableStock} left for ${selectedVariant.label}`
            : `${selectedVariant.label} in stock`;
    } else if (stockStatus === 'low_stock') {
        stockLabel = isCombo ? `Only ${availableStock} bundles available`
            : `Only ${availableStock} left in stock`;
    }

    const hasVariantImage = selectedVariant?.image_url;
    const mainImage = hasVariantImage ? selectedVariant.image_url : (images[activeImage] || null);

    return (
        <ShopLayout>
            <Head title={product.name} />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 sm:py-4">
                <nav className="flex text-sm text-gray-500 items-center gap-1 overflow-hidden">
                    <Link href="/" className="hover:text-blue-600 flex-shrink-0">Home</Link>
                    <span className="mx-1.5 text-gray-400">/</span>
                    <Link href={`/?category=${product.category_id}`} className="hover:text-blue-600 flex-shrink-0 truncate">
                        {product.category?.name || 'Products'}
                    </Link>
                    <span className="mx-1.5 text-gray-400">/</span>
                    <span className="text-gray-900 font-medium truncate">{product.name}</span>
                </nav>
            </div>

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-12 sm:pb-16">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6 sm:gap-8 lg:gap-10">
                    <div>
                        <div className="relative aspect-square bg-gray-100 rounded-lg overflow-hidden border border-gray-200">
                            {mainImage ? (
                                <img
                                    src={assetUrl(mainImage)}
                                    alt={product.name}
                                    className="w-full h-full object-cover"
                                />
                            ) : (
                                <div className="flex items-center justify-center h-full">
                                    <svg className="w-32 h-32 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                            )}

                            {isCombo && (
                                <div className="absolute top-3 left-3 px-3 py-1.5 bg-purple-600 text-white text-sm font-bold rounded-full shadow-lg">
                                    Bundle
                                </div>
                            )}

                            {isVariable && (
                                <div className="absolute top-3 left-3 px-3 py-1.5 bg-blue-600 text-white text-sm font-bold rounded-full shadow-lg">
                                    Multiple Options
                                </div>
                            )}

                            {hasPromotion && (
                                <div className="absolute top-3 right-3 px-3 py-1.5 bg-red-500 text-white text-sm font-bold rounded-full shadow-lg">
                                    {promotion.badge}
                                </div>
                            )}
                        </div>

                        {!isVariable && images.length > 1 && (
                            <div className="flex gap-2 mt-4">
                                {images.map((img, idx) => (
                                    <button
                                        key={idx}
                                        onClick={() => setActiveImage(idx)}
                                        className={`w-20 h-20 rounded-lg overflow-hidden border-2 transition-colors ${
                                            activeImage === idx ? 'border-blue-600' : 'border-gray-200 hover:border-gray-300'
                                        }`}
                                    >
                                        <img src={assetUrl(img)} alt="" className="w-full h-full object-cover" />
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>

                    <div>
                        <p className="text-sm text-gray-500 mb-1">{product.category?.name || ''}</p>
                        <h1 className="text-2xl sm:text-3xl font-bold text-gray-900">{product.name}</h1>

                        {isCombo ? (
                            <div className="mt-4">
                                <div className="flex items-baseline gap-3 flex-wrap">
                                    <span className="text-3xl font-bold text-blue-600">
                                        {safeNum(displayPrice).toLocaleString()} MMK
                                    </span>
                                    {originalPrice > 0 && originalPrice > displayPrice && (
                                        <span className="text-lg text-gray-400 line-through">
                                            {safeNum(originalPrice).toLocaleString()} MMK
                                        </span>
                                    )}
                                </div>
                                {detail?.combo_summary?.savings > 0 && (
                                    <div className="mt-3 inline-flex items-center gap-2 px-4 py-2 bg-green-50 border border-green-200 rounded-xl">
                                        <svg className="w-5 h-5 text-green-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <div>
                                            <p className="text-sm font-semibold text-green-800">
                                                Bundle Save {safeNum(detail.combo_summary.savings).toLocaleString()} MMK
                                            </p>
                                            {detail.combo_summary.savings_percentage > 0 && (
                                                <p className="text-xs text-green-600">{detail.combo_summary.savings_percentage}% vs buying individually</p>
                                            )}
                                        </div>
                                    </div>
                                )}
                            </div>
                        ) : isVariable ? (
                            <div className="mt-4">
                                {!allOptionsSelected || !displayPrice ? (
                                    <span className="text-3xl text-gray-400 font-medium">Select options to see price</span>
                                ) : (
                                    <div className="flex items-baseline gap-3 flex-wrap">
                                        <span className="text-3xl font-bold text-blue-600">
                                            {safeNum(displayPrice).toLocaleString()} MMK
                                        </span>
                                    </div>
                                )}
                            </div>
                        ) : (
                            <div className="mt-4 flex items-baseline gap-3 flex-wrap">
                                <span className="text-3xl font-bold text-blue-600">
                                    {safeNum(displayPrice).toLocaleString()} MMK
                                </span>
                                {originalPrice > 0 && (
                                    <span className="text-lg text-gray-400 line-through">
                                        {safeNum(originalPrice).toLocaleString()} MMK
                                    </span>
                                )}
                            </div>
                        )}

                        {hasPromotion && savings > 0 && (
                            <div className="mt-3 inline-flex items-center gap-2 px-4 py-2 bg-green-50 border border-green-200 rounded-xl">
                                <svg className="w-5 h-5 text-green-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <div>
                                    <p className="text-sm font-semibold text-green-800">
                                        You save {savings.toLocaleString()} MMK
                                    </p>
                                    <p className="text-xs text-green-600">{promotion.name}</p>
                                </div>
                            </div>
                        )}

                        {isVariable && optionKeys.length > 0 && (
                            <div className="mt-6 space-y-4">
                                {optionKeys.map((key, keyIdx) => (
                                    <div key={key}>
                                        <label className="text-sm font-medium text-gray-900 capitalize">{key}</label>
                                        <div className="flex flex-wrap gap-2 mt-2">
                                            {(optionValues[key] || []).map((value) => {
                                                const isSelected = selectedOptions[key] === value;
                                                return (
                                                    <button
                                                        key={value}
                                                        onClick={() => handleOptionChange(key, value)}
                                                        className={`px-4 py-2 rounded-lg border-2 text-sm font-medium transition-all ${
                                                            isSelected
                                                                ? 'border-blue-600 bg-blue-50 text-blue-700 ring-2 ring-blue-200'
                                                                : OPTION_STYLES[(keyIdx + optionValues[key].indexOf(value)) % OPTION_STYLES.length]
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
                                    <p className="text-sm text-gray-600">
                                        Selected: <span className="font-medium">{selectedVariant.label}</span>
                                    </p>
                                )}
                            </div>
                        )}

                        <div className="mt-4">
                            {isVariable && !selectedVariant && optionKeys.length > 0 ? (
                                <span className="inline-flex items-center px-3 py-1 bg-gray-100 text-gray-500 rounded-full text-sm font-medium">
                                    Select options to check availability
                                </span>
                            ) : (
                                <StockStatusBadge status={stockStatus} label={stockLabel} />
                            )}
                        </div>

                        {product.description && (
                            <div className="mt-6">
                                <h3 className="text-sm font-medium text-gray-900">Description</h3>
                                <p className="mt-2 text-gray-600 leading-relaxed whitespace-pre-wrap">
                                    {product.description}
                                </p>
                            </div>
                        )}

                        {availableStock > 0 && (
                            <div className="mt-6 space-y-4">
                                <div className="flex items-center gap-4">
                                    <label className="text-sm font-medium text-gray-700">Quantity:</label>
                                    <div className="flex items-center border border-gray-300 rounded-lg">
                                        <button
                                            onClick={() => setQuantity(Math.max(1, quantity - 1))}
                                            className="px-3 py-2 text-gray-600 hover:bg-gray-100 rounded-l-lg"
                                        >
                                            -
                                        </button>
                                        <span className="px-4 py-2 text-gray-900 font-medium min-w-[3rem] text-center">
                                            {quantity}
                                        </span>
                                        <button
                                            onClick={() => setQuantity(Math.min(availableStock, quantity + 1))}
                                            className="px-3 py-2 text-gray-600 hover:bg-gray-100 rounded-r-lg"
                                        >
                                            +
                                        </button>
                                    </div>
                                    <span className="text-sm text-gray-500">Max: {availableStock}</span>
                                </div>

                                <button
                                    onClick={handleAddToCart}
                                    disabled={!allOptionsSelected}
                                    className={`w-full py-3 rounded-lg font-medium text-lg transition-colors flex items-center justify-center gap-2 ${
                                        !allOptionsSelected
                                            ? 'bg-gray-300 text-gray-500 cursor-not-allowed'
                                            : addedToCart
                                                ? 'bg-green-600 text-white'
                                                : 'bg-blue-600 hover:bg-blue-700 text-white'
                                    }`}
                                >
                                    {addedToCart ? (
                                        <>
                                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                            </svg>
                                            Added to Cart!
                                        </>
                                    ) : !allOptionsSelected ? (
                                        'Select Options'
                                    ) : (
                                        <>
                                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z" />
                                            </svg>
                                            Add to Cart
                                        </>
                                    )}
                                </button>
                            </div>
                        )}
                    </div>
                </div>

                {isCombo && detail?.combo_summary && (
                    <div className="mt-10 space-y-6">
                        <h2 className="text-xl font-bold text-gray-900">Bundle Details</h2>
                        <ComboViewDetail product={{
                            ...product,
                            combo_items: detail.combo_summary.items || [],
                            combo_summary: detail.combo_summary,
                            combo_availability: detail.combo_availability,
                        }} />
                    </div>
                )}
            </div>
        </ShopLayout>
    );
}
