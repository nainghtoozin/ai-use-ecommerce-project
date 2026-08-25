import { usePage } from '@inertiajs/react';

export default function BrandStorySection({ data, variant = null }) {
    const { tenant, storefront } = usePage().props;
    if (!data?.description) return null;
    const layout = variant || storefront?.design?.variants?.brand_story || 'split';
    const showImage = data.image_url && layout !== 'text-only';
    return <section className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6"><div style={{ borderRadius: 'var(--storefront-radius-card, 0.75rem)' }} className={`grid grid-cols-1 ${showImage && layout === 'split' ? 'lg:grid-cols-2' : ''} gap-6 items-center bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 p-5 sm:p-8`}><div><h2 className="text-xl sm:text-2xl font-bold text-gray-900 dark:text-gray-100">{data.title || 'About Our Store'}</h2><p className="mt-3 text-sm leading-7 text-gray-600 dark:text-gray-300">{data.description}</p>{data.button_link && <a href={data.button_link.startsWith('/') ? `/store/${tenant.slug}${data.button_link}` : data.button_link} className="inline-block mt-4 text-sm font-semibold" style={{ color: 'var(--theme-color, #3B82F6)' }}>{data.button_text || 'Learn More'} &rarr;</a>}</div>{showImage && <img src={data.image_url} alt={data.title || 'About our store'} className="w-full h-56 object-cover" style={{ borderRadius: 'var(--storefront-radius-card, 0.75rem)' }} />}</div></section>;
}
