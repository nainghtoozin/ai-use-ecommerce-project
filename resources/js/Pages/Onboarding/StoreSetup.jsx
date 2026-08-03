import { useState, useEffect, useCallback, useRef } from 'react';
import { Head, useForm, usePage, router } from '@inertiajs/react';
import PlatformGuestLayout from '@/Layouts/PlatformGuestLayout';
import {
    CheckCircle2,
    Store,
    Globe,
    Palette,
    ClipboardCheck,
    ArrowRight,
    ArrowLeft,
    Loader2,
    Check,
    X,
    AlertCircle,
} from 'lucide-react';

const STEPS = [
    { key: 'info', label: 'Store Information', icon: Store },
    { key: 'business', label: 'Business Settings', icon: Globe },
    { key: 'branding', label: 'Branding', icon: Palette },
    { key: 'review', label: 'Review & Create', icon: ClipboardCheck },
];

function generateSlug(name) {
    return name
        .toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '')
        .slice(0, 63);
}

function StepIndicator({ currentStep }) {
    return (
        <nav aria-label="Progress" className="mb-8">
            <ol className="flex items-center justify-between">
                {STEPS.map((step, i) => {
                    const isCompleted = i < currentStep;
                    const isCurrent = i === currentStep;
                    const Icon = step.icon;

                    return (
                        <li key={step.key} className="flex-1 flex flex-col items-center relative">
                            {i > 0 && (
                                <div className={`absolute top-4 right-1/2 w-full h-0.5 -translate-y-1/2 ${
                                    isCompleted ? 'bg-indigo-500' : 'bg-gray-200 dark:bg-gray-700'
                                }`} style={{ left: '-50%', width: '100%', zIndex: 0 }} />
                            )}
                            <div className="relative z-10 flex flex-col items-center">
                                <div className={`w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition-colors ${
                                    isCompleted
                                        ? 'bg-indigo-600 text-white'
                                        : isCurrent
                                            ? 'bg-indigo-600 text-white ring-4 ring-indigo-100 dark:ring-indigo-900/30'
                                            : 'bg-gray-200 dark:bg-gray-700 text-gray-500 dark:text-gray-400'
                                }`}>
                                    {isCompleted ? <Check className="w-4 h-4" /> : i + 1}
                                </div>
                                <span className={`mt-2 text-xs font-medium text-center hidden sm:block ${
                                    isCurrent
                                        ? 'text-indigo-600 dark:text-indigo-400'
                                        : isCompleted
                                            ? 'text-indigo-600 dark:text-indigo-400'
                                            : 'text-gray-400 dark:text-gray-500'
                                }`}>
                                    {step.label}
                                </span>
                            </div>
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}

function FormField({ label, error, required, children, hint }) {
    return (
        <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                {label} {required && <span className="text-red-500">*</span>}
            </label>
            {children}
            {error && (
                <p className="mt-1 text-xs text-red-500 flex items-center gap-1">
                    <AlertCircle className="w-3 h-3" /> {error}
                </p>
            )}
            {hint && !error && (
                <p className="mt-1 text-xs text-gray-400 dark:text-gray-500">{hint}</p>
            )}
        </div>
    );
}

function StepStoreInfo({ data, setData, errors, currencies, timezones, countries, languages }) {
    const [slugStatus, setSlugStatus] = useState(null);
    const [checkingSlug, setCheckingSlug] = useState(false);
    const slugManuallyEdited = useRef(false);
    const debounceTimer = useRef(null);

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
        }, 500);
    }, []);

    useEffect(() => {
        return () => {
            if (debounceTimer.current) clearTimeout(debounceTimer.current);
        };
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

    return (
        <div className="space-y-5">
            <div>
                <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Store Information</h2>
                <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">Tell us about your store.</p>
            </div>

            <FormField label="Store Name" required error={errors.store_name}>
                <input
                    type="text"
                    value={data.store_name}
                    onChange={(e) => handleNameChange(e.target.value)}
                    className="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    placeholder="My Awesome Store"
                    maxLength={255}
                />
            </FormField>

            <FormField
                label="Store URL"
                required
                error={errors.store_slug}
                hint="Only lowercase letters, numbers, and hyphens. Min 3 characters."
            >
                <div className="relative">
                    <div className="flex items-center">
                        <span className="text-sm text-gray-400 dark:text-gray-500 whitespace-nowrap mr-1">/store/</span>
                        <input
                            type="text"
                            value={data.store_slug}
                            onChange={(e) => handleSlugChange(e.target.value)}
                            className="flex-1 rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            placeholder="my-awesome-store"
                            maxLength={63}
                        />
                        <div className="ml-2 flex-shrink-0">
                            {checkingSlug && <Loader2 className="w-4 h-4 animate-spin text-gray-400" />}
                            {!checkingSlug && slugStatus === 'available' && <Check className="w-4 h-4 text-green-500" />}
                            {!checkingSlug && slugStatus === 'taken' && <X className="w-4 h-4 text-red-500" />}
                        </div>
                    </div>
                    {slugStatus === 'taken' && (
                        <p className="mt-1 text-xs text-red-500">This URL is already taken. Try another.</p>
                    )}
                    {slugStatus === 'available' && data.store_slug?.length >= 3 && (
                        <p className="mt-1 text-xs text-green-600">This URL is available!</p>
                    )}
                </div>
            </FormField>

            <FormField label="Business Email" required error={errors.business_email}>
                <input
                    type="email"
                    value={data.business_email}
                    onChange={(e) => setData('business_email', e.target.value)}
                    className="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    placeholder="contact@mystore.com"
                />
            </FormField>

            <FormField label="Phone" error={errors.phone}>
                <input
                    type="tel"
                    value={data.phone}
                    onChange={(e) => setData('phone', e.target.value)}
                    className="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    placeholder="+95 9 123 456 789"
                />
            </FormField>
        </div>
    );
}

function StepBusinessSettings({ data, setData, errors, currencies, timezones, countries, languages }) {
    return (
        <div className="space-y-5">
            <div>
                <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Business Settings</h2>
                <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">Configure your store's regional settings.</p>
            </div>

            <FormField label="Country" required error={errors.country}>
                <select
                    value={data.country}
                    onChange={(e) => setData('country', e.target.value)}
                    className="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                    <option value="">Select country</option>
                    {countries.map((c) => (
                        <option key={c.code} value={c.code}>{c.name}</option>
                    ))}
                </select>
            </FormField>

            <FormField label="Language" required error={errors.language}>
                <select
                    value={data.language}
                    onChange={(e) => setData('language', e.target.value)}
                    className="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                    {languages.map((l) => (
                        <option key={l.code} value={l.code}>{l.name}</option>
                    ))}
                </select>
            </FormField>

            <FormField label="Currency" required error={errors.currency}>
                <select
                    value={data.currency}
                    onChange={(e) => setData('currency', e.target.value)}
                    className="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                    {currencies.map((c) => (
                        <option key={c.code} value={c.code}>{c.code} — {c.name} ({c.symbol})</option>
                    ))}
                </select>
            </FormField>

            <FormField label="Timezone" required error={errors.timezone}>
                <select
                    value={data.timezone}
                    onChange={(e) => setData('timezone', e.target.value)}
                    className="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                    {timezones.map((t) => (
                        <option key={t.value} value={t.value}>{t.label}</option>
                    ))}
                </select>
            </FormField>
        </div>
    );
}

function StepBranding({ data, setData, errors }) {
    return (
        <div className="space-y-5">
            <div>
                <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Branding</h2>
                <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">Customize your store's appearance.</p>
            </div>

            <FormField label="Theme Color" error={errors.theme_color} hint="Used for buttons, links, and accents.">
                <div className="flex items-center gap-3">
                    <input
                        type="color"
                        value={data.theme_color}
                        onChange={(e) => setData('theme_color', e.target.value)}
                        className="w-12 h-10 rounded-lg border border-gray-300 dark:border-gray-700 cursor-pointer"
                    />
                    <input
                        type="text"
                        value={data.theme_color}
                        onChange={(e) => setData('theme_color', e.target.value)}
                        className="flex-1 rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 px-4 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        placeholder="#6366F1"
                        maxLength={9}
                    />
                </div>
            </FormField>

            <FormField label="Store Description" error={errors.description}>
                <textarea
                    value={data.description}
                    onChange={(e) => setData('description', e.target.value)}
                    className="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    placeholder="A brief description of what you sell..."
                    rows={4}
                    maxLength={1000}
                />
                <p className="mt-1 text-xs text-gray-400 text-right">{data.description?.length || 0}/1000</p>
            </FormField>
        </div>
    );
}

function StepReview({ data, currencies, timezones, countries, languages }) {
    const currency = currencies.find((c) => c.code === data.currency);
    const timezone = timezones.find((t) => t.value === data.timezone);
    const country = countries.find((c) => c.code === data.country);
    const language = languages.find((l) => l.code === data.language);

    const rows = [
        { label: 'Store Name', value: data.store_name },
        { label: 'Store URL', value: `/store/${data.store_slug}` },
        { label: 'Business Email', value: data.business_email },
        { label: 'Phone', value: data.phone || '—' },
        { label: 'Country', value: country?.name || data.country },
        { label: 'Language', value: language?.name || data.language },
        { label: 'Currency', value: currency ? `${currency.code} (${currency.symbol})` : data.currency },
        { label: 'Timezone', value: timezone?.label || data.timezone },
        { label: 'Theme Color', value: data.theme_color, isColor: true },
        { label: 'Description', value: data.description || '—' },
    ];

    return (
        <div className="space-y-5">
            <div>
                <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Review & Create</h2>
                <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">Verify your details before creating your store.</p>
            </div>

            <div className="bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-200 dark:border-gray-700 divide-y divide-gray-200 dark:divide-gray-700">
                {rows.map((row) => (
                    <div key={row.label} className="px-4 py-3 flex items-center justify-between gap-4">
                        <span className="text-sm text-gray-500 dark:text-gray-400 flex-shrink-0">{row.label}</span>
                        {row.isColor ? (
                            <div className="flex items-center gap-2">
                                <div className="w-5 h-5 rounded-full border border-gray-300 dark:border-gray-600" style={{ backgroundColor: row.value }} />
                                <span className="text-sm font-medium text-gray-900 dark:text-gray-100 font-mono">{row.value}</span>
                            </div>
                        ) : (
                            <span className="text-sm font-medium text-gray-900 dark:text-gray-100 text-right truncate">{row.value}</span>
                        )}
                    </div>
                ))}
            </div>
        </div>
    );
}

export default function StoreSetup() {
    const {
        currencies,
        timezones,
        countries,
        languages,
        defaultCurrency,
        defaultTimezone,
        defaultLanguage,
    } = usePage().props;

    const [step, setStep] = useState(0);
    const [stepErrors, setStepErrors] = useState({});
    const [slugAvailable, setSlugAvailable] = useState(null);

    const { data, setData, post, processing, errors } = useForm({
        store_name: '',
        store_slug: '',
        business_email: '',
        phone: '',
        language: defaultLanguage || 'en',
        currency: defaultCurrency || 'MMK',
        timezone: defaultTimezone || 'Asia/Yangon',
        country: '',
        theme_color: '#6366F1',
        description: '',
    });

    const validateStep = (stepIndex) => {
        const errs = {};

        if (stepIndex === 0) {
            if (!data.store_name.trim()) errs.store_name = 'Store name is required.';
            if (!data.store_slug.trim()) errs.store_slug = 'Store URL is required.';
            else if (data.store_slug.length < 3) errs.store_slug = 'Must be at least 3 characters.';
            else if (!/^[a-z0-9][a-z0-9\-]*[a-z0-9]$/.test(data.store_slug) && data.store_slug.length > 1) {
                errs.store_slug = 'Must start and end with a letter or number.';
            }
            if (!data.business_email.trim()) errs.business_email = 'Business email is required.';
            else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.business_email)) errs.business_email = 'Invalid email address.';
        }

        if (stepIndex === 1) {
            if (!data.country) errs.country = 'Country is required.';
            if (!data.language) errs.language = 'Language is required.';
            if (!data.currency) errs.currency = 'Currency is required.';
            if (!data.timezone) errs.timezone = 'Timezone is required.';
        }

        setStepErrors(errs);
        return Object.keys(errs).length === 0;
    };

    const handleNext = () => {
        if (validateStep(step)) {
            setStep((s) => Math.min(s + 1, STEPS.length - 1));
        }
    };

    const handlePrev = () => {
        setStep((s) => Math.max(s - 1, 0));
    };

    const handleSubmit = () => {
        post('/onboarding/store-setup', {
            onError: (serverErrors) => {
                if (serverErrors.slug) {
                    setStep(0);
                    setStepErrors({ store_slug: serverErrors.slug });
                }
            },
        });
    };

    const displayErrors = { ...stepErrors, ...errors };

    return (
        <PlatformGuestLayout>
            <Head title="Set Up Your Store" />

            <div className="mb-6 text-center">
                <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    Set Up Your Store
                </h1>
                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Step {step + 1} of {STEPS.length}
                </p>
            </div>

            <StepIndicator currentStep={step} />

            <div className="min-h-[320px]">
                {step === 0 && (
                    <StepStoreInfo
                        data={data}
                        setData={setData}
                        errors={displayErrors}
                        currencies={currencies}
                        timezones={timezones}
                        countries={countries}
                        languages={languages}
                    />
                )}
                {step === 1 && (
                    <StepBusinessSettings
                        data={data}
                        setData={setData}
                        errors={displayErrors}
                        currencies={currencies}
                        timezones={timezones}
                        countries={countries}
                        languages={languages}
                    />
                )}
                {step === 2 && (
                    <StepBranding
                        data={data}
                        setData={setData}
                        errors={displayErrors}
                    />
                )}
                {step === 3 && (
                    <StepReview
                        data={data}
                        currencies={currencies}
                        timezones={timezones}
                        countries={countries}
                        languages={languages}
                    />
                )}
            </div>

            <div className="flex items-center justify-between mt-8 pt-5 border-t border-gray-200 dark:border-gray-700">
                {step > 0 ? (
                    <button
                        type="button"
                        onClick={handlePrev}
                        className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                    >
                        <ArrowLeft className="w-4 h-4" /> Previous
                    </button>
                ) : (
                    <div />
                )}

                {step < STEPS.length - 1 ? (
                    <button
                        type="button"
                        onClick={handleNext}
                        className="inline-flex items-center gap-2 px-6 py-2.5 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-colors"
                    >
                        Next <ArrowRight className="w-4 h-4" />
                    </button>
                ) : (
                    <button
                        type="button"
                        onClick={handleSubmit}
                        disabled={processing}
                        className="inline-flex items-center gap-2 px-6 py-2.5 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {processing ? (
                            <>
                                <Loader2 className="w-4 h-4 animate-spin" />
                                Creating store...
                            </>
                        ) : (
                            <>
                                <CheckCircle2 className="w-4 h-4" />
                                Create Store
                            </>
                        )}
                    </button>
                )}
            </div>
        </PlatformGuestLayout>
    );
}
