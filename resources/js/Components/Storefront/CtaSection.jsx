import { usePage } from '@inertiajs/react';

export default function CtaSection({ data, variant = null }) {
    const { tenant, storefront } = usePage().props;
    if (!data?.title) return null;
    const alignment = variant || storefront?.design?.variants?.cta || 'centered';
    return <section className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6"><div style={{ borderRadius: 'var(--storefront-radius-card, 0.75rem)', backgroundColor: 'var(--theme-color, #3B82F6)', backgroundImage: data.image_url ? `linear-gradient(rgba(0,0,0,.35),rgba(0,0,0,.35)), url(${data.image_url})` : undefined, backgroundSize: 'cover', backgroundPosition: 'center' }} className={`px-5 sm:px-10 py-8 sm:py-10 text-white overflow-hidden ${alignment === 'centered' ? 'text-center' : 'text-left'}`}><h2 className="text-xl sm:text-2xl font-bold">{data.title}</h2>{data.description && <p className="mt-2 text-sm text-white/85 max-w-2xl mx-auto">{data.description}</p>}{data.button_link && <a href={data.button_link.startsWith('/') ? `/store/${tenant.slug}${data.button_link}` : data.button_link} className="inline-block mt-5 bg-white px-4 py-2 text-sm font-semibold" style={{ color: 'var(--theme-color, #3B82F6)', borderRadius: 'var(--storefront-radius-button, 0.5rem)' }}>{data.button_text || storefront?.content?.labels?.shop_now || 'Shop Now'}</a>}</div></section>;
}
