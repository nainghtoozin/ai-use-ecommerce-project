import { useState } from 'react';
import { Link, router, Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

function Input({ field, label, type = 'text', placeholder = '', required = false, helpText = null, form, errors, handleChange }) {
    const id = `field_${field}`;
    return (
        <div>
            <label htmlFor={id} className="block text-sm font-medium text-gray-700 mb-1">
                {label} {required && '*'}
            </label>
            {type === 'textarea' ? (
                <textarea
                    id={id}
                    value={form[field]}
                    onChange={(e) => handleChange(field, e.target.value)}
                    className="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                    rows={3}
                />
            ) : (
                <input
                    id={id}
                    type={type}
                    value={form[field]}
                    onChange={(e) => handleChange(field, type === 'checkbox' ? e.target.checked : e.target.value)}
                    className={`w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm ${type === 'checkbox' ? 'w-4 h-4' : ''}`}
                    placeholder={placeholder}
                    required={required}
                />
            )}
            {helpText && <p className="text-xs text-gray-400 mt-1">{helpText}</p>}
            {errors[field] && <p className="text-xs text-red-600 mt-1">{errors[field]}</p>}
        </div>
    );
}

export default function EditPlan({ plan }) {
    const [form, setForm] = useState({
        name: plan.name,
        slug: plan.slug,
        description: plan.description || '',
        monthly_price: plan.monthly_price ?? '',
        yearly_price: plan.yearly_price ?? '',
        product_limit: plan.product_limit ?? '',
        staff_limit: plan.staff_limit ?? '',
        storage_limit: plan.storage_limit ?? '',
        analytics_enabled: plan.analytics_enabled,
        custom_domain_enabled: plan.custom_domain_enabled,
        status: plan.status,
    });
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);

    function handleChange(field, value) {
        setForm(prev => ({ ...prev, [field]: value }));
    }

    function handleSubmit(e) {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        router.put(`/superadmin/plans/${plan.id}`, {
            ...form,
            monthly_price: form.monthly_price === '' ? null : form.monthly_price,
            yearly_price: form.yearly_price === '' ? null : form.yearly_price,
            product_limit: form.product_limit === '' ? null : form.product_limit,
            staff_limit: form.staff_limit === '' ? null : form.staff_limit,
            storage_limit: form.storage_limit === '' ? null : form.storage_limit,
        }, {
            onSuccess: () => setProcessing(false),
            onError: (errs) => {
                setErrors(errs);
                setProcessing(false);
            },
        });
    }

    return (
        <AdminLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Edit Plan: {plan.name}</h2>}>
            <Head title={`Edit Plan - ${plan.name}`} />

            <div className="py-6">
                <div className="max-w-3xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <form onSubmit={handleSubmit} className="p-6 space-y-6">
                            <div className="border-b border-gray-200 pb-6">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">Plan Details</h3>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <Input field="name" label="Plan Name" required placeholder="Starter" form={form} errors={errors} handleChange={handleChange} />
                                    <Input field="slug" label="Slug" required placeholder="starter" helpText="URL-safe identifier." form={form} errors={errors} handleChange={handleChange} />
                                </div>

                                <div className="mt-4">
                                    <Input field="description" label="Description" type="textarea" placeholder="For growing stores..." form={form} errors={errors} handleChange={handleChange} />
                                </div>
                            </div>

                            <div className="border-b border-gray-200 pb-6">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">Pricing</h3>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <Input field="monthly_price" label="Monthly Price ($)" type="number" placeholder="29" helpText="Set to 0 for free plan. Leave empty if not available." form={form} errors={errors} handleChange={handleChange} />
                                    <Input field="yearly_price" label="Yearly Price ($)" type="number" placeholder="290" helpText="Leave empty if not available." form={form} errors={errors} handleChange={handleChange} />
                                </div>
                            </div>

                            <div className="border-b border-gray-200 pb-6">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">Limits</h3>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <Input field="product_limit" label="Product Limit" type="number" placeholder="e.g. 100" helpText="Leave empty for unlimited." form={form} errors={errors} handleChange={handleChange} />
                                    <Input field="staff_limit" label="Staff Accounts" type="number" placeholder="e.g. 10" helpText="Leave empty for unlimited." form={form} errors={errors} handleChange={handleChange} />
                                    <Input field="storage_limit" label="Storage (MB)" type="number" placeholder="e.g. 1000" helpText="Leave empty for unlimited." form={form} errors={errors} handleChange={handleChange} />
                                </div>
                            </div>

                            <div className="border-b border-gray-200 pb-6">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">Features</h3>

                                <div className="space-y-3">
                                    <label className="flex items-center gap-3">
                                        <input
                                            type="checkbox"
                                            checked={form.analytics_enabled}
                                            onChange={(e) => handleChange('analytics_enabled', e.target.checked)}
                                            className="rounded border-gray-300 text-blue-600 focus:ring-blue-500 w-4 h-4"
                                        />
                                        <div>
                                            <span className="text-sm font-medium text-gray-700">Analytics Dashboard</span>
                                            <p className="text-xs text-gray-400">Sales reports, revenue charts, and growth metrics</p>
                                        </div>
                                    </label>
                                    <label className="flex items-center gap-3">
                                        <input
                                            type="checkbox"
                                            checked={form.custom_domain_enabled}
                                            onChange={(e) => handleChange('custom_domain_enabled', e.target.checked)}
                                            className="rounded border-gray-300 text-blue-600 focus:ring-blue-500 w-4 h-4"
                                        />
                                        <div>
                                            <span className="text-sm font-medium text-gray-700">Custom Domain</span>
                                            <p className="text-xs text-gray-400">Use your own domain name for the store</p>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <div>
                                <h3 className="text-lg font-medium text-gray-900 mb-4">Status</h3>
                                <div className="flex gap-4">
                                    {['active', 'inactive', 'deprecated'].map((s) => (
                                        <label key={s} className="flex items-center gap-2">
                                            <input
                                                type="radio"
                                                name="status"
                                                value={s}
                                                checked={form.status === s}
                                                onChange={(e) => handleChange('status', e.target.value)}
                                                className="border-gray-300 text-blue-600 focus:ring-blue-500"
                                            />
                                            <span className="text-sm text-gray-700 capitalize">{s}</span>
                                        </label>
                                    ))}
                                </div>
                                <p className="text-xs text-gray-400 mt-2">
                                    Active: available for new subscriptions. Inactive: hidden from signup, existing subscribers keep it. Deprecated: existing subscribers keep it, shows upgrade prompt.
                                </p>
                            </div>

                            <div className="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
                                <Link
                                    href="/superadmin/plans"
                                    className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                                >
                                    Cancel
                                </Link>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50"
                                >
                                    {processing ? 'Saving...' : 'Save Changes'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
