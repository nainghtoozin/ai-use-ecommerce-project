import { Head, Link } from '@inertiajs/react';
import PlatformGuestLayout from '@/Layouts/PlatformGuestLayout';
import { Mail, LogIn } from 'lucide-react';

export default function RegistrationUnavailable({ supportUrl }) {
    const href = supportUrl || '/support';

    return (
        <PlatformGuestLayout>
            <Head title="Registration Currently Unavailable" />

            <div className="mb-6 text-center">
                <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    Registration Currently Unavailable
                </h1>
                <p className="mt-3 text-sm text-gray-600 dark:text-gray-400">
                    We're currently not accepting new merchant registrations. Please contact our support team if you need assistance or would like to know when registration will reopen.
                </p>
            </div>

            <div className="flex flex-col items-center gap-3">
                <a
                    href={href}
                    className="w-full inline-flex items-center justify-center px-4 py-2.5 bg-indigo-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                >
                    <Mail className="w-4 h-4 mr-2" />
                    Contact Support
                </a>

                <Link
                    href="/login"
                    className="inline-flex items-center text-sm text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 font-medium"
                >
                    <LogIn className="w-4 h-4 mr-1" />
                    Already have an account? Log in
                </Link>
            </div>
        </PlatformGuestLayout>
    );
}
