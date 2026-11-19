import { useState, useEffect, useCallback, useRef } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { Loader2, Check, X, AlertCircle, ChevronDown, Globe, Store } from 'lucide-react';

function generateSlug(name) {
    return name
        .toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '')
        .slice(0, 63);
}

export default function StoreSetup({ currencies, timezones, countries, languages, storeCategories, defaultCurrency, defaultTimezone, defaultLanguage }) {
    const { errors } = usePage().props;
    const [stepErrors, setStepErrors] = useState({});
    const [slugStatus, setSlugStatus] = useState(null);
    const [checkingSlug, setCheckingSlug] = useState(false);
    const [advancedOpen, setAdvancedOpen] = useState(false);
    const slugManuallyEdited = useRef(false);
    const debounceTimer = useRef(null);

    const { data, setData, post, processing } = useForm({
        store_name: '',
        store_slug: '',
        business_email: '',
        phone: '',
        language: defaultLanguage || 'en',
        currency: defaultCurrency || 'MMK',
        timezone: defaultTimezone || 'Asia/Yangon',
        country: 'MM',
        theme_color: '#6366F1',
        description: '',
        store_category_id: '',
    });

    const checkSlug = useCallback((slug) => {
        if (debounceTimer.current) clearTimeout(debounceTimer.current);
        if (!slug || slug.length < 3) {
            setSlugStatus(null);
            return;
        }
        debounceTimer.current = setTimeout(async () => {
            setCheckingSlug(true);
            try {
                const response = await fetch(`/onboarding/check-slug?slug=${encodeURIComponent(slug)}`);
                const result = await response.json();
                setSlugStatus(result.available ? 'available' : 'taken');
            } catch {
                setSlugStatus(null);
            } finally {
                setCheckingSlug(false);
            }
        }, 400);
    }, []);

    useEffect(() => {
        return () => { if (debounceTimer.current) clearTimeout(debounceTimer.current); };
    }, []);

    const handleNameChange = (value) => {
        setData('store_name', value);
        if (!slugManuallyEdited.current) {
            const slug = generateSlug(value);
            setData('store_slug', slug);
            checkSlug(slug);
        }
    };

    const handleSlugChange = (value) => {
        slugManuallyEdited.current = true;
        const cleaned = value.toLowerCase().replace(/[^a-z0-9-]/g, '');
        setData('store_slug', cleaned);
        checkSlug(cleaned);
    };

    const handleSubmit = () => {
        post('/onboarding/store-setup', {
            onError: (serverErrors) => {
                setStepErrors(serverErrors || {});
            },
        });
    };

    const displayErrors = { ...stepErrors, ...errors };
    const categoryName = storeCategories?.find((c) => String(c.id) === String(data.store_category_id))?.name;

    return (
        <div className="min-h-screen bg-gray-50 dark:bg-gray-950 py-6 px-4 sm:px-6 lg:px-8">
            <div className="w-full max-w-7xl mx-auto">
                <div className="mb-6">
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Set Up Your Store</h1>
                    <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">Create your store in one step. Preview updates live as you type.</p>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-5 gap-6">
                    {/* Form column */}
                    <div className="lg:col-span-3 space-y-4">
                        <div className="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-sm px-5 py-5 sm:px-6">
                            <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100 mb-3">Store Details</h2>
                            <div className="space-y-3">
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Store Name *</label>
                                        <input type="text" value={data.store_name} onChange={(e) => handleNameChange(e.target.value)}
                                            className="w-full rounded-lg border border-gray-300 dark:border-gray-700 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500" placeholder="My Awesome Store" />
                                        {displayErrors.store_name && <p className="text-xs text-red-500 mt-1">{displayErrors.store_name}</p>}
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Store URL *</label>
                                        <div className="flex items-center gap-2">
                                            <span className="text-sm text-gray-400">/store/</span>
                                            <input type="text" value={data.store_slug} onChange={(e) => handleSlugChange(e.target.value)}
                                                className="flex-1 rounded-lg border border-gray-300 dark:border-gray-700 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500" placeholder="my-awesome-store" />
                                            {checkingSlug && <Loader2 className="w-4 h-4 animate-spin text-gray-400" />}
                                            {!checkingSlug && slugStatus === 'available' && <Check className="w-4 h-4 text-green-500" />}
                                            {!checkingSlug && slugStatus === 'taken' && <X className="w-4 h-4 text-red-500" />}
                                        </div>
                                        {displayErrors.store_slug && <p className="text-xs text-red-500 mt-1">{displayErrors.store_slug}</p>}
                                        {slugStatus === 'taken' && <p className="text-xs text-red-500 mt-1">This URL is already taken.</p>}
                                    </div>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Business Email *</label>
                                    <input type="email" value={data.business_email} onChange={(e) => setData('business_email', e.target.value)}
                                        className="w-full rounded-lg border border-gray-300 dark:border-gray-700 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500" placeholder="business@example.com" />
                                    {displayErrors.business_email && <p className="text-xs text-red-500 mt-1">{displayErrors.business_email}</p>}
                                </div>

                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone</label>
                                        <input type="text" value={data.phone} onChange={(e) => setData('phone', e.target.value)}
                                            className="w-full rounded-lg border border-gray-300 dark:border-gray-700 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500" placeholder="09xxxxxxxxx" />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Store Category</label>
                                        <select value={data.store_category_id} onChange={(e) => setData('store_category_id', e.target.value)}
                                            className="w-full rounded-lg border border-gray-300 dark:border-gray-700 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500">
                                            <option value="">Select category</option>
                                            {storeCategories?.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                                        </select>
                                    </div>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description (optional)</label>
                                    <textarea value={data.description} onChange={(e) => setData('description', e.target.value)} rows={2}
                                        className="w-full rounded-lg border border-gray-300 dark:border-gray-700 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500" placeholder="Brief description of your store" />
                                </div>
                            </div>

                            {/* Regional Settings */}
                            <div className="border border-gray-200 dark:border-gray-700 rounded-lg mt-4">
                                <button type="button" onClick={() => setAdvancedOpen(!advancedOpen)}
                                    className="w-full flex items-center justify-between px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    <span className="flex items-center gap-2"><Globe className="w-4 h-4" /> Regional Settings</span>
                                    <ChevronDown className={`w-4 h-4 transition-transform ${advancedOpen ? 'rotate-180' : ''}`} />
                                </button>
                                {advancedOpen && (
                                    <div className="px-4 pb-3 space-y-3 border-t border-gray-200 dark:border-gray-700 pt-3">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Country</label>
                                            <select value={data.country} onChange={(e) => setData('country', e.target.value)}
                                                className="w-full rounded-lg border border-gray-300 dark:border-gray-700 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500">
                                                {countries?.map((c) => <option key={c.code} value={c.code}>{c.name}</option>)}
                                            </select>
                                        </div>
                                        <div className="grid grid-cols-3 gap-3">
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Language</label>
                                                <select value={data.language} onChange={(e) => setData('language', e.target.value)}
                                                    className="w-full rounded-lg border border-gray-300 dark:border-gray-700 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500">
                                                    {languages?.map((l) => <option key={l.code} value={l.code}>{l.name}</option>)}
                                                </select>
                                            </div>
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Currency</label>
                                                <select value={data.currency} onChange={(e) => setData('currency', e.target.value)}
                                                    className="w-full rounded-lg border border-gray-300 dark:border-gray-700 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500">
                                                    {currencies?.map((c) => <option key={c.code} value={c.code}>{c.code}</option>)}
                                                </select>
                                            </div>
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Timezone</label>
                                                <select value={data.timezone} onChange={(e) => setData('timezone', e.target.value)}
                                                    className="w-full rounded-lg border border-gray-300 dark:border-gray-700 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500">
                                                    {timezones?.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </div>

                            {displayErrors.store_slug && <p className="text-xs text-red-500 mt-1">{displayErrors.store_slug}</p>}
                            {displayErrors.store_name && <p className="text-xs text-red-500 mt-1">{displayErrors.store_name}</p>}
                            {displayErrors.business_email && <p className="text-xs text-red-500 mt-1">{displayErrors.business_email}</p>}
                        </div>
                    </div>

                    {/* Preview column */}
                    <div className="lg:col-span-2">
                        <div className="lg:sticky lg:top-6 rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-sm px-5 py-5">
                            <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100 mb-3 flex items-center gap-2">
                                <Store className="w-4 h-4" /> Live Preview
                            </h2>
                            <div className="space-y-2 text-sm">
                                <div><span className="text-gray-500">Store Name:</span> <span className="font-medium">{data.store_name || 'Your Store Name'}</span></div>
                                <div><span className="text-gray-500">Store URL:</span> <span className="font-medium">/store/{data.store_slug || 'your-store-slug'}</span></div>
                                <div><span className="text-gray-500">Business Email:</span> <span className="font-medium">{data.business_email || 'business@example.com'}</span></div>
                                {data.phone && <div><span className="text-gray-500">Phone:</span> <span className="font-medium">{data.phone}</span></div>}
                                {categoryName && <div><span className="text-gray-500">Category:</span> <span className="font-medium">{categoryName}</span></div>}
                                <div><span className="text-gray-500">Country:</span> <span className="font-medium">{data.country}</span></div>
                                <div><span className="text-gray-500">Language:</span> <span className="font-medium">{data.language}</span></div>
                                <div><span className="text-gray-500">Currency:</span> <span className="font-medium">{data.currency}</span></div>
                                <div><span className="text-gray-500">Timezone:</span> <span className="font-medium">{data.timezone}</span></div>
                                {data.description && <div><span className="text-gray-500">Description:</span> <span className="font-medium">{data.description}</span></div>}
                                <div><span className="text-gray-500">Theme Color:</span> <span className="inline-block w-4 h-4 rounded align-middle ml-1" style={{ backgroundColor: data.theme_color }} /></div>
                            </div>

                            <button type="button" onClick={handleSubmit} disabled={processing}
                                className="w-full mt-4 py-2.5 bg-indigo-600 text-white rounded-lg font-semibold text-sm hover:bg-indigo-700 transition-colors disabled:opacity-50">
                                {processing ? 'Creating...' : 'Create Store'}
                            </button>

                            <p className="text-xs text-gray-400 mt-3 text-center">Store creation uses the existing backend logic. Branding can be customized later in the Dashboard.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
