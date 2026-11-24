import { useState, useCallback, useRef, useEffect } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';
import FilterBar from '@/Components/FilterBar';
import Sidebar from '@/Components/Sidebar';
import MobileProductFilterSheet from '@/Components/MobileProductFilterSheet';
import ProductGrid from '@/Components/ProductGrid';
import BackToTopButton from '@/Components/BackToTopButton';
import VariantSelectModal from '@/Components/VariantSelectModal';
import { useCart } from '@/Hooks/useCart';

export default function StoreProducts({ tenant, products, categories, brands, searchQuery, filters: initFilters = {} }) {
    const { storefront } = usePage().props;
    const { addToCart, addingId } = useCart();
    const [query, setQuery] = useState(searchQuery || '');
    const [selectedCategory, setSelectedCategory] = useState(initFilters.category_id || '');
    const [selectedBrand, setSelectedBrand] = useState(initFilters.brand_id || '');
    const [selectedType, setSelectedType] = useState(initFilters.type || '');
    const [minPrice, setMinPrice] = useState(initFilters.min_price || '');
    const [maxPrice, setMaxPrice] = useState(initFilters.max_price || '');
    const [sortBy, setSortBy] = useState(initFilters.sort || 'recommended');
    const [inStock, setInStock] = useState(initFilters.in_stock || false);
    const [loading, setLoading] = useState(false);
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [mobileFilterOpen, setMobileFilterOpen] = useState(false);
    const [variableProduct, setVariableProduct] = useState(null);

    const debounceRef = useRef(null);

    useEffect(() => {
        setQuery(searchQuery || '');
        setSelectedCategory(initFilters.category_id || '');
        setSelectedBrand(initFilters.brand_id || '');
        setSelectedType(initFilters.type || '');
        setMinPrice(initFilters.min_price || '');
        setMaxPrice(initFilters.max_price || '');
        setSortBy(initFilters.sort || 'recommended');
        setInStock(initFilters.in_stock || false);
    }, [searchQuery, initFilters]);

    useEffect(() => {
        return () => {
            if (debounceRef.current) clearTimeout(debounceRef.current);
        };
    }, []);

    const applyFilters = useCallback((q, cat, brand, type, min, max, sort, stock) => {
        setLoading(true);
        const params = {};
        if (q) params.query = q;
        if (cat) params.category = cat;
        if (brand) params.brand = brand;
        if (type) params.type = type;
        if (min) params.min_price = min;
        if (max) params.max_price = max;
        if (sort && sort !== 'recommended') params.sort = sort;
        if (stock) params.in_stock = 1;

        router.get(`/store/${tenant.slug}/products`, params, {
            preserveState: true,
            preserveScroll: true,
            only: ['products', 'searchQuery', 'filters'],
            reset: ['products'],
            replace: true,
            onFinish: () => setLoading(false),
        });
    }, [tenant.slug]);

    const handleQueryChange = useCallback((value) => {
        setQuery(value);
    }, []);

    const handlePriceApply = useCallback(() => {
        applyFilters(query, selectedCategory, selectedBrand, selectedType, minPrice, maxPrice, sortBy, inStock);
    }, [query, selectedCategory, selectedBrand, selectedType, minPrice, maxPrice, sortBy, inStock, applyFilters]);

    const handleCategoryChange = useCallback((categoryId) => {
        setSelectedCategory(categoryId);
    }, []);

    const handleBrandChange = useCallback((brandId) => {
        setSelectedBrand(brandId);
    }, []);

    const handleTypeChange = useCallback((type) => {
        setSelectedType(type);
    }, []);

    const handleMinPriceChange = useCallback((value) => {
        setMinPrice(value);
    }, []);

    const handleMaxPriceChange = useCallback((value) => {
        setMaxPrice(value);
    }, []);

    const handleSortChange = useCallback((sort) => {
        setSortBy(sort);
    }, []);

    const handleInStockChange = useCallback((checked) => {
        setInStock(checked);
    }, []);

    // Auto-apply filters (debounced)
    useEffect(() => {
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => {
            applyFilters(query, selectedCategory, selectedBrand, selectedType, minPrice, maxPrice, sortBy, inStock);
        }, 350);
        return () => {
            if (debounceRef.current) clearTimeout(debounceRef.current);
        };
    }, [query, selectedCategory, selectedBrand, selectedType, minPrice, maxPrice, sortBy, inStock, applyFilters]);

    const clearFilters = useCallback(() => {
        setQuery('');
        setSelectedCategory('');
        setSelectedBrand('');
        setSelectedType('');
        setMinPrice('');
        setMaxPrice('');
        setSortBy('recommended');
        setInStock(false);
        router.get(`/store/${tenant.slug}/products`, {}, {
            preserveState: true,
            preserveScroll: true,
            only: ['products', 'searchQuery', 'filters'],
            reset: ['products'],
            replace: true,
        });
    }, [tenant.slug]);

    const handleAddToCart = useCallback(async (productId, meta) => {
        await addToCart(productId, 1, undefined, meta || null);
    }, [addToCart]);

    const handleSelectVariant = useCallback((product) => {
        setVariableProduct(product);
    }, []);

    const handleModalAddToCart = useCallback(async (variantId, quantity, meta) => {
        if (!variableProduct) return { error: 'No product selected.' };
        return addToCart(variableProduct.id, quantity, variantId, { name: variableProduct.name, ...(meta || {}) });
    }, [variableProduct, addToCart]);

    const hasMore = (products?.current_page ?? 1) < (products?.last_page ?? 1);
    const hasActiveFilters = query || selectedCategory || selectedBrand || selectedType || minPrice || maxPrice || sortBy !== 'recommended' || inStock;

    return (
        <ShopLayout>
            <Head title={`Products - ${storefront?.identity?.site_title || tenant.name}`} />

            <div className="hidden md:block">
            <FilterBar
                query={query}
                onQueryChange={handleQueryChange}
                categories={categories || []}
                brands={brands || []}
                selectedCategory={selectedCategory}
                selectedBrand={selectedBrand}
                onCategoryChange={handleCategoryChange}
                onBrandChange={handleBrandChange}
                sortBy={sortBy}
                onSortChange={handleSortChange}
                inStock={inStock}
                onInStockChange={handleInStockChange}
                onClearFilters={clearFilters}
                totalProducts={products?.total ?? 0}
                hasActiveFilters={hasActiveFilters}
                onToggleSidebar={() => setSidebarOpen(prev => !prev)}
                sidebarOpen={sidebarOpen}
                selectedType={selectedType}
                onTypeChange={handleTypeChange}
                minPrice={minPrice}
                maxPrice={maxPrice}
                onMinPriceChange={handleMinPriceChange}
                onMaxPriceChange={handleMaxPriceChange}
                onPriceApply={handlePriceApply}
            />
            </div>

            <div className="md:hidden px-4 pt-2 space-y-2">
                <input
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    placeholder="Search products, brands, SKU..."
                    className="w-full border rounded-lg px-3 py-2 text-sm"
                />
                <button onClick={() => setMobileFilterOpen(true)} className="px-3 py-1.5 border rounded-lg text-sm">
                    Filter {hasActiveFilters ? `(${[query, selectedCategory, selectedBrand, selectedType, minPrice, maxPrice, inStock, sortBy !== 'recommended' ? sortBy : ''].filter(Boolean).length})` : ''}
                </button>
            </div>

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
                            onSelectVariant={handleSelectVariant}
                            addingId={addingId}
                            onClearFilters={clearFilters}
                        />
                    </div>
                </div>
            </div>

            <MobileProductFilterSheet
                open={mobileFilterOpen}
                onClose={() => setMobileFilterOpen(false)}
                categories={categories || []}
                brands={brands || []}
                selectedCategory={selectedCategory}
                setSelectedCategory={setSelectedCategory}
                selectedBrand={selectedBrand}
                setSelectedBrand={setSelectedBrand}
                selectedType={selectedType}
                setSelectedType={setSelectedType}
                minPrice={minPrice}
                setMinPrice={setMinPrice}
                maxPrice={maxPrice}
                setMaxPrice={setMaxPrice}
                sortBy={sortBy}
                setSortBy={setSortBy}
                inStock={inStock}
                setInStock={setInStock}
                onApply={() => applyFilters(query, selectedCategory, selectedBrand, selectedType, minPrice, maxPrice, sortBy, inStock)}
                onReset={clearFilters}
            />

            {variableProduct && (
                <VariantSelectModal
                    product={variableProduct}
                    onClose={() => setVariableProduct(null)}
                    onAddToCart={handleModalAddToCart}
                />
            )}

            <BackToTopButton />
        </ShopLayout>
    );
}


