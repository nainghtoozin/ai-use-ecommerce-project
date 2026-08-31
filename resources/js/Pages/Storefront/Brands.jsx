import { Link, usePage } from '@inertiajs/react';
import { Head } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';
import { assetUrl } from '@/Utils/helpers';

export default function StoreBrands({ tenant, brands, filters }) {
    const { storefront } = usePage().props;
    const labels = storefront?.content?.labels || {};

    return (
        <ShopLayout>
            <Head title={`Brands - ${storefront?.identity?.site_title || tenant.name}`} />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <nav className="flex items-center text-xs text-gray-400 dark:text-gray-500 mb-4 gap-1">
                    <Link href={`/store/${tenant.slug}`} className="hover:text-indigo-600 transition-colors">{tenant.name}</Link>
                    <svg className="w-3 h-3 mx-0.5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg>
                    <span className="text-gray-600 dark:text-gray-400">Brands</span>
                </nav>

                <div className="mb-6">
                    <h1 className="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-gray-100">Our Brands</h1>
                    <p className="mt-2 text-gray-500 dark:text-gray-400">Browse products by brand</p>
                </div>

                {filters.featured && (
                    <div className="mb-4">
                        <span className="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300">
                            Featured Brands Only
                        </span>
                    </div>
                )}

                {brands.length === 0 ? (
                    <div className="text-center py-12 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800">
                        <svg className="mx-auto w-16 h-16 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        <h3 className="mt-4 text-lg font-medium text-gray-900 dark:text-gray-100">No brands available</h3>
                        <p className="mt-2 text-gray-500 dark:text-gray-400">Check back later for our brand partners.</p>
                    </div>
                ) : (
                    <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                        {brands.map((brand) => (
                            <Link
                                key={brand.id}
                                href={tenant ? `/store/${tenant.slug}/brands/${brand.id}` : `/brands/${brand.id}`}
                                className="group bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-4 hover:shadow-lg hover:border-indigo-300 dark:hover:border-indigo-700 transition-all duration-200"
                            >
                                <div className="aspect-square flex items-center justify-center mb-3">
                                    {brand.logo_url ? (
                                        <img
                                            src={assetUrl(brand.logo_url)}
                                            alt={brand.name}
                                            className="max-w-full max-h-full object-contain group-hover:scale-105 transition-transform duration-300"
                                        />
                                    ) : (
                                        <div className="w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                                            <span className="text-2xl font-bold text-gray-400 dark:text-gray-500">
                                                {brand.name?.charAt(0)?.toUpperCase() || 'B'}
                                            </span>
                                        </div>
                                    )}
                                </div>
                                <div className="text-center">
                                    <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors truncate">
                                        {brand.name}
                                    </h3>
                                    <p className="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                        {brand.products_count || 0} products
                                    </p>
                                </div>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </ShopLayout>
    );
}
