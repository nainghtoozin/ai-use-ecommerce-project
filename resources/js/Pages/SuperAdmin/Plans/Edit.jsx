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

export default function EditPlan({ plan, allFeatures = [] }) {
    const [form, setForm] = useState({
        name: plan.name,
        slug: plan.slug,
        description: plan.description || '',
        monthly_price: plan.monthly_price ?? '',
        yearly_price: plan.yearly_price ?? '',
        product_limit: plan.product_limit ?? '',
        staff_limit: plan.staff_limit ?? '',
        storage_limit: plan.storage_limit ?? '',
        orders_monthly_limit: plan.orders_monthly_limit ?? '',
        coupon_limit: plan.coupon_limit ?? '',
        promotion_limit: plan.promotion_limit ?? '',
        flash_sale_limit: plan.flash_sale_limit ?? '',
        api_request_limit: plan.api_request_limit ?? '',
        image_limit: plan.image_limit ?? '',
        image_max_size_kb: plan.image_max_size_kb ?? '',
        branch_limit: plan.branch_limit ?? '',
        warehouse_limit: plan.warehouse_limit ?? '',
        pos_device_limit: plan.pos_device_limit ?? '',
        analytics_enabled: plan.analytics_enabled,
        custom_domain_enabled: plan.custom_domain_enabled,
        status: plan.status,
    });
    const [features, setFeatures] = useState(
        Object.fromEntries(allFeatures.map(f => [f.key, f.enabled]))
    );
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);

    function handleChange(field, value) {
        setForm(prev => ({ ...prev, [field]: value }));
    }

    function handleFeatureToggle(key, checked) {
        setFeatures(prev => ({ ...prev, [key]: checked }));
    }

    function handleSubmit(e) {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        const featuresPayload = Object.entries(features).map(([key, enabled]) => ({
            key,
            enabled,
        }));

        router.put(`/superadmin/plans/${plan.id}`, {
            ...form,
            monthly_price: form.monthly_price === '' ? null : form.monthly_price,
            yearly_price: form.yearly_price === '' ? null : form.yearly_price,
            product_limit: form.product_limit === '' ? null : form.product_limit,
            staff_limit: form.staff_limit === '' ? null : form.staff_limit,
            storage_limit: form.storage_limit === '' ? null : form.storage_limit,
            orders_monthly_limit: form.orders_monthly_limit === '' ? null : form.orders_monthly_limit,
            coupon_limit: form.coupon_limit === '' ? null : form.coupon_limit,
            promotion_limit: form.promotion_limit === '' ? null : form.promotion_limit,
            flash_sale_limit: form.flash_sale_limit === '' ? null : form.flash_sale_limit,
            api_request_limit: form.api_request_limit === '' ? null : form.api_request_limit,
            image_limit: form.image_limit === '' ? null : form.image_limit,
            image_max_size_kb: form.image_max_size_kb === '' ? null : form.image_max_size_kb,
            branch_limit: form.branch_limit === '' ? null : form.branch_limit,
            warehouse_limit: form.warehouse_limit === '' ? null : form.warehouse_limit,
            pos_device_limit: form.pos_device_limit === '' ? null : form.pos_device_limit,
            features: featuresPayload,
        }, {
            onSuccess: () => setProcessing(false),
            onError: (errs) => {
                setErrors(errs);
                setProcessing(false);
            },
        });
    }

    const featureCategories = [
        { label: 'Product Features', keys: ['single_products', 'variable_products', 'combo_products', 'digital_products'] },
        { label: 'Analytics', keys: ['reports'] },
        { label: 'Store Features', keys: ['custom_domain', 'advanced_seo', 'theme_editor', 'custom_css', 'maintenance_mode'] },
        { label: 'Customer Features', keys: ['reviews', 'wishlist', 'compare'] },
        { label: 'Marketing', keys: ['coupons', 'promotions', 'flash_sales'] },
        { label: 'Integrations', keys: ['telegram_integration', 'whatsapp_integration', 'social_media_integration', 'google_analytics', 'meta_pixel', 'mailchimp_integration'] },
        { label: 'AI', keys: ['ai_product_generator', 'ai_description', 'ai_seo', 'ai_translation'] },
        { label: 'Payment Gateways', keys: ['payment_gateways_cod', 'payment_gateways_kbzpay', 'payment_gateways_wavepay', 'payment_gateways_stripe', 'payment_gateways_paypal', 'payment_gateways_manual'] },
    ];

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
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3 mt-4">
                                <Input field="orders_monthly_limit" label="Monthly Orders" type="number" placeholder="e.g. 500" helpText="Leave empty for unlimited." form={form} errors={errors} handleChange={handleChange} />
                                <Input field="coupon_limit" label="Coupons" type="number" placeholder="e.g. 20" helpText="Leave empty for unlimited." form={form} errors={errors} handleChange={handleChange} />
                                <Input field="promotion_limit" label="Promotions" type="number" placeholder="e.g. 10" helpText="Leave empty for unlimited." form={form} errors={errors} handleChange={handleChange} />
                            </div>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3 mt-4">
                                <Input field="flash_sale_limit" label="Flash Sales" type="number" placeholder="e.g. 5" helpText="Leave empty for unlimited." form={form} errors={errors} handleChange={handleChange} />
                                <Input field="api_request_limit" label="API Requests" type="number" placeholder="e.g. 10000" helpText="Leave empty for unlimited." form={form} errors={errors} handleChange={handleChange} />
                                <Input field="image_limit" label="Images per Product" type="number" placeholder="e.g. 10" helpText="Leave empty for unlimited." form={form} errors={errors} handleChange={handleChange} />
                            </div>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3 mt-4">
                                <Input field="image_max_size_kb" label="Max Image Size (KB)" type="number" placeholder="e.g. 2048" helpText="Leave empty for unlimited." form={form} errors={errors} handleChange={handleChange} />
                                <Input field="branch_limit" label="Branches" type="number" placeholder="e.g. 3" helpText="Leave empty for unlimited." form={form} errors={errors} handleChange={handleChange} />
                                <Input field="warehouse_limit" label="Warehouses" type="number" placeholder="e.g. 2" helpText="Leave empty for unlimited." form={form} errors={errors} handleChange={handleChange} />
                            </div>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3 mt-4">
                                <Input field="pos_device_limit" label="POS Devices" type="number" placeholder="e.g. 3" helpText="Leave empty for unlimited." form={form} errors={errors} handleChange={handleChange} />
                            </div>
                            </div>

                            <div className="border-b border-gray-200 pb-6">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">Features</h3>

                                <div className="space-y-6">
                                    {featureCategories.map(cat => (
                                        <div key={cat.label}>
                                            <h4 className="text-sm font-semibold text-gray-800 mb-2 uppercase tracking-wider">{cat.label}</h4>
                                            <div className="space-y-2">
                                                {cat.keys.map(key => {
                                                    const feature = allFeatures.find(f => f.key === key);
                                                    if (!feature) return null;
                                                    return (
                                                        <label key={key} className="flex items-center gap-3 cursor-pointer">
                                                            <input
                                                                type="checkbox"
                                                                checked={features[key] || false}
                                                                onChange={(e) => handleFeatureToggle(key, e.target.checked)}
                                                                className="rounded border-gray-300 text-blue-600 focus:ring-blue-500 w-4 h-4"
                                                            />
                                                            <div>
                                                                <span className="text-sm font-medium text-gray-700">{feature.label}</span>
                                                            </div>
                                                        </label>
                                                    );
                                                })}
                                            </div>
                                        </div>
                                    ))}
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
