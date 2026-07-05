import { useState } from 'react';
import { Link, router, Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

function Input({ field, label, type = 'text', placeholder = '', required = false, helpText = null, form, errors, handleChange }) {
    const id = `field_${field}`;
    const value = form[field];
    return (
        <div>
            <label htmlFor={id} className="block text-sm font-medium text-gray-700 mb-1">
                {label} {required && '*'}
            </label>
            {type === 'textarea' ? (
                <textarea
                    id={id}
                    value={value ?? ''}
                    onChange={(e) => handleChange(field, e.target.value)}
                    className="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                    rows={4}
                />
            ) : type === 'file' ? (
                <input
                    id={id}
                    type="file"
                    accept="image/jpg,image/jpeg,image/png,image/webp"
                    onChange={(e) => handleChange(field, e.target.files[0])}
                    className="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                />
            ) : (
                <input
                    id={id}
                    type={type}
                    value={value ?? ''}
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

export default function EditBillingPaymentMethod({ paymentMethod }) {
    const [form, setForm] = useState({
        display_name: paymentMethod.display_name || '',
        type: paymentMethod.type || 'bank_transfer',
        account_name: paymentMethod.account_name || '',
        account_number: paymentMethod.account_number || '',
        bank_name: paymentMethod.bank_name || '',
        branch: paymentMethod.branch || '',
        instructions: paymentMethod.instructions || '',
        currency: paymentMethod.currency || '',
        qr_image: null,
        is_active: paymentMethod.is_active ?? true,
        is_default: paymentMethod.is_default ?? false,
        sort_order: paymentMethod.sort_order ?? '',
        supports_manual_payment: paymentMethod.supports_manual_payment ?? true,
        supports_gateway: paymentMethod.supports_gateway ?? false,
        gateway_code: paymentMethod.gateway_code || '',
    });
    const [qrPreview, setQrPreview] = useState(null);
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);

    function handleChange(field, value) {
        setForm(prev => ({ ...prev, [field]: value }));
        if (field === 'qr_image' && value) {
            const reader = new FileReader();
            reader.onloadend = () => setQrPreview(reader.result);
            reader.readAsDataURL(value);
        } else if (field === 'qr_image' && !value) {
            setQrPreview(null);
        }
    }

    function handleSubmit(e) {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        const formData = new FormData();
        formData.append('_method', 'PUT');
        formData.append('display_name', form.display_name);
        formData.append('type', form.type);
        formData.append('account_name', form.account_name || '');
        formData.append('account_number', form.account_number || '');
        formData.append('bank_name', form.bank_name || '');
        formData.append('branch', form.branch || '');
        formData.append('instructions', form.instructions || '');
        formData.append('currency', form.currency || '');
        formData.append('is_active', form.is_active ? '1' : '0');
        formData.append('is_default', form.is_default ? '1' : '0');
        formData.append('supports_manual_payment', form.supports_manual_payment ? '1' : '0');
        formData.append('supports_gateway', form.supports_gateway ? '1' : '0');
        formData.append('gateway_code', form.gateway_code || '');
        if (form.sort_order !== '') formData.append('sort_order', form.sort_order);
        if (form.qr_image) formData.append('qr_image', form.qr_image);

        router.post(`/superadmin/billing-payment-methods/${paymentMethod.id}`, formData, {
            forceFormData: true,
            onSuccess: () => setProcessing(false),
            onError: (errs) => {
                setErrors(errs);
                setProcessing(false);
            },
        });
    }

    const isBankTransfer = form.type === 'bank_transfer';
    const existingQr = paymentMethod.qr_image_url && !qrPreview ? paymentMethod.qr_image_url : null;

    return (
        <AdminLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Edit Billing Payment Method</h2>}>
            <Head title={`Edit ${paymentMethod.display_name}`} />

            <div className="py-6">
                <div className="max-w-3xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <form onSubmit={handleSubmit} className="p-6 space-y-6">
                            <div className="border-b border-gray-200 pb-6">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">Basic Information</h3>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <Input field="display_name" label="Display Name" required placeholder="e.g. KBZ Bank" form={form} errors={errors} handleChange={handleChange} />
                                    <div>
                                        <label htmlFor="field_type" className="block text-sm font-medium text-gray-700 mb-1">Type *</label>
                                        <select
                                            id="field_type"
                                            value={form.type}
                                            onChange={(e) => handleChange('type', e.target.value)}
                                            className="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                        >
                                            <option value="bank_transfer">Bank Transfer</option>
                                            <option value="cod">Cash on Delivery</option>
                                            <option value="gateway">Gateway</option>
                                        </select>
                                        {errors.type && <p className="text-xs text-red-600 mt-1">{errors.type}</p>}
                                    </div>
                                </div>
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 mt-4">
                                    <Input field="currency" label="Currency" placeholder="e.g. MMK" helpText="3-letter ISO code" form={form} errors={errors} handleChange={handleChange} />
                                    <Input field="sort_order" label="Sort Order" type="number" placeholder="0" helpText="Lower values appear first" form={form} errors={errors} handleChange={handleChange} />
                                </div>
                            </div>

                            {isBankTransfer && (
                                <div className="border-b border-gray-200 pb-6">
                                    <h3 className="text-lg font-medium text-gray-900 mb-4">Bank Account Details</h3>

                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <Input field="bank_name" label="Bank Name" placeholder="e.g. KBZ Bank" form={form} errors={errors} handleChange={handleChange} />
                                        <Input field="branch" label="Branch" placeholder="e.g. Main Branch" form={form} errors={errors} handleChange={handleChange} />
                                    </div>
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 mt-4">
                                        <Input field="account_name" label="Account Name" placeholder="e.g. Demo Company" form={form} errors={errors} handleChange={handleChange} />
                                        <Input field="account_number" label="Account Number" placeholder="e.g. 0123456789" form={form} errors={errors} handleChange={handleChange} />
                                    </div>
                                    <div className="mt-4">
                                        <Input field="instructions" label="Payment Instructions" type="textarea" placeholder="Enter payment instructions for merchants..." form={form} errors={errors} handleChange={handleChange} />
                                    </div>
                                    <div className="mt-4">
                                        <Input field="qr_image" label="QR Code Image" type="file" helpText="Upload a new QR code to replace the current one" form={form} errors={errors} handleChange={handleChange} />
                                        {(qrPreview || existingQr) && (
                                            <div className="mt-3">
                                                <p className="text-xs text-gray-500 mb-1">{qrPreview ? 'Preview:' : 'Current QR:'}</p>
                                                <img src={qrPreview || existingQr} alt="QR Code" className="w-24 h-24 rounded-lg border border-gray-200 object-cover" />
                                            </div>
                                        )}
                                    </div>
                                </div>
                            )}

                            {form.type === 'gateway' && (
                                <div className="border-b border-gray-200 pb-6">
                                    <h3 className="text-lg font-medium text-gray-900 mb-4">Gateway Configuration</h3>
                                    <Input field="gateway_code" label="Gateway Code" placeholder="e.g. stripe, paypal" helpText="The gateway identifier used by the system" form={form} errors={errors} handleChange={handleChange} />
                                    <div className="mt-4">
                                        <label className="flex items-center gap-2">
                                            <input
                                                type="checkbox"
                                                checked={form.supports_gateway}
                                                onChange={(e) => handleChange('supports_gateway', e.target.checked)}
                                                className="rounded border-gray-300 text-blue-600 focus:ring-blue-500 w-4 h-4"
                                            />
                                            <span className="text-sm font-medium text-gray-700">Supports Gateway</span>
                                        </label>
                                    </div>
                                </div>
                            )}

                            <div className="border-b border-gray-200 pb-6">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">Configuration</h3>
                                <div className="space-y-3">
                                    <label className="flex items-center gap-2">
                                        <input
                                            type="checkbox"
                                            checked={form.supports_manual_payment}
                                            onChange={(e) => handleChange('supports_manual_payment', e.target.checked)}
                                            className="rounded border-gray-300 text-blue-600 focus:ring-blue-500 w-4 h-4"
                                        />
                                        <span className="text-sm font-medium text-gray-700">Supports Manual Payment</span>
                                    </label>
                                </div>
                            </div>

                            <div>
                                <h3 className="text-lg font-medium text-gray-900 mb-4">Status</h3>
                                <div className="space-y-3">
                                    <label className="flex items-center gap-2">
                                        <input
                                            type="checkbox"
                                            checked={form.is_active}
                                            onChange={(e) => handleChange('is_active', e.target.checked)}
                                            className="rounded border-gray-300 text-blue-600 focus:ring-blue-500 w-4 h-4"
                                        />
                                        <span className="text-sm font-medium text-gray-700">Active</span>
                                    </label>
                                    <label className="flex items-center gap-2">
                                        <input
                                            type="checkbox"
                                            checked={form.is_default}
                                            onChange={(e) => handleChange('is_default', e.target.checked)}
                                            className="rounded border-gray-300 text-blue-600 focus:ring-blue-500 w-4 h-4"
                                        />
                                        <span className="text-sm font-medium text-gray-700">Default Method</span>
                                    </label>
                                </div>
                            </div>

                            <div className="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
                                <Link
                                    href="/superadmin/billing-payment-methods"
                                    className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                                >
                                    Cancel
                                </Link>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50"
                                >
                                    {processing ? 'Updating...' : 'Update Billing Payment Method'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
