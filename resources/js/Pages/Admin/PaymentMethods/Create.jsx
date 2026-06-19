import { useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';

export default function PaymentMethodCreate() {
    const { auth } = usePage().props;
    const permissions = auth?.user?.permissions || [];
    const can = (perm) => permissions.includes(perm);
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        type: 'bank_transfer',
        account_name: '',
        account_number: '',
        qr_image: null,
        bank_name: '',
        is_active: true,
    });
    const [qrPreview, setQrPreview] = useState(null);
    const isCod = data.type === 'cod';

    function handleTypeChange(newType) {
        setData('type', newType);
        if (newType === 'cod') {
            setData('account_name', '');
            setData('account_number', '');
            setData('bank_name', '');
            setData('qr_image', null);
            setQrPreview(null);
        }
    }

    function handleSubmit(e) {
        e.preventDefault();
        post(adminUrl('/admin/payment-methods'));
    }

    function handleQrFile(e) {
        const file = e.target.files[0];
        setData('qr_image', file);
        if (file) {
            const reader = new FileReader();
            reader.onloadend = () => setQrPreview(reader.result);
            reader.readAsDataURL(file);
        } else {
            setQrPreview(null);
        }
    }

    if (!can('payments.create')) {
        return (
            <AdminLayout>
                <Head title="Unauthorized" />
                <div className="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    <div className="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
                        <p className="text-red-700 font-medium">You do not have permission to create payment methods.</p>
                    </div>
                </div>
            </AdminLayout>
        );
    }

    return (
        <AdminLayout>
            <Head title="Create Payment Method" />
            <div className="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div className="mb-6">
                    <Link href={adminUrl('/admin/payment-methods')} className="text-sm text-blue-600 hover:underline">&larr; Back to Payment Methods</Link>
                    <h1 className="text-2xl font-bold text-gray-900 mt-2">Create Payment Method</h1>
                </div>

                <div className="bg-white rounded-lg border border-gray-200 p-6">
                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div>
                            <label htmlFor="name" className="block text-sm font-medium text-gray-700 mb-1">Name</label>
                            <input id="name" type="text" value={data.name} onChange={(e) => setData('name', e.target.value)}
                                className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required />
                            {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                        </div>

                        <div>
                            <label htmlFor="type" className="block text-sm font-medium text-gray-700 mb-1">Type</label>
                            <select id="type" value={data.type} onChange={(e) => handleTypeChange(e.target.value)}
                                className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="cod">Cash on Delivery (COD)</option>
                            </select>
                            {errors.type && <p className="mt-1 text-sm text-red-600">{errors.type}</p>}
                        </div>

                        {!isCod && (
                            <>
                                <div>
                                    <label htmlFor="account_name" className="block text-sm font-medium text-gray-700 mb-1">Account Name</label>
                                    <input id="account_name" type="text" value={data.account_name} onChange={(e) => setData('account_name', e.target.value)}
                                        className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                    {errors.account_name && <p className="mt-1 text-sm text-red-600">{errors.account_name}</p>}
                                </div>

                                <div>
                                    <label htmlFor="account_number" className="block text-sm font-medium text-gray-700 mb-1">Account Number</label>
                                    <input id="account_number" type="text" value={data.account_number} onChange={(e) => setData('account_number', e.target.value)}
                                        className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                    {errors.account_number && <p className="mt-1 text-sm text-red-600">{errors.account_number}</p>}
                                </div>

                                <div>
                                    <label htmlFor="qr_image" className="block text-sm font-medium text-gray-700 mb-1">QR Code Image</label>
                                    <input id="qr_image" type="file" accept="image/jpg,image/jpeg,image/png,image/webp"
                                        onChange={handleQrFile}
                                        className="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
                                    {errors.qr_image && <p className="mt-1 text-sm text-red-600">{errors.qr_image}</p>}
                                    {qrPreview && (
                                        <div className="mt-3">
                                            <p className="text-xs text-gray-500 mb-1">Preview:</p>
                                            <img src={qrPreview} alt="QR Preview" className="w-24 h-24 rounded-lg border border-gray-200 object-cover" />
                                        </div>
                                    )}
                                </div>

                                <div>
                                    <label htmlFor="bank_name" className="block text-sm font-medium text-gray-700 mb-1">Bank Name (optional)</label>
                                    <input id="bank_name" type="text" value={data.bank_name} onChange={(e) => setData('bank_name', e.target.value)}
                                        className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                    {errors.bank_name && <p className="mt-1 text-sm text-red-600">{errors.bank_name}</p>}
                                </div>
                            </>
                        )}

                        {isCod && (
                            <div className="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3">
                                <p className="text-sm text-blue-700">
                                    Cash on Delivery requires no bank account details. Customers pay when their order is delivered.
                                </p>
                            </div>
                        )}

                        <div className="flex items-center gap-2">
                            <input id="is_active" type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)}
                                className="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                            <label htmlFor="is_active" className="text-sm font-medium text-gray-700">Active</label>
                        </div>

                        <div className="flex justify-end gap-3">
                            <Link href={adminUrl('/admin/payment-methods')} className="px-4 py-2 text-gray-600 hover:text-gray-800">Cancel</Link>
                            <button type="submit" disabled={processing}
                                className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
                                {processing ? 'Creating...' : 'Create Payment Method'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AdminLayout>
    );
}
