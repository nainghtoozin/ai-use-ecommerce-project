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

export default function CreatePaymentMethod() {
    const [form, setForm] = useState({
        name: '',
        display_name: '',
        slug: '',
        type: 'bank_transfer',
        account_name: '',
        account_number: '',
        bank_name: '',
        branch: '',
        instructions: '',
        currency: '',
        qr_image: null,
        is_active: true,
        sort_order: '',
    });
    const [qrPreview, setQrPreview] = useState(null);
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);

    function handleChange(field, value) {
        setForm(prev => {
            const updated = { ...prev, [field]: value };
            if (field === 'name' && !prev.slug) {
                updated.slug = value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
            }
            return updated;
        });
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
        formData.append('name', form.name);
        formData.append('display_name', form.display_name || '');
        formData.append('slug', form.slug || '');
        formData.append('type', form.type);
        formData.append('account_name', form.account_name || '');
        formData.append('account_number', form.account_number || '');
        formData.append('bank_name', form.bank_name || '');
        formData.append('branch', form.branch || '');
        formData.append('instructions', form.instructions || '');
        formData.append('currency', form.currency || '');
        formData.append('is_active', form.is_active ? '1' : '0');
        if (form.sort_order !== '') formData.append('sort_order', form.sort_order);
        if (form.qr_image) formData.append('qr_image', form.qr_image);

        router.post('/superadmin/payment-methods', formData, {
            forceFormData: true,
            onSuccess: () => setProcessing(false),
            onError: (errs) => {
                setErrors(errs);
                setProcessing(false);
            },
        });
    }

    const isBankTransfer = form.type === 'bank_transfer';

    return (
        <AdminLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Create Payment Method</h2>}>
            <Head title="Create Payment Method" />

            <div className="py-6">
                <div className="max-w-3xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <form onSubmit={handleSubmit} className="p-6 space-y-6">
                            <div className="border-b border-gray-200 pb-6">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">Basic Information</h3>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <Input field="name" label="Name" required placeholder="e.g. KBZ Bank Transfer" form={form} errors={errors} handleChange={handleChange} />
                                    <Input field="display_name" label="Display Name" placeholder="e.g. KBZ Bank" helpText="Shown to customers on checkout" form={form} errors={errors} handleChange={handleChange} />
                                </div>
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 mt-4">
                                    <Input field="slug" label="Slug" placeholder="kbz-bank-transfer" helpText="Auto-generated from name" form={form} errors={errors} handleChange={handleChange} />
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
                                        <Input field="account_name" label="Account Name" placeholder="e.g. John Doe" form={form} errors={errors} handleChange={handleChange} />
                                        <Input field="account_number" label="Account Number" placeholder="e.g. 0123456789" form={form} errors={errors} handleChange={handleChange} />
                                    </div>
                                    <div className="mt-4">
                                        <Input field="instructions" label="Payment Instructions" type="textarea" placeholder="Enter payment instructions for customers..." form={form} errors={errors} handleChange={handleChange} />
                                    </div>
                                    <div className="mt-4">
                                        <Input field="qr_image" label="QR Code Image" type="file" helpText="Upload a QR code image for this payment method" form={form} errors={errors} handleChange={handleChange} />
                                        {qrPreview && (
                                            <div className="mt-3">
                                                <p className="text-xs text-gray-500 mb-1">Preview:</p>
                                                <img src={qrPreview} alt="QR Preview" className="w-24 h-24 rounded-lg border border-gray-200 object-cover" />
                                            </div>
                                        )}
                                    </div>
                                </div>
                            )}

                            {!isBankTransfer && (
                                <div className="border-b border-gray-200 pb-6">
                                    <h3 className="text-lg font-medium text-gray-900 mb-4">Payment Type</h3>
                                    <div className="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3">
                                        <p className="text-sm text-blue-700">
                                            Cash on Delivery requires no bank account details. Customers pay when their order is delivered.
                                        </p>
                                    </div>
                                </div>
                            )}

                            <div>
                                <h3 className="text-lg font-medium text-gray-900 mb-4">Status</h3>
                                <label className="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        checked={form.is_active}
                                        onChange={(e) => handleChange('is_active', e.target.checked)}
                                        className="rounded border-gray-300 text-blue-600 focus:ring-blue-500 w-4 h-4"
                                    />
                                    <span className="text-sm font-medium text-gray-700">Active</span>
                                </label>
                                <p className="text-xs text-gray-400 mt-1">
                                    Inactive methods are hidden from merchant checkout pages.
                                </p>
                            </div>

                            <div className="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
                                <Link
                                    href="/superadmin/payment-methods"
                                    className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                                >
                                    Cancel
                                </Link>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50"
                                >
                                    {processing ? 'Creating...' : 'Create Payment Method'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
