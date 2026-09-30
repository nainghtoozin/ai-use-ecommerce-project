import { useForm, Link, Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';

export default function CustomersEdit({ customer }) {
    const { data, setData, put, processing, errors } = useForm({
        name: customer.name || '',
        email: customer.email || '',
        phone: customer.phone || '',
    });

    function handleSubmit(e) {
        e.preventDefault();
        put(adminUrl(`/admin/customers/${customer.id}`));
    }

    return (
        <AdminLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Edit Customer: {customer.name}</h2>}>
            <Head title={`Edit Customer: ${customer.name}`} />

            <div className="py-6">
                <div className="max-w-3xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white dark:bg-gray-900 overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <form onSubmit={handleSubmit} className="space-y-6">
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name</label>
                                        <input
                                            type="text"
                                            value={data.name}
                                            onChange={(e) => setData('name', e.target.value)}
                                            className="w-full rounded-lg border-gray-300 dark:border-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        />
                                        {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                                        <input
                                            type="email"
                                            value={data.email}
                                            onChange={(e) => setData('email', e.target.value)}
                                            className="w-full rounded-lg border-gray-300 dark:border-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        />
                                        {errors.email && <p className="mt-1 text-sm text-red-600">{errors.email}</p>}
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone</label>
                                        <input
                                            type="text"
                                            value={data.phone}
                                            onChange={(e) => setData('phone', e.target.value)}
                                            className="w-full rounded-lg border-gray-300 dark:border-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        />
                                        {errors.phone && <p className="mt-1 text-sm text-red-600">{errors.phone}</p>}
                                    </div>
                                </div>

                                <div className="flex items-center gap-3">
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50"
                                    >
                                        Save changes
                                    </button>
                                    <Link href={adminUrl(`/admin/customers/${customer.id}`)} className="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200">
                                        Cancel
                                    </Link>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
