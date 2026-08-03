import { useState, useEffect } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import PlatformGuestLayout from '@/Layouts/PlatformGuestLayout';
import { Mail, Loader2, CheckCircle2, ArrowLeft } from 'lucide-react';

export default function VerifyEmail() {
    const { auth, status } = usePage().props;
    const { post, processing } = useForm({});
    const [resent, setResent] = useState(false);
    const [cooldown, setCooldown] = useState(0);

    useEffect(() => {
        if (cooldown > 0) {
            const timer = setTimeout(() => setCooldown(cooldown - 1), 1000);
            return () => clearTimeout(timer);
        }
    }, [cooldown]);

    function submit(e) {
        e.preventDefault();
        if (cooldown > 0) return;

        post('/email/verification-notification', {
            onSuccess: () => {
                setResent(true);
                setCooldown(60);
            },
        });
    }

    const isVerified = auth?.user?.email_verified_at;
    const justRegistered = status === 'verification-link-sent' || !status;
    const showResentStatus = resent || status === 'verification-link-sent';

    return (
        <PlatformGuestLayout>
            <Head title="Verify Email" />

            {isVerified ? (
                <div className="text-center">
                    <CheckCircle2 className="w-16 h-16 text-green-500 mx-auto mb-4" />
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-2">
                        Email Verified
                    </h1>
                    <p className="text-sm text-gray-600 dark:text-gray-400 mb-6">
                        Your email address has been verified successfully.
                    </p>
                    <Link
                        href="/"
                        className="inline-flex items-center justify-center px-4 py-2.5 bg-indigo-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                    >
                        Continue
                    </Link>
                </div>
            ) : (
                <>
                    <div className="text-center mb-6">
                        <div className="w-16 h-16 bg-indigo-100 dark:bg-indigo-900/30 rounded-full flex items-center justify-center mx-auto mb-4">
                            <Mail className="w-8 h-8 text-indigo-600 dark:text-indigo-400" />
                        </div>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-2">
                            Verify your email
                        </h1>
                        <p className="text-sm text-gray-600 dark:text-gray-400">
                            We've sent a verification link to{' '}
                            <span className="font-medium text-gray-900 dark:text-gray-100">
                                {auth?.user?.email}
                            </span>
                            . Click the link in the email to activate your account.
                        </p>
                    </div>

                    {showResentStatus && (
                        <div className="mb-4 p-3 rounded-md bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800">
                            <p className="text-sm text-green-700 dark:text-green-300 text-center">
                                A new verification link has been sent to your email address.
                            </p>
                        </div>
                    )}

                    <form onSubmit={submit} className="space-y-4">
                        <button
                            type="submit"
                            disabled={processing || cooldown > 0}
                            className="w-full inline-flex items-center justify-center px-4 py-2.5 bg-indigo-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {processing ? (
                                <>
                                    <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                                    Sending...
                                </>
                            ) : cooldown > 0 ? (
                                `Resend in ${cooldown}s`
                            ) : (
                                'Resend Verification Email'
                            )}
                        </button>

                        <div className="flex items-center justify-between">
                            <Link
                                href="/logout"
                                method="post"
                                as="button"
                                className="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                            >
                                <ArrowLeft className="w-3.5 h-3.5" />
                                Sign out
                            </Link>

                            <Link
                                href="/login"
                                className="text-sm text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 font-medium"
                            >
                                Back to Sign in
                            </Link>
                        </div>
                    </form>

                    <div className="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <p className="text-xs text-center text-gray-500 dark:text-gray-400">
                            Didn't receive the email? Check your spam folder or try resending.
                        </p>
                    </div>
                </>
            )}
        </PlatformGuestLayout>
    );
}
