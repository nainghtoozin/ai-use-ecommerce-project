import { Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function AdminBillingPaymentHistory() {
    return (
        <AdminLayout>
            <Head title="Payment History" />

            <div className="p-6 lg:p-8 space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Payment History</h1>
                        <p className="text-sm text-gray-500 mt-1">View your past payments and billing records</p>
                    </div>
                </div>

                <div className="bg-white rounded-xl border border-gray-200 p-8 text-center">
                    <div className="text-4xl text-gray-300 mb-3">
                        <svg className="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    </div>
                    <h3 className="text-base font-semibold text-gray-900 mb-2">No Payments Yet</h3>
                    <p className="text-sm text-gray-500 max-w-md mx-auto">
                        You have no payment records yet. Payments will appear here once your subscription is processed through a payment gateway.
                    </p>
                </div>

                <div className="bg-white rounded-xl border border-gray-200">
                    <div className="px-6 py-4 border-b border-gray-100">
                        <h3 className="text-base font-semibold text-gray-900">Coming Next</h3>
                    </div>
                    <div className="p-6">
                        <p className="text-sm text-gray-500">
                            Full payment history — including invoices, receipts, payment method details, and downloadable PDFs — will be available in a future update.
                        </p>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
