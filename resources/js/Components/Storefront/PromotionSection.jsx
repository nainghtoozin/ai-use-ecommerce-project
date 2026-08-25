import { usePage } from '@inertiajs/react';

export default function PromotionSection({ promotions = [] }) {
    const { storefront, tenant } = usePage().props;
    if (!promotions.length) return null;
    const labels = storefront?.content?.labels || {};
    return <section className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-6"><div className="grid grid-cols-1 md:grid-cols-2 gap-4">{promotions.map((promotion) => <a key={promotion.id} href={promotion.link?.startsWith('/') ? `/store/${tenant.slug}${promotion.link}` : (promotion.link || '#')} style={{ borderRadius: 'var(--storefront-radius-card, 0.75rem)' }} className={`group overflow-hidden border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm hover:shadow-md transition-shadow ${promotion.desktop_visible === false ? 'lg:hidden' : ''} ${promotion.mobile_visible === false ? 'max-sm:hidden' : ''}`}>{promotion.image_url && <img src={promotion.image_url} alt={promotion.title} width="640" height="320" loading="lazy" className="w-full h-36 sm:h-44 object-cover group-hover:scale-[1.02] transition-transform" /> }<div className="p-4"><h2 className="text-lg font-bold text-gray-900 dark:text-gray-100">{promotion.title}</h2>{promotion.description && <p className="mt-1 text-sm text-gray-500 dark:text-gray-400 line-clamp-2">{promotion.description}</p>}<span className="inline-block mt-3 text-sm font-semibold" style={{ color: 'var(--theme-color, #3B82F6)' }}>{promotion.cta_label || labels.shop_now || 'Shop Now'} &rarr;</span></div></a>)}</div></section>;
}
