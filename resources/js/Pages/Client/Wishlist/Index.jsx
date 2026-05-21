import { useState, useCallback } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Heart, ShoppingCart, Trash2 } from 'lucide-react';
import ShopLayout from '@/Layouts/ShopLayout';
import BackToTopButton from '@/Components/BackToTopButton';
import { useCart } from '@/Hooks/useCart';
import { useWishlist } from '@/Hooks/useWishlist';

function WishlistItemCard({ item, onRemove, onAddToCart, addingId, processingId }) {
    const [added, setAdded] = useState(false);
    const product = item.product;

    if (!product) return null;

    const isOutOfStock = product.stock === 0;
    const hasPromotion = product.promotion_price && product.promotion_price < product.price;
    const displayPrice = hasPromotion ? product.promotion_price : product.price;
    const originalPrice = hasPromotion ? product.price : null;

    const handleAddToCart = async () => {
        setAdded(true);
        await onAddToCart(product.id);
        setTimeout(() => setAdded(false), 2000);
    };

    return (
        <div className="group relative bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-lg transition-all duration-300 overflow-hidden flex flex-col">
            <Link href={`/client/product/${product.id}`} className="block">
                <div className="relative aspect-square bg-gray-100 overflow-hidden">
                    {product.photo1_url ? (
                        <img
                            src={product.photo1_url}
                            alt={product.name}
                            className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110"
                        />
                    ) : (
                        <div className="absolute inset-0 flex items-center justify-center">
                            <i className="bi bi-image text-4xl text-gray-300"></i>
                        </div>
                    )}

                    {isOutOfStock && (
                        <div className="absolute inset-0 bg-black/50 flex items-center justify-center">
                            <span className="px-4 py-2 bg-red-500 text-white text-sm font-semibold rounded-full">
                                Out of Stock
                            </span>
                        </div>
                    )}

                    {hasPromotion && (
                        <div className="absolute top-3 left-3 px-2.5 py-1 bg-red-500 text-white text-xs font-bold rounded-full shadow-sm">
                            {product.promotion_badge}
                        </div>
                    )}
                </div>
            </Link>

            <div className="p-4 flex-1 flex flex-col">
                <Link href={`/client/product/${product.id}`} className="flex-1">
                    {product.category?.name && (
                        <p className="text-xs font-medium text-gray-500 mb-1 uppercase tracking-wide">
                            {product.category.name}
                        </p>
                    )}
                    <h3 className="text-sm font-semibold text-gray-900 line-clamp-2 group-hover:text-blue-600 transition-colors min-h-[2.5rem]">
                        {product.name}
                    </h3>
                </Link>

                <div className="mt-2 flex items-baseline gap-2 flex-wrap">
                    <span className="text-lg font-bold text-gray-900">
                        {Number(displayPrice).toLocaleString()}
                    </span>
                    <span className="text-xs text-gray-400 font-medium">MMK</span>
                    {originalPrice && (
                        <span className="text-sm text-gray-400 line-through w-full sm:w-auto">
                            {Number(originalPrice).toLocaleString()} MMK
                        </span>
                    )}
                </div>

                <div className="mt-4 space-y-2">
                    {isOutOfStock ? (
                        <button
                            disabled
                            className="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-gray-100 text-gray-400 rounded-xl text-sm font-medium cursor-not-allowed"
                        >
                            <ShoppingCart className="w-4 h-4" />
                            Out of Stock
                        </button>
                    ) : (
                        <button
                            onClick={handleAddToCart}
                            disabled={addingId === product.id}
                            className={`w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold transition-all duration-200 ${
                                added
                                    ? 'bg-green-600 text-white'
                                    : 'bg-blue-600 text-white hover:bg-blue-700 active:scale-[0.98] shadow-md hover:shadow-lg'
                            }`}
                        >
                            {addingId === product.id ? (
                                <>
                                    <svg className="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                    </svg>
                                    Adding...
                                </>
                            ) : added ? (
                                <>
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                    </svg>
                                    Added to Cart
                                </>
                            ) : (
                                <>
                                    <ShoppingCart className="w-4 h-4" />
                                    Add to Cart
                                </>
                            )}
                        </button>
                    )}

                    <button
                        onClick={() => onRemove(item.product_id)}
                        disabled={processingId === product.id}
                        className="w-full flex items-center justify-center gap-2 px-4 py-2.5 border border-gray-200 text-gray-500 rounded-xl text-sm font-medium hover:bg-red-50 hover:text-red-600 hover:border-red-200 transition-all duration-200 disabled:opacity-50"
                    >
                        {processingId === product.id ? (
                            <svg className="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                        ) : (
                            <Trash2 className="w-4 h-4" />
                        )}
                        Remove
                    </button>
                </div>
            </div>
        </div>
    );
}

function SkeletonGrid() {
    return (
        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-6">
            {Array.from({ length: 4 }).map((_, i) => (
                <div key={i} className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden animate-pulse">
                    <div className="aspect-square bg-gray-200" />
                    <div className="p-4">
                        <div className="h-3 bg-gray-200 rounded w-1/3 mb-2" />
                        <div className="h-4 bg-gray-200 rounded w-3/4 mb-2" />
                        <div className="h-4 bg-gray-200 rounded w-1/2 mb-3" />
                        <div className="h-10 bg-gray-200 rounded-xl mb-2" />
                        <div className="h-10 bg-gray-200 rounded-xl" />
                    </div>
                </div>
            ))}
        </div>
    );
}

function EmptyState() {
    return (
        <div className="text-center py-16">
            <div className="mx-auto w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-6">
                <Heart className="w-10 h-10 text-gray-300" />
            </div>
            <h2 className="text-xl font-semibold text-gray-900 mb-2">Your wishlist is empty</h2>
            <p className="text-gray-500 mb-8 max-w-md mx-auto">
                Save your favorite items here and come back to them later.
            </p>
            <Link
                href="/products"
                className="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 text-white rounded-xl font-semibold hover:bg-blue-700 transition-colors shadow-md"
            >
                <ShoppingCart className="w-4 h-4" />
                Browse Products
            </Link>
        </div>
    );
}

export default function WishlistIndex({ wishlistItems = [] }) {
    const { addToCart, addingId } = useCart();
    const { removeFromWishlist, moveAllToCart, clearWishlist, processingId } = useWishlist();
    const [movingAll, setMovingAll] = useState(false);
    const [allMoved, setAllMoved] = useState(false);
    const [clearing, setClearing] = useState(false);

    const handleAddToCart = useCallback(async (productId) => {
        await addToCart(productId, 1);
    }, [addToCart]);

    const handleRemove = useCallback(async (productId) => {
        await removeFromWishlist(productId);
    }, [removeFromWishlist]);

    const handleMoveAllToCart = async () => {
        setMovingAll(true);
        const result = await moveAllToCart();
        if (result.success) {
            setAllMoved(true);
            setTimeout(() => setAllMoved(false), 3000);
        }
        setMovingAll(false);
    };

    const handleClear = async () => {
        setClearing(true);
        await clearWishlist();
        setClearing(false);
    };

    return (
        <ShopLayout>
            <Head title="My Wishlist" />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 flex items-center gap-2">
                            <Heart className="w-6 h-6 text-red-500 fill-red-500" />
                            My Wishlist
                        </h1>
                        <p className="text-sm text-gray-500 mt-1">
                            {wishlistItems.length} {wishlistItems.length === 1 ? 'item' : 'items'}
                        </p>
                    </div>

                    {wishlistItems.length > 0 && (
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
                                className="flex items-center gap-2 px-4 py-2.5 border border-gray-300 text-gray-600 rounded-xl text-sm font-semibold hover:bg-red-50 hover:text-red-600 hover:border-red-200 transition-all duration-200 disabled:opacity-50"
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

                {wishlistItems.length === 0 ? (
                    <EmptyState />
                ) : (
                    <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-6">
                        {wishlistItems.map((item) => (
                            <WishlistItemCard
                                key={item.id}
                                item={item}
                                onRemove={handleRemove}
                                onAddToCart={handleAddToCart}
                                addingId={addingId}
                                processingId={processingId}
                            />
                        ))}
                    </div>
                )}
            </div>

            <BackToTopButton />
        </ShopLayout>
    );
}
