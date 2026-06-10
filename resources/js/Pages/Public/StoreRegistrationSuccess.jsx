import { Head, Link, usePage } from '@inertiajs/react';
import { assetUrl } from '@/Utils/helpers';

export default function StoreRegistrationSuccess() {
    const { storeSlug, storeUrl, siteName, logoUrl } = usePage().props;
    const logo = assetUrl(logoUrl);

    return (
        <>
            <Head title="Store Created Successfully" />

            <div className="min-h-screen bg-gradient-to-br from-green-50 via-white to-emerald-50 flex flex-col">
                <div className="max-w-lg mx-auto px-4 py-16 sm:py-24 text-center flex-1 flex flex-col justify-center">
                    <div className="w-20 h-20 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-6">
                        <svg className="w-10 h-10 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                        </svg>
                    </div>

                    <h1 className="text-3xl font-extrabold text-gray-900 sm:text-4xl">
                        Store Created!
                    </h1>

                    <p className="mt-4 text-lg text-gray-600">
                        Your store <span className="font-semibold text-gray-900">{storeSlug}</span> has been created!
                    </p>

                    <div className="mt-8 bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                        <p className="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-2">
                            Your Store URL
                        </p>
                        {storeUrl ? (
                            <a
                                href={storeUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="text-lg font-mono text-indigo-600 hover:text-indigo-800 break-all underline"
                            >
                                {storeUrl}
                            </a>
                        ) : (
                            <p className="text-lg text-gray-400">—</p>
                        )}
                    </div>

                    <div className="mt-6 p-4 bg-yellow-50 rounded-xl border border-yellow-100 text-left">
                        <div className="flex items-start gap-3">
                            <div className="w-8 h-8 rounded-full bg-yellow-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                                <svg className="w-4 h-4 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div>
                                <p className="text-sm font-semibold text-yellow-800">Verify your email address</p>
                                <ul className="mt-2 text-sm text-yellow-700 space-y-1 list-disc list-inside">
                                    <li>We sent a verification email to the address you provided.</li>
                                    <li>Click the link in the email to activate your store.</li>
                                    <li>Your store is <span className="font-semibold">pending</span> — it will be activated automatically once you verify your email.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div className="mt-10 flex flex-col sm:flex-row gap-4 justify-center">
                        {storeUrl && (
                            <a
                                href={storeUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center justify-center px-8 py-3 rounded-xl text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition-all"
                            >
                                Go to Your Store
                            </a>
                        )}
                        <Link
                            href="/"
                            className="inline-flex items-center justify-center px-8 py-3 rounded-xl text-sm font-bold text-gray-700 bg-gray-100 hover:bg-gray-200 transition-all"
                        >
                            Back to Home
                        </Link>
                    </div>
                </div>

                <footer className="border-t border-gray-200 bg-white mt-auto">
                    <div className="max-w-lg mx-auto px-4 py-6 text-center text-sm text-gray-400">
                        &copy; {new Date().getFullYear()} {siteName}. All rights reserved.
                    </div>
                </footer>
            </div>
        </>
    );
}
