import { useState, useCallback, useRef, useEffect } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { InfiniteScroll } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';
import ProductCard from '@/Components/ProductCard';
import { assetUrl } from '@/Utils/helpers';
import { useCart } from '@/Hooks/useCart';
import { Link } from '@inertiajs/react';
import BackToTopButton from '@/Components/BackToTopButton';

export default function ClientProductIndex({ products, categories, banners, searchQuery, filters: initFilters = {} }) {
    const { props } = usePage();
    const { website_info } = props;
    const { addToCart, addingId } = useCart();
    const [query, setQuery] = useState(searchQuery || '');
    const [selectedCategory, setSelectedCategory] = useState(initFilters.category_id || '');
    const [sortBy, setSortBy] = useState(initFilters.sort || 'latest');
    const [loading, setLoading] = useState(false);
    const [currentBanner, setCurrentBanner] = useState(0);

    const debounceRef = useRef(null);

    useEffect(() => {
        if (!banners?.length) return;
        const interval = setInterval(() => {
            setCurrentBanner(prev => (prev + 1) % banners.length);
        }, 5000);
        return () => clearInterval(interval);
    }, [banners?.length]);

    const handleQueryChange = (value) => {
        setQuery(value);
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => {
            handleSearchSubmit(value, selectedCategory, sortBy);
        }, 400);
    };

    const handleCategoryChange = (categoryId) => {
        setSelectedCategory(categoryId);
        handleSearchSubmit(query, categoryId, sortBy);
    };

    const handleSortChange = (sort) => {
        setSortBy(sort);
        handleSearchSubmit(query, selectedCategory, sort);
    };

    const handleSearchSubmit = (q, cat, sort) => {
        setLoading(true);
        const params = {};
        if (q) params.query = q;
        if (cat) params.category = cat;
        if (sort && sort !== 'latest') params.sort = sort;

        router.get('/', params, {
            preserveState: true,
            preserveScroll: true,
            only: ['products', 'searchQuery', 'filters'],
            reset: ['products'],
            replace: true,
            onFinish: () => setLoading(false),
        });
    };

    const handleAddToCart = useCallback(async (productId) => {
        await addToCart(productId, 1);
    }, [addToCart]);

    const clearFilters = () => {
        setQuery('');
        setSelectedCategory('');
        setSortBy('latest');
        router.get('/', {}, {
            preserveState: true,
            preserveScroll: true,
            only: ['products', 'searchQuery', 'filters'],
            reset: ['products'],
            replace: true,
        });
    };

    const hasMore = (products?.current_page ?? 1) < (products?.last_page ?? 1);
    const productCount = products?.data?.length ?? 0;

    return (
        <ShopLayout>
            <Head title="Shop" />

            {/* Promotion Banner Carousel */}
            {banners?.length > 0 && (
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-8">
                    <div className="relative overflow-hidden rounded-2xl bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 shadow-xl">
                        <div
                            className="relative z-10 flex transition-transform duration-500 ease-in-out"
                            style={{ transform: `translateX(-${currentBanner * 100}%)` }}
                        >
                            {banners.map((banner) => (
                                <a
                                    key={banner.id}
                                    href={banner.link}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="min-w-full flex items-center gap-6 sm:gap-10 px-6 sm:px-10 lg:px-14 py-8 sm:py-10 lg:py-14"
                                >
                                    <div className="flex-1 min-w-0">
                                        <h2 className="text-xl sm:text-2xl lg:text-3xl font-extrabold text-white leading-tight">
                                            {banner.title}
                                        </h2>
                                        {banner.description && (
                                            <p className="mt-2 sm:mt-3 text-sm sm:text-base text-blue-100 max-w-lg leading-relaxed">
                                                {banner.description}
                                            </p>
                                        )}
                                        <span className="mt-4 sm:mt-5 inline-flex items-center gap-1.5 px-4 py-2 bg-white/20 backdrop-blur-sm text-white text-sm font-semibold rounded-full hover:bg-white/30 transition-colors">
                                            Shop Now
                                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                            </svg>
                                        </span>
                                    </div>
                                    {banner.image && (
                                        <div className="hidden sm:block w-40 lg:w-56 flex-shrink-0">
                                            <img
                                                src={assetUrl(banner.image)}
                                                alt={banner.title}
                                                className="w-full h-28 lg:h-36 object-cover rounded-xl shadow-lg"
                                            />
                                        </div>
                                    )}
                                </a>
                            ))}
                        </div>

                        {banners.length > 1 && (
                            <>
                                <div className="absolute bottom-3 sm:bottom-4 left-1/2 -translate-x-1/2 z-20 flex gap-2">
                                    {banners.map((_, idx) => (
                                        <button
                                            key={idx}
                                            onClick={() => setCurrentBanner(idx)}
                                            className={`w-2 h-2 rounded-full transition-all duration-300 ${
                                                idx === currentBanner ? 'bg-white w-6' : 'bg-white/50 hover:bg-white/70'
                                            }`}
                                        />
                                    ))}
                                </div>
                                <button
                                    onClick={() => setCurrentBanner(prev => (prev - 1 + banners.length) % banners.length)}
                                    className="absolute left-2 sm:left-3 top-1/2 -translate-y-1/2 z-20 w-8 h-8 sm:w-10 sm:h-10 bg-white/20 backdrop-blur-sm hover:bg-white/30 rounded-full flex items-center justify-center text-white transition-colors"
                                >
                                    <svg className="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" /></svg>
                                </button>
                                <button
                                    onClick={() => setCurrentBanner(prev => (prev + 1) % banners.length)}
                                    className="absolute right-2 sm:right-3 top-1/2 -translate-y-1/2 z-20 w-8 h-8 sm:w-10 sm:h-10 bg-white/20 backdrop-blur-sm hover:bg-white/30 rounded-full flex items-center justify-center text-white transition-colors"
                                >
                                    <svg className="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg>
                                </button>
                            </>
                        )}
                    </div>
                </div>
            )}

            {/* Hero Section from Settings */}
            {website_info?.hero_title && (
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-8">
                    <div className="relative overflow-hidden rounded-2xl bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 shadow-xl">
                        <div className="flex items-center gap-6 sm:gap-10 px-6 sm:px-10 lg:px-14 py-8 sm:py-10 lg:py-14">
                            <div className="flex-1 min-w-0">
                                <h2 className="text-xl sm:text-2xl lg:text-3xl font-extrabold text-white leading-tight">
                                    {website_info.hero_title}
                                </h2>
                                {website_info.hero_subtitle && (
                                    <p className="mt-2 sm:mt-3 text-sm sm:text-base text-blue-100 max-w-lg leading-relaxed">
                                        {website_info.hero_subtitle}
                                    </p>
                                )}
                                {website_info.hero_button_text && (
                                    <Link
                                        href={website_info.hero_button_link || '/'}
                                        className="mt-4 sm:mt-5 inline-flex items-center gap-1.5 px-4 py-2 bg-white/20 backdrop-blur-sm text-white text-sm font-semibold rounded-full hover:bg-white/30 transition-colors"
                                    >
                                        {website_info.hero_button_text}
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                        </svg>
                                    </Link>
                                )}
                            </div>
                            {website_info.hero_image && (
                                <div className="hidden sm:block w-40 lg:w-56 flex-shrink-0">
                                    <img
                                        src={assetUrl(website_info.hero_image)}
                                        alt={website_info.hero_title}
                                        className="w-full h-28 lg:h-36 object-cover rounded-xl shadow-lg"
                                    />
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            )}

            {/* Fallback banner - only show if no hero and no banners */}
            {!website_info?.hero_title && !banners?.length && (
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-8">
                    <div className="relative overflow-hidden rounded-2xl bg-gradient-to-r from-amber-500 to-orange-600 shadow-xl px-6 sm:px-10 lg:px-14 py-8 sm:py-10 lg:py-14">
                        <div className="relative z-10">
                            <h2 className="text-xl sm:text-2xl lg:text-3xl font-extrabold text-white">Special Offers</h2>
                            <p className="mt-2 sm:mt-3 text-sm sm:text-base text-amber-100 max-w-lg">
                                Check out our latest deals and discounts on top products!
                            </p>
                            <span className="mt-4 sm:mt-5 inline-flex items-center gap-1.5 px-4 py-2 bg-white/20 backdrop-blur-sm text-white text-sm font-semibold rounded-full">
                                Shop Now <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 8l4 4m0 0l-4 4m4-4H3" /></svg>
                            </span>
                        </div>
                        <div className="absolute -top-6 -right-6 w-48 h-48 bg-white/5 rounded-full"></div>
                        <div className="absolute -bottom-8 -left-8 w-64 h-64 bg-white/5 rounded-full"></div>
                    </div>
                </div>
            )}

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="mb-6 sm:mb-8">
                    <div className="flex flex-col sm:flex-row gap-3 sm:gap-4 mb-4 sm:mb-6">
                        <div className="flex-1">
                            <input
                                type="text"
                                value={query}
                                onChange={(e) => handleQueryChange(e.target.value)}
                                placeholder="Search products..."
                                className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            />
                        </div>
                        <div className="flex gap-2">
                            <select
                                value={selectedCategory}
                                onChange={(e) => handleCategoryChange(e.target.value)}
                                className="border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="">All Categories</option>
                                {categories?.map((cat) => (
                                    <option key={cat.id} value={cat.id}>{cat.name}</option>
                                ))}
                            </select>
                            <select
                                value={sortBy}
                                onChange={(e) => handleSortChange(e.target.value)}
                                className="border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="latest">Latest</option>
                                <option value="price_asc">Price: Low to High</option>
                                <option value="price_desc">Price: High to Low</option>
                                <option value="name">Name A-Z</option>
                            </select>
                        </div>
                    </div>

                    {(query || selectedCategory) && (
                        <div className="flex items-center gap-2 mb-4 flex-wrap">
                            <span className="text-sm text-gray-500">Active filters:</span>
                            {query && (
                                <span className="inline-flex items-center gap-1 px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-sm">
                                    Search: {query}
                                    <button onClick={() => handleQueryChange('')} className="hover:text-blue-900">×</button>
                                </span>
                            )}
                            {selectedCategory && (
                                <span className="inline-flex items-center gap-1 px-3 py-1 bg-indigo-100 text-indigo-700 rounded-full text-sm">
                                    {categories?.find(c => c.id == selectedCategory)?.name}
                                    <button onClick={() => handleCategoryChange('')} className="hover:text-indigo-900">×</button>
                                </span>
                            )}
                            <button
                                onClick={clearFilters}
                                className="text-sm text-red-600 hover:text-red-800 ml-2"
                            >
                                Clear all
                            </button>
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
                ) : productCount === 0 ? (
                    <div className="text-center py-12 sm:py-16 bg-white rounded-xl border border-gray-200">
                        <i className="bi bi-search text-5xl text-gray-300"></i>
                        <h3 className="mt-4 text-lg font-medium text-gray-900">No products found</h3>
                        <p className="mt-2 text-gray-500">Try adjusting your search or filter criteria.</p>
                        <button
                            onClick={clearFilters}
                            className="mt-4 sm:mt-6 px-4 sm:px-6 py-2 sm:py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                        >
                            View all products
                        </button>
                    </div>
                ) : (
                    <>
                        <div className="mb-4 sm:mb-6">
                            <p className="text-sm sm:text-base text-gray-500">
                                <i className="bi bi-box-seam me-1.5"></i>
                                {productCount} Products Available
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
                            <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6">
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

                        {!hasMore && productCount > 0 && (
                            <div className="mt-8 flex justify-center items-center py-4">
                                <p className="text-gray-400 text-sm">You've reached the end</p>
                            </div>
                        )}
                    </>
                )}
            </div>
            <BackToTopButton />
        </ShopLayout>
    );
}
