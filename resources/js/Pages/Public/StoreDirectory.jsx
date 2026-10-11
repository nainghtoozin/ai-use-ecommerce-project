import { Head, Link, router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import PlatformLayout from '@/Layouts/PlatformLayout';
import {
    Search,
    Package,
    X,
    Loader2,
    Store,
    AlertCircle,
    ArrowLeft,
    ArrowRight,
    ChevronDown,
    SlidersHorizontal,
} from 'lucide-react';
import { useTranslation } from '@/Utils/useTranslation';

const SEARCH_DEBOUNCE_MS = 300;

function readFilters(filters = {}) {
    return {
        search: filters.search || '',
        categoryId: filters.category_id !== null && filters.category_id !== undefined && filters.category_id !== ''
            ? String(filters.category_id)
            : '',
        sort: filters.sort || 'newest',
    };
}

export default function StoreDirectory() {
    const { stores, categories, filters } = usePage().props;
    const { t } = useTranslation();

    const initial = useMemo(() => readFilters(filters), []);

    const [search, setSearch] = useState(initial.search);
    const [categoryId, setCategoryId] = useState(initial.categoryId);
    const [sort, setSort] = useState(initial.sort);
    const [filtersOpen, setFiltersOpen] = useState(false);
    const [isLoading, setIsLoading] = useState(false);
    const [hasError, setHasError] = useState(false);

    const applied = useRef(initial);
    const latest = useRef(initial);
    const requestId = useRef(0);

    useEffect(() => {
        latest.current = { search, categoryId, sort };
    });

    const applyFilters = useCallback((next, { replace = false } = {}) => {
        const params = {};
        if (next.search) params.search = next.search;
        if (next.categoryId) params.category_id = next.categoryId;
        if (next.sort && next.sort !== 'newest') params.sort = next.sort;

        applied.current = {
            search: next.search || '',
            categoryId: next.categoryId ? String(next.categoryId) : '',
            sort: next.sort || 'newest',
        };

        const id = ++requestId.current;

        router.get('/marketplace', params, {
            preserveState: true,
            preserveScroll: true,
            replace,
            only: ['stores', 'filters'],
            onStart: () => {
                setIsLoading(true);
                setHasError(false);
            },
            onFinish: () => {
                if (id === requestId.current) setIsLoading(false);
            },
            onError: () => {
                if (id === requestId.current) {
                    setIsLoading(false);
                    setHasError(true);
                }
            },
        });
    }, []);

    useEffect(() => {
        if (search === applied.current.search) return;

        const handle = setTimeout(() => {
            applyFilters({ ...latest.current, search }, { replace: true });
        }, SEARCH_DEBOUNCE_MS);

        return () => clearTimeout(handle);
    }, [search, applyFilters]);

    useEffect(() => {
        const server = readFilters(filters);
        const committed = applied.current;

        if (
            server.search === committed.search
            && server.categoryId === committed.categoryId
            && server.sort === committed.sort
        ) {
            return;
        }

        applied.current = server;
        setSearch(server.search);
        setCategoryId(server.categoryId);
        setSort(server.sort);
    }, [filters?.search, filters?.category_id, filters?.sort]);

    const handleCategory = (value) => {
        if (value === categoryId) return;
        setCategoryId(value);
        applyFilters({ search, categoryId: value, sort });
    };

    const handleSort = (value) => {
        if (value === sort) return;
        setSort(value);
        applyFilters({ search, categoryId, sort: value });
    };

    const clearFilters = () => {
        setSearch('');
        setCategoryId('');
        setSort('newest');
        applyFilters({ search: '', categoryId: '', sort: 'newest' });
    };

    const activeFilterCount = (categoryId !== '' ? 1 : 0) + (sort !== 'newest' ? 1 : 0);
    const hasActiveFilters = Boolean(search) || activeFilterCount > 0;
    const storeList = stores?.data || [];
    const total = stores?.total ?? storeList.length;

    const selectedCategoryName = (categories || []).find((c) => String(c.id) === categoryId)?.name;
    const sortLabels = {
        name_az: t('landing.marketplace.sort_name_az'),
        name_za: t('landing.marketplace.sort_name_za'),
    };
    const filterSummary = [selectedCategoryName, sort !== 'newest' ? sortLabels[sort] : null]
        .filter(Boolean)
        .join(' · ');

    return (
        <PlatformLayout>
            <Head title={t('landing.marketplace.title')} />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-6">
                <div className="mb-4">
                    <Link
                        href="/"
                        className="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 mb-2 transition-colors"
                    >
                        <ArrowLeft className="w-4 h-4" aria-hidden="true" />
                        {t('landing.marketplace.back_to_home')}
                    </Link>
                    <h1 className="text-xl sm:text-2xl font-bold tracking-tight text-gray-900 dark:text-gray-100">
                        {t('landing.marketplace.heading')}
                    </h1>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400 max-w-2xl">
                        {t('landing.marketplace.intro')}
                    </p>
                </div>

                <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm p-2.5 sm:p-3 mb-4">
                    <div className="flex flex-col sm:flex-row sm:items-center gap-2.5">
                        <div className="relative flex-1 min-w-0">
                            <label htmlFor="marketplace-search" className="sr-only">
                                {t('landing.marketplace.search_label')}
                            </label>
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" aria-hidden="true" />
                            <input
                                id="marketplace-search"
                                type="search"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder={t('landing.marketplace.search_placeholder')}
                                autoComplete="off"
                                className="w-full pl-9 pr-9 py-2 text-sm border border-gray-300 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            />
                            {isLoading && (
                                <Loader2
                                    className="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 animate-spin motion-reduce:animate-none"
                                    aria-hidden="true"
                                />
                            )}
                        </div>

                        <button
                            type="button"
                            onClick={() => setFiltersOpen((v) => !v)}
                            aria-expanded={filtersOpen}
                            aria-controls="marketplace-filter-panel"
                            className="sm:hidden inline-flex items-center justify-center gap-2 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-800 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors"
                        >
                            <SlidersHorizontal className="w-4 h-4" aria-hidden="true" />
                            {t('landing.marketplace.filters')}
                            {activeFilterCount > 0 && (
                                <span className="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full bg-blue-600 text-white text-[11px] font-semibold">
                                    {activeFilterCount}
                                </span>
                            )}
                            <ChevronDown
                                className={`w-4 h-4 transition-transform duration-200 motion-reduce:transition-none ${filtersOpen ? 'rotate-180' : ''}`}
                                aria-hidden="true"
                            />
                        </button>

                        <div
                            id="marketplace-filter-panel"
                            className={`${filtersOpen ? 'flex' : 'hidden'} sm:flex flex-col sm:flex-row sm:items-center gap-2.5`}
                        >
                            <div className="relative sm:w-48">
                                <label htmlFor="marketplace-category" className="sr-only">
                                    {t('landing.marketplace.category_label')}
                                </label>
                                <select
                                    id="marketplace-category"
                                    value={categoryId}
                                    onChange={(e) => handleCategory(e.target.value)}
                                    className="w-full appearance-none px-3 py-2 pr-9 text-sm border border-gray-300 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                >
                                    <option value="">{t('landing.marketplace.all_categories')}</option>
                                    {(categories || []).map((cat) => (
                                        <option key={cat.id} value={String(cat.id)}>{cat.name}</option>
                                    ))}
                                </select>
                                <ChevronDown className="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" aria-hidden="true" />
                            </div>

                            <div className="relative sm:w-40">
                                <label htmlFor="marketplace-sort" className="sr-only">
                                    {t('landing.marketplace.sort_label')}
                                </label>
                                <select
                                    id="marketplace-sort"
                                    value={sort}
                                    onChange={(e) => handleSort(e.target.value)}
                                    className="w-full appearance-none px-3 py-2 pr-9 text-sm border border-gray-300 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                >
                                    <option value="newest">{t('landing.marketplace.sort_newest')}</option>
                                    <option value="name_az">{t('landing.marketplace.sort_name_az')}</option>
                                    <option value="name_za">{t('landing.marketplace.sort_name_za')}</option>
                                </select>
                                <ChevronDown className="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" aria-hidden="true" />
                            </div>

                            {activeFilterCount > 0 && (
                                <button
                                    type="button"
                                    onClick={clearFilters}
                                    className="inline-flex items-center justify-center gap-1.5 px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-300 transition-colors whitespace-nowrap"
                                >
                                    <X className="w-4 h-4" aria-hidden="true" />
                                    {t('landing.marketplace.clear_filters')}
                                </button>
                            )}
                        </div>
                    </div>

                    {!filtersOpen && filterSummary && (
                        <p className="sm:hidden mt-2 text-xs text-gray-500 dark:text-gray-400 truncate">
                            {filterSummary}
                        </p>
                    )}
                </div>

                <div className="flex items-center justify-between mb-2.5 min-h-[18px]">
                    <p className="text-sm text-gray-500 dark:text-gray-400" aria-live="polite">
                        {t('landing.marketplace.stores_count', { count: total })}
                    </p>
                    {isLoading && (
                        <span className="inline-flex items-center gap-1.5 text-xs text-gray-400">
                            <Loader2 className="w-3.5 h-3.5 animate-spin motion-reduce:animate-none" aria-hidden="true" />
                            {t('landing.marketplace.updating')}
                        </span>
                    )}
                </div>

                {hasError ? (
                    <div className="text-center py-10 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800">
                        <AlertCircle className="w-9 h-9 text-red-400 mx-auto mb-2.5" aria-hidden="true" />
                        <p className="text-sm text-gray-600 dark:text-gray-400 mb-3">
                            {t('landing.marketplace.load_error')}
                        </p>
                        <button
                            type="button"
                            onClick={() => applyFilters({ search, categoryId, sort })}
                            className="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
                        >
                            {t('landing.marketplace.retry')}
                        </button>
                    </div>
                ) : storeList.length > 0 ? (
                    <>
                        <div
                            className={`grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5 sm:gap-3 transition-opacity duration-200 motion-reduce:transition-none ${
                                isLoading ? 'opacity-60' : 'opacity-100'
                            }`}
                        >
                            {storeList.map((store) => (
                                <Link
                                    key={store.id}
                                    href={store.store_url}
                                    className="group flex items-center gap-3 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 px-3.5 py-3 hover:shadow-sm hover:border-gray-300 dark:hover:border-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-150 motion-reduce:transition-none"
                                >
                                    {store.logo_url ? (
                                        <img
                                            src={store.logo_url}
                                            alt=""
                                            className="w-10 h-10 rounded-lg object-cover border border-gray-200 dark:border-gray-700 flex-shrink-0"
                                            loading="lazy"
                                        />
                                    ) : (
                                        <div
                                            className="w-10 h-10 rounded-lg bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white flex-shrink-0"
                                            aria-hidden="true"
                                        >
                                            <Store className="w-5 h-5" />
                                        </div>
                                    )}

                                    <div className="min-w-0 flex-1">
                                        <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                                            {store.name}
                                        </h3>
                                        <div className="mt-0.5 flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                                            <Package className="w-3.5 h-3.5 flex-shrink-0" aria-hidden="true" />
                                            <span className="truncate">
                                                {t('landing.marketplace.products_count', {
                                                    count: Number.isFinite(Number(store.products_count)) ? Number(store.products_count) : 0,
                                                })}
                                            </span>
                                        </div>
                                    </div>

                                    <span className="flex-shrink-0 inline-flex items-center gap-1 text-sm font-medium text-blue-600 dark:text-blue-400 group-hover:underline">
                                        <span className="hidden sm:inline">{t('landing.marketplace.visit_store')}</span>
                                        <ArrowRight className="w-4 h-4" aria-hidden="true" />
                                    </span>
                                </Link>
                            ))}
                        </div>

                        {stores?.links && stores.links.length > 3 && (
                            <div className="flex flex-wrap justify-center gap-1.5 mt-4">
                                {stores.links.map((link, index) =>
                                    link.url ? (
                                        <Link
                                            key={index}
                                            href={link.url}
                                            preserveScroll
                                            className="px-3 py-1.5 text-sm rounded-lg transition-colors bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ) : (
                                        <span
                                            key={index}
                                            className="px-3 py-1.5 text-sm rounded-lg text-gray-400 cursor-not-allowed"
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    )
                                )}
                            </div>
                        )}
                    </>
                ) : (
                    <div className="text-center py-12 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800">
                        <div className="w-11 h-11 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center mx-auto mb-2.5">
                            <Package className="w-5 h-5 text-gray-400" aria-hidden="true" />
                        </div>
                        <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-1">
                            {t('landing.marketplace.no_stores')}
                        </h3>
                        <p className="text-sm text-gray-500 dark:text-gray-400 mb-3">
                            {t('landing.marketplace.no_stores_desc')}
                        </p>
                        {hasActiveFilters && (
                            <button
                                type="button"
                                onClick={clearFilters}
                                className="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
                            >
                                <X className="w-4 h-4" aria-hidden="true" />
                                {t('landing.marketplace.clear_filters')}
                            </button>
                        )}
                    </div>
                )}
            </div>
        </PlatformLayout>
    );
}
