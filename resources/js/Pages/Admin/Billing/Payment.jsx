import { Head, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Upload, ShieldCheck, Clock, ArrowLeft } from 'lucide-react';
import { adminUrl } from '@/Utils/adminUrl';

export default function AdminBillingPayment() {
    const { props } = usePage();
    const params = new URLSearchParams(typeof window !== 'undefined' ? window.location.search : '');
    const intentRef = params.get('intent') || props?.intent?.reference_number;
    const planName = params.get('plan') || props?.plan;

    return (
        <AdminLayout>
            <Head title="Payment" />

            <div className="p-6 lg:p-8 space-y-6 max-w-2xl mx-auto">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Payment</h1>
                    <p className="text-sm text-gray-500 mt-1">Complete your payment to activate the subscription</p>
                </div>

                <div className="bg-white rounded-xl border border-gray-200 p-8 text-center">
                    <div className="w-16 h-16 rounded-2xl bg-blue-100 flex items-center justify-center mx-auto mb-4">
                        <Upload className="w-8 h-8 text-blue-600" />
                    </div>
                    <h2 className="text-xl font-bold text-gray-900 mb-2">Manual Payment</h2>
                    <p className="text-sm text-gray-500 max-w-md mx-auto mb-1">
                        The payment upload interface will be available in the next update.
                    </p>
                    <p className="text-xs text-gray-400 max-w-md mx-auto mb-6">
                        {intentRef && <>Reference: <span className="font-mono font-semibold">{intentRef}</span></>}
                    </p>

                    <div className="bg-amber-50 border border-amber-200 rounded-xl p-4 text-left mb-6">
                        <div className="flex items-start gap-2.5">
                            <Clock className="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" />
                            <div>
                                <p className="text-sm font-semibold text-amber-800">Coming Next</p>
                                <p className="text-xs text-amber-600 mt-1">
                                    You will be able to upload payment evidence (bank transfer receipt, screenshot, etc.)
                                    and submit it for admin review. Your subscription will be activated after confirmation.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="bg-blue-50 border border-blue-200 rounded-xl p-4 text-left mb-6">
                        <div className="flex items-start gap-2.5">
                            <ShieldCheck className="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" />
                            <div>
                                <p className="text-sm font-semibold text-blue-800">Why is manual payment safe?</p>
                                <ul className="text-xs text-blue-600 mt-1 space-y-1">
                                    <li>✓ Your payment is reviewed by our team.</li>
                                    <li>✓ Your subscription is activated only after confirmation.</li>
                                    <li>✓ Your payment reference is unique.</li>
                                    <li>✓ Your payment history is permanently recorded.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <button
                        onClick={() => router.get(adminUrl('/admin/billing'), {}, { preserveState: false })}
                        className="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 transition-colors"
                    >
                        <ArrowLeft className="w-4 h-4" />
                        Back to Billing
                    </button>
                </div>
            </div>
        </AdminLayout>
    );
}
