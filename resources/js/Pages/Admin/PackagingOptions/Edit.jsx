import { Head, useForm, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import { usePermission } from '@/Hooks/usePermission';
import { BackLink, CancelLink, CheckboxRow, FormCard, FormGroup, Notice, PageHeader, PrimaryButton, TextInput, TextareaInput, UnauthorizedState } from '@/Components/Admin/StorefrontUI';

export default function PackagingOptionEdit({ packagingOption }) {
    const { can } = usePermission();
    const { flash } = usePage().props;

    const { data, setData, put, processing, errors } = useForm({
        name: packagingOption.name || '',
        code: packagingOption.code || '',
        description: packagingOption.description || '',
        fee: packagingOption.fee || 0,
        is_active: packagingOption.is_active ?? true,
        sort_order: packagingOption.sort_order || 0,
    });

    function handleSubmit(e) {
        e.preventDefault();
        put(adminUrl(`/admin/packaging-options/${packagingOption.id}`));
    }

    if (!can('packaging-options.update')) {
        return (
            <AdminLayout>
                <Head title="Unauthorized" />
                <UnauthorizedState message="You do not have permission to edit packaging options." />
            </AdminLayout>
        );
    }

    return (
        <AdminLayout>
            <Head title={`Edit ${packagingOption.name}`} />
            <div className="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-6 lg:py-8">
                <BackLink href={adminUrl('/admin/storefront/checkout')}>Back to Checkout</BackLink>
                <PageHeader eyebrow="Packaging Options" title="Edit Packaging Option" />

                {flash?.success && (
                    <Notice tone="success" className="mb-4">{flash.success}</Notice>
                )}

                <FormCard>
                    <form onSubmit={handleSubmit} className="space-y-6">
                        <FormGroup title="General" description="Name, code, and description shown at checkout.">
                            <div className="space-y-6">
                                <TextInput id="name" label="Name" type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} error={errors.name} required />

                                <TextInput id="code" label="Code" type="text" value={data.code} onChange={(e) => setData('code', e.target.value.toUpperCase())} error={errors.code} required />

                                <TextareaInput id="description" label="Description (optional)" value={data.description} onChange={(e) => setData('description', e.target.value)} error={errors.description} />
                            </div>
                        </FormGroup>

                        <FormGroup title="Pricing & Order" description="Fee charged and display order at checkout.">
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <TextInput id="fee" label="Fee" type="number" min="0" value={data.fee} onChange={(e) => setData('fee', parseInt(e.target.value) || 0)} error={errors.fee} required />

                                <TextInput id="sort_order" label="Sort Order" type="number" min="0" value={data.sort_order} onChange={(e) => setData('sort_order', parseInt(e.target.value) || 0)} error={errors.sort_order} />
                            </div>
                        </FormGroup>

                        <FormGroup title="Visibility">
                            <CheckboxRow id="is_active" label="Active" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} />
                        </FormGroup>

                        <div className="flex justify-end gap-3 pt-2 border-t border-gray-100 dark:border-gray-800">
                            <CancelLink href={adminUrl('/admin/packaging-options')} />
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
