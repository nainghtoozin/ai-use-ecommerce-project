import { useState, useRef, useEffect } from 'react';
import { usePage } from '@inertiajs/react';

export default function FilterBar({
    query,
    onQueryChange,
    categories,
    brands,
    selectedCategory,
    selectedBrand,
    onCategoryChange,
    onBrandChange,
    sortBy,
    onSortChange,
    inStock,
    onInStockChange,
    onClearFilters,
    totalProducts,
    hasActiveFilters,
    onToggleSidebar,
    sidebarOpen = false,
    selectedType,
    onTypeChange,
    minPrice,
    maxPrice,
    onMinPriceChange,
    onMaxPriceChange,
}) {
    const { storefront } = usePage().props;
    const labels = storefront?.content?.labels || {};
    const [localSearch, setLocalSearch] = useState(query || '');
    const [localMinPrice, setLocalMinPrice] = useState(minPrice || '');
    const [localMaxPrice, setLocalMaxPrice] = useState(maxPrice || '');
    const debounceRef = useRef(null);
    const priceDebounceRef = useRef(null);

    useEffect(() => {
        setLocalSearch(query || '');
    }, [query]);

    useEffect(() => {
        setLocalMinPrice(minPrice || '');
        setLocalMaxPrice(maxPrice || '');
    }, [minPrice, maxPrice]);

    const handleSearchInput = (value) => {
        setLocalSearch(value);
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => {
            onQueryChange(value);
        }, 400);
    };

    const handleMinPriceChange = (value) => {
        setLocalMinPrice(value);
        if (priceDebounceRef.current) clearTimeout(priceDebounceRef.current);
        priceDebounceRef.current = setTimeout(() => {
            onMinPriceChange(value);
        }, 500);
    };

    const handleMaxPriceChange = (value) => {
        setLocalMaxPrice(value);
        if (priceDebounceRef.current) clearTimeout(priceDebounceRef.current);
        priceDebounceRef.current = setTimeout(() => {
            onMaxPriceChange(value);
        }, 500);
    };

    const activeChips = [];
    if (selectedCategory) {
        const cat = categories?.find(c => c.id == selectedCategory);
        if (cat) activeChips.push({ key: 'category', label: cat.name, onRemove: () => onCategoryChange('') });
    }
    if (selectedBrand) {
        const brand = brands?.find(b => b.id == selectedBrand);
        if (brand) activeChips.push({ key: 'brand', label: brand.name, onRemove: () => onBrandChange('') });
    }
    if (selectedType) {
        activeChips.push({ key: 'type', label: selectedType.charAt(0).toUpperCase() + selectedType.slice(1), onRemove: () => onTypeChange('') });
    }
    if (inStock) {
        activeChips.push({ key: 'in_stock', label: 'In Stock', onRemove: () => onInStockChange(false) });
    }
    if (localMinPrice || localMaxPrice) {
        const label = localMinPrice && localMaxPrice
            ? `${localMinPrice} - ${localMaxPrice}`
            : localMinPrice
                ? `From ${localMinPrice}`
                : `Up to ${localMaxPrice}`;
        activeChips.push({ key: 'price', label, onRemove: () => { setLocalMinPrice(''); setLocalMaxPrice(''); onMinPriceChange(''); onMaxPriceChange(''); } });
    }

    return (
        <div className="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 shadow-sm">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
                <div className="grid grid-cols-2 sm:flex gap-3">
                    <div className="relative col-span-2 sm:flex-1">
                        <label htmlFor="storefront-search" className="sr-only">
                            {labels.search_products || 'Search products'}
                        </label>
                        <input
                            id="storefront-search"
                            type="text"
                            value={localSearch}
                            onChange={(e) => handleSearchInput(e.target.value)}
                            placeholder={labels.search_products || 'Search products...'}
                            aria-label={labels.search_products || 'Search products'}
                            autoComplete="off"
                            className="w-full pl-10 pr-4 py-2.5 border border-gray-300 dark:border-gray-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-gray-50 hover:bg-white dark:bg-gray-900 transition-colors text-sm"
                        />
                        <svg className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        {localSearch && (
                            <button
                                type="button"
                                onClick={() => { setLocalSearch(''); onQueryChange(''); }}
                                aria-label="Clear search"
                                className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                            >
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        )}
                    </div>

                    <label htmlFor="storefront-category" className="sr-only">
                        Filter by category
                    </label>
                    <select
                        id="storefront-category"
                        value={selectedCategory}
                        onChange={(e) => onCategoryChange(e.target.value)}
                        aria-label="Filter by category"
                        className="w-full sm:w-44 border border-gray-300 dark:border-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 hover:bg-white dark:bg-gray-900 transition-colors"
                    >
                        <option value="">{labels.all_categories || 'All Categories'}</option>
                        {categories?.map((cat) => (
                            <option key={cat.id} value={cat.id}>{cat.name}</option>
                        ))}
                    </select>

                    {brands && brands.length > 0 && (
                        <>
                            <label htmlFor="storefront-brand" className="sr-only">
                                Filter by brand
                            </label>
                            <select
                                id="storefront-brand"
                                value={selectedBrand}
                                onChange={(e) => onBrandChange(e.target.value)}
                                aria-label="Filter by brand"
                                className="w-full sm:w-44 border border-gray-300 dark:border-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 hover:bg-white dark:bg-gray-900 transition-colors"
                            >
                                <option value="">All Brands</option>
                                {brands.map((brand) => (
                                    <option key={brand.id} value={brand.id}>{brand.name}</option>
                                ))}
                            </select>
                        </>
                    )}

                    <label htmlFor="storefront-type" className="sr-only">
                        Filter by product type
                    </label>
                    <select
                        id="storefront-type"
                        value={selectedType || ''}
                        onChange={(e) => onTypeChange(e.target.value)}
                        aria-label="Filter by product type"
                        className="w-full sm:w-36 border border-gray-300 dark:border-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 hover:bg-white dark:bg-gray-900 transition-colors"
                    >
                        <option value="">All Types</option>
                        <option value="single">Single</option>
                        <option value="variable">Variable</option>
                        <option value="combo">Combo</option>
                    </select>

                    <button
                        type="button"
                        onClick={onToggleSidebar}
                        aria-label="Toggle category sidebar"
                        aria-expanded={sidebarOpen}
                        className="col-span-2 lg:hidden flex items-center justify-center gap-2 px-4 py-2.5 border border-gray-300 dark:border-gray-700 rounded-xl text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:bg-gray-950 transition-colors"
                    >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h7" />
                        </svg>
                        {labels.categories || 'Categories'}
                    </button>
                </div>

                <div className="grid grid-cols-2 sm:flex gap-3 mt-3">
                    <div className="flex items-center gap-2">
                        <label htmlFor="storefront-min-price" className="sr-only">
                            Minimum price
                        </label>
                        <input
                            id="storefront-min-price"
                            type="number"
                            value={localMinPrice}
                            onChange={(e) => handleMinPriceChange(e.target.value)}
                            placeholder="Min"
                            aria-label="Minimum price"
                            min="0"
                            inputMode="numeric"
                            className="w-24 sm:w-28 px-3 py-2 border border-gray-300 dark:border-gray-700 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 hover:bg-white dark:bg-gray-900 transition-colors"
                        />
                        <span className="text-gray-400" aria-hidden="true">-</span>
                        <label htmlFor="storefront-max-price" className="sr-only">
                            Maximum price
                        </label>
                        <input
                            id="storefront-max-price"
                            type="number"
                            value={localMaxPrice}
                            onChange={(e) => handleMaxPriceChange(e.target.value)}
                            placeholder="Max"
                            aria-label="Maximum price"
                            min="0"
                            inputMode="numeric"
                            className="w-24 sm:w-28 px-3 py-2 border border-gray-300 dark:border-gray-700 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 hover:bg-white dark:bg-gray-900 transition-colors"
                        />
                    </div>

                    <label htmlFor="storefront-sort" className="sr-only">
                        Sort products
                    </label>
                    <select
                        id="storefront-sort"
                        value={sortBy}
                        onChange={(e) => onSortChange(e.target.value)}
                        aria-label="Sort products"
                        className="w-full sm:w-44 border border-gray-300 dark:border-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 hover:bg-white dark:bg-gray-900 transition-colors"
                    >
                        <option value="recommended">Recommended</option>
                        <option value="newest">Newest</option>
                        <option value="price_asc">Price: Low to High</option>
                        <option value="price_desc">Price: High to Low</option>
                        <option value="name_asc">Name: A-Z</option>
                        <option value="name_desc">Name: Z-A</option>
                    </select>

                    <label className="flex items-center gap-2 cursor-pointer select-none">
                        <input
                            type="checkbox"
                            checked={inStock}
                            onChange={(e) => onInStockChange(e.target.checked)}
                            aria-label="Show only in-stock products"
                            className="w-4 h-4 text-blue-600 border-gray-300 dark:border-gray-700 rounded focus:ring-blue-500"
                        />
                        <span className="text-sm text-gray-700 dark:text-gray-300">In Stock</span>
                    </label>

                    {hasActiveFilters && (
                        <button
                            type="button"
                            onClick={onClearFilters}
                            aria-label="Clear all filters"
                            className="flex items-center gap-1 text-sm text-red-600 hover:text-red-800 transition-colors"
                        >
                            <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            Clear all
                        </button>
                    )}

                    <span className="hidden sm:flex items-center gap-1 text-sm text-gray-500 dark:text-gray-400 ml-auto" aria-live="polite">
                        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                        {totalProducts} Products
                    </span>
                </div>

                {activeChips.length > 0 && (
                    <div className="flex flex-wrap gap-2 mt-3" role="list" aria-label="Active filters">
                        {activeChips.map((chip) => (
                            <span
                                key={chip.key}
                                role="listitem"
                                className="inline-flex items-center gap-1 px-3 py-1 bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 rounded-full text-sm"
                            >
                                {chip.label}
                                <button
                                    type="button"
                                    onClick={chip.onRemove}
                                    aria-label={`Remove filter: ${chip.label}`}
                                    className="ml-1 hover:text-blue-900 dark:hover:text-blue-100"
                                >
                                    <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </span>
                        ))}
                    </div>
                )}

                <div className="sm:hidden flex items-center justify-between mt-3">
                    <span className="text-sm text-gray-500 dark:text-gray-400" aria-live="polite">
                        {totalProducts} Products
                    </span>
                </div>
            </div>
        </div>
    );
}
