import { useState, useRef } from 'react';
import { usePage } from '@inertiajs/react';

export default function FilterBar({
    query,
    onQueryChange,
    categories,
    selectedCategory,
    onCategoryChange,
    sortBy,
    onSortChange,
    inStock,
    onInStockChange,
    onClearFilters,
    totalProducts,
    hasActiveFilters,
    onToggleSidebar,
}) {
    const { storefront } = usePage().props;
    const labels = storefront?.content?.labels || {};
    const [localSearch, setLocalSearch] = useState(query || '');
    const debounceRef = useRef(null);

    const handleSearchInput = (value) => {
        setLocalSearch(value);
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => {
            onQueryChange(value);
        }, 400);
    };

    return (
        <div className="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 shadow-sm">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
                <div className="grid grid-cols-2 sm:flex gap-3">
                    <div className="relative col-span-2 sm:flex-1">
                        <input
                            type="text"
                            value={localSearch}
                            onChange={(e) => handleSearchInput(e.target.value)}
                            placeholder={labels.search_products || 'Search products...'}
                            className="w-full pl-10 pr-4 py-2.5 border border-gray-300 dark:border-gray-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-gray-50 hover:bg-white dark:bg-gray-900 transition-colors text-sm"
                        />
                        <svg className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>

                    <select
                        value={selectedCategory}
                        onChange={(e) => onCategoryChange(e.target.value)}
                        className="w-full sm:w-44 border border-gray-300 dark:border-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 hover:bg-white dark:bg-gray-900 transition-colors"
                    >
                        <option value="">{labels.all_categories || 'All Categories'}</option>
                        {categories.map((cat) => (
                            <option key={cat.id} value={cat.id}>{cat.name}</option>
                        ))}
                    </select>

                    <select
                        value={sortBy}
                        onChange={(e) => onSortChange(e.target.value)}
                        className="w-full sm:w-44 border border-gray-300 dark:border-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 hover:bg-white dark:bg-gray-900 transition-colors"
                    >
                        <option value="latest">Latest</option>
                        <option value="price_asc">Price: Low to High</option>
                        <option value="price_desc">Price: High to Low</option>
                        <option value="name">Name: A-Z</option>
                    </select>

                    <button
                        onClick={onToggleSidebar}
                        className="col-span-2 lg:hidden flex items-center justify-center gap-2 px-4 py-2.5 border border-gray-300 dark:border-gray-700 rounded-xl text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:bg-gray-950 transition-colors"
                    >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h7" />
                        </svg>
                        {labels.categories || 'Categories'}
                    </button>
                </div>

                <div className="flex flex-wrap items-center gap-x-4 gap-y-2 mt-3">
                    <label className="flex items-center gap-2 cursor-pointer select-none">
                        <input
                            type="checkbox"
                            checked={inStock}
                            onChange={(e) => onInStockChange(e.target.checked)}
                            className="w-4 h-4 text-blue-600 border-gray-300 dark:border-gray-700 rounded focus:ring-blue-500"
                        />
                        <span className="text-sm text-gray-700 dark:text-gray-300">In Stock Only</span>
                    </label>

                    <div className="hidden">
                        {[1, 2, 3, 4, 5].map((star) => (
                            <svg key={star} className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                            </svg>
                        ))}
                        <span className="text-xs text-gray-400 dark:text-gray-500 ml-1">Rating</span>
                    </div>

                    {hasActiveFilters && (
                        <button
                            onClick={onClearFilters}
                            className="flex items-center gap-1 text-sm text-red-600 hover:text-red-800 transition-colors"
                        >
                            <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            Clear all
                        </button>
                    )}

                    <span className="text-sm text-gray-500 dark:text-gray-400 ml-auto">
                        <svg className="w-3.5 h-3.5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                        {totalProducts} Products
                    </span>
                </div>
            </div>
        </div>
    );
}
