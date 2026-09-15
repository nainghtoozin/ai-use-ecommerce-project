import { Head, useForm, usePage, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import { usePermission } from '@/Hooks/usePermission';
import { useState } from 'react';
import { BackLink, CancelLink, CheckboxRow, FormCard, FormGroup, Notice, OutlineButton, PageHeader, PrimaryButton, SectionHeader, SelectInput, StatusPill, SuccessButton, TH, TextInput, TextareaInput, UnauthorizedState } from '@/Components/Admin/StorefrontUI';

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
                <UnauthorizedState message="You do not have permission to edit delivery services." />
            </AdminLayout>
        );
    }

    return (
        <AdminLayout>
            <Head title={`Edit ${deliveryService.name}`} />
            <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6 lg:py-8">
                <BackLink href={adminUrl('/admin/storefront/checkout')}>Back to Checkout</BackLink>
                <PageHeader eyebrow="Delivery Services" title="Edit Delivery Service" />

                {flash?.success && (
                    <Notice tone="success" className="mb-4">{flash.success}</Notice>
                )}

                <FormCard className="mb-6">
                    <form onSubmit={handleSubmit} className="space-y-6">
                        <FormGroup title="General" description="Name, code, and description shown at checkout.">
                            <div className="space-y-6">
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <TextInput id="name" label="Name" type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} error={errors.name} required />

                                    <TextInput id="code" label="Code" type="text" value={data.code} onChange={(e) => setData('code', e.target.value.toUpperCase())} error={errors.code} required />
                                </div>

                                <TextareaInput id="description" label="Description (optional)" value={data.description} onChange={(e) => setData('description', e.target.value)} error={errors.description} />
                            </div>
                        </FormGroup>

                        <FormGroup title="Fees" description="Base charge and optional per-kilogram rate.">
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <TextInput id="base_fee" label="Base Fee" type="number" min="0" value={data.base_fee} onChange={(e) => setData('base_fee', parseInt(e.target.value) || 0)} error={errors.base_fee} required />

                                <TextInput id="fee_per_kg" label="Fee per Kg (optional)" type="number" min="0" value={data.fee_per_kg || ''} onChange={(e) => setData('fee_per_kg', e.target.value ? parseInt(e.target.value) : '')} error={errors.fee_per_kg} />
                            </div>
                        </FormGroup>

                        <FormGroup title="Schedule" description="Delivery time window and display order.">
                            <div className="space-y-6">
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <TextInput id="min_days" label="Min Delivery Days" type="number" min="0" value={data.min_days} onChange={(e) => setData('min_days', parseInt(e.target.value) || 0)} error={errors.min_days} required />

                                    <TextInput id="max_days" label="Max Delivery Days" type="number" min="0" value={data.max_days} onChange={(e) => setData('max_days', parseInt(e.target.value) || 0)} error={errors.max_days} required />
                                </div>

                                <TextInput id="sort_order" label="Sort Order" type="number" min="0" value={data.sort_order} onChange={(e) => setData('sort_order', parseInt(e.target.value) || 0)} error={errors.sort_order} />
                            </div>
                        </FormGroup>

                        <FormGroup title="Visibility">
                            <CheckboxRow id="is_active" label="Active" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} />
                        </FormGroup>

                        <div className="flex justify-end gap-3 pt-2 border-t border-gray-100 dark:border-gray-800">
                            <CancelLink href={adminUrl('/admin/delivery-services')} />
                            <PrimaryButton disabled={processing}>
                                {processing ? 'Saving...' : 'Save Changes'}
                            </PrimaryButton>
                        </div>
                    </form>
                </FormCard>

                <FormCard>
                    <SectionHeader
                        title="City-Specific Pricing"
                        description="Override fees and delivery windows per city."
                        actions={!showAddPricing && citiesWithoutPricing?.length > 0 && (
                            <PrimaryButton size="sm" type="button" onClick={() => setShowAddPricing(true)}>
                                Add City Pricing
                            </PrimaryButton>
                        )}
                    />
                    <div className="mt-4">

                    {showAddPricing && (
                        <form onSubmit={handleAddPricing} className="mb-6 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                                <SelectInput id="city_id" label="City" value={newPricing.city_id} onChange={(e) => setNewPricing({ ...newPricing, city_id: e.target.value })} required>
                                    <option value="">Select City</option>
                                    {citiesWithoutPricing.map(city => (
                                        <option key={city.id} value={city.id}>{city.name}</option>
                                    ))}
                                </SelectInput>
                                <TextInput id="fee" label="Fee Override" type="number" min="0" value={newPricing.fee} onChange={(e) => setNewPricing({ ...newPricing, fee: e.target.value ? parseInt(e.target.value) : '' })} />
                                <TextInput id="min_days" label="Min Days" type="number" min="0" value={newPricing.min_days} onChange={(e) => setNewPricing({ ...newPricing, min_days: e.target.value ? parseInt(e.target.value) : '' })} />
                                <TextInput id="max_days" label="Max Days" type="number" min="0" value={newPricing.max_days} onChange={(e) => setNewPricing({ ...newPricing, max_days: e.target.value ? parseInt(e.target.value) : '' })} />
                            </div>
                            <div className="flex gap-2">
                                <SuccessButton size="sm" type="submit">Save</SuccessButton>
                                <OutlineButton size="sm" onClick={() => setShowAddPricing(false)}>Cancel</OutlineButton>
                            </div>
                        </form>
                    )}

                    {deliveryService.pricing?.length > 0 ? (
                        <div className="overflow-x-auto -mx-4 px-4 sm:mx-0 sm:px-0">
                        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                            <thead className="bg-gray-50 dark:bg-gray-950">
                                <tr>
                                    <TH>City</TH>
                                    <TH>Fee Override</TH>
                                    <TH>Days</TH>
                                    <TH align="center">Active</TH>
                                    <TH align="right">Actions</TH>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                {deliveryService.pricing.map(pricing => (
                                    <tr key={pricing.id}>
                                        <td className="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">{pricing.city?.name || `City #${pricing.city_id}`}</td>
                                        <td className="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{pricing.fee ?? '-'}</td>
                                        <td className="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                                            {pricing.min_days || deliveryService.min_days}-{pricing.max_days || deliveryService.max_days}
                                        </td>
                                        <td className="px-6 py-4 text-center">
                                            <StatusPill active={pricing.is_active} />
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            <button onClick={() => handleRemovePricing(pricing.id)} className="inline-block px-1 py-1 text-red-600 hover:text-red-800 text-sm">Remove</button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        </div>
                    ) : (
                        <p className="text-sm text-gray-500 dark:text-gray-400">No city-specific pricing configured. This service uses base fees for all cities.</p>
                    )}
                    </div>
                </FormCard>
            </div>
        </AdminLayout>
    );
}
