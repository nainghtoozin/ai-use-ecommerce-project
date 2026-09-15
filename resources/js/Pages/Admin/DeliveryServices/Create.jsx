import { Head, useForm, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import { usePermission } from '@/Hooks/usePermission';
import { BackLink, CancelLink, CheckboxRow, FormCard, FormGroup, PageHeader, PrimaryButton, TextInput, TextareaInput, UnauthorizedState } from '@/Components/Admin/StorefrontUI';

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
                <UnauthorizedState message="You do not have permission to create delivery services." />
            </AdminLayout>
        );
    }

    return (
        <AdminLayout>
            <Head title="Create Delivery Service" />
            <div className="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-6 lg:py-8">
                <BackLink href={adminUrl('/admin/storefront/checkout')}>Back to Checkout</BackLink>
                <PageHeader eyebrow="Delivery Services" title="Create Delivery Service" />

                <FormCard>
                    <form onSubmit={handleSubmit} className="space-y-6">
                        <FormGroup title="General" description="Name, code, and description shown at checkout.">
                            <div className="space-y-6">
                                <TextInput id="name" label="Name" type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} error={errors.name} required />

                                <TextInput id="code" label="Code" type="text" value={data.code} onChange={(e) => setData('code', e.target.value.toUpperCase())} error={errors.code} required />

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
                                {processing ? 'Creating...' : 'Create Delivery Service'}
                            </PrimaryButton>
                        </div>
                    </form>
                </FormCard>
            </div>
        </AdminLayout>
    );
}
