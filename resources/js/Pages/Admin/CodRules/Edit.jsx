import { Head, useForm, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import { usePermission } from '@/Hooks/usePermission';
import { formatCurrency, getCurrencyConfig } from '@/Utils/currency';
import { useState } from 'react';
import { BackLink, CancelLink, CheckboxRow, FIELD_CHECKBOX, FormCard, FormGroup, Notice, PageHeader, PrimaryButton, TextInput, UnauthorizedState } from '@/Components/Admin/StorefrontUI';

export default function CodRuleEdit({ codRule, cities }) {
    const { can } = usePermission();
    const cc = getCurrencyConfig(usePage().props.platform_setting, usePage().props.website_info);
    const { flash } = usePage().props;

    const [selectedAllowed, setSelectedAllowed] = useState(codRule.allowed_city_ids || []);
    const [selectedExcluded, setSelectedExcluded] = useState(codRule.excluded_city_ids || []);

    const { data, setData, put, processing, errors } = useForm({
        name: codRule.name || '',
        min_order_amount: codRule.min_order_amount || '',
        max_order_amount: codRule.max_order_amount || '',
        cod_fee: codRule.cod_fee || 0,
        apply_cod_fee_to_total: codRule.apply_cod_fee_to_total ?? true,
        is_active: codRule.is_active ?? true,
    });

    function handleSubmit(e) {
        e.preventDefault();
        setData('allowed_city_ids', selectedAllowed);
        setData('excluded_city_ids', selectedExcluded);
        put(adminUrl(`/admin/cod-rules/${codRule.id}`));
    }

    function toggleCity(list, setList, cityId) {
        if (list.includes(cityId)) {
            setList(list.filter(id => id !== cityId));
        } else {
            setList([...list, cityId]);
        }
    }

    if (!can('cod-rules.update')) {
        return (
            <AdminLayout>
                <Head title="Unauthorized" />
                <UnauthorizedState message="You do not have permission to edit COD rules." />
            </AdminLayout>
        );
    }

    return (
        <AdminLayout>
            <Head title={`Edit ${codRule.name}`} />
            <div className="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-6 lg:py-8">
                <BackLink href={adminUrl('/admin/storefront/checkout')}>Back to Checkout</BackLink>
                <PageHeader eyebrow="COD Rules" title="Edit COD Rule" />

                {flash?.success && (
                    <Notice tone="success" className="mb-4">{flash.success}</Notice>
                )}

                <FormCard>
                    <form onSubmit={handleSubmit} className="space-y-6">
                        <FormGroup title="Rule">
                            <TextInput id="name" label="Name" type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} error={errors.name} required />
                        </FormGroup>

                        <FormGroup title="Order Amount" description="Eligible order total range. Leave empty for no limit.">
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <TextInput id="min_order_amount" label="Min Order Amount" type="number" min="0" step="0.01" value={data.min_order_amount} onChange={(e) => setData('min_order_amount', e.target.value)} error={errors.min_order_amount} />

                                <TextInput id="max_order_amount" label="Max Order Amount" type="number" min="0" step="0.01" value={data.max_order_amount} onChange={(e) => setData('max_order_amount', e.target.value)} error={errors.max_order_amount} />
                            </div>
                        </FormGroup>

                        {errors.city_restrictions && (
                            <Notice tone="error" className="p-3">{errors.city_restrictions}</Notice>
                        )}

                        <FormGroup title="Cities" description="Control which cities this rule applies to.">
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Allowed Cities</label>
                                    <div className="border border-gray-300 dark:border-gray-700 rounded-xl bg-gray-50 dark:bg-gray-800/50 p-3 max-h-48 overflow-y-auto space-y-1">
                                        {cities.length === 0 ? (
                                            <p className="text-sm text-gray-500">No cities available</p>
                                        ) : cities.map((city) => (
                                            <label key={city.id} className="flex items-center gap-2 text-sm rounded-lg px-2 py-1 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer transition-colors">
                                                <input type="checkbox"
                                                    checked={selectedAllowed.includes(city.id)}
                                                    onChange={() => toggleCity(selectedAllowed, setSelectedAllowed, city.id)}
                                                    className={FIELD_CHECKBOX} />
                                                <span className="text-gray-700 dark:text-gray-300">{city.name}</span>
                                            </label>
                                        ))}
                                    </div>
                                    <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">Leave empty to allow all cities</p>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Excluded Cities</label>
                                    <div className="border border-gray-300 dark:border-gray-700 rounded-xl bg-gray-50 dark:bg-gray-800/50 p-3 max-h-48 overflow-y-auto space-y-1">
                                        {cities.length === 0 ? (
                                            <p className="text-sm text-gray-500">No cities available</p>
                                        ) : cities.map((city) => (
                                            <label key={city.id} className="flex items-center gap-2 text-sm rounded-lg px-2 py-1 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer transition-colors">
                                                <input type="checkbox"
                                                    checked={selectedExcluded.includes(city.id)}
                                                    onChange={() => toggleCity(selectedExcluded, setSelectedExcluded, city.id)}
                                                    className={FIELD_CHECKBOX} />
                                                <span className="text-gray-700 dark:text-gray-300">{city.name}</span>
                                            </label>
                                        ))}
                                    </div>
                                    <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">Leave empty to exclude no cities</p>
                                </div>
                            </div>
                        </FormGroup>

                        <FormGroup title="Fee" description="Extra charge for cash-on-delivery orders.">
                            <div className="space-y-4">
                                <TextInput id="cod_fee" label="COD Fee" type="number" min="0" step="0.01" value={data.cod_fee} onChange={(e) => setData('cod_fee', parseFloat(e.target.value) || 0)} error={errors.cod_fee} required />

                                <CheckboxRow id="apply_cod_fee_to_total" label="Apply COD fee to total" checked={data.apply_cod_fee_to_total} onChange={(e) => setData('apply_cod_fee_to_total', e.target.checked)} />
                            </div>
                        </FormGroup>

                        <FormGroup title="Visibility">
                            <CheckboxRow id="is_active" label="Active" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} />
                        </FormGroup>

                        <div className="flex justify-end gap-3 pt-2 border-t border-gray-100 dark:border-gray-800">
                            <CancelLink href={adminUrl('/admin/storefront/checkout')} />
                            <PrimaryButton disabled={processing}>
                                {processing ? 'Saving...' : 'Save Changes'}
                            </PrimaryButton>
                        </div>
                    </form>
                </FormCard>
            </div>
        </AdminLayout>
    );
}
