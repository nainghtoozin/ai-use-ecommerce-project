import { useState } from 'react';
import { Head, useForm, usePage, router } from '@inertiajs/react';
import PlatformGuestLayout from '@/Layouts/PlatformGuestLayout';
import { Loader2 } from 'lucide-react';

export default function SecuritySettings({ twoFactorEnabled, twoFactorRequired, recoveryCodesRemaining, googleLinked, googleEmail, hasPassword }) {
    const { errors, status } = usePage().props;
    const [showDisable, setShowDisable] = useState(false);
    const [showUnlink, setShowUnlink] = useState(false);
    const { data, setData, post, processing, reset } = useForm({ code: '', password: '' });

    const disable = (e) => {
        e.preventDefault();
        post('/two-factor/disable', {
            onSuccess: () => {
                setShowDisable(false);
                reset();
            },
        });
    };

    const regenerate = (e) => {
        e.preventDefault();
        post('/two-factor/recovery-codes/regenerate');
    };

    const unlink = (e) => {
        e.preventDefault();
        router.post('/auth/google/unlink', { password: data.password }, {
            onSuccess: () => {
                setShowUnlink(false);
                reset();
            },
        });
    };

    return (
        <PlatformGuestLayout>
            <Head title="Security Settings" />
            <div className="mb-6 text-center">
                <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Security Settings</h1>
            </div>

            {status && (
                <div className="mb-4 p-3 rounded-md bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800">
                    <p className="text-sm text-green-700 dark:text-green-300">{status}</p>
                </div>
            )}

            <div className="space-y-6">
                <div className="p-4 border rounded-md dark:border-gray-700">
                    <h2 className="font-semibold text-gray-900 dark:text-gray-100">Two-Factor Authentication</h2>
                    <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        Status: {twoFactorEnabled ? 'Enabled' : 'Disabled'} {twoFactorRequired && !twoFactorEnabled ? '(Required)' : ''}
                    </p>
                    {twoFactorEnabled && (
                        <p className="text-sm text-gray-600 dark:text-gray-400">Recovery codes remaining: {recoveryCodesRemaining}</p>
                    )}
                    <div className="mt-3 flex gap-2">
                        {!twoFactorEnabled && (
                            <a href="/two-factor/setup" className="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-semibold">
                                Enable 2FA
                            </a>
                        )}
                        {twoFactorEnabled && (
                            <>
                                <button onClick={() => setShowDisable(!showDisable)} className="px-4 py-2 border rounded-md text-sm">
                                    Disable
                                </button>
                                <form onSubmit={regenerate}>
                                    <button type="submit" className="px-4 py-2 border rounded-md text-sm">
                                        Regenerate recovery codes
                                    </button>
                                </form>
                            </>
                        )}
                    </div>

                    {showDisable && twoFactorEnabled && (
                        <form onSubmit={disable} className="mt-3 space-y-2">
                            <input
                                type="password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                placeholder="Current password (if applicable)"
                                className="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 shadow-sm"
                            />
                            {errors.password && <p className="text-red-500 text-xs mt-1">{errors.password}</p>}
                            <input
                                type="text"
                                value={data.code}
                                onChange={(e) => setData('code', e.target.value)}
                                placeholder="Authentication or recovery code"
                                className="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 shadow-sm"
                            />
                            {errors.code && <p className="text-red-500 text-xs mt-1">{errors.code}</p>}
                            <button
                                type="submit"
                                disabled={processing}
                                className="mt-2 px-4 py-2 bg-red-600 text-white rounded-md text-sm font-semibold"
                            >
                                {processing ? 'Disabling...' : 'Confirm Disable'}
                            </button>
                        </form>
                    )}
                </div>

                <div className="p-4 border rounded-md dark:border-gray-700">
                    <h2 className="font-semibold text-gray-900 dark:text-gray-100">Password Login</h2>
                    <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        Status: {hasPassword ? 'Enabled' : 'Not Set'}
                    </p>
                    {!hasPassword && (
                        <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            You currently sign in with Google. Create a password to also sign in using your email address.
                        </p>
                    )}
                    <div className="mt-3">
                        <a href="/forgot-password" className="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-semibold">
                            {hasPassword ? 'Change Password' : 'Create Password'}
                        </a>
                    </div>
                </div>

                <div className="p-4 border rounded-md dark:border-gray-700">
                    <h2 className="font-semibold text-gray-900 dark:text-gray-100">Google Account</h2>
                    <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        {googleLinked ? `Linked: ${googleEmail}` : 'Not linked'}
                    </p>
                    {!googleLinked && (
                        <a href="/auth/google/redirect/link" className="mt-3 inline-block px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-semibold">
                            Link Google Account
                        </a>
                    )}
                    {googleLinked && (
                        <>
                            <button
                                type="button"
                                onClick={() => setShowUnlink(!showUnlink)}
                                className="mt-3 px-4 py-2 border rounded-md text-sm"
                            >
                                Unlink Google Account
                            </button>
                            {showUnlink && (
                                <form onSubmit={unlink} className="mt-3 space-y-2">
                                    <input
                                        type="password"
                                        value={data.password}
                                        onChange={(e) => setData('password', e.target.value)}
                                        placeholder="Current password (if applicable)"
                                        className="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 shadow-sm"
                                    />
                                    {errors.password && <p className="text-red-500 text-xs mt-1">{errors.password}</p>}
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="mt-2 px-4 py-2 bg-red-600 text-white rounded-md text-sm font-semibold"
                                    >
                                        {processing ? 'Unlinking...' : 'Confirm Unlink'}
                                    </button>
                                </form>
                            )}
                        </>
                    )}
                </div>
            </div>
        </PlatformGuestLayout>
    );
}