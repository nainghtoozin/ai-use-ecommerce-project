import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import { usePermission } from '@/Hooks/usePermission';
import { formatCurrency, getCurrencyConfig } from '@/Utils/currency';
import { useState } from 'react';

export default function CodRuleCreate({ cities }) {
    const { can } = usePermission();
    const cc = getCurrencyConfig(usePage().props.platform_setting, usePage().props.website_info);

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        min_order_amount: '',
        max_order_amount: '',
        allowed_city_ids: [],
        excluded_city_ids: [],
        cod_fee: 0,
        apply_cod_fee_to_total: true,
        is_active: true,
    });

    const [selectedAllowed, setSelectedAllowed] = useState([]);
    const [selectedExcluded, setSelectedExcluded] = useState([]);

    function handleSubmit(e) {
        e.preventDefault();
        setData('allowed_city_ids', selectedAllowed);
        setData('excluded_city_ids', selectedExcluded);
        post(adminUrl('/admin/storefront/checkout'));
    }

    function toggleCity(list, setList, cityId) {
        if (list.includes(cityId)) {
            setList(list.filter(id => id !== cityId));
        } else {
            setList([...list, cityId]);
        }
    }

    if (!can('cod-rules.create')) {
        return (
            <AdminLayout>
                <Head title="Unauthorized" />
                <div className="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    <div className="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
                        <p className="text-red-700 font-medium">You do not have permission to create COD rules.</p>
                    </div>
                </div>
            </AdminLayout>
        );
    }

    return (
        <AdminLayout>
            <Head title="Create COD Rule" />
            <div className="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div className="mb-6">
                    <Link href={adminUrl('/admin/storefront/checkout')} className="text-sm text-blue-600 hover:underline">&larr; Back to Checkout</Link>
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-2">Create COD Rule</h1>
                </div>

                <div className="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800 p-6">
                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div>
                            <label htmlFor="name" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name</label>
                            <input id="name" type="text" value={data.name} onChange={(e) => setData('name', e.target.value)}
                                className="w-full border border-gray-300 dark:border-gray-700 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required />
                            {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label htmlFor="min_order_amount" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Min Order Amount</label>
                                <input id="min_order_amount" type="number" min="0" step="0.01" value={data.min_order_amount} onChange={(e) => setData('min_order_amount', e.target.value)}
                                    className="w-full border border-gray-300 dark:border-gray-700 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                {errors.min_order_amount && <p className="mt-1 text-sm text-red-600">{errors.min_order_amount}</p>}
                            </div>

                            <div>
                                <label htmlFor="max_order_amount" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Max Order Amount</label>
                                <input id="max_order_amount" type="number" min="0" step="0.01" value={data.max_order_amount} onChange={(e) => setData('max_order_amount', e.target.value)}
                                    className="w-full border border-gray-300 dark:border-gray-700 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                {errors.max_order_amount && <p className="mt-1 text-sm text-red-600">{errors.max_order_amount}</p>}
                            </div>
                        </div>

                        {errors.city_restrictions && (
                            <div className="bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-700">
                                {errors.city_restrictions}
                            </div>
                        )}

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Allowed Cities</label>
                                <div className="border border-gray-300 dark:border-gray-700 rounded-lg p-3 max-h-48 overflow-y-auto space-y-1">
                                    {cities.length === 0 ? (
                                        <p className="text-sm text-gray-500">No cities available</p>
                                    ) : cities.map((city) => (
                                        <label key={city.id} className="flex items-center gap-2 text-sm">
                                            <input type="checkbox"
                                                checked={selectedAllowed.includes(city.id)}
                                                onChange={() => toggleCity(selectedAllowed, setSelectedAllowed, city.id)}
                                                className="rounded border-gray-300 dark:border-gray-700" />
                                            <span className="text-gray-700 dark:text-gray-300">{city.name}</span>
                                        </label>
                                    ))}
                                </div>
                                <p className="mt-1 text-xs text-gray-500">Leave empty to allow all cities</p>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Excluded Cities</label>
                                <div className="border border-gray-300 dark:border-gray-700 rounded-lg p-3 max-h-48 overflow-y-auto space-y-1">
                                    {cities.length === 0 ? (
                                        <p className="text-sm text-gray-500">No cities available</p>
                                    ) : cities.map((city) => (
                                        <label key={city.id} className="flex items-center gap-2 text-sm">
                                            <input type="checkbox"
                                                checked={selectedExcluded.includes(city.id)}
                                                onChange={() => toggleCity(selectedExcluded, setSelectedExcluded, city.id)}
                                                className="rounded border-gray-300 dark:border-gray-700" />
                                            <span className="text-gray-700 dark:text-gray-300">{city.name}</span>
                                        </label>
                                    ))}
                                </div>
                                <p className="mt-1 text-xs text-gray-500">Leave empty to exclude no cities</p>
                            </div>
                        </div>

                        <div>
                            <label htmlFor="cod_fee" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">COD Fee</label>
                            <input id="cod_fee" type="number" min="0" step="0.01" value={data.cod_fee} onChange={(e) => setData('cod_fee', parseFloat(e.target.value) || 0)}
                                className="w-full border border-gray-300 dark:border-gray-700 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required />
                            {errors.cod_fee && <p className="mt-1 text-sm text-red-600">{errors.cod_fee}</p>}
                        </div>

                        <div className="flex items-center gap-2">
                            <input id="apply_cod_fee_to_total" type="checkbox" checked={data.apply_cod_fee_to_total} onChange={(e) => setData('apply_cod_fee_to_total', e.target.checked)}
                                className="rounded border-gray-300 dark:border-gray-700 text-blue-600 focus:ring-blue-500" />
                            <label htmlFor="apply_cod_fee_to_total" className="text-sm font-medium text-gray-700 dark:text-gray-300">Apply COD fee to total</label>
                        </div>

                        <div className="flex items-center gap-2">
                            <input id="is_active" type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)}
                                className="rounded border-gray-300 dark:border-gray-700 text-blue-600 focus:ring-blue-500" />
                            <label htmlFor="is_active" className="text-sm font-medium text-gray-700 dark:text-gray-300">Active</label>
                        </div>

                        <div className="flex justify-end gap-3">
                            <Link href={adminUrl('/admin/storefront/checkout')} className="px-4 py-2 text-gray-600 hover:text-gray-800 dark:text-gray-200">Cancel</Link>
                            <button type="submit" disabled={processing}
                                className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
                                {processing ? 'Creating...' : 'Create COD Rule'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AdminLayout>
    );
}
