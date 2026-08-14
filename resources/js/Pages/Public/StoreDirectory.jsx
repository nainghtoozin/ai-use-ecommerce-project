import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState, useCallback } from 'react';
import PlatformLayout from '@/Layouts/PlatformLayout';
import { Search, Package, ArrowLeft, ChevronDown } from 'lucide-react';
import { useTranslation } from '@/Utils/useTranslation';
import { assetUrl } from '@/Utils/helpers';

export default function StoreDirectory() {
    const { stores, categories, filters } = usePage().props;
    const { t } = useTranslation();

    const [search, setSearch] = useState(filters?.search || '');
    const [categoryId, setCategoryId] = useState(filters?.category_id || '');
    const [sort, setSort] = useState(filters?.sort || 'newest');

    const applyFilters = useCallback(() => {
        router.get('/marketplace', {
            search: search || undefined,
            category_id: categoryId || undefined,
            sort,
        }, { preserveState: true, preserveScroll: true });
    }, [search, categoryId, sort]);

    const handleSearchKeyDown = (e) => {
        if (e.key === 'Enter') applyFilters();
    };

    const clearFilters = () => {
        setSearch('');
        setCategoryId('');
        setSort('newest');
        router.get('/marketplace', {}, { preserveState: true });
    };

    const hasActiveFilters = search || categoryId || sort !== 'newest';

    return (
        <PlatformLayout>
            <Head title={t('landing.marketplace.title')} />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                {/* Header */}
                <div className="mb-8">
                    <Link href="/" className="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 mb-4 transition-colors">
                        <ArrowLeft className="w-4 h-4" />
                        {t('landing.marketplace.back_to_home')}
                    </Link>
                    <h1 className="text-3xl sm:text-4xl font-bold text-gray-900 dark:text-gray-100">
                        {t('landing.marketplace.title')}
                    </h1>
                    <p className="mt-2 text-gray-500 dark:text-gray-400 text-lg">
                        {t('landing.marketplace.subtitle')}
                    </p>
                </div>

                {/* Filters */}
                <div className="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 p-4 sm:p-6 mb-8">
                    <div className="flex flex-col sm:flex-row gap-3">
                        <div className="relative flex-1">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onKeyDown={handleSearchKeyDown}
                                placeholder={t('landing.marketplace.search_placeholder')}
                                className="w-full pl-10 pr-4 py-2.5 text-sm border border-gray-300 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            />
                        </div>

                        <div className="relative">
                            <select
                                value={categoryId}
                                onChange={(e) => { setCategoryId(e.target.value); setTimeout(applyFilters, 0); }}
                                className="appearance-none w-full sm:w-48 px-4 py-2.5 pr-10 text-sm border border-gray-300 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            >
                                <option value="">{t('landing.marketplace.all_categories')}</option>
                                {categories?.map((cat) => (
                                    <option key={cat.id} value={cat.id}>{cat.name}</option>
                                ))}
                            </select>
                            <ChevronDown className="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
                        </div>

                        <div className="relative">
                            <select
                                value={sort}
                                onChange={(e) => { setSort(e.target.value); setTimeout(applyFilters, 0); }}
                                className="appearance-none w-full sm:w-40 px-4 py-2.5 pr-10 text-sm border border-gray-300 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            >
                                <option value="newest">{t('landing.marketplace.sort_newest')}</option>
                                <option value="name_az">{t('landing.marketplace.sort_name_az')}</option>
                                <option value="name_za">{t('landing.marketplace.sort_name_za')}</option>
                            </select>
                            <ChevronDown className="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
                        </div>

                        <button
                            onClick={applyFilters}
                            className="px-6 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-xl hover:bg-blue-700 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                        >
                            <Search className="w-4 h-4 sm:hidden" />
                            <span className="hidden sm:inline">Search</span>
                        </button>

                        {hasActiveFilters && (
                            <button
                                onClick={clearFilters}
                                className="px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 rounded-xl hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                            >
                                Clear
                            </button>
                        )}
                    </div>
                </div>

                {/* Store Grid */}
                {stores?.data?.length > 0 ? (
                    <>
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                            {stores.data.map((store) => (
                                <Link
                                    key={store.id}
                                    href={store.store_url}
                                    className="group bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 p-6 hover:shadow-lg hover:border-gray-300 dark:hover:border-gray-700 transition-all duration-200"
                                >
                                    <div className="flex items-center gap-4 mb-4">
                                        {store.logo_url ? (
                                            <img
                                                src={store.logo_url}
                                                alt={store.name}
                                                className="w-14 h-14 rounded-xl object-cover border border-gray-200 dark:border-gray-700"
                                                loading="lazy"
                                            />
                                        ) : (
                                            <div className="w-14 h-14 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white font-bold text-xl">
                                                {store.name?.charAt(0)}
                                            </div>
                                        )}
                                        <div className="flex-1 min-w-0">
                                            <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100 truncate group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                                                {store.name}
                                            </h3>
                                            <div className="flex items-center gap-1 text-sm text-gray-500 dark:text-gray-400">
                                                <Package className="w-3.5 h-3.5" />
                                                <span>{t('landing.marketplace.products_count', {
                                                    count: Number.isFinite(Number(store.products_count)) ? Number(store.products_count) : 0,
                                                })}</span>
                                            </div>
                                        </div>
                                    </div>
                                    {store.description && (
                                        <p className="text-sm text-gray-500 dark:text-gray-400 line-clamp-2 leading-relaxed mb-4">
                                            {store.description}
                                        </p>
                                    )}
                                    <div className="flex items-center justify-end">
                                        <span className="text-sm font-medium text-blue-600 dark:text-blue-400 group-hover:underline">
                                            {t('landing.marketplace.visit_store')} →
                                        </span>
                                    </div>
                                </Link>
                            ))}
                        </div>

                        {/* Pagination */}
                        {stores.links && stores.links.length > 3 && (
                            <div className="flex justify-center gap-2 mt-10">
                                {stores.links.map((link, index) => (
                                    <Link
                                        key={index}
                                        href={link.url || '#'}
                                        className={`px-3 py-2 text-sm rounded-lg transition-colors ${
                                            link.active
                                                ? 'bg-blue-600 text-white'
                                                : link.url
                                                    ? 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700'
                                                    : 'text-gray-400 cursor-not-allowed'
                                        }`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        )}
                    </>
                ) : (
                    <div className="text-center py-16">
                        <Package className="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-4" />
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">
                            {t('landing.marketplace.no_stores')}
                        </h3>
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            {t('landing.marketplace.no_stores_desc')}
                        </p>
                    </div>
                )}
            </div>
        </PlatformLayout>
    );
}
