import { useEffect, useState } from 'react';
import { Head, useForm, usePage, router } from '@inertiajs/react';
import PlatformGuestLayout from '@/Layouts/PlatformGuestLayout';
import { Loader2, Copy, Check } from 'lucide-react';
import QRCode from 'qrcode';

export default function TwoFactorSetup({ secret, qrUri, recoveryCodes, regenerated }) {
    const { errors } = usePage().props;
    const [qrDataUrl, setQrDataUrl] = useState('');
    const [copied, setCopied] = useState(false);
    const [cancelling, setCancelling] = useState(false);
    const cancel = () => {
        if (cancelling) return;
        setCancelling(true);
        router.post('/logout', {}, { onFinish: () => setCancelling(false) });
    };
    const { data, setData, post, processing } = useForm({ code: '' });

    useEffect(() => {
        if (qrUri) {
            QRCode.toDataURL(qrUri, { width: 200, margin: 1 })
                .then(setQrDataUrl)
                .catch(() => setQrDataUrl(''));
        } else {
            setQrDataUrl('');
        }
    }, [qrUri]);

    const copySecret = () => {
        if (secret) {
            navigator.clipboard.writeText(secret);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        }
    };

    const submit = (e) => {
        e.preventDefault();
        post('/two-factor/setup');
    };

    if (recoveryCodes) {
        return (
            <PlatformGuestLayout>
                <Head title="Recovery Codes" />
                <div className="mb-6 text-center">
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Save your recovery codes</h1>
                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Store these codes in a safe place. They will not be shown again.
                    </p>
                </div>
                <div className="grid grid-cols-2 gap-2 mb-6">
                    {recoveryCodes.map((code) => (
                        <code key={code} className="px-3 py-2 bg-gray-100 dark:bg-gray-800 rounded text-sm font-mono text-center">
                            {code}
                        </code>
                    ))}
                </div>
                <a
                    href="/security"
                    className="w-full inline-flex items-center justify-center px-4 py-2.5 bg-indigo-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-indigo-700"
                >
                    Continue
                </a>
            </PlatformGuestLayout>
        );
    }

    return (
        <PlatformGuestLayout>
            <Head title="Set Up Two-Factor Authentication" />
            <div className="mb-6 text-center">
                <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Set up two-factor authentication</h1>
                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Scan the QR code with your authenticator app.
                </p>
            </div>

            {errors.code && (
                <div className="mb-4 p-3 rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
                    <p className="text-sm text-red-700 dark:text-red-300">{errors.code}</p>
                </div>
            )}

            {qrDataUrl && (
                <div className="flex justify-center mb-4">
                    <img src={qrDataUrl} alt="QR Code" className="border rounded-md" />
                </div>
            )}

            {secret && (
                <div className="mb-4">
                    <label className="block font-medium text-sm text-gray-700 dark:text-gray-300 mb-1">Manual setup key</label>
                    <div className="flex items-center gap-2">
                        <code className="flex-1 px-3 py-2 bg-gray-100 dark:bg-gray-800 rounded text-sm font-mono break-all">{secret}</code>
                        <button type="button" onClick={copySecret} className="p-2 rounded border border-gray-300 dark:border-gray-700">
                            {copied ? <Check className="w-4 h-4" /> : <Copy className="w-4 h-4" />}
                        </button>
                    </div>
                </div>
            )}

            <form onSubmit={submit}>
                <div>
                    <label className="block font-medium text-sm text-gray-700 dark:text-gray-300">Verification code</label>
                    <input
                        type="text"
                        value={data.code}
                        onChange={(e) => setData('code', e.target.value)}
                        className="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-center tracking-widest text-lg"
                        required
                    />
                </div>
                <div className="mt-6">
                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full inline-flex items-center justify-center px-4 py-2.5 bg-indigo-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-indigo-700 disabled:opacity-50"
                    >
                        {processing ? <><Loader2 className="w-4 h-4 mr-2 animate-spin" /> Enabling...</> : 'Enable Two-Factor Authentication'}
                    </button>
                </div>
            </form>

            <div className="mt-4 text-center text-sm">
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
