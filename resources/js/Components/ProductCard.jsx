import { useState, useEffect, memo } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import { assetUrl } from '@/Utils/helpers';
import { Heart } from 'lucide-react';
import { useWishlist } from '@/Hooks/useWishlist';

const ProductCard = memo(function ProductCard({ product, onAddToCart, addingId = null }) {
    const { props } = usePage();
    const { auth, wishlisted_ids = [] } = props;
    const { toggleWishlist } = useWishlist();

    const [imageLoaded, setImageLoaded] = useState(false);
    const [isAdding, setIsAdding] = useState(false);
    const [wishlistAnim, setWishlistAnim] = useState(false);
    const [optimisticWishlisted, setOptimisticWishlisted] = useState(
        wishlisted_ids.includes(product.id)
    );

    useEffect(() => {
        setOptimisticWishlisted(wishlisted_ids.includes(product.id));
    }, [wishlisted_ids, product.id]);

    const handleAddToCart = async (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (addingId === product.id) return;

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
            router.visit('/login');
            return;
        }

        setOptimisticWishlisted((prev) => !prev);
        setWishlistAnim(true);
        setTimeout(() => setWishlistAnim(false), 400);

        toggleWishlist(product.id, optimisticWishlisted);
    };

    const isOutOfStock = product.stock === 0;
    const isLowStock = product.stock > 0 && product.stock < 10;
    const hasPromotion = product.promotion_price && product.promotion_price < product.price;
    const displayPrice = hasPromotion ? product.promotion_price : product.price;
    const originalPrice = hasPromotion ? product.price : null;
    const savingsAmount = hasPromotion ? Math.round(product.price - product.promotion_price) : 0;

    return (
        <div className="group relative bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 overflow-hidden flex flex-col">
            <Link href={`/client/product/${product.id}`} className="block">
                <div className="relative aspect-square bg-gray-100 overflow-hidden">
                    {product.photo1_url ? (
                        <>
                            <img
                                src={product.photo1_url}
                                alt={product.name}
                                className={`w-full h-full object-cover transition-transform duration-500 group-hover:scale-110 ${
                                    imageLoaded ? 'opacity-100' : 'opacity-0'
                                }`}
                                onLoad={() => setImageLoaded(true)}
                            />
                            {!imageLoaded && (
                                <div className="absolute inset-0 flex items-center justify-center">
                                    <div className="w-8 h-8 border-2 border-gray-200 border-t-blue-500 rounded-full animate-spin"></div>
                                </div>
                            )}
                        </>
                    ) : (
                        <div className="absolute inset-0 flex items-center justify-center">
                            <div className="text-center">
                                <i className="bi bi-image text-4xl text-gray-300"></i>
                                <p className="mt-2 text-sm text-gray-400">No Image</p>
                            </div>
                        </div>
                    )}

                    {isOutOfStock && (
                        <div className="absolute inset-0 bg-black/50 flex items-center justify-center">
                            <span className="px-4 py-2 bg-red-500 text-white text-sm font-semibold rounded-full">
                                Out of Stock
                            </span>
                        </div>
                    )}

                    {isLowStock && !isOutOfStock && (
                        <div className="absolute top-3 left-3 px-2.5 py-1 bg-orange-500 text-white text-xs font-medium rounded-full shadow-sm">
                            Only {product.stock} left
                        </div>
                    )}

                    {hasPromotion && (
                        <div className="absolute top-14 right-3 px-2.5 py-1 bg-red-500 text-white text-xs font-bold rounded-full shadow-sm">
                            {product.promotion_badge}
                        </div>
                    )}

                    {!hasPromotion && product.discount_percentage > 0 && (
                        <div className="absolute top-14 right-3 px-2.5 py-1 bg-red-500 text-white text-xs font-bold rounded-full shadow-sm">
                            -{product.discount_percentage}%
                        </div>
                    )}

                    <button
                        onClick={handleWishlistToggle}
                        className={`absolute top-3 right-3 z-10 w-9 h-9 rounded-full flex items-center justify-center transition-all duration-200 ${
                            optimisticWishlisted
                                ? 'bg-red-50 shadow-md'
                                : 'bg-white/80 backdrop-blur-sm shadow-md hover:bg-white hover:shadow-lg'
                        } ${wishlistAnim ? 'scale-110' : 'scale-100'}`}
                        aria-label={optimisticWishlisted ? 'Remove from wishlist' : 'Add to wishlist'}
                    >
                        <Heart
                            className={`w-[18px] h-[18px] transition-all duration-300 ${
                                optimisticWishlisted
                                    ? 'fill-red-500 text-red-500 scale-110'
                                    : 'fill-none text-gray-600 hover:text-red-400'
                            }`}
                        />
                    </button>
                </div>
            </Link>

            <div className="p-4 flex-1 flex flex-col">
                <Link href={`/client/product/${product.id}`} className="flex-1">
                    {product.category?.name && (
                        <p className="text-xs font-medium text-gray-500 mb-1.5 uppercase tracking-wide">
                            {product.category.name}
                        </p>
                    )}
                    <h3 className="text-sm font-semibold text-gray-900 line-clamp-2 group-hover:text-blue-600 transition-colors min-h-[2.5rem]">
                        {product.name}
                    </h3>
                </Link>

                <div className="mt-3">
                    <div className="flex items-baseline gap-2 flex-wrap">
                        <span className="text-xl font-bold text-gray-900">
                            {Number(displayPrice).toLocaleString()}
                        </span>
                        <span className="text-xs text-gray-400 font-medium">MMK</span>
                        {originalPrice && (
                            <span className="text-sm text-gray-400 line-through w-full sm:w-auto">
                                {Number(originalPrice).toLocaleString()} MMK
                            </span>
                        )}
                    </div>
                    {savingsAmount > 0 && (
                        <p className="text-xs text-green-600 font-semibold mt-1 flex items-center gap-1">
                            <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Save {Number(savingsAmount).toLocaleString()} MMK
                        </p>
                    )}
                </div>

                <div className="mt-4 space-y-2">
                    {isOutOfStock ? (
                        <button
                            disabled
                            className="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-gray-100 text-gray-400 rounded-xl text-sm font-medium cursor-not-allowed"
                        >
                            <i className="bi bi-x-circle"></i>
                            Out of Stock
                        </button>
                    ) : (
                        <button
                            onClick={handleAddToCart}
                            disabled={addingId === product.id || isAdding}
                            className="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-semibold hover:bg-blue-700 active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200 shadow-md hover:shadow-lg"
                        >
                            {addingId === product.id || isAdding ? (
                                <>
                                    <svg className="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                    Adding...
                                </>
                            ) : (
                                <>
                                    <i className="bi bi-cart-plus"></i>
                                    Add to Cart
                                </>
                            )}
                        </button>
                    )}
                    <Link
                        href={`/client/product/${product.id}`}
                        className="w-full flex items-center justify-center gap-2 px-4 py-2.5 border-2 border-gray-200 text-gray-700 rounded-xl text-sm font-semibold hover:border-gray-300 hover:bg-gray-50 transition-all duration-200"
                    >
                        <i className="bi bi-eye"></i>
                        View Details
                    </Link>
                </div>
            </div>
        </div>
    );
});

export default ProductCard;
