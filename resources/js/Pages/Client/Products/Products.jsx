import { useState, useCallback, useRef, useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';
import FilterBar from '@/Components/FilterBar';
import Sidebar from '@/Components/Sidebar';
import ProductGrid from '@/Components/ProductGrid';
import BackToTopButton from '@/Components/BackToTopButton';
import { useCart } from '@/Hooks/useCart';

export default function ClientProducts({ products, categories, searchQuery, filters: initFilters = {} }) {
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
        const params = {};
        if (q) params.query = q;
        if (cat) params.category = cat;
        if (sort && sort !== 'latest') params.sort = sort;
        if (stock) params.in_stock = 1;

        router.get('/products', params, {
            preserveState: true,
            preserveScroll: true,
            only: ['products', 'searchQuery', 'filters'],
            reset: ['products'],
            replace: true,
            onFinish: () => setLoading(false),
        });
    }, []);

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
        router.get('/products', {}, {
            preserveState: true,
            preserveScroll: true,
            only: ['products', 'searchQuery', 'filters'],
            reset: ['products'],
            replace: true,
        });
    }, []);

    const handleAddToCart = useCallback(async (productId) => {
        await addToCart(productId, 1);
    }, [addToCart]);

    const hasMore = (products?.current_page ?? 1) < (products?.last_page ?? 1);
    const hasActiveFilters = query || selectedCategory || sortBy !== 'latest' || inStock;

    return (
        <ShopLayout>
            <Head title="Products" />

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

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <div className="flex gap-8">
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
