import { useState } from 'react';
import { usePage } from '@inertiajs/react';
import { Megaphone, ZoomIn } from 'lucide-react';
import PromotionCountdown from '@/Components/Storefront/PromotionCountdown';
import PromotionImageViewer from '@/Components/Storefront/PromotionImageViewer';

const PROMO_ART_BG = 'linear-gradient(135deg, color-mix(in srgb, var(--theme-color, #3B82F6) 20%, transparent), color-mix(in srgb, var(--theme-color, #3B82F6) 6%, transparent))';

function PromoImage({ promotion, containerClassName, iconClassName, onOpen }) {
    const [failed, setFailed] = useState(false);
    const clickable = Boolean(onOpen && promotion.image_url && !failed);
    return (
        <div
            className={`relative overflow-hidden ${containerClassName || ''} ${clickable ? 'cursor-zoom-in' : ''}`}
            style={{ background: PROMO_ART_BG }}
            role={clickable ? 'button' : undefined}
            tabIndex={clickable ? 0 : undefined}
            aria-label={clickable ? `View ${promotion.title} image` : undefined}
            onClick={clickable ? (e) => { e.preventDefault(); e.stopPropagation(); onOpen(); } : undefined}
            onKeyDown={clickable ? (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); e.stopPropagation(); onOpen(); } } : undefined}
        >
            {promotion.image_url && !failed ? (
                <img
                    src={promotion.image_url}
                    alt={promotion.title}
                    width="640"
                    height="320"
                    loading="lazy"
                    className="absolute inset-0 w-full h-full object-cover object-center pointer-events-none"
                    onError={() => setFailed(true)}
                />
            ) : (
                <div className="absolute inset-0 flex flex-col items-center justify-center gap-1.5">
                    <Megaphone className={iconClassName || 'w-7 h-7'} style={{ color: 'var(--theme-color, #3B82F6)' }} />
                    <span className="text-[10px] font-semibold uppercase tracking-[0.2em] text-gray-500 dark:text-gray-400">Special Offer</span>
                </div>
            )}
            {clickable && (
                <span className="absolute bottom-2 right-2 p-1.5 rounded-full bg-black/50 text-white opacity-0 group-hover:opacity-100 focus-visible:opacity-100 transition-opacity pointer-events-none">
                    <ZoomIn className="w-4 h-4" />
                </span>
            )}
        </div>
    );
}

function PromoBody({ promotion, titleClass, ctaLabel, button }) {
    return (
        <div>
            <h2 className={`font-extrabold leading-tight text-gray-900 dark:text-gray-100 ${titleClass}`}>{promotion.title}</h2>
            {promotion.promotion?.badge && (
                <span className="mt-1.5 inline-block px-2 py-0.5 bg-red-500 text-white text-[11px] font-bold rounded-full">
                    {promotion.promotion.badge}
                </span>
            )}
            {promotion.description && <p className="mt-1.5 text-[13px] text-gray-500 dark:text-gray-400 line-clamp-2">{promotion.description}</p>}
            <PromotionCountdown variant="boxes" startsAt={promotion.starts_at} endsAt={promotion.ends_at} />
            {button ? (
                <span className="inline-flex items-center mt-2.5 px-4 py-1.5 rounded-lg text-sm font-semibold text-white" style={{ backgroundColor: 'var(--theme-color, #3B82F6)' }}>
                    {ctaLabel} &rarr;
                </span>
            ) : (
                <span className="inline-block mt-2.5 text-sm font-semibold" style={{ color: 'var(--theme-color, #3B82F6)' }}>
                    {ctaLabel} &rarr;
                </span>
            )}
        </div>
    );
}

export default function PromotionSection({ promotions = [] }) {
    const { storefront, tenant } = usePage().props;
    const [viewerIndex, setViewerIndex] = useState(null);

    if (!promotions.length) return null;

    const labels = storefront?.content?.labels || {};
    const hrefFor = (promotion) => {
        const dest = promotion.destination || promotion.link || '#';
        return dest.startsWith('/') ? `/store/${tenant.slug}${dest}` : dest;
    };
    const visibilityClass = (promotion) => `${promotion.desktop_visible === false ? 'lg:hidden' : ''} ${promotion.mobile_visible === false ? 'max-sm:hidden' : ''}`;
    const ctaFor = (promotion) => promotion.cta_label || labels.shop_now || 'Shop Now';

    const gallery = promotions
        .filter((promotion) => promotion.image_url)
        .map((promotion) => ({ id: promotion.id, src: promotion.image_url, alt: promotion.title }));
    const openViewer = (promotion) => {
        const galleryIndex = gallery.findIndex((item) => item.id === promotion.id);
        if (galleryIndex >= 0) setViewerIndex(galleryIndex);
    };

    const [featured, ...rest] = promotions;

    return (
        <section className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2 sm:py-3">
            <a
                href={hrefFor(featured)}
                style={{ borderRadius: 'var(--storefront-radius-card, 0.75rem)' }}
                className={`group grid md:grid-cols-[38%_62%] overflow-hidden border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm hover:shadow-md transition-shadow ${visibilityClass(featured)}`}
            >
                <PromoImage promotion={featured} containerClassName="min-h-[190px] md:min-h-[220px]" iconClassName="w-8 h-8" onOpen={() => openViewer(featured)} />
                <div className="p-4 sm:p-5 flex flex-col justify-center">
                    <PromoBody promotion={featured} titleClass="text-lg sm:text-xl" ctaLabel={ctaFor(featured)} button />
                </div>
            </a>
            {rest.length > 0 && (
                <div className="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    {rest.map((promotion) => (
                        <a
                            key={promotion.id}
                            href={hrefFor(promotion)}
                            style={{ borderRadius: 'var(--storefront-radius-card, 0.75rem)' }}
                            className={`group grid sm:grid-cols-[38%_62%] overflow-hidden border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm hover:shadow-md transition-shadow ${visibilityClass(promotion)}`}
                        >
                            <PromoImage promotion={promotion} containerClassName="min-h-28 sm:min-h-[130px]" iconClassName="w-5 h-5" onOpen={() => openViewer(promotion)} />
                            <div className="p-3 flex flex-col justify-center min-w-0">
                                <PromoBody promotion={promotion} titleClass="text-sm" ctaLabel={ctaFor(promotion)} button={false} />
                            </div>
                        </a>
                    ))}
                </div>
            )}
            {viewerIndex !== null && gallery.length > 0 && (
                <PromotionImageViewer
                    images={gallery}
                    index={viewerIndex}
                    onClose={() => setViewerIndex(null)}
                    onIndex={setViewerIndex}
                />
            )}
        </section>
    );
}
