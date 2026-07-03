import { Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function AdminBillingSettings() {
    return (
        <AdminLayout>
            <Head title="Billing Settings" />

            <div className="p-6 lg:p-8 space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Billing Settings</h1>
                        <p className="text-sm text-gray-500 mt-1">Configure your billing preferences and payment methods</p>
                    </div>
                </div>

                <div className="bg-white rounded-xl border border-gray-200 p-8 text-center">
                    <div className="text-4xl text-gray-300 mb-3">
                        <svg className="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    </div>
                    <h3 className="text-base font-semibold text-gray-900 mb-2">Coming Next</h3>
                    <p className="text-sm text-gray-500 max-w-md mx-auto">
                        Billing settings — including default payment method, tax information, email receipts, and billing address — will be available in a future update.
                    </p>
                </div>
            </div>
        </AdminLayout>
    );
}
