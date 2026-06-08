import { Head, Link, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';

export default function CityEdit({ city }) {
    const { data, setData, put, processing, errors } = useForm({
        name: city.name || '',
        delivery_fee: city.delivery_fee || '',
        is_active: city.is_active ?? true,
    });

    function handleSubmit(e) {
        e.preventDefault();
        put(adminUrl(`/admin/cities/${city.id}`));
    }

    return (
        <AdminLayout>
            <Head title={`Edit ${city.name}`} />
            <div className="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div className="mb-6">
                    <Link href={adminUrl('/admin/cities')} className="text-sm text-blue-600 hover:underline">&larr; Back to Cities</Link>
                    <h1 className="text-2xl font-bold text-gray-900 mt-2">Edit City</h1>
                </div>

                <div className="bg-white rounded-lg border border-gray-200 p-6">
                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div>
                            <label htmlFor="name" className="block text-sm font-medium text-gray-700 mb-1">City Name</label>
                            <input id="name" type="text" value={data.name} onChange={(e) => setData('name', e.target.value)}
                                className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required />
                            {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                        </div>

                        <div>
                            <label htmlFor="delivery_fee" className="block text-sm font-medium text-gray-700 mb-1">Delivery Fee</label>
                            <input id="delivery_fee" type="number" step="0.01" min="0" value={data.delivery_fee} onChange={(e) => setData('delivery_fee', e.target.value)}
                                className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required />
                            {errors.delivery_fee && <p className="mt-1 text-sm text-red-600">{errors.delivery_fee}</p>}
                        </div>

                        <div className="flex items-center gap-2">
                            <input id="is_active" type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)}
                                className="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                            <label htmlFor="is_active" className="text-sm font-medium text-gray-700">Active</label>
                        </div>

                        <div className="flex justify-end gap-3">
                            <Link href={adminUrl('/admin/cities')} className="px-4 py-2 text-gray-600 hover:text-gray-800">Cancel</Link>
                            <button type="submit" disabled={processing}
                                className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
                                {processing ? 'Updating...' : 'Update City'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AdminLayout>
    );
}
