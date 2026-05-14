import { Head, Link, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function CategoryEdit({ category }) {
    const { data, setData, put, processing, errors } = useForm({
        name: category.name || '',
        description: category.description || '',
    });

    function handleSubmit(e) {
        e.preventDefault();
        put(`/admin/categories/${category.id}`);
    }

    return (
        <AdminLayout>
            <Head title={`Edit ${category.name}`} />
            <div className="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div className="mb-6">
                    <Link href="/admin/categories" className="text-sm text-blue-600 hover:underline">&larr; Back to Categories</Link>
                    <h1 className="text-2xl font-bold text-gray-900 mt-2">Edit Category</h1>
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
                            <label htmlFor="description" className="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea id="description" value={data.description} onChange={(e) => setData('description', e.target.value)} rows={3}
                                className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                            {errors.description && <p className="mt-1 text-sm text-red-600">{errors.description}</p>}
                        </div>

                        <div className="flex justify-end gap-3">
                            <Link href="/admin/categories" className="px-4 py-2 text-gray-600 hover:text-gray-800">Cancel</Link>
                            <button type="submit" disabled={processing}
                                className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
                                {processing ? 'Updating...' : 'Update Category'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AdminLayout>
    );
}
