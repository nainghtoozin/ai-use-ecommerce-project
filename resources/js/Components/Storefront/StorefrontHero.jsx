import { useState, useEffect, useCallback, useRef } from 'react';
import { sanitizeStorefrontHtml } from '@/Utils/sanitizeStorefrontHtml';

export default function StorefrontHero({ store, websiteInfo, storefront }) {
    const identity = storefront?.identity || {};
    const section = storefront?.homepage?.sections?.find((item) => item.type === 'hero');

    const configuration = section?.configuration || {};
    const storeName = identity.site_title || identity.name || store?.name || websiteInfo?.site_name || 'My Store';
    const heroTitle = configuration.title || storeName;
    const hasConfiguredSubtitle = Object.prototype.hasOwnProperty.call(configuration, 'subtitle');
    const storeDescription = hasConfiguredSubtitle
        ? (configuration.subtitle || '')
        : (identity.tagline || websiteInfo?.site_tagline || identity.description || '');
    const hasConfiguredImages = Object.prototype.hasOwnProperty.call(configuration, 'images');
    const heroImages = hasConfiguredImages ? (configuration.images || []) : (storefront?.media?.hero || websiteInfo?.hero_images_urls || []);
    const [failedImages, setFailedImages] = useState([]);
    const availableImages = heroImages.filter((url) => !failedImages.includes(url));
    const hasImages = availableImages.length > 0;
    const imageCount = availableImages.length;

    const requestedVariant = section?.variant && !['default'].includes(section.variant)
        ? section.variant
        : (storefront?.design?.variants?.hero || 'split');
    const variant = hasImages && !['text-only', 'minimal'].includes(requestedVariant) ? requestedVariant : 'text-only';

    const buttonLink = configuration.button_link || '';
    const buttonHref = buttonLink.startsWith('http://') || buttonLink.startsWith('https://')
        ? buttonLink
        : buttonLink ? `/store/${store.slug}${buttonLink}` : '';
    const hasButton = configuration.button_text && buttonHref;
    const [current, setCurrent] = useState(0);
    const touchStart = useRef(null);
    const touchEnd = useRef(null);
    const minSwipeDistance = 50;

    const goTo = useCallback((idx) => setCurrent(idx), []);
    const goNext = useCallback(() => setCurrent((prev) => (prev + 1) % imageCount), [imageCount]);
    const goPrev = useCallback(() => setCurrent((prev) => (prev - 1 + imageCount) % imageCount), [imageCount]);

    useEffect(() => {
        if (section?.enabled === false || imageCount < 2) return;
        const interval = setInterval(goNext, 5000);
        return () => clearInterval(interval);
    }, [imageCount, goNext, section?.enabled]);

    useEffect(() => {
        if (current >= imageCount) setCurrent(0);
    }, [imageCount, current]);

    const onTouchStart = (e) => { touchEnd.current = null; touchStart.current = e.targetTouches[0].clientX; };
    const onTouchMove = (e) => { touchEnd.current = e.targetTouches[0].clientX; };
    const onTouchEnd = () => {
        if (!touchStart.current || !touchEnd.current) return;
        const distance = touchStart.current - touchEnd.current;
        if (Math.abs(distance) >= minSwipeDistance) {
            if (distance > 0) goNext();
            else goPrev();
        }
    };

    if (section && (section.enabled === false || section.enabled === 0)) return null;

    const visibilityClass = section?.desktop_visible === false && section?.mobile_visible === false
        ? 'hidden'
        : section?.desktop_visible === false
            ? 'lg:hidden'
            : section?.mobile_visible === false
                ? 'hidden sm:block'
                : '';

    const isTextOnly = !hasImages || variant === 'text-only';
    const isMinimal = variant === 'minimal';

    if (isTextOnly) {
        return (
            <div className={`max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 ${visibilityClass}`}>
                <div className="relative overflow-hidden px-6 sm:px-10 py-6 sm:py-8 text-center rounded-xl" style={{ background: 'linear-gradient(135deg, var(--storefront-color-primary, #3B82F6), var(--storefront-color-secondary, #1D4ED8))' }}>
                    <h1 className="text-lg sm:text-xl lg:text-2xl font-bold text-white leading-tight line-clamp-2 break-words">{heroTitle}</h1>
                    {storeDescription && <p className="mt-2 text-sm sm:text-base text-indigo-100 leading-relaxed max-w-2xl mx-auto line-clamp-3" dangerouslySetInnerHTML={{ __html: sanitizeStorefrontHtml(storeDescription) }} />}
                    {hasButton && <div className="mt-4 flex justify-center"><a href={buttonHref} className="inline-flex items-center gap-1.5 px-5 py-2.5 bg-white text-indigo-700 font-semibold text-sm rounded-lg hover:bg-indigo-50 transition-all shadow-md">{configuration.button_text}<svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 8l4 4m0 0l-4 4m4-4H3" /></svg></a></div>}
                </div>
            </div>
        );
    }

    return (
        <div className={`max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 ${visibilityClass}`}>
            <div className="relative overflow-hidden rounded-xl px-6 sm:px-10 py-4 sm:py-5 lg:min-h-[260px] lg:max-h-[340px] flex flex-col md:flex-row md:items-center md:gap-6 lg:gap-8" style={{ background: 'linear-gradient(135deg, var(--storefront-color-primary, #3B82F6), var(--storefront-color-secondary, #1D4ED8))' }}>
                <div className={`flex-1 min-w-0 z-10 ${isMinimal ? 'text-center max-w-2xl mx-auto' : ''}`}>
                    <h1 className="text-base sm:text-lg lg:text-xl font-bold text-white leading-tight line-clamp-2 break-words">{heroTitle}</h1>
                    {storeDescription && <p className="mt-1 text-sm text-indigo-100 leading-relaxed line-clamp-2 max-w-xl" dangerouslySetInnerHTML={{ __html: sanitizeStorefrontHtml(storeDescription) }} />}
                    {hasButton && <div className="mt-2.5 flex flex-wrap gap-2"><a href={buttonHref} className="inline-flex items-center gap-1.5 px-4 py-2 bg-white text-indigo-700 font-semibold text-xs sm:text-sm rounded-lg hover:bg-indigo-50 transition-all shadow-md">{configuration.button_text}<svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 8l4 4m0 0l-4 4m4-4H3" /></svg></a></div>}
                </div>
                <div className="relative w-full md:w-[45%] lg:w-[50%] flex-shrink-0 overflow-hidden rounded-xl h-32 sm:h-36 md:h-44 lg:h-48 mt-3 md:mt-0" onTouchStart={onTouchStart} onTouchMove={onTouchMove} onTouchEnd={onTouchEnd}>
                    {imageCount === 1 ? (
                        <img src={availableImages[0]} alt={configuration.image_alts?.[0] || `${storeName} banner`} className="w-full h-full object-contain" loading="eager" fetchPriority="high" onError={() => setFailedImages((prev) => [...prev, availableImages[0]])} />
                    ) : (
                        <>
                            <div className="flex h-full transition-transform duration-500 ease-in-out" style={{ transform: `translateX(-${current * 100}%)` }}>
                                {availableImages.map((url, idx) => (
                                    <div key={idx} className="min-w-full h-full">
                                        <img src={url} alt={configuration.image_alts?.[idx] || `${storeName} banner ${idx + 1}`} className="w-full h-full object-contain" loading={idx === 0 ? 'eager' : 'lazy'} onError={() => setFailedImages((prev) => prev.includes(url) ? prev : [...prev, url])} />
                                    </div>
                                ))}
                            </div>
                            <button onClick={(e) => { e.preventDefault(); goPrev(); }} className="absolute left-2 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full bg-black/40 hover:bg-black/60 text-white flex items-center justify-center transition-colors" aria-label="Previous image"><svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" /></svg></button>
                            <button onClick={(e) => { e.preventDefault(); goNext(); }} className="absolute right-2 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full bg-black/40 hover:bg-black/60 text-white flex items-center justify-center transition-colors" aria-label="Next image"><svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg></button>
                            <div className="absolute bottom-2 left-1/2 -translate-x-1/2 z-10 flex gap-1.5">
                                {availableImages.map((_, idx) => (
                                    <button key={idx} onClick={(e) => { e.preventDefault(); goTo(idx); }} className={`w-1.5 h-1.5 rounded-full transition-all duration-300 ${idx === current ? 'bg-white w-4' : 'bg-white/60 hover:bg-white/80'}`} aria-label={`Banner ${idx + 1}`} />
                                ))}
                            </div>
                        </>
                    )}
                </div>
            </div>
        </div>
    );
}