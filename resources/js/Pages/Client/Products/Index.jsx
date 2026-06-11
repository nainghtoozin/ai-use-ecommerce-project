import { useState, useCallback, useRef, useEffect } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { InfiniteScroll } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';
import ProductCard from '@/Components/ProductCard';
import BackToTopButton from '@/Components/BackToTopButton';
import HeroSection from '@/Components/Storefront/HeroSection';
import FeaturedCategories from '@/Components/Storefront/FeaturedCategories';
import FeaturedProducts from '@/Components/Storefront/FeaturedProducts';
import PromotionBanner from '@/Components/Storefront/PromotionBanner';
import StoreFeatures from '@/Components/Storefront/StoreFeatures';
import { useCart } from '@/Hooks/useCart';

export default function ClientProductIndex({ products, categories, searchQuery, filters: initFilters = {}, featuredCategories, latestProducts, featuredProducts, bestsellerProducts, promotionBanners, hasProducts }) {
    const { props } = usePage();
    const { website_info } = props;
    const { addToCart, addingId } = useCart();
    const [query, setQuery] = useState(searchQuery || '');
    const [selectedCategory, setSelectedCategory] = useState(initFilters.category_id || '');
    const [sortBy, setSortBy] = useState(initFilters.sort || 'latest');
    const [loading, setLoading] = useState(false);
    const debounceRef = useRef(null);

    useEffect(() => {
        setQuery(searchQuery || '');
        setSelectedCategory(initFilters.category_id || '');
        setSortBy(initFilters.sort || 'latest');
    }, [searchQuery, initFilters]);

    useEffect(() => {
        return () => { if (debounceRef.current) clearTimeout(debounceRef.current); };
    }, []);

    const applyFilters = useCallback((q, cat, sort) => {
        setLoading(true);
        const params = {};
        if (q) params.query = q;
        if (cat) params.category = cat;
        if (sort && sort !== 'latest') params.sort = sort;
        router.get('/', params, {
            preserveState: true, preserveScroll: true,
            only: ['products', 'searchQuery', 'filters'],
            reset: ['products'], replace: true,
            onFinish: () => setLoading(false),
        });
    }, []);

    const handleQueryChange = useCallback((value) => {
        setQuery(value);
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => applyFilters(value, selectedCategory, sortBy), 400);
    }, [selectedCategory, sortBy, applyFilters]);

    const handleCategoryChange = useCallback((categoryId) => {
        setSelectedCategory(categoryId);
        applyFilters(query, categoryId, sortBy);
    }, [query, sortBy, applyFilters]);

    const handleSortChange = useCallback((sort) => {
        setSortBy(sort);
        applyFilters(query, selectedCategory, sort);
    }, [query, selectedCategory, applyFilters]);

    const handleAddToCart = useCallback(async (productId) => {
        await addToCart(productId, 1);
    }, [addToCart]);

    const clearFilters = useCallback(() => {
        setQuery(''); setSelectedCategory(''); setSortBy('latest'); setLoading(true);
        router.get('/', {}, {
            preserveState: true, preserveScroll: true,
            only: ['products', 'searchQuery', 'filters'],
            reset: ['products'], replace: true,
        });
    }, []);

    const hasMore = (products?.current_page ?? 1) < (products?.last_page ?? 1);
    const productCount = products?.data?.length ?? 0;
    const hasActiveFilters = query || selectedCategory || sortBy !== 'latest';

    return (
        <ShopLayout>
            <Head title="Shop" />

            <HeroSection websiteInfo={website_info} />

            {!hasActiveFilters && (
                <>
                    <PromotionBanner banners={promotionBanners} />
                    <FeaturedCategories categories={featuredCategories} />
                    <FeaturedProducts
                        products={latestProducts}
                        title="Latest Products"
                        subtitle="Newly added items"
                        onAddToCart={handleAddToCart}
                        addingId={addingId}
                    />
                    <FeaturedProducts
                        products={featuredProducts}
                        title="Featured Products"
                        subtitle="Top picks for you"
                        onAddToCart={handleAddToCart}
                        addingId={addingId}
                    />
                    <FeaturedProducts
                        products={bestsellerProducts}
                        title="Best Sellers"
                        subtitle="Most popular items"
                        onAddToCart={handleAddToCart}
                        addingId={addingId}
                    />
                    <StoreFeatures />
                </>
            )}

            <section className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="mb-6 sm:mb-8">
                    <div className="flex flex-col sm:flex-row gap-3 sm:gap-4 mb-4 sm:mb-6">
                        <div className="flex-1">
                            <input type="text" value={query}
                                onChange={(e) => handleQueryChange(e.target.value)}
                                placeholder="Search products..."
                                className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
                        </div>
                        <div className="flex gap-2">
                            <select value={selectedCategory} onChange={(e) => handleCategoryChange(e.target.value)}
                                className="border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <option value="">All Categories</option>
                                {categories?.map((cat) => (
                                    <option key={cat.id} value={cat.id}>{cat.name}</option>
                                ))}
                            </select>
                            <select value={sortBy} onChange={(e) => handleSortChange(e.target.value)}
                                className="border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <option value="latest">Latest</option>
                                <option value="price_asc">Price: Low to High</option>
                                <option value="price_desc">Price: High to Low</option>
                                <option value="name">Name A-Z</option>
                            </select>
                        </div>
                    </div>

                    {hasActiveFilters && (
                        <div className="flex items-center gap-2 mb-4 flex-wrap">
                            <span className="text-sm text-gray-500">Active filters:</span>
                            {query && (
                                <span className="inline-flex items-center gap-1 px-3 py-1 bg-indigo-100 text-indigo-700 rounded-full text-sm">
                                    Search: {query}
                                    <button onClick={() => handleQueryChange('')} className="hover:text-indigo-900">&times;</button>
                                </span>
                            )}
                            {selectedCategory && (
                                <span className="inline-flex items-center gap-1 px-3 py-1 bg-indigo-100 text-indigo-700 rounded-full text-sm">
                                    {categories?.find(c => c.id == selectedCategory)?.name}
                                    <button onClick={() => handleCategoryChange('')} className="hover:text-indigo-900">&times;</button>
                                </span>
                            )}
                            <button onClick={clearFilters} className="text-sm text-red-600 hover:text-red-800 ml-2">Clear all</button>
                        </div>
                    )}
                </div>

                {loading ? (
                    <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6">
                        {Array(8).fill(null).map((_, i) => (
                            <div key={i} className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden animate-pulse">
                                <div className="aspect-square bg-gray-200"></div>
                                <div className="p-4">
                                    <div className="h-4 bg-gray-200 rounded w-3/4 mb-2"></div>
                                    <div className="h-3 bg-gray-200 rounded w-1/2 mb-2"></div>
                                    <div className="h-5 bg-gray-200 rounded w-1/4"></div>
                                </div>
                            </div>
                        ))}
                    </div>
                ) : productCount === 0 && hasActiveFilters ? (
                    <div className="text-center py-12 sm:py-16 bg-white rounded-xl border border-gray-200">
                        <svg className="w-12 h-12 text-gray-300 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <h3 className="mt-4 text-lg font-medium text-gray-900">No products found</h3>
                        <p className="mt-2 text-gray-500">Try adjusting your search or filter criteria.</p>
                        <button onClick={clearFilters} className="mt-4 sm:mt-6 px-4 sm:px-6 py-2 sm:py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">View all products</button>
                    </div>
                ) : productCount > 0 ? (
                    <>
                        <div className="mb-4 sm:mb-6">
                            <p className="text-sm sm:text-base text-gray-500">{productCount} Products Available</p>
                        </div>
                        <InfiniteScroll data="products" onlyNext preserveUrl
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
                            )}>
                            <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6">
                                {products.data.map((product) => (
                                    <ProductCard key={product.id} product={product} onAddToCart={handleAddToCart} addingId={addingId} />
                                ))}
                            </div>
                        </InfiniteScroll>
                        {!hasMore && productCount > 0 && (
                            <div className="mt-8 flex justify-center items-center py-4">
                                <p className="text-gray-400 text-sm">You've reached the end</p>
                            </div>
                        )}
                    </>
                ) : !hasActiveFilters && !hasProducts ? null : null}
            </section>

            <BackToTopButton />
        </ShopLayout>
    );
}
