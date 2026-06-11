import { usePage } from '@inertiajs/react';

export default function EmptyStoreState({ storeName }) {
    const { tenant } = usePage().props;
    const name = storeName || tenant?.name || 'This Store';

    return (
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-20">
            <div className="text-center max-w-md mx-auto">
                <div className="w-20 h-20 rounded-full bg-indigo-100 flex items-center justify-center mx-auto mb-6">
                    <svg className="w-10 h-10 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                </div>
                <h1 className="text-2xl sm:text-3xl font-bold text-gray-900">
                    Welcome to {name}
                </h1>
                <p className="mt-3 text-sm sm:text-base text-gray-500 leading-relaxed">
                    This store is preparing products. Please check back soon!
                </p>
            </div>
        </div>
    );
}
