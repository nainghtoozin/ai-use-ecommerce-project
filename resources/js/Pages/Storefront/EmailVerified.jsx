import { Head, Link } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';

export default function EmailVerified({ tenant, storeUrl }) {
    return (
        <GuestLayout>
            <Head title="Email Verified" />
            <div className="text-center">
                <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-2">Email Verified!</h1>
                <p className="text-sm text-gray-600 dark:text-gray-400 mb-6">
                    Welcome to {tenant?.name}. Your email address has been successfully verified. You can now continue shopping.
                </p>
                <a
                    href={storeUrl}
                    className="inline-flex items-center justify-center px-4 py-2.5 bg-indigo-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-indigo-700"
                >
                    Continue Shopping
                </a>
            </div>
        </GuestLayout>
    );
}
