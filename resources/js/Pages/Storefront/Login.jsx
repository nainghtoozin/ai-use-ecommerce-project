import { Head, Link, useForm, usePage } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';

export default function StorefrontLogin({ status, tenant }) {
    const { errors, storefront, flash } = usePage().props;
    const { data, setData, post, processing, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const customerStatus = flash?.customer_status;
    const isBlocked = customerStatus && (customerStatus.status === 'suspended' || customerStatus.status === 'banned');
    const isBanned = customerStatus?.status === 'banned';

    const submit = (e) => {
        e.preventDefault();
        post(route('storefront.login', { store_slug: tenant.slug }), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title={`Log in - ${storefront?.identity?.site_title || tenant.name}`} />

            <div className="mb-6 text-center">
                <h2 className="text-xl font-semibold text-gray-900 dark:text-gray-100">
                    Log in to {tenant.name}
                </h2>
                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Sign in to your account to continue shopping
                </p>
            </div>

            {status && (
                <div className="mb-4 font-medium text-sm text-green-600">
                    {status}
                </div>
            )}

            {isBlocked && (
                <div
                    role="alert"
                    className={`mb-4 flex gap-2.5 rounded-xl border p-3 ${
                        isBanned
                            ? 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950/40'
                            : 'border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/40'
                    }`}
                >
                    <span
                        className={`flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full ${
                            isBanned
                                ? 'bg-red-100 text-red-600 dark:bg-red-900 dark:text-red-300'
                                : 'bg-amber-100 text-amber-600 dark:bg-amber-900 dark:text-amber-300'
                        }`}
                    >
                        {isBanned ? (
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="9" strokeWidth={2} />
                                <line x1="5.5" y1="5.5" x2="18.5" y2="18.5" strokeWidth={2} />
                            </svg>
                        ) : (
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="9" strokeWidth={2} />
                                <line x1="10" y1="9" x2="10" y2="15" strokeWidth={2} strokeLinecap="round" />
                                <line x1="14" y1="9" x2="14" y2="15" strokeWidth={2} strokeLinecap="round" />
                            </svg>
                        )}
                    </span>
                    <div className="min-w-0 flex-1">
                        <p className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                            {isBanned ? 'Account Banned' : 'Account Suspended'}
                        </p>
                        <p className="mt-0.5 text-[13px] leading-snug text-gray-600 dark:text-gray-400">
                            {isBanned
                                ? 'Your account has been banned and you cannot sign in to this store.'
                                : 'Your account has been suspended and you cannot sign in to this store at the moment.'}
                        </p>
                        {customerStatus.reason && (
                            <div className="mt-1.5 rounded-lg bg-white/70 dark:bg-gray-900/60 px-2.5 py-1.5">
                                <p className="text-[11px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Reason
                                </p>
                                <p className="text-[13px] text-gray-800 dark:text-gray-200">
                                    &ldquo;{customerStatus.reason}&rdquo;
                                </p>
                            </div>
                        )}
                        <Link
                            href={route('storefront.support', { store_slug: tenant.slug })}
                            className={`mt-2 inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium transition-colors ${
                                isBanned
                                    ? 'bg-red-600 text-white hover:bg-red-700'
                                    : 'bg-amber-600 text-white hover:bg-amber-700'
                            }`}
                        >
                            <svg className="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 10h.01M12 10h.01M16 10h.01M21 12a9 9 0 01-13.2 7.9L3 21l1.1-4.8A9 9 0 1121 12z" />
                            </svg>
                            Help & Support
                        </Link>
                    </div>
                </div>
            )}

            <div className="mb-4">
                <a
                    href={`/auth/google/redirect/merchant?tenant_slug=${tenant.slug}`}
                    className="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 border border-gray-300 dark:border-gray-700 rounded-md font-semibold text-sm text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 transition"
                >
                    <svg className="w-5 h-5" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                    Continue with Google
                </a>
            </div>
            <div className="relative my-4">
                <div className="absolute inset-0 flex items-center">
                    <div className="w-full border-t border-gray-300 dark:border-gray-700" />
                </div>
                <div className="relative flex justify-center text-xs">
                    <span className="bg-white dark:bg-gray-900 px-2 text-gray-500">or</span>
                </div>
            </div>

            <form onSubmit={submit}>
                <div>
                    <label htmlFor="email" className="block font-medium text-sm text-gray-700 dark:text-gray-300">
                        Email
                    </label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        autoComplete="username"
                        onChange={(e) => setData('email', e.target.value)}
                    />
                    {errors.email && <p className="text-red-500 text-sm mt-1">{errors.email}</p>}
                </div>

                <div className="mt-4">
                    <label htmlFor="password" className="block font-medium text-sm text-gray-700 dark:text-gray-300">
                        Password
                    </label>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        autoComplete="current-password"
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    {errors.password && <p className="text-red-500 text-sm mt-1">{errors.password}</p>}
                </div>

                <div className="mt-4">
                    <label className="flex items-center">
                        <input
                            type="checkbox"
                            name="remember"
                            checked={data.remember}
                            onChange={(e) => setData('remember', e.target.checked)}
                            className="rounded border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500"
                        />
                        <span className="ms-2 text-sm text-gray-600 dark:text-gray-400">Remember me</span>
                    </label>
                </div>

                <div className="flex items-center justify-between mt-4">
                    <Link
                        href={route('storefront.password.request', { store_slug: tenant.slug })}
                        className="underline text-sm text-gray-600 hover:text-gray-900 dark:text-gray-100"
                    >
                        Forgot your password?
                    </Link>

                    <button
                        type="submit"
                        className="ms-4 inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50"
                        disabled={processing}
                    >
                        Log in
                    </button>
                </div>

                <div className="mt-4 text-center text-sm text-gray-600 dark:text-gray-400">
                    Don't have an account?{' '}
                    <Link
                        href={route('storefront.register', { store_slug: tenant.slug })}
                        className="text-blue-600 hover:underline"
                    >
                        Register
                    </Link>
                </div>

                <div className="mt-2 text-center text-sm text-gray-500 dark:text-gray-400">
                    <Link
                        href={route('storefront.index', { store_slug: tenant.slug })}
                        className="underline text-gray-600 hover:text-gray-900 dark:text-gray-100"
                    >
                        Back to store
                    </Link>
                </div>
            </form>
        </GuestLayout>
    );
}


