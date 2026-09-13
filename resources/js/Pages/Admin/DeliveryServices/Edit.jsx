import { Head, Link, useForm, usePage, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import { usePermission } from '@/Hooks/usePermission';
import { useState } from 'react';

export default function DeliveryServiceEdit({ deliveryService, cities, citiesWithoutPricing }) {
    const { can } = usePermission();
    const { flash } = usePage().props;
    const [showAddPricing, setShowAddPricing] = useState(false);
    const [newPricing, setNewPricing] = useState({ city_id: '', fee: '', min_days: '', max_days: '', is_active: true });

    const { data, setData, put, processing, errors } = useForm({
        name: deliveryService.name || '',
        code: deliveryService.code || '',
        description: deliveryService.description || '',
        base_fee: deliveryService.base_fee || 0,
        fee_per_kg: deliveryService.fee_per_kg || '',
        min_days: deliveryService.min_days || 1,
        max_days: deliveryService.max_days || 3,
        is_active: deliveryService.is_active ?? true,
        sort_order: deliveryService.sort_order || 0,
    });

    function handleSubmit(e) {
        e.preventDefault();
        put(adminUrl(`/admin/delivery-services/${deliveryService.id}`));
    }

    function handleAddPricing(e) {
        e.preventDefault();
        router.post(adminUrl(`/admin/delivery-services/${deliveryService.id}/add-city-pricing`), newPricing, {
            onSuccess: () => {
                setShowAddPricing(false);
                setNewPricing({ city_id: '', fee: '', min_days: '', max_days: '', is_active: true });
            }
        });
    }

    function handleRemovePricing(pricingId) {
        if (confirm('Remove this city pricing?')) {
            router.delete(adminUrl(`/admin/delivery-services/pricing/${pricingId}`));
        }
    }

    if (!can('delivery-services.update')) {
        return (
            <AdminLayout>
                <Head title="Unauthorized" />
                <div className="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    <div className="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
                        <p className="text-red-700 font-medium">You do not have permission to edit delivery services.</p>
                    </div>
                </div>
            </AdminLayout>
        );
    }

    return (
        <AdminLayout>
            <Head title={`Edit ${deliveryService.name}`} />
            <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div className="mb-6">
                    <Link href={adminUrl('/admin/storefront/checkout')} className="text-sm text-blue-600 hover:underline">&larr; Back to Checkout</Link>
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-2">Edit Delivery Service</h1>
                </div>

                {flash?.success && (
                    <div className="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm">
                        {flash.success}
                    </div>
                )}

                <div className="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800 p-6 mb-6">
                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
                                {processing ? 'Saving...' : 'Save Changes'}
                            </button>
                        </div>
                    </form>
                </div>

                <div className="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800 p-6">
                    <div className="flex justify-between items-center mb-4">
                        <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">City-Specific Pricing</h2>
                        {!showAddPricing && citiesWithoutPricing?.length > 0 && (
                            <button onClick={() => setShowAddPricing(true)}
                                className="px-3 py-1.5 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                Add City Pricing
                            </button>
                        )}
                    </div>

                    {showAddPricing && (
                        <form onSubmit={handleAddPricing} className="mb-6 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                                <div>
                                    <label htmlFor="city_id" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">City</label>
                                    <select id="city_id" value={newPricing.city_id} onChange={(e) => setNewPricing({ ...newPricing, city_id: e.target.value })}
                                        className="w-full border border-gray-300 dark:border-gray-700 rounded-lg px-3 py-2 text-sm" required>
                                        <option value="">Select City</option>
                                        {citiesWithoutPricing.map(city => (
                                            <option key={city.id} value={city.id}>{city.name}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label htmlFor="fee" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fee Override</label>
                                    <input id="fee" type="number" min="0" value={newPricing.fee} onChange={(e) => setNewPricing({ ...newPricing, fee: e.target.value ? parseInt(e.target.value) : '' })}
                                        className="w-full border border-gray-300 dark:border-gray-700 rounded-lg px-3 py-2 text-sm" />
                                </div>
                                <div>
                                    <label htmlFor="min_days" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Min Days</label>
                                    <input id="min_days" type="number" min="0" value={newPricing.min_days} onChange={(e) => setNewPricing({ ...newPricing, min_days: e.target.value ? parseInt(e.target.value) : '' })}
                                        className="w-full border border-gray-300 dark:border-gray-700 rounded-lg px-3 py-2 text-sm" />
                                </div>
                                <div>
                                    <label htmlFor="max_days" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Max Days</label>
                                    <input id="max_days" type="number" min="0" value={newPricing.max_days} onChange={(e) => setNewPricing({ ...newPricing, max_days: e.target.value ? parseInt(e.target.value) : '' })}
                                        className="w-full border border-gray-300 dark:border-gray-700 rounded-lg px-3 py-2 text-sm" />
                                </div>
                            </div>
                            <div className="flex gap-2">
                                <button type="submit" className="px-3 py-1.5 text-sm bg-green-600 text-white rounded-lg hover:bg-green-700">Save</button>
                                <button type="button" onClick={() => setShowAddPricing(false)} className="px-3 py-1.5 text-sm bg-gray-500 text-white rounded-lg hover:bg-gray-600">Cancel</button>
                            </div>
                        </form>
                    )}

                    {deliveryService.pricing?.length > 0 ? (
                        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                            <thead className="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">City</th>
                                    <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Fee Override</th>
                                    <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Days</th>
                                    <th className="px-4 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Active</th>
                                    <th className="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                {deliveryService.pricing.map(pricing => (
                                    <tr key={pricing.id}>
                                        <td className="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{pricing.city?.name || `City #${pricing.city_id}`}</td>
                                        <td className="px-4 py-2 text-sm text-gray-600 dark:text-gray-400">{pricing.fee ?? '-'}</td>
                                        <td className="px-4 py-2 text-sm text-gray-600 dark:text-gray-400">
                                            {pricing.min_days || deliveryService.min_days}-{pricing.max_days || deliveryService.max_days}
                                        </td>
                                        <td className="px-4 py-2 text-center">
                                            <span className={`px-2 py-0.5 rounded-full text-xs ${pricing.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                                                {pricing.is_active ? 'Active' : 'Inactive'}
                                            </span>
                                        </td>
                                        <td className="px-4 py-2 text-right">
                                            <button onClick={() => handleRemovePricing(pricing.id)} className="text-red-600 hover:text-red-800 text-sm">Remove</button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    ) : (
                        <p className="text-sm text-gray-500 dark:text-gray-400">No city-specific pricing configured. This service uses base fees for all cities.</p>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
