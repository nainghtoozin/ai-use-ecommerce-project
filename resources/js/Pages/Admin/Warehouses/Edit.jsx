import { Head, usePage, router } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Building2, ArrowLeft } from 'lucide-react';
import { adminUrl } from '@/Utils/adminUrl';

export default function WarehouseEdit({ warehouse }) {
    const { auth } = usePage().props;
    if (!auth?.user?.permissions?.includes('warehouses.update')) {
        return (
            <AdminLayout>
                <Head title="Access Denied" />
                <div className="py-12 text-center text-gray-500">You do not have permission to edit warehouses.</div>
            </AdminLayout>
        );
    }

    const [data, setData] = useState({
        name: warehouse.name || '',
        code: warehouse.code || '',
        description: warehouse.description || '',
        is_default: warehouse.is_default || false,
        is_active: warehouse.is_active ?? true,
        address: warehouse.address || '',
    });
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);

    const handleChange = (field, value) => {
        setData((prev) => ({ ...prev, [field]: value }));
        if (errors[field]) setErrors((prev) => ({ ...prev, [field]: null }));
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        setProcessing(true);
        router.put(adminUrl(`/admin/warehouses/${warehouse.id}`), data, {
            onSuccess: () => setProcessing(false),
            onError: (err) => {
                setErrors(err);
                setProcessing(false);
            },
        });
    };

    return (
        <AdminLayout>
            <Head title="Edit Warehouse" />

            <div className="py-6">
                <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center gap-3 mb-6">
                        <a
                            href={adminUrl('/admin/warehouses')}
                            onClick={(e) => { e.preventDefault(); router.get(adminUrl('/admin/warehouses')); }}
                            className="text-gray-400 hover:text-gray-600"
                        >
                            <ArrowLeft className="w-5 h-5" />
                        </a>
                        <div className="flex items-center gap-3">
                            <Building2 className="w-8 h-8 text-gray-500" />
                            <div>
                                <h1 className="text-2xl font-semibold text-gray-900">Edit Warehouse</h1>
                                <p className="text-sm text-gray-500">{warehouse.name}</p>
                            </div>
                        </div>
                    </div>

                    <form onSubmit={handleSubmit} className="bg-white rounded-lg border border-gray-200 p-6 space-y-6">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                            <input
                                type="text"
                                value={data.name}
                                onChange={(e) => handleChange('name', e.target.value)}
                                className={`w-full border ${errors.name ? 'border-red-300' : 'border-gray-300'} rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500`}
                            />
                            {errors.name && <p className="text-sm text-red-600 mt-1">{errors.name}</p>}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Code</label>
                            <input
                                type="text"
                                value={data.code}
                                onChange={(e) => handleChange('code', e.target.value.toUpperCase())}
                                className={`w-full border ${errors.code ? 'border-red-300' : 'border-gray-300'} rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500`}
                            />
                            {errors.code && <p className="text-sm text-red-600 mt-1">{errors.code}</p>}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea
                                value={data.description}
                                onChange={(e) => handleChange('description', e.target.value)}
                                rows={3}
                                className={`w-full border ${errors.description ? 'border-red-300' : 'border-gray-300'} rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500`}
                            />
                            {errors.description && <p className="text-sm text-red-600 mt-1">{errors.description}</p>}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Address</label>
                            <textarea
                                value={data.address}
                                onChange={(e) => handleChange('address', e.target.value)}
                                rows={2}
                                className={`w-full border ${errors.address ? 'border-red-300' : 'border-gray-300'} rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500`}
                            />
                            {errors.address && <p className="text-sm text-red-600 mt-1">{errors.address}</p>}
                        </div>

                        <div className="flex items-center gap-6">
                            <label className="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={data.is_default}
                                    onChange={(e) => handleChange('is_default', e.target.checked)}
                                    className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                />
                                <span className="text-sm text-gray-700">Set as default warehouse</span>
                            </label>
                            <label className="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={data.is_active}
                                    onChange={(e) => handleChange('is_active', e.target.checked)}
                                    className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                />
                                <span className="text-sm text-gray-700">Active</span>
                            </label>
                        </div>

                        <div className="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
                            <a
                                href={adminUrl('/admin/warehouses')}
                                onClick={(e) => { e.preventDefault(); router.get(adminUrl('/admin/warehouses')); }}
                                className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
                            >
                                Cancel
                            </a>
                            <button
                                type="submit"
                                disabled={processing}
                                className="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50"
                            >
                                {processing ? 'Saving...' : 'Update Warehouse'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AdminLayout>
    );
}
