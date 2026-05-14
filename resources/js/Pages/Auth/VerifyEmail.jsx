import { Head, Link, useForm, usePage } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';

export default function VerifyEmail() {
    const { auth } = usePage().props;
    const { post, processing } = useForm({});

    function submit(e) {
        e.preventDefault();
        post('/email/verification-notification');
    }

    return (
        <GuestLayout>
            <Head title="Verify Email" />

            <p className="text-sm text-gray-600 mb-6">
                Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn't receive the email, we'll gladly send you another.
            </p>

            {auth?.user?.email_verified_at && (
                <div className="mb-4 text-sm font-medium text-green-600">Your email is verified.</div>
            )}

            <form onSubmit={submit}>
                <div className="flex items-center justify-between">
                    <Link href="/logout" method="post" as="button" className="underline text-sm text-gray-600 hover:text-gray-900">
                        Log Out
                    </Link>
                    <button type="submit" disabled={processing}
                        className="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 disabled:opacity-50">
                        {processing ? 'Sending...' : 'Resend Verification Email'}
                    </button>
                </div>
            </form>
        </GuestLayout>
    );
}
