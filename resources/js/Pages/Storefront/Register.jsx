import { useEffect, useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import axios from 'axios';
import { assetUrl } from '@/Utils/helpers';

const inputClass = 'block w-full min-w-0 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3.5 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 transition-colors focus:outline-none focus:ring-2 focus:ring-[var(--theme-color)]/30 focus:border-[var(--theme-color)]';
const inputHeight = 'h-11';
const labelClass = 'flex items-baseline justify-between text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5';

function RequiredMark() {
    return <span className="text-red-500" aria-hidden="true">*</span>;
}

function OptionalText() {
    return <span className="text-xs font-normal text-gray-400 dark:text-gray-500">Optional</span>;
}

function FieldError({ message }) {
    if (!message) return null;
    return <p role="alert" className="text-xs text-red-600 dark:text-red-400 mt-1">{message}</p>;
}

function EyeButton({ visible, onToggle, label }) {
    return (
        <button
            type="button"
            onClick={onToggle}
            aria-label={label}
            aria-pressed={visible}
            tabIndex={0}
            className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
        >
            {visible ? (
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.812 9.812 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
            ) : (
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
            )}
        </button>
    );
}

function SectionHeading({ children }) {
    return (
        <h3 className="text-xs font-semibold uppercase tracking-widest text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-800 pb-2">
            {children}
        </h3>
    );
}

export default function StorefrontRegister() {
    const { tenant, storefront, errors, cities = [] } = usePage().props;
    const { data, setData, post, processing, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        phone: '',
        address: '',
        city_id: '',
        township_id: '',
        postal_code: '',
    });
    const [townships, setTownships] = useState([]);
    const [townshipsLoading, setTownshipsLoading] = useState(false);
    const [showPassword, setShowPassword] = useState(false);
    const [showConfirm, setShowConfirm] = useState(false);

    const storeName = storefront?.identity?.site_title || tenant.name || 'Store';
    const logoUrl = assetUrl(storefront?.identity?.logo_url);

    useEffect(() => {
        return () => {
            reset('password', 'password_confirmation');
        };
    }, []);

    const fetchTownships = (cityId) => {
        if (!cityId) { setTownships([]); return; }
        setTownshipsLoading(true);
        axios.get(`/api/townships/${cityId}`)
            .then((r) => setTownships(r.data?.townships || []))
            .catch(() => setTownships([]))
            .finally(() => setTownshipsLoading(false));
    };

    const handleCityChange = (cityId) => {
        setData({ ...data, city_id: cityId, township_id: '', postal_code: '' });
        fetchTownships(cityId);
    };

    const handleTownshipChange = (townshipId) => {
        const selected = townships.find((t) => String(t.id) === String(townshipId));
        setData({ ...data, township_id: townshipId, postal_code: selected?.postal_code || '' });
    };

    const submit = (e) => {
        e.preventDefault();
        post(route('storefront.register', { store_slug: tenant.slug }));
    };

    return (
        <div
            className="min-h-screen bg-gray-100 dark:bg-gray-950"
            style={{ backgroundImage: 'radial-gradient(640px 320px at 50% -90px, color-mix(in srgb, var(--theme-color, #3B82F6) 10%, transparent), transparent)' }}
        >
            <Head title={`Register - ${storeName}`} />

            <div className="mx-auto w-full max-w-[760px] px-3 sm:px-6 pt-4 sm:pt-6 pb-8 sm:pb-10">
                <div className="flex items-center justify-center gap-2.5">
                    {logoUrl ? (
                        <img src={logoUrl} alt={storeName} className="h-8 w-auto rounded-lg" />
                    ) : (
                        <div
                            className="w-8 h-8 rounded-lg flex items-center justify-center text-white text-sm font-bold"
                            style={{ backgroundColor: 'var(--theme-color, #3B82F6)' }}
                            aria-hidden="true"
                        >
                            {storeName.trim().charAt(0).toUpperCase()}
                        </div>
                    )}
                    <span className="text-base font-bold text-gray-900 dark:text-gray-100">{tenant.name}</span>
                </div>

                <div className="mt-4 rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-[0_2px_16px_rgb(0_0_0/0.06)] px-4 py-5 sm:px-8 sm:py-7">
                    <h2 className="text-xl font-bold text-gray-900 dark:text-gray-100">
                        Create your account
                    </h2>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Create your account to shop faster and check out more easily.
                    </p>

                <form onSubmit={submit} noValidate className="mt-5">
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-x-8 gap-y-6">
                    <section aria-label="Account Information">
                    <SectionHeading>Account Information</SectionHeading>
                    <div className="mt-3 space-y-3">
                        <div>
                            <label htmlFor="name" className={labelClass}><span>Name <RequiredMark /></span></label>
                            <input
                                id="name" type="text" name="name" value={data.name}
                                className={`${inputClass} ${inputHeight}`} autoComplete="name" placeholder="Your full name"
                                onChange={(e) => setData('name', e.target.value)}
                                required aria-required="true"
                            />
                            <FieldError message={errors.name} />
                        </div>

                        <div>
                            <label htmlFor="email" className={labelClass}><span>Email <RequiredMark /></span></label>
                            <input
                                id="email" type="email" name="email" value={data.email}
                                className={`${inputClass} ${inputHeight}`} autoComplete="email" placeholder="you@example.com"
                                onChange={(e) => setData('email', e.target.value)}
                                required aria-required="true"
                            />
                            <FieldError message={errors.email} />
                        </div>

                        <div className="space-y-3">
                            <div>
                                <label htmlFor="password" className={labelClass}><span>Password <RequiredMark /></span></label>
                                <div className="relative">
                                    <input
                                        id="password" type={showPassword ? 'text' : 'password'} name="password" value={data.password}
                                        className={`${inputClass} ${inputHeight} pr-11`} autoComplete="new-password" placeholder="••••••••"
                                        onChange={(e) => setData('password', e.target.value)}
                                        required aria-required="true"
                                    />
                                    <EyeButton visible={showPassword} onToggle={() => setShowPassword((v) => !v)} label={showPassword ? 'Hide password' : 'Show password'} />
                                </div>
                                <FieldError message={errors.password} />
                            </div>

                            <div>
                                <label htmlFor="password_confirmation" className={labelClass}><span>Confirm Password <RequiredMark /></span></label>
                                <div className="relative">
                                    <input
                                        id="password_confirmation" type={showConfirm ? 'text' : 'password'} name="password_confirmation" value={data.password_confirmation}
                                        className={`${inputClass} ${inputHeight} pr-11`} autoComplete="new-password" placeholder="••••••••"
                                        onChange={(e) => setData('password_confirmation', e.target.value)}
                                        required aria-required="true"
                                    />
                                    <EyeButton visible={showConfirm} onToggle={() => setShowConfirm((v) => !v)} label={showConfirm ? 'Hide password confirmation' : 'Show password confirmation'} />
                                </div>
                                <FieldError message={errors.password_confirmation} />
                            </div>
                        </div>
                    </div>
                    </section>

                    <section aria-label="Delivery Information">
                    <SectionHeading>Delivery Information</SectionHeading>
                    <div className="mt-3 space-y-3">
                            <div>
                                <label htmlFor="phone" className={labelClass}><span>Phone Number <RequiredMark /></span></label>
                                <input
                                    id="phone" type="tel" name="phone" value={data.phone}
                                    className={`${inputClass} ${inputHeight}`} autoComplete="tel" placeholder="09xxxxxxxxx"
                                    onChange={(e) => setData('phone', e.target.value)}
                                    required aria-required="true"
                                />
                                <FieldError message={errors.phone} />
                            </div>

                        <div>
                            <label htmlFor="address" className={labelClass}><span>Delivery Address</span><OptionalText /></label>
                            <input
                                id="address" type="text" name="address" value={data.address}
                                className={`${inputClass} ${inputHeight}`} autoComplete="street-address" placeholder="Street, building, apartment"
                                onChange={(e) => setData('address', e.target.value)}
                            />
                            <FieldError message={errors.address} />
                        </div>

                        <div className="space-y-3">
                            <div>
                                <label htmlFor="city_id" className={labelClass}><span>City</span><OptionalText /></label>
                                <select
                                    id="city_id" name="city_id" value={data.city_id}
                                    className={`${inputClass} ${inputHeight} pr-9`}
                                    onChange={(e) => handleCityChange(e.target.value)}
                                >
                                    <option value="">Select city</option>
                                    {cities.map((city) => (
                                        <option key={city.id} value={city.id}>{city.name}</option>
                                    ))}
                                </select>
                                <FieldError message={errors.city_id} />
                            </div>

                            <div>
                                <label htmlFor="township_id" className={labelClass}><span>Township</span><OptionalText /></label>
                                <select
                                    id="township_id" name="township_id" value={data.township_id}
                                    disabled={!data.city_id || townshipsLoading}
                                    className={`${inputClass} ${inputHeight} pr-9 disabled:opacity-50 disabled:cursor-not-allowed`}
                                    onChange={(e) => handleTownshipChange(e.target.value)}
                                >
                                    <option value="">{townshipsLoading ? 'Loading townships...' : data.city_id ? 'Select township' : 'Select a city first'}</option>
                                    {townships.map((township) => (
                                        <option key={township.id} value={township.id}>{township.name}</option>
                                    ))}
                                </select>
                                <FieldError message={errors.township_id} />
                                {data.postal_code !== '' && (
                                    <p className="text-xs text-gray-500 dark:text-gray-400 mt-1.5" aria-live="polite">
                                        Postal Code: <span className="font-semibold text-gray-700 dark:text-gray-300">{data.postal_code}</span>
                                    </p>
                                )}
                            </div>
                        </div>

                        <p className="flex items-start gap-1.5 text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                            <svg className="w-3.5 h-3.5 shrink-0 mt-px" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            <span>Save your delivery information here to have it filled automatically at checkout. You can still edit it before placing an order.</span>
                        </p>
                    </div>
                    </section>
                    </div>

                    <div className="mt-6 pt-4 border-t border-gray-200 dark:border-gray-800 flex flex-col gap-3 md:flex-row md:items-center">
                        <div className="flex items-center gap-4 text-sm order-2 md:order-1">
                            <Link
                                href={route('storefront.index', { store_slug: tenant.slug })}
                                className="text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200 transition-colors"
                            >
                                Back to store
                            </Link>
                            <Link
                                href={route('storefront.login', { store_slug: tenant.slug })}
                                className="text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200 transition-colors"
                            >
                                Already registered?
                            </Link>
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="order-1 md:order-2 md:ml-auto inline-flex items-center justify-center gap-2 w-full md:w-auto px-8 h-11 text-white text-sm font-semibold rounded-lg shadow-sm transition-all hover:opacity-90 active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed"
                            style={{ backgroundColor: 'var(--theme-color, #1F2937)' }}
                        >
                            {processing ? (
                                <svg className="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" /><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                            ) : (
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" /></svg>
                            )}
                            {processing ? 'Creating account...' : 'Create Account'}
                        </button>
                    </div>
                </form>
                </div>
            </div>
        </div>
    );
}
