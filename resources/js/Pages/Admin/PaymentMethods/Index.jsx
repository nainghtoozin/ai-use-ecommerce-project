import { Head, Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { assetUrl } from '@/Utils/helpers';
import { adminUrl } from '@/Utils/adminUrl';
import { usePermission } from '@/Hooks/usePermission';

const SYSTEM_METHODS = ['Cash', 'Cash On Delivery'];

export default function PaymentMethodsIndex({ paymentMethods }) {
    const { can } = usePermission();
    const isSystemMethod = (name) => SYSTEM_METHODS.includes(name);
    function handleToggle(id) {
        router.post(adminUrl(`/admin/payment-methods/${id}/toggle`));
    }

    function handleDelete(id) {
        if (confirm('Delete this payment method?')) {
            router.delete(adminUrl(`/admin/payment-methods/${id}`));
        }
    }

    return (
        <AdminLayout>
            <Head title="Payment Methods" />
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                    <h1 className="text-2xl font-bold text-gray-900">Payment Methods</h1>
                    {can('payments.create') && (
                        <Link href={adminUrl('/admin/payment-methods/create')} className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>
                            Add Payment Method
                        </Link>
                    )}
                </div>

                <div className="bg-white rounded-lg border border-gray-200 overflow-hidden">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">QR</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account Name</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account Number</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bank</th>
                                <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Active</th>
                                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200">
                            {!paymentMethods?.data?.length ? (
                                <tr><td colSpan="8" className="px-6 py-12 text-center text-gray-500">No payment methods found.</td></tr>
                            ) : paymentMethods.data.map((pm, index) => (
                                <tr key={pm.id} className="hover:bg-gray-50">
                                    <td className="px-6 py-4">
                                        {pm.qr_image_url ? (
                                            <img src={pm.qr_image_url} alt={`${pm.name} QR`} className="w-10 h-10 rounded object-cover" />
                                        ) : (
                                            <div className="w-10 h-10 rounded bg-gray-100 flex items-center justify-center">
                                                <svg className="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" /></svg>
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-6 py-4 text-sm text-gray-500">{index + 1}</td>
                                    <td className="px-6 py-4 text-sm font-medium text-gray-900">{pm.name}</td>
                                    <td className="px-6 py-4 text-sm text-gray-600">{pm.account_name || '-'}</td>
                                    <td className="px-6 py-4 text-sm text-gray-600">{pm.account_number || '-'}</td>
                                    <td className="px-6 py-4 text-sm text-gray-600">{pm.bank_name || '-'}</td>
                                    <td className="px-6 py-4 text-center">
                                        {can('payments.update') ? (
                                            <button onClick={() => handleToggle(pm.id)}
                                                className={`px-2.5 py-0.5 rounded-full text-xs font-medium cursor-pointer ${pm.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                                                {pm.is_active ? 'Active' : 'Inactive'}
                                            </button>
                                        ) : (
                                            <span className={`px-2.5 py-0.5 rounded-full text-xs font-medium ${pm.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                                                {pm.is_active ? 'Active' : 'Inactive'}
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-6 py-4 text-right text-sm">
                                        <div className="flex justify-end gap-2">
                                            {can('payments.update') && !isSystemMethod(pm.name) && (
                                                <Link href={adminUrl(`/admin/payment-methods/${pm.id}/edit`)} className="text-blue-600 hover:text-blue-800">Edit</Link>
                                            )}
                                            {can('payments.delete') && !isSystemMethod(pm.name) && (
                                                <button onClick={() => handleDelete(pm.id)} className="text-red-600 hover:text-red-800">Delete</button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {paymentMethods?.links && paymentMethods.links.length > 3 && (
                    <div className="mt-4 flex items-center justify-between">
                        <p className="text-sm text-gray-500">Showing {paymentMethods.from} to {paymentMethods.to} of {paymentMethods.total} results</p>
                        <div className="flex gap-1">
                            {paymentMethods.links.map((link, i) => (
                                <Link key={i} href={link.url || '#'}
                                    className={`px-3 py-1 text-sm rounded-md ${link.active ? 'bg-blue-600 text-white' : link.url ? 'text-gray-700 hover:bg-gray-100' : 'text-gray-400 cursor-not-allowed'}`}>
                                    {link.label.replace('&laquo;', '\u00ab').replace('&raquo;', '\u00bb')}
                                </Link>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
