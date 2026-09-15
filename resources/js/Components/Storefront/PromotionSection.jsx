import { useState } from 'react';
import { usePage } from '@inertiajs/react';
import { Megaphone } from 'lucide-react';
import PromotionCountdown from '@/Components/Storefront/PromotionCountdown';

const PROMO_ART_BG = 'linear-gradient(135deg, color-mix(in srgb, var(--theme-color, #3B82F6) 20%, transparent), color-mix(in srgb, var(--theme-color, #3B82F6) 6%, transparent))';

function PromoImage({ promotion, iconClassName }) {
    const [failed, setFailed] = useState(false);
    if (promotion.image_url && !failed) {
        return (
            <img
                src={promotion.image_url}
                alt={promotion.title}
                width="640"
                height="320"
                loading="lazy"
                className="block w-full h-auto"
                onError={() => setFailed(true)}
            />
        );
    }
    return (
        <div
            className="flex flex-col items-center justify-center gap-1.5 py-10"
            style={{ background: PROMO_ART_BG }}
        >
            <Megaphone className={iconClassName || 'w-7 h-7'} style={{ color: 'var(--theme-color, #3B82F6)' }} />
            <span className="text-[10px] font-semibold uppercase tracking-[0.2em] text-gray-500 dark:text-gray-400">Special Offer</span>
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
                <span className="inline-flex items-center mt-3 px-5 py-2 rounded-lg text-sm font-semibold text-white" style={{ backgroundColor: 'var(--theme-color, #3B82F6)' }}>
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

    if (!promotions.length) return null;

    const labels = storefront?.content?.labels || {};
    const hrefFor = (promotion) => {
        const dest = promotion.destination || promotion.link || '#';
        return dest.startsWith('/') ? `/store/${tenant.slug}${dest}` : dest;
    };
    const visibilityClass = (promotion) => `${promotion.desktop_visible === false ? 'lg:hidden' : ''} ${promotion.mobile_visible === false ? 'max-sm:hidden' : ''}`;
    const ctaFor = (promotion) => promotion.cta_label || labels.shop_now || 'Shop Now';

    const [featured, ...rest] = promotions;

    return (
        <section className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 sm:py-4">
            <a
                href={hrefFor(featured)}
                style={{ borderRadius: 'var(--storefront-radius-card, 0.75rem)' }}
                className={`group grid md:grid-cols-[38%_62%] overflow-hidden border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm hover:shadow-md transition-shadow ${visibilityClass(featured)}`}
            >
                <div className="flex flex-col justify-center">
                    <PromoImage promotion={featured} iconClassName="w-8 h-8" />
                </div>
                <div className="p-5 sm:p-6 flex flex-col justify-center">
                    <PromoBody promotion={featured} titleClass="text-xl sm:text-2xl" ctaLabel={ctaFor(featured)} button />
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
                            <div className="flex flex-col justify-center">
                                <PromoImage promotion={promotion} iconClassName="w-5 h-5" />
                            </div>
                            <div className="p-4 flex flex-col justify-center min-w-0">
                                <PromoBody promotion={promotion} titleClass="text-[15px]" ctaLabel={ctaFor(promotion)} button={false} />
                            </div>
                        </a>
                    ))}
                </div>
            )}
        </section>
    );
}
