import { useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import PlatformGuestLayout from '@/Layouts/PlatformGuestLayout';
import { Loader2 } from 'lucide-react';

export default function Login({ status }) {
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
            </form>
        </PlatformGuestLayout>
    );
}
