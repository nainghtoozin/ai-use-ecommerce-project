import { useState, useMemo } from 'react';
import { Head, Link, usePage, useForm } from '@inertiajs/react';
import { assetUrl } from '@/Utils/helpers';

export default function CreateStore() {
    const { appUrl, siteName, logoUrl } = usePage().props;
    const logo = assetUrl(logoUrl);

    const { data, setData, post, processing, errors: serverErrors, reset } = useForm({
        name: '',
        slug: '',
        description: '',
        domain: '',
        owner_name: '',
        owner_email: '',
        password: '',
        password_confirmation: '',
    });

    const [localErrors, setLocalErrors] = useState({});
    const [touched, setTouched] = useState({});

    const slugPattern = /^[a-z0-9-]+$/;

    const validateField = (field, value, currentPassword) => {
        let msg = '';
        switch (field) {
            case 'name':
                if (!value.trim()) msg = 'Store name is required.';
                break;
            case 'slug':
                if (!value.trim()) msg = 'Store slug is required.';
                else if (!slugPattern.test(value)) msg = 'Only lowercase letters, numbers, and hyphens allowed.';
                else if (value.length < 3) msg = 'Slug must be at least 3 characters.';
                break;
            case 'owner_name':
                if (!value.trim()) msg = 'Owner name is required.';
                break;
            case 'owner_email':
                if (!value.trim()) msg = 'Email is required.';
                else if (!/\S+@\S+\.\S+/.test(value)) msg = 'Invalid email format.';
                break;
            case 'password':
                if (!value) msg = 'Password is required.';
                else if (value.length < 8) msg = 'Password must be at least 8 characters.';
                break;
            case 'password_confirmation':
                if (value !== currentPassword) msg = 'Passwords do not match.';
                break;
        }
        setLocalErrors(prev => {
            const next = { ...prev };
            if (msg) next[field] = msg;
            else delete next[field];
            return next;
        });
    };

    const setField = (field, value) => {
        const currentPassword = field === 'password' ? value : data.password;
        setData(field, value);
        setTouched(prev => ({ ...prev, [field]: true }));
        validateField(field, value, currentPassword);
    };

    const generateSlug = (name) => {
        return name
            .toLowerCase()
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-')
            .replace(/^-+|-+$/g, '');
    };

    const handleNameChange = (e) => {
        const val = e.target.value;
        const currentPassword = data.password;
        setData('name', val);
        setTouched(prev => ({ ...prev, name: true }));
        validateField('name', val, currentPassword);
        if (!touched.slug) {
            const autoSlug = generateSlug(val);
            setData('slug', autoSlug);
            setTouched(prev => ({ ...prev, slug: false }));
            validateField('slug', autoSlug, currentPassword);
        }
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/create-store');
    };

    const storeUrl = useMemo(() => {
        const slug = data.slug || '{slug}';
        return `${appUrl.replace(/\/+$/, '')}/store/${slug}`;
    }, [data.slug, appUrl]);

    const allErrors = { ...localErrors, ...serverErrors };
    const hasLocalErrors = Object.keys(localErrors).length > 0;
    const canSubmit = data.name && data.slug && data.owner_name && data.owner_email && data.password && data.password_confirmation && !hasLocalErrors;

    return (
        <>
            <Head title="Create Your Store" />

            <div className="min-h-screen bg-gradient-to-br from-indigo-50 via-white to-purple-50">
                <div className="max-w-5xl mx-auto px-4 py-8">
                    <div className="flex items-center justify-between mb-10">
                        <Link href="/" className="flex items-center gap-3">
                            {logo && <img src={logo} alt={siteName} className="h-9 w-auto" />}
                            <span className="text-xl font-bold text-gray-900">{siteName}</span>
                        </Link>
                        <Link href="/login" className="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                            Sign In →
                        </Link>
                    </div>

                    <div className="text-center mb-10">
                        <h1 className="text-4xl font-extrabold text-gray-900 sm:text-5xl">
                            Launch Your Online Store
                        </h1>
                        <p className="mt-4 text-lg text-gray-600 max-w-2xl mx-auto">
                            Everything you need to start selling online. No credit card required.
                        </p>
                    </div>

                    <form onSubmit={handleSubmit}>
                        <div className="grid grid-cols-1 lg:grid-cols-5 gap-8">
                            <div className="lg:col-span-3 space-y-8">
                                <div className="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 sm:p-8">
                                    <h2 className="text-xl font-bold text-gray-900 mb-6 flex items-center gap-2">
                                        <span className="w-8 h-8 rounded-full bg-indigo-600 text-white text-sm flex items-center justify-center font-bold">1</span>
                                        Store Information
                                    </h2>

                                    <div className="space-y-5">
                                        <div>
                                            <label htmlFor="name" className="block text-sm font-semibold text-gray-700 mb-1">
                                                Store Name <span className="text-red-500">*</span>
                                            </label>
                                            <input
                                                id="name"
                                                type="text"
                                                value={data.name}
                                                onChange={handleNameChange}
                                                placeholder="My Awesome Store"
                                                className={`w-full rounded-lg border ${allErrors.name && touched.name ? 'border-red-300 ring-red-500' : 'border-gray-300'} px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500`}
                                            />
                                            {allErrors.name && touched.name && <p className="text-red-500 text-xs mt-1">{allErrors.name}</p>}
                                        </div>

                                        <div>
                                            <label htmlFor="slug" className="block text-sm font-semibold text-gray-700 mb-1">
                                                Store Slug <span className="text-red-500">*</span>
                                            </label>
                                            <input
                                                id="slug"
                                                type="text"
                                                value={data.slug}
                                                onChange={(e) => setField('slug', e.target.value)}
                                                placeholder="my-awesome-store"
                                                className={`w-full rounded-lg border ${allErrors.slug && touched.slug ? 'border-red-300 ring-red-500' : 'border-gray-300'} px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono`}
                                            />
                                            {allErrors.slug && touched.slug && <p className="text-red-500 text-xs mt-1">{allErrors.slug}</p>}
                                            <p className="text-gray-400 text-xs mt-1">
                                                {storeUrl}
                                            </p>
                                        </div>

                                        <div>
                                            <label htmlFor="description" className="block text-sm font-semibold text-gray-700 mb-1">
                                                Store Description
                                            </label>
                                            <textarea
                                                id="description"
                                                rows={3}
                                                value={data.description}
                                                onChange={(e) => setField('description', e.target.value)}
                                                placeholder="Tell customers what your store is about..."
                                                className="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div className="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 sm:p-8">
                                    <h2 className="text-xl font-bold text-gray-900 mb-6 flex items-center gap-2">
                                        <span className="w-8 h-8 rounded-full bg-indigo-600 text-white text-sm flex items-center justify-center font-bold">2</span>
                                        Domain
                                    </h2>

                                    <div>
                                        <label htmlFor="domain" className="block text-sm font-semibold text-gray-700 mb-1">
                                            Custom Domain <span className="text-gray-400 font-normal">(optional)</span>
                                        </label>
                                        <input
                                            id="domain"
                                            type="text"
                                            value={data.domain}
                                            onChange={(e) => setField('domain', e.target.value)}
                                            placeholder="mystore.com"
                                            className={`w-full rounded-lg border ${allErrors.domain ? 'border-red-300 ring-red-500' : 'border-gray-300'} px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500`}
                                        />
                                        {allErrors.domain && <p className="text-red-500 text-xs mt-1">{allErrors.domain}</p>}
                                        <p className="text-gray-400 text-xs mt-1">
                                            Leave empty to use your subdomain on {new URL(appUrl).hostname}
                                        </p>
                                    </div>
                                </div>

                                <div className="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 sm:p-8">
                                    <h2 className="text-xl font-bold text-gray-900 mb-6 flex items-center gap-2">
                                        <span className="w-8 h-8 rounded-full bg-indigo-600 text-white text-sm flex items-center justify-center font-bold">3</span>
                                        Owner Account
                                    </h2>

                                    <div className="space-y-5">
                                        <div>
                                            <label htmlFor="owner_name" className="block text-sm font-semibold text-gray-700 mb-1">
                                                Owner Name <span className="text-red-500">*</span>
                                            </label>
                                            <input
                                                id="owner_name"
                                                type="text"
                                                value={data.owner_name}
                                                onChange={(e) => setField('owner_name', e.target.value)}
                                                placeholder="John Doe"
                                                className={`w-full rounded-lg border ${allErrors.owner_name && touched.owner_name ? 'border-red-300 ring-red-500' : 'border-gray-300'} px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500`}
                                            />
                                            {allErrors.owner_name && touched.owner_name && <p className="text-red-500 text-xs mt-1">{allErrors.owner_name}</p>}
                                        </div>

                                        <div>
                                            <label htmlFor="owner_email" className="block text-sm font-semibold text-gray-700 mb-1">
                                                Owner Email <span className="text-red-500">*</span>
                                            </label>
                                            <input
                                                id="owner_email"
                                                type="email"
                                                value={data.owner_email}
                                                onChange={(e) => setField('owner_email', e.target.value)}
                                                placeholder="john@example.com"
                                                className={`w-full rounded-lg border ${allErrors.owner_email && touched.owner_email ? 'border-red-300 ring-red-500' : 'border-gray-300'} px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500`}
                                            />
                                            {allErrors.owner_email && touched.owner_email && <p className="text-red-500 text-xs mt-1">{allErrors.owner_email}</p>}
                                        </div>

                                        <div>
                                            <label htmlFor="password" className="block text-sm font-semibold text-gray-700 mb-1">
                                                Password <span className="text-red-500">*</span>
                                            </label>
                                            <input
                                                id="password"
                                                type="password"
                                                value={data.password}
                                                onChange={(e) => setField('password', e.target.value)}
                                                placeholder="Min. 8 characters"
                                                className={`w-full rounded-lg border ${allErrors.password && touched.password ? 'border-red-300 ring-red-500' : 'border-gray-300'} px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500`}
                                            />
                                            {allErrors.password && touched.password && <p className="text-red-500 text-xs mt-1">{allErrors.password}</p>}
                                        </div>

                                        <div>
                                            <label htmlFor="password_confirmation" className="block text-sm font-semibold text-gray-700 mb-1">
                                                Confirm Password <span className="text-red-500">*</span>
                                            </label>
                                            <input
                                                id="password_confirmation"
                                                type="password"
                                                value={data.password_confirmation}
                                                onChange={(e) => setField('password_confirmation', e.target.value)}
                                                placeholder="Repeat password"
                                                className={`w-full rounded-lg border ${allErrors.password_confirmation && touched.password_confirmation ? 'border-red-300 ring-red-500' : 'border-gray-300'} px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500`}
                                            />
                                            {allErrors.password_confirmation && touched.password_confirmation && <p className="text-red-500 text-xs mt-1">{allErrors.password_confirmation}</p>}
                                        </div>
                                    </div>
                                </div>

                                <div className="flex items-center gap-4 pt-2 pb-8">
                                    <button
                                        type="submit"
                                        disabled={!canSubmit || processing}
                                        className={`px-8 py-3 rounded-xl text-sm font-bold text-white transition-all duration-200 ${canSubmit && !processing
                                            ? 'bg-indigo-600 hover:bg-indigo-700 shadow-lg shadow-indigo-200 hover:shadow-xl hover:shadow-indigo-300 cursor-pointer'
                                            : 'bg-gray-300 cursor-not-allowed'
                                            }`}
                                    >
                                        {processing ? 'Creating Store...' : 'Create Your Store'}
                                    </button>
                                    <p className="text-xs text-gray-400">
                                        By clicking you agree to our Terms of Service.
                                    </p>
                                </div>
                            </div>

                            <div className="lg:col-span-2">
                                <div className="sticky top-8 space-y-6">
                                    <div className="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                                        <h3 className="text-sm font-bold text-gray-900 uppercase tracking-wider mb-4">
                                            Preview
                                        </h3>

                                        <div className="bg-gray-50 rounded-xl p-5 border border-gray-100 space-y-4">
                                            <div>
                                                <p className="text-xs font-semibold text-gray-500 uppercase tracking-wider">Store URL</p>
                                                <p className="text-sm font-mono text-indigo-600 break-all mt-1">
                                                    {storeUrl}
                                                </p>
                                            </div>

                                            <div className="border-t border-gray-200 pt-4">
                                                <p className="text-xs font-semibold text-gray-500 uppercase tracking-wider">Store Name</p>
                                                <p className="text-lg font-bold text-gray-900 mt-1">
                                                    {data.name || 'Your Store Name'}
                                                </p>
                                            </div>

                                            <div className="border-t border-gray-200 pt-4">
                                                <p className="text-xs font-semibold text-gray-500 uppercase tracking-wider">Slug</p>
                                                <p className="text-sm font-mono text-gray-700 mt-1">
                                                    {data.slug || '{slug}'}
                                                </p>
                                            </div>

                                            {data.description && (
                                                <div className="border-t border-gray-200 pt-4">
                                                    <p className="text-xs font-semibold text-gray-500 uppercase tracking-wider">Description</p>
                                                    <p className="text-sm text-gray-600 mt-1 line-clamp-3">
                                                        {data.description}
                                                    </p>
                                                </div>
                                            )}

                                            {data.domain && (
                                                <div className="border-t border-gray-200 pt-4">
                                                    <p className="text-xs font-semibold text-gray-500 uppercase tracking-wider">Custom Domain</p>
                                                    <p className="text-sm font-mono text-gray-700 mt-1">
                                                        {data.domain}
                                                    </p>
                                                </div>
                                            )}

                                            <div className="border-t border-gray-200 pt-4">
                                                <p className="text-xs font-semibold text-gray-500 uppercase tracking-wider">Owner</p>
                                                <p className="text-sm text-gray-700 mt-1">
                                                    {data.owner_name || '—'}
                                                </p>
                                                <p className="text-sm text-gray-500">
                                                    {data.owner_email || '—'}
                                                </p>
                                            </div>
                                        </div>

                                        <div className="mt-5 p-4 bg-indigo-50 rounded-xl border border-indigo-100">
                                            <div className="flex items-start gap-3">
                                                <div className="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0">
                                                    <svg className="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                </div>
                                                <p className="text-xs text-indigo-700">
                                                    Your store will be accessible immediately after creation. You can change these details later from your store settings.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <footer className="border-t border-gray-200 bg-white mt-8">
                    <div className="max-w-5xl mx-auto px-4 py-6 flex items-center justify-between text-sm text-gray-400">
                        <span>&copy; {new Date().getFullYear()} {siteName}. All rights reserved.</span>
                        <div className="flex gap-4">
                            <Link href="/client/privacy" className="hover:text-gray-600">Privacy</Link>
                            <Link href="/client/terms" className="hover:text-gray-600">Terms</Link>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
