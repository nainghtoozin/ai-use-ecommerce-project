import { useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { assetUrl } from '@/Utils/helpers';

export default function FeaturedBrands({ brands, title = 'Featured Brands', description = '', variant = null }) {
    const { tenant, storefront } = usePage().props;
    const [failedImages, setFailedImages] = useState({});

    if (!brands?.length) return null;

    const handleImageError = (id) => setFailedImages((prev) => ({ ...prev, [id]: true }));

    const layout = variant || storefront?.design?.variants?.brands || 'grid';

    return (
        <section id="brands-section" className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 sm:py-4">
            <div className="flex items-center justify-between mb-2">
                <div><h2 className="text-lg sm:text-xl font-bold text-gray-900 dark:text-gray-100">{title}</h2>{description && <p className="text-sm text-gray-500 mt-1">{description}</p>}</div>
                <Link
                    href={tenant ? `/store/${tenant.slug}/brands` : '/brands'}
                    className="text-sm font-medium transition-colors"
                    style={{ color: 'var(--theme-color, #3B82F6)' }}
                >
                    {storefront?.content?.labels?.view_all_brands || 'View All Brands'} &rarr;
                </Link>
            </div>
            <div className={`grid ${layout === 'horizontal' ? 'flex gap-4 overflow-x-auto pb-2' : 'grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6'} gap-3 sm:gap-4`}>
                {brands.slice(0, 6).map((brand) => {
                    const logoUrl = brand.logo_url || assetUrl(brand.logo);
                    const showLogo = logoUrl && !failedImages[brand.id];
                    return (
                        <Link
                            key={brand.id}
                            href={tenant ? `/store/${tenant.slug}/brands/${brand.id}` : `/brands/${brand.id}`}
                            style={{ borderRadius: 'var(--storefront-radius-card, 0.75rem)', borderColor: 'var(--storefront-color-border, #E5E7EB)' }}
                            className={`group flex flex-col items-center gap-2 p-4 sm:p-5 bg-white dark:bg-gray-900 hover:shadow-md transition-all duration-200 border ${layout === 'horizontal' ? 'min-w-[10rem] snap-start' : ''}`}
                        >
                            {showLogo ? (
                                <img
                                    src={logoUrl}
                                    alt={brand.name}
                                    className="w-12 h-12 sm:w-14 sm:h-14 object-contain group-hover:scale-110 transition-transform duration-300"
                                    loading="lazy"
                                    onError={() => handleImageError(brand.id)}
                                />
                            ) : (
                                <div className="w-12 h-12 sm:w-14 sm:h-14 rounded-full bg-[var(--storefront-color-surface-muted,#F9FAFB)] flex items-center justify-center">
                                    <span className="text-xl sm:text-2xl font-bold text-gray-400 dark:text-gray-500">
                                        {brand.name?.charAt(0)?.toUpperCase() || 'B'}
                                    </span>
                                </div>
                            )}
                            <div className="text-center">
                                <h3 className="text-xs sm:text-sm font-medium text-gray-900 dark:text-gray-100 group-hover:text-indigo-600 transition-colors truncate max-w-full">
                                    {brand.name}
                                </h3>
                                {brand.products_count > 0 && (
                                    <p className="mt-0.5 text-xs text-gray-400 dark:text-gray-500">{brand.products_count} items</p>
                                )}
                            </div>
                        </Link>
                    );
                })}
            </div>
        </section>
    );
}
