import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import { usePermission } from '@/Hooks/usePermission';

export default function DeliveryServiceCreate() {
    const { can } = usePermission();
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        code: '',
        description: '',
        base_fee: 0,
        fee_per_kg: '',
        min_days: 1,
        max_days: 3,
        is_active: true,
        sort_order: 0,
    });

    function handleSubmit(e) {
        e.preventDefault();
        post(adminUrl('/admin/delivery-services'));
    }

    if (!can('delivery-services.create')) {
        return (
            <AdminLayout>
                <Head title="Unauthorized" />
                <div className="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    <div className="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
                        <p className="text-red-700 font-medium">You do not have permission to create delivery services.</p>
                    </div>
                </div>
            </AdminLayout>
        );
    }

    return (
        <AdminLayout>
            <Head title="Create Delivery Service" />
            <div className="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div className="mb-6">
                    <Link href={adminUrl('/admin/storefront/checkout')} className="text-sm text-blue-600 hover:underline">&larr; Back to Checkout</Link>
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-2">Create Delivery Service</h1>
                </div>

                <div className="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800 p-6">
                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div>
                            <label htmlFor="name" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name</label>
                            <input id="name" type="text" value={data.name} onChange={(e) => setData('name', e.target.value)}
                                className="w-full border border-gray-300 dark:border-gray-700 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required />
                            {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                        </div>

                        <div>
                            <label htmlFor="code" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Code</label>
                            <input id="code" type="text" value={data.code} onChange={(e) => setData('code', e.target.value.toUpperCase())}
                                className="w-full border border-gray-300 dark:border-gray-700 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required />
                            {errors.code && <p className="mt-1 text-sm text-red-600">{errors.code}</p>}
                        </div>

                        <div>
                            <label htmlFor="description" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description (optional)</label>
                            <textarea id="description" value={data.description} onChange={(e) => setData('description', e.target.value)}
                                className="w-full border border-gray-300 dark:border-gray-700 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" rows="3" />
                            {errors.description && <p className="mt-1 text-sm text-red-600">{errors.description}</p>}
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label htmlFor="base_fee" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Base Fee</label>
                                <input id="base_fee" type="number" min="0" value={data.base_fee} onChange={(e) => setData('base_fee', parseInt(e.target.value) || 0)}
                                    className="w-full border border-gray-300 dark:border-gray-700 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required />
                                {errors.base_fee && <p className="mt-1 text-sm text-red-600">{errors.base_fee}</p>}
                            </div>

                            <div>
                                <label htmlFor="fee_per_kg" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fee per Kg (optional)</label>
                                <input id="fee_per_kg" type="number" min="0" value={data.fee_per_kg || ''} onChange={(e) => setData('fee_per_kg', e.target.value ? parseInt(e.target.value) : '')}
                                    className="w-full border border-gray-300 dark:border-gray-700 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                {errors.fee_per_kg && <p className="mt-1 text-sm text-red-600">{errors.fee_per_kg}</p>}
                            </div>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label htmlFor="min_days" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Min Delivery Days</label>
                                <input id="min_days" type="number" min="0" value={data.min_days} onChange={(e) => setData('min_days', parseInt(e.target.value) || 0)}
                                    className="w-full border border-gray-300 dark:border-gray-700 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required />
                                {errors.min_days && <p className="mt-1 text-sm text-red-600">{errors.min_days}</p>}
                            </div>

                            <div>
                                <label htmlFor="max_days" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Max Delivery Days</label>
                                <input id="max_days" type="number" min="0" value={data.max_days} onChange={(e) => setData('max_days', parseInt(e.target.value) || 0)}
                                    className="w-full border border-gray-300 dark:border-gray-700 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required />
                                {errors.max_days && <p className="mt-1 text-sm text-red-600">{errors.max_days}</p>}
                            </div>
                        </div>

                        <div>
                            <label htmlFor="sort_order" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Sort Order</label>
                            <input id="sort_order" type="number" min="0" value={data.sort_order} onChange={(e) => setData('sort_order', parseInt(e.target.value) || 0)}
                                className="w-full border border-gray-300 dark:border-gray-700 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                            {errors.sort_order && <p className="mt-1 text-sm text-red-600">{errors.sort_order}</p>}
                        </div>

                        <div className="flex items-center gap-2">
                            <input id="is_active" type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)}
                                className="rounded border-gray-300 dark:border-gray-700 text-blue-600 focus:ring-blue-500" />
                            <label htmlFor="is_active" className="text-sm font-medium text-gray-700 dark:text-gray-300">Active</label>
                        </div>

                        <div className="flex justify-end gap-3">
                            <Link href={adminUrl('/admin/delivery-services')} className="px-4 py-2 text-gray-600 hover:text-gray-800 dark:text-gray-200">Cancel</Link>
                            <button type="submit" disabled={processing}
                                className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
                                {processing ? 'Creating...' : 'Create Delivery Service'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AdminLayout>
    );
}
