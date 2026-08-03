import { Link } from '@inertiajs/react';
import { ArrowRight, Package } from 'lucide-react';
import { useTranslation } from '@/Utils/useTranslation';
import { assetUrl } from '@/Utils/helpers';

export default function StoreDirectorySection({ stores }) {
    const { t } = useTranslation();

    if (!stores || stores.length === 0) return null;

    return (
        <section className="py-16 sm:py-20 lg:py-24 bg-white dark:bg-gray-900">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-12">
                    <div>
                        <h2 className="text-3xl sm:text-4xl font-bold text-gray-900 dark:text-gray-100">
                            {t('landing.directory.title')}
                        </h2>
                        <p className="mt-4 text-gray-500 dark:text-gray-400 text-lg">
                            {t('landing.directory.subtitle')}
                        </p>
                    </div>
                    <Link
                        href="/marketplace"
                        className="inline-flex items-center gap-2 text-sm font-semibold text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 transition-colors"
                    >
                        {t('landing.directory.view_all')}
                        <ArrowRight className="w-4 h-4" />
                    </Link>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    {stores.map((store) => (
                        <Link
                            key={store.id}
                            href={store.store_url}
                            className="group bg-gray-50 dark:bg-gray-950 rounded-2xl border border-gray-200 dark:border-gray-800 p-6 hover:shadow-lg hover:border-gray-300 dark:hover:border-gray-700 transition-all duration-200"
                        >
                            <div className="flex items-center gap-4 mb-4">
                                {store.logo_url ? (
                                    <img
                                        src={store.logo_url}
                                        alt={store.name}
                                        className="w-12 h-12 rounded-xl object-cover border border-gray-200 dark:border-gray-700"
                                        loading="lazy"
                                    />
                                ) : (
                                    <div className="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white font-bold text-lg">
                                        {store.name?.charAt(0)}
                                    </div>
                                )}
                                <div className="flex-1 min-w-0">
                                    <h3 className="text-base font-semibold text-gray-900 dark:text-gray-100 truncate group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                                        {store.name}
                                    </h3>
                                    <div className="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                                        <Package className="w-3 h-3" />
                                        <span>{t('landing.directory.products', { count: store.products_count })}</span>
                                    </div>
                                </div>
                            </div>
                            {store.description && (
                                <p className="text-sm text-gray-500 dark:text-gray-400 line-clamp-2 leading-relaxed">
                                    {store.description}
                                </p>
                            )}
                        </Link>
                    ))}
                </div>
            </div>
        </section>
    );
}
