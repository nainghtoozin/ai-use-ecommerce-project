import { useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import PlatformGuestLayout from '@/Layouts/PlatformGuestLayout';
import { Loader2 } from 'lucide-react';

export default function Login({ status, googlePurpose = 'merchant' }) {
    const { errors } = usePage().props;
    const { data, setData, post, processing, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();
        post('/login', {
            onFinish: () => reset('password'),
        });
    };

    return (
        <PlatformGuestLayout>
            <Head title="Sign In" />

            <div className="mb-6 text-center">
                <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    Welcome back
                </h1>
                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Sign in to your account
                </p>
            </div>

            {status && (
                <div className="mb-4 p-3 rounded-md bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800">
                    <p className="text-sm text-green-700 dark:text-green-300">{status}</p>
                </div>
            )}

            {errors.email && !status && (
                <div className="mb-4 p-3 rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
                    <p className="text-sm text-red-700 dark:text-red-300">{errors.email}</p>
                </div>
            )}

            <div className="mb-4">
                <a
                    href={`/auth/google/redirect/${googlePurpose}`}
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
                        Email Address
                    </label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        autoComplete="username"
                        placeholder="you@example.com"
                        onChange={(e) => setData('email', e.target.value)}
                        required
                    />
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
                        className="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        autoComplete="current-password"
                        placeholder="Enter your password"
                        onChange={(e) => setData('password', e.target.value)}
                        required
                    />
                    {errors.password && <p className="text-red-500 text-xs mt-1">{errors.password}</p>}
                </div>

                <div className="mt-4 flex items-center justify-between">
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

                    <Link
                        href="/forgot-password"
                        className="text-sm text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 font-medium"
                    >
                        Forgot password?
                    </Link>
                </div>

                <div className="mt-6">
                    <button
                        type="submit"
                        className="w-full inline-flex items-center justify-center px-4 py-2.5 bg-indigo-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50 disabled:cursor-not-allowed"
                        disabled={processing}
                    >
                        {processing ? (
                            <>
                                <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                                Signing in...
                            </>
                        ) : (
                            'Sign in'
                        )}
                    </button>
                </div>

                <div className="mt-4 text-center text-sm text-gray-600 dark:text-gray-400">
                    Don't have an account?{' '}
                    <Link href="/register" className="text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 font-medium">
                        Create one
                    </Link>
                </div>

                <div className="mt-2 text-center text-sm text-gray-600 dark:text-gray-400">
                    New to password login?{' '}
                    <Link href="/forgot-password" className="text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 font-medium">
                        Set up your password
                    </Link>
                </div>
            </form>
        </PlatformGuestLayout>
    );
}


