import { useState, useCallback, useRef, useEffect } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';
import ProductGrid from '@/Components/ProductGrid';
import BackToTopButton from '@/Components/BackToTopButton';
import HeroSection from '@/Components/Storefront/HeroSection';
import EmptyStoreState from '@/Components/Storefront/EmptyStoreState';
import { useCart } from '@/Hooks/useCart';

export default function StoreIndex({ tenant, products, categories, searchQuery, filters: initFilters = {}, hasProducts }) {
    const { website_info } = usePage().props;
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
        router.get(`/store/${tenant.slug}`, params, {
            preserveState: true, preserveScroll: true,
            only: ['products', 'searchQuery', 'filters'],
            reset: ['products'], replace: true,
            onFinish: () => setLoading(false),
        });
    }, [tenant.slug]);

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

    const clearFilters = useCallback(() => {
        setQuery(''); setSelectedCategory(''); setSortBy('latest'); setLoading(true);
        router.get(`/store/${tenant.slug}`, {}, {
            preserveState: true, preserveScroll: true,
            only: ['products', 'searchQuery', 'filters'],
            reset: ['products'], replace: true,
            onFinish: () => setLoading(false),
        });
    }, [tenant.slug]);

    const handleAddToCart = useCallback(async (productId) => {
        await addToCart(productId, 1);
    }, [addToCart]);

    const hasMore = (products?.current_page ?? 1) < (products?.last_page ?? 1);
    const hasActiveFilters = query || selectedCategory || sortBy !== 'latest';

    return (
        <ShopLayout>
            <Head title={tenant.name} />

            <HeroSection store={tenant} websiteInfo={website_info} />

            {!hasProducts && !hasActiveFilters ? (
                <EmptyStoreState storeName={tenant.name} />
            ) : (
                <section id="products-section" className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 sm:py-4">
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4">
                        <div className="flex items-center gap-4 flex-1">
                            <div className="relative flex-1">
                                <input type="text" placeholder="Search products..." value={query}
                                    onChange={(e) => handleQueryChange(e.target.value)}
                                    className="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 pl-10 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                                <svg className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <select value={selectedCategory} onChange={(e) => handleCategoryChange(e.target.value)}
                                className="rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                <option value="">All Categories</option>
                                {categories.map((cat) => (
                                    <option key={cat.id} value={cat.id}>{cat.name}</option>
                                ))}
                            </select>
                            <select value={sortBy} onChange={(e) => handleSortChange(e.target.value)}
                                className="rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                <option value="latest">Latest</option>
                                <option value="price_asc">Price: Low to High</option>
                                <option value="price_desc">Price: High to Low</option>
                                <option value="name">Name: A-Z</option>
                            </select>
                        </div>
                        {hasActiveFilters && (
                            <button onClick={clearFilters} className="text-sm text-indigo-600 hover:text-indigo-800 font-medium">Clear all filters</button>
                        )}
                    </div>

                    <ProductGrid
                        products={products}
                        hasMore={hasMore}
                        loading={loading}
                        onAddToCart={handleAddToCart}
                        addingId={addingId}
                        onClearFilters={clearFilters}
                    />
                </section>
            )}

            <BackToTopButton />
        </ShopLayout>
    );
}
