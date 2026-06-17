import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';

export default function UnitEdit({ unit }) {
    const { auth } = usePage().props;
    if (!auth?.user?.permissions?.includes('units.update')) {
        return <AdminLayout><div className="text-center py-16"><p className="text-red-600 font-semibold">Unauthorized</p></div></AdminLayout>;
    }
    const { data, setData, put, processing, errors } = useForm({
        name: unit.name || '',
        short_name: unit.short_name || '',
        description: unit.description || '',
        is_active: unit.is_active ?? true,
    });

    function handleSubmit(e) {
        e.preventDefault();
        put(adminUrl(`/admin/units/${unit.id}`));
    }

    return (
        <AdminLayout>
            <Head title={`Edit ${unit.name}`} />
            <div className="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div className="mb-6">
                    <Link href={adminUrl('/admin/units')} className="text-sm text-blue-600 hover:underline">&larr; Back to Units</Link>
                    <h1 className="text-2xl font-bold text-gray-900 mt-2">Edit Unit</h1>
                    <p className="text-sm text-gray-500 mt-1">Update measurement unit details</p>
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
                            <label htmlFor="short_name" className="block text-sm font-medium text-gray-700 mb-1">Short Name</label>
                            <input id="short_name" type="text" value={data.short_name} onChange={(e) => setData('short_name', e.target.value)}
                                className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required />
                            {errors.short_name && <p className="mt-1 text-sm text-red-600">{errors.short_name}</p>}
                        </div>

                        <div>
                            <label htmlFor="description" className="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea id="description" value={data.description} onChange={(e) => setData('description', e.target.value)} rows={3}
                                className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                            {errors.description && <p className="mt-1 text-sm text-red-600">{errors.description}</p>}
                        </div>

                        <div className="flex items-center gap-3">
                            <input type="checkbox" id="is_active" checked={data.is_active}
                                onChange={(e) => setData('is_active', e.target.checked)}
                                className="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500" />
                            <label htmlFor="is_active" className="text-sm font-medium text-gray-700">Active</label>
                        </div>

                        <div className="flex justify-end gap-3 pt-4 border-t">
                            <Link href={adminUrl('/admin/units')} className="px-4 py-2 text-gray-600 hover:text-gray-800">Cancel</Link>
                            <button type="submit" disabled={processing}
                                className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
                                {processing ? 'Updating...' : 'Update Unit'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AdminLayout>
    );
}
