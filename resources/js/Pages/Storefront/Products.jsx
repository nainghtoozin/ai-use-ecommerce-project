import { useState, useCallback, useRef, useEffect } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';
import FilterBar from '@/Components/FilterBar';
import Sidebar from '@/Components/Sidebar';
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
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => {
            applyFilters(value, selectedCategory, selectedBrand, selectedType, minPrice, maxPrice, sortBy, inStock);
        }, 400);
    }, [selectedCategory, selectedBrand, selectedType, minPrice, maxPrice, sortBy, inStock, applyFilters]);

    const handleCategoryChange = useCallback((categoryId) => {
        setSelectedCategory(categoryId);
        applyFilters(query, categoryId, selectedBrand, selectedType, minPrice, maxPrice, sortBy, inStock);
    }, [query, selectedBrand, selectedType, minPrice, maxPrice, sortBy, inStock, applyFilters]);

    const handleBrandChange = useCallback((brandId) => {
        setSelectedBrand(brandId);
        applyFilters(query, selectedCategory, brandId, selectedType, minPrice, maxPrice, sortBy, inStock);
    }, [query, selectedCategory, selectedType, minPrice, maxPrice, sortBy, inStock, applyFilters]);

    const handleTypeChange = useCallback((type) => {
        setSelectedType(type);
        applyFilters(query, selectedCategory, selectedBrand, type, minPrice, maxPrice, sortBy, inStock);
    }, [query, selectedCategory, selectedBrand, minPrice, maxPrice, sortBy, inStock, applyFilters]);

    const handleMinPriceChange = useCallback((value) => {
        setMinPrice(value);
    }, []);

    const handleMaxPriceChange = useCallback((value) => {
        setMaxPrice(value);
    }, []);

    const handlePriceApply = useCallback(() => {
        applyFilters(query, selectedCategory, selectedBrand, selectedType, minPrice, maxPrice, sortBy, inStock);
    }, [query, selectedCategory, selectedBrand, selectedType, minPrice, maxPrice, sortBy, inStock, applyFilters]);

    const handleSortChange = useCallback((sort) => {
        setSortBy(sort);
        applyFilters(query, selectedCategory, selectedBrand, selectedType, minPrice, maxPrice, sort, inStock);
    }, [query, selectedCategory, selectedBrand, selectedType, minPrice, maxPrice, inStock, applyFilters]);

    const handleInStockChange = useCallback((checked) => {
        setInStock(checked);
        applyFilters(query, selectedCategory, selectedBrand, selectedType, minPrice, maxPrice, sortBy, checked);
    }, [query, selectedCategory, selectedBrand, selectedType, minPrice, maxPrice, sortBy, applyFilters]);

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

    const handleAddToCart = useCallback(async (productId) => {
        await addToCart(productId, 1);
    }, [addToCart]);

    const handleSelectVariant = useCallback((product) => {
        setVariableProduct(product);
    }, []);

    const handleModalAddToCart = useCallback(async (variantId, quantity) => {
        if (!variableProduct) return;
        await addToCart(variableProduct.id, quantity, variantId);
        setVariableProduct(null);
    }, [variableProduct, addToCart]);

    const hasMore = (products?.current_page ?? 1) < (products?.last_page ?? 1);
    const hasActiveFilters = query || selectedCategory || selectedBrand || selectedType || minPrice || maxPrice || sortBy !== 'recommended' || inStock;

    return (
        <ShopLayout>
            <Head title={`Products - ${storefront?.identity?.site_title || tenant.name}`} />

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
