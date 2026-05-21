import { useCallback } from 'react';
import { InfiniteScroll } from '@inertiajs/react';
import ProductCard from '@/Components/ProductCard';

const SkeletonCard = () => (
    <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden animate-pulse">
        <div className="aspect-square bg-gray-200" />
        <div className="p-4">
            <div className="h-3 bg-gray-200 rounded w-1/3 mb-2" />
            <div className="h-4 bg-gray-200 rounded w-3/4 mb-2" />
            <div className="h-3 bg-gray-200 rounded w-1/2 mb-3" />
            <div className="h-5 bg-gray-200 rounded w-1/4 mb-3" />
            <div className="h-10 bg-gray-200 rounded-xl" />
        </div>
    </div>
);

const SkeletonGrid = () => (
    <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-6">
        {Array.from({ length: 12 }).map((_, i) => (
            <SkeletonCard key={i} />
        ))}
    </div>
);

const EmptyState = ({ onClearFilters }) => (
    <div className="text-center py-12 sm:py-16 bg-white rounded-xl border border-gray-200">
        <svg className="mx-auto w-16 h-16 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
        </svg>
        <h3 className="mt-4 text-lg font-medium text-gray-900">No products found</h3>
        <p className="mt-2 text-gray-500 max-w-md mx-auto">
            Try adjusting your search or filter criteria to find what you are looking for.
        </p>
        {onClearFilters && (
            <button
                onClick={onClearFilters}
                className="mt-4 sm:mt-6 px-4 sm:px-6 py-2 sm:py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium"
            >
                View all products
            </button>
        )}
    </div>
);

export default function ProductGrid({
    products,
    hasMore,
    loading,
    onAddToCart,
    addingId,
    onClearFilters,
}) {
    const handleAddToCart = useCallback(async (productId) => {
        if (onAddToCart) {
            await onAddToCart(productId);
        }
    }, [onAddToCart]);

    if (loading && !products?.data?.length) {
        return <SkeletonGrid />;
    }

    if (!products?.data?.length) {
        return <EmptyState onClearFilters={onClearFilters} />;
    }

    return (
        <>
            <div className="mb-4 sm:mb-6">
                <p className="text-sm sm:text-base text-gray-500">
                    Showing {products.data.length} of {products.total} Products
                </p>
            </div>

            <InfiniteScroll
                data="products"
                onlyNext
                preserveUrl
                loading={() => (
                    <div className="mt-8 flex justify-center items-center py-4">
                        <div className="flex items-center gap-2 text-gray-500">
                            <svg className="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                            <span>Loading more products...</span>
                        </div>
                    </div>
                )}
            >
                <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-6">
                    {products.data.map((product) => (
                        <ProductCard
                            key={product.id}
                            product={product}
                            onAddToCart={handleAddToCart}
                            addingId={addingId}
                        />
                    ))}
                </div>
            </InfiniteScroll>

            {!hasMore && (
                <div className="mt-8 flex justify-center items-center py-4">
                    <p className="text-gray-400 text-sm">You have reached the end</p>
                </div>
            )}
        </>
    );
}
