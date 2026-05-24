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
    const [currentHeroSlide, setCurrentHeroSlide] = useState(0);

    const heroImages = website_info?.hero_images_urls?.length
        ? website_info.hero_images_urls
        : website_info?.hero_image_url
            ? [website_info.hero_image_url]
            : [];

    const isMultipleHeroImages = heroImages.length > 1;

    const debounceRef = useRef(null);

    useEffect(() => {
        if (!banners?.length) return;
        const interval = setInterval(() => {
            setCurrentBanner(prev => (prev + 1) % banners.length);
        }, 5000);
        return () => clearInterval(interval);
    }, [banners?.length]);

    useEffect(() => {
        if (!isMultipleHeroImages) return;
        const interval = setInterval(() => {
            setCurrentHeroSlide(prev => (prev + 1) % heroImages.length);
        }, 5000);
        return () => clearInterval(interval);
    }, [isMultipleHeroImages, heroImages.length]);

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
                        <div className="flex flex-col sm:flex-row sm:items-center gap-4 sm:gap-10 px-6 sm:px-10 lg:px-14 py-6 sm:py-8 lg:py-12">
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
                            {heroImages.length > 0 && (
                                <div className="w-full sm:w-56 lg:w-72 xl:w-80 flex-shrink-0">
                                    <div className="relative w-full h-40 lg:h-48 xl:h-56">
                                        {heroImages.map((img, idx) => (
                                            <img
                                                key={idx}
                                                src={assetUrl(img)}
                                                alt={`${website_info.hero_title}${isMultipleHeroImages ? ` - ${idx + 1}` : ''}`}
                                                className={`absolute inset-0 w-full h-full object-cover rounded-xl shadow-xl transition-opacity duration-700 ${
                                                    idx === currentHeroSlide % heroImages.length ? 'opacity-100' : 'opacity-0'
                                                }`}
                                                loading={idx === 0 ? 'eager' : 'lazy'}
                                            />
                                        ))}
                                    </div>
                                    {isMultipleHeroImages && (
                                        <div className="flex justify-center gap-1.5 mt-3">
                                            {heroImages.map((_, idx) => (
                                                <button
                                                    key={idx}
                                                    onClick={() => setCurrentHeroSlide(idx)}
                                                    className={`w-1.5 h-1.5 rounded-full transition-all duration-300 ${
                                                        idx === currentHeroSlide % heroImages.length
                                                            ? 'bg-white w-4'
                                                            : 'bg-white/50 hover:bg-white/70'
                                                    }`}
                                                />
                                            ))}
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            )}

            {/* Fallback banner - only show if no hero and no banners */}
            {/* Contact Bar */}
            {(() => {
                const ci = website_info?.contact_info || {};
                const ai = website_info?.address_info || {};
                const mapsLink = ai.google_maps_link || website_info?.google_maps_embed_url;
                const addrParts = [ai.address_line_1, ai.address_line_2, ai.city, ai.state_region, ai.postal_code].filter(Boolean);
                const addrFull = addrParts.length > 0 || ai.country;
                const hasContact = ci.primary_phone || ci.secondary_phone || ci.support_email || ci.contact_email || ci.sales_email || ci.whatsapp_number || ci.telegram_username || addrParts.length > 0;
                if (!hasContact) return null;
                return (
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-8">
                        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4">
                            {(ci.primary_phone || ci.secondary_phone) && (
                                <a href={`tel:${ci.primary_phone || ci.secondary_phone}`} className="flex items-center gap-3 bg-white rounded-xl border border-gray-200 p-4 hover:shadow-md transition-shadow group">
                                    <div className="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0" style={{ backgroundColor: 'rgba(var(--theme-color-rgb,59,130,246),0.1)' }}>
                                        <svg className="w-5 h-5" style={{ color: 'var(--theme-color,#3B82F6)' }} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                        </svg>
                                    </div>
                                    <div className="min-w-0">
                                        <p className="text-xs text-gray-500">Call Us</p>
                                        <p className="text-sm font-medium text-gray-900 truncate group-hover:text-[var(--theme-color,#3B82F6)] transition-colors">{ci.primary_phone || ci.secondary_phone}</p>
                                    </div>
                                </a>
                            )}
                            {(ci.support_email || ci.contact_email) && (
                                <a href={`mailto:${ci.support_email || ci.contact_email}`} className="flex items-center gap-3 bg-white rounded-xl border border-gray-200 p-4 hover:shadow-md transition-shadow group">
                                    <div className="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0" style={{ backgroundColor: 'rgba(var(--theme-color-rgb,59,130,246),0.1)' }}>
                                        <svg className="w-5 h-5" style={{ color: 'var(--theme-color,#3B82F6)' }} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <div className="min-w-0">
                                        <p className="text-xs text-gray-500">Email Us</p>
                                        <p className="text-sm font-medium text-gray-900 truncate group-hover:text-[var(--theme-color,#3B82F6)] transition-colors">{ci.support_email || ci.contact_email}</p>
                                    </div>
                                </a>
                            )}
                            {ci.whatsapp_number && (
                                <a href={`https://wa.me/${ci.whatsapp_number.replace(/\D/g,'')}`} target="_blank" rel="noopener noreferrer" className="flex items-center gap-3 bg-white rounded-xl border border-gray-200 p-4 hover:shadow-md transition-shadow group">
                                    <div className="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0" style={{ backgroundColor: 'rgba(16,185,129,0.1)' }}>
                                        <svg className="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
                                        </svg>
                                    </div>
                                    <div className="min-w-0">
                                        <p className="text-xs text-gray-500">WhatsApp</p>
                                        <p className="text-sm font-medium text-gray-900 truncate group-hover:text-green-600 transition-colors">{ci.whatsapp_number}</p>
                                    </div>
                                </a>
                            )}
                            {ci.telegram_username && (
                                <a href={`https://t.me/${ci.telegram_username}`} target="_blank" rel="noopener noreferrer" className="flex items-center gap-3 bg-white rounded-xl border border-gray-200 p-4 hover:shadow-md transition-shadow group">
                                    <div className="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0" style={{ backgroundColor: 'rgba(14,165,233,0.1)' }}>
                                        <svg className="w-5 h-5 text-sky-600" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M11.944 0A12 12 0 000 12a12 12 0 0012 12 12 12 0 0012-12A12 12 0 0012 0a12 12 0 000 24zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 01.171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z" />
                                        </svg>
                                    </div>
                                    <div className="min-w-0">
                                        <p className="text-xs text-gray-500">Telegram</p>
                                        <p className="text-sm font-medium text-gray-900 truncate group-hover:text-sky-600 transition-colors">@{ci.telegram_username}</p>
                                    </div>
                                </a>
                            )}
                            {addrParts.length > 0 && (
                                <a
                                    href={mapsLink || '#'}
                                    target={mapsLink ? '_blank' : undefined}
                                    rel={mapsLink ? 'noopener noreferrer' : undefined}
                                    className="flex items-center gap-3 bg-white rounded-xl border border-gray-200 p-4 hover:shadow-md transition-shadow group"
                                >
                                    <div className="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0" style={{ backgroundColor: 'rgba(139,92,246,0.1)' }}>
                                        <svg className="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                    </div>
                                    <div className="min-w-0">
                                        <p className="text-xs text-gray-500">Visit Us</p>
                                        <p className="text-sm font-medium text-gray-900 truncate group-hover:text-[var(--theme-color,#3B82F6)] transition-colors">{addrParts[0]}</p>
                                    </div>
                                </a>
                            )}
                        </div>
                        {mapsLink && (
                            <div className="mt-3 flex justify-end">
                                <a
                                    href={mapsLink}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-600 bg-white rounded-lg border border-gray-200 hover:shadow-sm hover:text-[var(--theme-color,#3B82F6)] transition-all"
                                >
                                    <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                                    </svg>
                                    Open in Google Maps
                                    <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                    </svg>
                                </a>
                            </div>
                        )}
                    </div>
                );
            })()}

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
