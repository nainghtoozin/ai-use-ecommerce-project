import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';
import { assetUrl } from '@/Utils/helpers';

export default function ProductShow({ product, promotion }) {
    const [activeImage, setActiveImage] = useState(0);
    const [quantity, setQuantity] = useState(1);
    const [addedToCart, setAddedToCart] = useState(false);

    const images = [product.photo1, product.photo2].filter(Boolean);
    const hasPromotion = promotion && promotion.discount > 0;
    const displayPrice = hasPromotion ? Math.max(0, product.price - promotion.discount) : product.price;
    const savings = hasPromotion ? Math.round(promotion.discount) : 0;

    function handleAddToCart() {
        router.post('/cart/add', {
            product_id: product.id,
            quantity: quantity,
        }, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                setAddedToCart(true);
                setTimeout(() => setAddedToCart(false), 2000);
            },
        });
    }

    return (
        <ShopLayout>
            <Head title={product.name} />

            {/* Breadcrumb */}
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 sm:py-4">
                <nav className="flex text-sm text-gray-500 items-center gap-1 overflow-hidden">
                    <Link href="/" className="hover:text-blue-600 flex-shrink-0">Home</Link>
                    <span className="mx-1.5 text-gray-400">/</span>
                    <Link href={`/?category=${product.category_id}`} className="hover:text-blue-600 flex-shrink-0 truncate">
                        {product.category?.name}
                    </Link>
                    <span className="mx-1.5 text-gray-400">/</span>
                    <span className="text-gray-900 font-medium truncate">{product.name}</span>
                </nav>
            </div>

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-12 sm:pb-16">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6 sm:gap-8 lg:gap-10">
                    {/* Images */}
                    <div>
                        {/* Main Image */}
                        <div className="relative aspect-square bg-gray-100 rounded-lg overflow-hidden border border-gray-200">
                            {images.length > 0 ? (
                                <img
                                    src={assetUrl(images[activeImage])}
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

                            {/* Promotion badge */}
                            {hasPromotion && (
                                <div className="absolute top-3 right-3 px-3 py-1.5 bg-red-500 text-white text-sm font-bold rounded-full shadow-lg">
                                    {promotion.badge}
                                </div>
                            )}
                        </div>

                        {/* Thumbnails */}
                        {images.length > 1 && (
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

                    {/* Details */}
                    <div>
                        <p className="text-sm text-gray-500 mb-1">{product.category?.name}</p>
                        <h1 className="text-2xl sm:text-3xl font-bold text-gray-900">{product.name}</h1>

                        {/* Price with promotion */}
                        <div className="mt-4 flex items-baseline gap-3 flex-wrap">
                            <span className="text-3xl font-bold text-blue-600">
                                {Number(displayPrice).toLocaleString()} MMK
                            </span>
                            {hasPromotion && (
                                <span className="text-lg text-gray-400 line-through">
                                    {Number(product.price).toLocaleString()} MMK
                                </span>
                            )}
                        </div>

                        {/* Savings callout */}
                        {hasPromotion && (
                            <div className="mt-3 inline-flex items-center gap-2 px-4 py-2 bg-green-50 border border-green-200 rounded-xl">
                                <svg className="w-5 h-5 text-green-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <div>
                                    <p className="text-sm font-semibold text-green-800">
                                        You save {Number(savings).toLocaleString()} MMK
                                    </p>
                                    <p className="text-xs text-green-600">{promotion.name}</p>
                                </div>
                            </div>
                        )}

                        {/* Stock */}
                        <div className="mt-4">
                            {product.stock === 0 ? (
                                <span className="inline-flex items-center px-3 py-1 bg-red-100 text-red-700 rounded-full text-sm font-medium">
                                    Out of Stock
                                </span>
                            ) : product.stock < 10 ? (
                                <span className="inline-flex items-center px-3 py-1 bg-yellow-100 text-yellow-700 rounded-full text-sm font-medium">
                                    Only {product.stock} left in stock
                                </span>
                            ) : (
                                <span className="inline-flex items-center px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm font-medium">
                                    In Stock
                                </span>
                            )}
                        </div>

                        {/* Description */}
                        {product.description && (
                            <div className="mt-6">
                                <h3 className="text-sm font-medium text-gray-900">Description</h3>
                                <p className="mt-2 text-gray-600 leading-relaxed whitespace-pre-wrap">
                                    {product.description}
                                </p>
                            </div>
                        )}

                        {/* Quantity & Add to Cart */}
                        {product.stock > 0 && (
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
                                            onClick={() => setQuantity(Math.min(product.stock, quantity + 1))}
                                            className="px-3 py-2 text-gray-600 hover:bg-gray-100 rounded-r-lg"
                                        >
                                            +
                                        </button>
                                    </div>
                                    <span className="text-sm text-gray-500">Max: {product.stock}</span>
                                </div>

                                <button
                                    onClick={handleAddToCart}
                                    className={`w-full py-3 rounded-lg font-medium text-lg transition-colors flex items-center justify-center gap-2 ${
                                        addedToCart
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
            </div>
        </ShopLayout>
    );
}
