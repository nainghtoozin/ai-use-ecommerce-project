import { useState, useEffect } from 'react';
import { usePage } from '@inertiajs/react';
import { assetUrl } from '@/Utils/helpers';

export default function HeroSection({ store, websiteInfo }) {
    const { tenant } = usePage().props;
    const storeName = store?.name || websiteInfo?.site_name || 'My Store';
    const storeDescription = websiteInfo?.site_description || websiteInfo?.site_tagline || '';
    const logoUrl = assetUrl(store?.logo || websiteInfo?.logo);
    const heroImages = websiteInfo?.hero_images_urls || [];
    const hasImages = heroImages.length > 0;
    const [current, setCurrent] = useState(0);

    useEffect(() => {
        if (heroImages.length < 2) return;
        const interval = setInterval(() => setCurrent(prev => (prev + 1) % heroImages.length), 5000);
        return () => clearInterval(interval);
    }, [heroImages.length]);

    return (
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
            <div className="relative flex flex-col md:flex-row md:items-center gap-4 md:gap-6 bg-gradient-to-r from-indigo-600 to-purple-600 rounded-2xl overflow-hidden px-5 sm:px-8 lg:px-10 py-5 sm:py-6 md:max-h-[260px]">
                <div className="flex-1 min-w-0">
                    {logoUrl && (
                        <img src={logoUrl} alt={storeName} className="h-7 sm:h-9 w-auto mb-2.5" loading="eager" />
                    )}
                    <h1 className="text-lg sm:text-xl lg:text-2xl font-bold text-white leading-tight truncate">
                        {storeName}
                    </h1>
                    {storeDescription && (
                        <p className="mt-1 text-sm sm:text-sm text-indigo-100 leading-relaxed max-w-xl line-clamp-2">
                            {storeDescription}
                        </p>
                    )}
                    <div className="mt-3 flex flex-wrap gap-2">
                        <a
                            href={`/store/${tenant.slug}#products-section`}
                            className="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-white text-indigo-700 font-semibold text-xs sm:text-sm rounded-lg hover:bg-indigo-50 transition-all shadow-md"
                        >
                            Shop Now
                            <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 8l4 4m0 0l-4 4m4-4H3" />
                            </svg>
                        </a>
                        <a
                            href={`/store/${tenant.slug}#categories-section`}
                            className="inline-flex items-center gap-1.5 px-3.5 py-1.5 border border-white/30 text-white font-semibold text-xs sm:text-sm rounded-lg hover:bg-white/10 transition-all"
                        >
                            Browse Categories
                        </a>
                    </div>
                </div>
                {hasImages && (
                    <div className="relative w-full md:w-48 lg:w-56 flex-shrink-0 overflow-hidden rounded-xl h-32 sm:h-36 md:h-44 bg-black/10">
                        <div className="flex transition-transform duration-500 ease-in-out h-full" style={{ transform: `translateX(-${current * 100}%)` }}>
                            {heroImages.map((url, idx) => (
                                <div key={idx} className="min-w-full h-full">
                                    <img
                                        src={url}
                                        alt={`Banner ${idx + 1}`}
                                        className="w-full h-full object-cover"
                                        loading={idx === 0 ? 'eager' : 'lazy'}
                                    />
                                </div>
                            ))}
                        </div>
                        {heroImages.length > 1 && (
                            <div className="absolute bottom-2 left-1/2 -translate-x-1/2 z-10 flex gap-1.5">
                                {heroImages.map((_, idx) => (
                                    <button
                                        key={idx}
                                        onClick={(e) => { e.preventDefault(); setCurrent(idx); }}
                                        className={`w-1.5 h-1.5 rounded-full transition-all duration-300 ${idx === current ? 'bg-white w-4' : 'bg-white/60 hover:bg-white/80'}`}
                                        aria-label={`Banner ${idx + 1}`}
                                    />
                                ))}
                            </div>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}
