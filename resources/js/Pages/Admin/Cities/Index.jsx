import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function CitiesIndex({ cities }) {
    function handleToggle(id) {
        router.post(`/admin/cities/${id}/toggle`);
    }

    function handleDelete(id) {
        if (confirm('Delete this city? This will also delete associated townships.')) {
            router.delete(`/admin/cities/${id}`);
        }
    }

    function handleImportMyanmar() {
        if (confirm('Import real Myanmar cities and townships? Existing entries will be skipped.')) {
            router.post('/admin/locations/import-myanmar');
        }
    }

    return (
        <AdminLayout>
            <Head title="Cities" />
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                    <h1 className="text-2xl font-bold text-gray-900">Cities</h1>
                    <div className="flex gap-2">
                        <button onClick={handleImportMyanmar}
                            className="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 flex items-center gap-2">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 3v12" /></svg>
                            Import Myanmar Locations
                        </button>
                        <Link href="/admin/cities/create" className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>
                            Add City
                        </Link>
                    </div>
                </div>

                <div className="bg-white rounded-lg border border-gray-200 overflow-hidden">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Delivery Fee</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Townships</th>
                                <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Active</th>
                                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200">
                            {!cities?.data?.length ? (
                                <tr><td colSpan="5" className="px-6 py-12 text-center text-gray-500">No cities found.</td></tr>
                            ) : cities.data.map((city) => (
                                <tr key={city.id} className="hover:bg-gray-50">
                                    <td className="px-6 py-4 text-sm font-medium text-gray-900">{city.name}</td>
                                    <td className="px-6 py-4 text-sm text-gray-600">{Number(city.delivery_fee).toLocaleString()} MMK</td>
                                    <td className="px-6 py-4 text-sm text-gray-600">{city.townships_count ?? 0}</td>
                                    <td className="px-6 py-4 text-center">
                                        <button onClick={() => handleToggle(city.id)}
                                            className={`px-2.5 py-0.5 rounded-full text-xs font-medium cursor-pointer ${city.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                                            {city.is_active ? 'Active' : 'Inactive'}
                                        </button>
                                    </td>
                                    <td className="px-6 py-4 text-right text-sm">
                                        <div className="flex justify-end gap-2">
                                            <Link href={`/admin/cities/${city.id}/edit`} className="text-blue-600 hover:text-blue-800">Edit</Link>
                                            <button onClick={() => handleDelete(city.id)} className="text-red-600 hover:text-red-800">Delete</button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {cities?.links && cities.links.length > 3 && (
                    <div className="mt-4 flex items-center justify-between">
                        <p className="text-sm text-gray-500">Showing {cities.from} to {cities.to} of {cities.total} results</p>
                        <div className="flex gap-1">
                            {cities.links.map((link, i) => (
                                <Link key={i} href={link.url || '#'}
                                    className={`px-3 py-1 text-sm rounded-md ${link.active ? 'bg-blue-600 text-white' : link.url ? 'text-gray-700 hover:bg-gray-100' : 'text-gray-400 cursor-not-allowed'}`}>
                                    {link.label.replace('&laquo;', '«').replace('&raquo;', '»')}
                                </Link>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
