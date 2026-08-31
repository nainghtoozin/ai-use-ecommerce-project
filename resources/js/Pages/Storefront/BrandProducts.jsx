import { useState, useCallback, useRef, useEffect } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';
import FilterBar from '@/Components/FilterBar';
import Sidebar from '@/Components/Sidebar';
import ProductGrid from '@/Components/ProductGrid';
import BackToTopButton from '@/Components/BackToTopButton';
import { useCart } from '@/Hooks/useCart';
import { assetUrl } from '@/Utils/helpers';

export default function StoreBrandProducts({ tenant, brand, products, categories, brands, searchQuery, filters: initFilters = {} }) {
    const { storefront } = usePage().props;
    const { addToCart, addingId } = useCart();
    const [query, setQuery] = useState(searchQuery || '');
    const [selectedCategory, setSelectedCategory] = useState(initFilters.category_id || '');
    const [sortBy, setSortBy] = useState(initFilters.sort || 'latest');
    const [inStock, setInStock] = useState(initFilters.in_stock || false);
    const [loading, setLoading] = useState(false);
    const [sidebarOpen, setSidebarOpen] = useState(false);

    const debounceRef = useRef(null);

    useEffect(() => {
        setQuery(searchQuery || '');
        setSelectedCategory(initFilters.category_id || '');
        setSortBy(initFilters.sort || 'latest');
        setInStock(initFilters.in_stock || false);
    }, [searchQuery, initFilters]);

    useEffect(() => {
        return () => {
            if (debounceRef.current) clearTimeout(debounceRef.current);
        };
    }, []);

    const applyFilters = useCallback((q, cat, sort, stock) => {
        setLoading(true);
        const params = { brand: brand.id };
        if (q) params.query = q;
        if (cat) params.category = cat;
        if (sort && sort !== 'latest') params.sort = sort;
        if (stock) params.in_stock = 1;

        router.get(`/store/${tenant.slug}/brands/${brand.id}`, params, {
            preserveState: true,
            preserveScroll: true,
            only: ['products', 'searchQuery', 'filters'],
            reset: ['products'],
            replace: true,
            onFinish: () => setLoading(false),
        });
    }, [tenant.slug, brand.id]);

    const handleQueryChange = useCallback((value) => {
        setQuery(value);
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => {
            applyFilters(value, selectedCategory, sortBy, inStock);
        }, 400);
    }, [selectedCategory, sortBy, inStock, applyFilters]);

    const handleCategoryChange = useCallback((categoryId) => {
        setSelectedCategory(categoryId);
        applyFilters(query, categoryId, sortBy, inStock);
    }, [query, sortBy, inStock, applyFilters]);

    const handleSortChange = useCallback((sort) => {
        setSortBy(sort);
        applyFilters(query, selectedCategory, sort, inStock);
    }, [query, selectedCategory, inStock, applyFilters]);

    const handleInStockChange = useCallback((checked) => {
        setInStock(checked);
        applyFilters(query, selectedCategory, sortBy, checked);
    }, [query, selectedCategory, sortBy, applyFilters]);

    const clearFilters = useCallback(() => {
        setQuery('');
        setSelectedCategory('');
        setSortBy('latest');
        setInStock(false);
        router.get(`/store/${tenant.slug}/brands/${brand.id}`, {}, {
            preserveState: true,
            preserveScroll: true,
            only: ['products', 'searchQuery', 'filters'],
            reset: ['products'],
            replace: true,
        });
    }, [tenant.slug, brand.id]);

    const handleAddToCart = useCallback(async (productId) => {
        await addToCart(productId, 1);
    }, [addToCart]);

    const hasMore = (products?.current_page ?? 1) < (products?.last_page ?? 1);
    const hasActiveFilters = query || selectedCategory || sortBy !== 'latest' || inStock;

    return (
        <ShopLayout>
            <Head title={`${brand.name} Products - ${storefront?.identity?.site_title || tenant.name}`} />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <nav className="flex flex-wrap items-center text-xs text-gray-400 dark:text-gray-500 mb-3 gap-1">
                    <a href={`/store/${tenant.slug}`} className="hover:text-indigo-600 transition-colors">{tenant.name}</a>
                    <svg className="w-3 h-3 mx-0.5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg>
                    <a href={`/store/${tenant.slug}/brands`} className="hover:text-indigo-600 transition-colors">Brands</a>
                    <svg className="w-3 h-3 mx-0.5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg>
                    <span className="text-gray-600 dark:text-gray-400 truncate">{brand.name}</span>
                </nav>

                <div className="flex flex-col sm:flex-row sm:items-center gap-4 mb-6">
                    <div className="flex items-center gap-4">
                        {brand.logo_url && (
                            <img
                                src={assetUrl(brand.logo_url)}
                                alt={brand.name}
                                className="w-16 h-16 object-contain rounded-lg border border-gray-200 dark:border-gray-700"
                            />
                        )}
                        <div>
                            <h1 className="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-gray-100">{brand.name}</h1>
                            {brand.description && (
                                <p className="mt-1 text-gray-500 dark:text-gray-400 text-sm max-w-2xl">{brand.description}</p>
                            )}
                        </div>
                    </div>
                </div>

                <FilterBar
                    query={query}
                    onQueryChange={handleQueryChange}
                    categories={categories || []}
                    selectedCategory={selectedCategory}
                    onCategoryChange={handleCategoryChange}
                    sortBy={sortBy}
                    onSortChange={handleSortChange}
                    inStock={inStock}
                    onInStockChange={handleInStockChange}
                    onClearFilters={clearFilters}
                    totalProducts={products?.total ?? 0}
                    hasActiveFilters={hasActiveFilters}
                    onToggleSidebar={() => setSidebarOpen(prev => !prev)}
                />

                <div className="flex gap-8 mt-6">
                    <Sidebar
                        categories={categories || []}
                        selectedCategory={selectedCategory}
                        onCategoryChange={handleCategoryChange}
                        isOpen={sidebarOpen}
                        onClose={() => setSidebarOpen(false)}
                    />

                    <div className="flex-1 min-w-0">
                        <ProductGrid
                            products={products}
                            hasMore={hasMore}
                            loading={loading}
                            onAddToCart={handleAddToCart}
                            addingId={addingId}
                            onClearFilters={clearFilters}
                        />
                    </div>
                </div>
            </div>

            <BackToTopButton />
        </ShopLayout>
    );
}
