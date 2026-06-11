import { Link, usePage } from '@inertiajs/react';
import { assetUrl } from '@/Utils/helpers';

export default function FeaturedCategories({ categories }) {
    const { tenant } = usePage().props;

    if (!categories?.length) return null;

    return (
        <section id="categories-section" className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 sm:py-4">
            <div className="flex items-center justify-between mb-2">
                <h2 className="text-lg sm:text-xl font-bold text-gray-900">Categories</h2>
                <Link
                    href={(tenant?.slug ? `/store/${tenant.slug}` : '') + '/products'}
                    className="text-sm font-medium text-indigo-600 hover:text-indigo-800 transition-colors"
                >
                    View All &rarr;
                </Link>
            </div>
            <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-2 sm:gap-3">
                {categories.slice(0, 6).map((category) => (
                    <Link
                        key={category.id}
                        href={(tenant?.slug ? `/store/${tenant.slug}` : '') + `/products?category=${category.id}`}
                        className="group flex flex-col items-center gap-2 p-4 sm:p-5 bg-white rounded-xl border border-gray-200 hover:border-indigo-200 hover:shadow-md transition-all duration-200"
                    >
                        <div className="w-12 h-12 sm:w-14 sm:h-14 rounded-full bg-gray-50 flex items-center justify-center group-hover:bg-indigo-50 transition-colors">
                            {category.image ? (
                                <img
                                    src={assetUrl(category.image)}
                                    alt={category.name}
                                    className="w-8 h-8 sm:w-10 sm:h-10 object-contain group-hover:scale-110 transition-transform duration-300"
                                    loading="lazy"
                                />
                            ) : (
                                <svg className="w-5 h-5 sm:w-6 sm:h-6 text-gray-400 group-hover:text-indigo-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                </svg>
                            )}
                        </div>
                        <div className="text-center">
                            <h3 className="text-xs sm:text-sm font-medium text-gray-900 group-hover:text-indigo-600 transition-colors truncate max-w-full">
                                {category.name}
                            </h3>
                            {category.products_count > 0 && (
                                <p className="mt-0.5 text-xs text-gray-400">{category.products_count} items</p>
                            )}
                        </div>
                    </Link>
                ))}
            </div>
        </section>
    );
}
