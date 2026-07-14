import { Head, Link, usePage } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';

export default function InvitationExpired() {
    const { message } = usePage().props;

    return (
        <GuestLayout>
            <Head title="Invitation Expired" />

            <div className="text-center">
                <div className="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg className="w-10 h-10 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>

                <h2 className="text-2xl font-bold text-gray-900 mb-2">
                    Invitation Expired
                </h2>

                <p className="text-gray-600 mb-8">
                    {message || 'This invitation is no longer valid. It may have expired or been revoked.'}
                </p>

                <div className="bg-gray-50 rounded-lg p-4 mb-8">
                    <p className="text-sm text-gray-500">
                        If you believe this is an error, please contact the store owner to send a new invitation.
                    </p>
                </div>

                <div className="flex flex-col sm:flex-row gap-3 justify-center">
                    <Link
                        href="/"
                        className="inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700"
                    >
                        Go to Homepage
                    </Link>
                </div>
            </div>
        </GuestLayout>
    );
}
