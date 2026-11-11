import { useState } from 'react';
import { Head, useForm, usePage, router } from '@inertiajs/react';
import PlatformGuestLayout from '@/Layouts/PlatformGuestLayout';
import { Loader2 } from 'lucide-react';

export default function TwoFactorChallenge() {
    const { errors } = usePage().props;
    const [useRecovery, setUseRecovery] = useState(false);
    const { data, setData, post, processing } = useForm({ code: '' });
    const [cancelling, setCancelling] = useState(false);

    const cancel = () => {
        if (cancelling) return;
        setCancelling(true);
        router.post('/logout', {}, {
            onFinish: () => setCancelling(false),
        });
    };

    const submit = (e) => {
        e.preventDefault();
        post('/two-factor/challenge');
    };

    return (
        <PlatformGuestLayout>
            <Head title="Two-Factor Authentication" />
            <div className="mb-6 text-center">
                <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Two-Factor Authentication</h1>
                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    {useRecovery ? 'Enter one of your recovery codes.' : 'Enter the 6-digit code from your authenticator app.'}
                </p>
            </div>

            {errors.code && (
                <div className="mb-4 p-3 rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
                    <p className="text-sm text-red-700 dark:text-red-300">{errors.code}</p>
                </div>
            )}

            <form onSubmit={submit}>
                <div>
                    <label className="block font-medium text-sm text-gray-700 dark:text-gray-300">
                        {useRecovery ? 'Recovery Code' : 'Authentication Code'}
                    </label>
                    <input
                        type="text"
                        value={data.code}
                        onChange={(e) => setData('code', e.target.value)}
                        className="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-center tracking-widest text-lg"
                        autoFocus
                        required
                    />
                </div>

                <div className="mt-6">
                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full inline-flex items-center justify-center px-4 py-2.5 bg-indigo-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-indigo-700 disabled:opacity-50"
                    >
                        {processing ? <><Loader2 className="w-4 h-4 mr-2 animate-spin" /> Verifying...</> : 'Verify'}
                    </button>
                </div>
            </form>

            <div className="mt-4 text-center text-sm">
                <button
                    type="button"
                    onClick={() => setUseRecovery(!useRecovery)}
                    className="text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 font-medium"
                >
                    {useRecovery ? 'Use authenticator code' : 'Use a recovery code'}
                </button>
            </div>

            <div className="mt-2 text-center text-sm">
                <button
                    type="button"
                    onClick={cancel}
                    disabled={cancelling}
                    className="text-gray-500 hover:text-gray-700 dark:text-gray-400"
                >
                    {cancelling ? 'Cancelling...' : 'Cancel'}
                </button>
            </div>
        </PlatformGuestLayout>
    );
}
