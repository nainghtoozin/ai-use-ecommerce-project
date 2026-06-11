import { useState, useEffect } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { assetUrl } from '@/Utils/helpers';

export default function PromotionBanner({ banners }) {
    const { tenant } = usePage().props;
    const [current, setCurrent] = useState(0);

    useEffect(() => {
        if (!banners?.length || banners.length < 2) return;
        const interval = setInterval(() => setCurrent(prev => (prev + 1) % banners.length), 5000);
        return () => clearInterval(interval);
    }, [banners?.length]);

    if (!banners?.length) return null;

    return (
        <section className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
            <div className="relative overflow-hidden rounded-2xl bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-500 shadow-xl">
                <div className="flex transition-transform duration-500 ease-in-out" style={{ transform: `translateX(-${current * 100}%)` }}>
                    {banners.map((banner) => (
                        <a
                            key={banner.id}
                            href={banner.link || '#'}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="min-w-full flex items-center gap-6 sm:gap-10 px-6 sm:px-10 lg:px-14 py-8 sm:py-10 lg:py-14"
                        >
                            <div className="flex-1 min-w-0">
                                <h2 className="text-xl sm:text-2xl lg:text-3xl font-extrabold text-white leading-tight">
                                    {banner.title}
                                </h2>
                                {banner.description && (
                                    <p className="mt-2 sm:mt-3 text-sm sm:text-base text-indigo-100 max-w-lg leading-relaxed">
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
                                        loading="lazy"
                                    />
                                </div>
                            )}
                        </a>
                    ))}
                </div>
                {banners.length > 1 && (
                    <>
                        <div className="absolute bottom-3 sm:bottom-4 left-1/2 -translate-x-1/2 z-10 flex gap-2">
                            {banners.map((_, idx) => (
                                <button
                                    key={idx}
                                    onClick={() => setCurrent(idx)}
                                    className={`w-2 h-2 rounded-full transition-all duration-300 ${idx === current ? 'bg-white w-6' : 'bg-white/50 hover:bg-white/70'}`}
                                />
                            ))}
                        </div>
                        <button onClick={() => setCurrent(prev => (prev - 1 + banners.length) % banners.length)}
                            className="absolute left-2 sm:left-3 top-1/2 -translate-y-1/2 z-10 w-8 h-8 sm:w-10 sm:h-10 bg-white/20 backdrop-blur-sm hover:bg-white/30 rounded-full flex items-center justify-center text-white transition-colors">
                            <svg className="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" /></svg>
                        </button>
                        <button onClick={() => setCurrent(prev => (prev + 1) % banners.length)}
                            className="absolute right-2 sm:right-3 top-1/2 -translate-y-1/2 z-10 w-8 h-8 sm:w-10 sm:h-10 bg-white/20 backdrop-blur-sm hover:bg-white/30 rounded-full flex items-center justify-center text-white transition-colors">
                            <svg className="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg>
                        </button>
                    </>
                )}
            </div>
        </section>
    );
}
