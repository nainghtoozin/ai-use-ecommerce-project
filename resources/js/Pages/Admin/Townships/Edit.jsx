import { Head, Link, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';

export default function TownshipEdit({ township, cities = [] }) {
    const { data, setData, put, processing, errors } = useForm({
        city_id: township.city_id || '',
        name: township.name || '',
        postal_code: township.postal_code || '',
        is_active: township.is_active ?? true,
    });

    function handleSubmit(e) {
        e.preventDefault();
        put(adminUrl(`/admin/townships/${township.id}`));
    }

    return (
        <AdminLayout>
            <Head title={`Edit ${township.name}`} />
            <div className="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div className="mb-6">
                    <Link href={adminUrl('/admin/townships')} className="text-sm text-blue-600 hover:underline">&larr; Back to Townships</Link>
                    <h1 className="text-2xl font-bold text-gray-900 mt-2">Edit Township</h1>
                </div>

                <div className="bg-white rounded-lg border border-gray-200 p-6">
                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div>
                            <label htmlFor="city_id" className="block text-sm font-medium text-gray-700 mb-1">City</label>
                            <select id="city_id" value={data.city_id} onChange={(e) => setData('city_id', e.target.value)}
                                className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                <option value="">Select a city</option>
                                {cities.map((city) => (
                                    <option key={city.id} value={city.id}>{city.name}</option>
                                ))}
                            </select>
                            {errors.city_id && <p className="mt-1 text-sm text-red-600">{errors.city_id}</p>}
                        </div>

                        <div>
                            <label htmlFor="name" className="block text-sm font-medium text-gray-700 mb-1">Township Name</label>
                            <input id="name" type="text" value={data.name} onChange={(e) => setData('name', e.target.value)}
                                className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required />
                            {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                        </div>

                        <div>
                            <label htmlFor="postal_code" className="block text-sm font-medium text-gray-700 mb-1">Postal Code (optional)</label>
                            <input id="postal_code" type="text" value={data.postal_code} onChange={(e) => setData('postal_code', e.target.value)}
                                className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                            {errors.postal_code && <p className="mt-1 text-sm text-red-600">{errors.postal_code}</p>}
                        </div>

                        <div className="flex items-center gap-2">
                            <input id="is_active" type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)}
                                className="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                            <label htmlFor="is_active" className="text-sm font-medium text-gray-700">Active</label>
                        </div>

                        <div className="flex justify-end gap-3">
                            <Link href={adminUrl('/admin/townships')} className="px-4 py-2 text-gray-600 hover:text-gray-800">Cancel</Link>
                            <button type="submit" disabled={processing}
                                className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
                                {processing ? 'Updating...' : 'Update Township'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AdminLayout>
    );
}
