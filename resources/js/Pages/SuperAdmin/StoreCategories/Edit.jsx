import { useState } from 'react';
import { Link, router, Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

function Input({ field, label, type = 'text', placeholder = '', required = false, helpText = null, form, errors, handleChange }) {
    const id = `field_${field}`;
    const value = form[field];
    return (
        <div>
            <label htmlFor={id} className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                {label} {required && '*'}
            </label>
            {type === 'textarea' ? (
                <textarea
                    id={id}
                    value={value ?? ''}
                    onChange={(e) => handleChange(field, e.target.value)}
                    className="w-full rounded-lg border-gray-300 dark:border-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                    rows={4}
                    placeholder={placeholder}
                />
            ) : (
                <input
                    id={id}
                    type={type}
                    value={value ?? ''}
                    onChange={(e) => handleChange(field, type === 'checkbox' ? e.target.checked : e.target.value)}
                    className={`w-full rounded-lg border-gray-300 dark:border-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm ${type === 'checkbox' ? 'w-4 h-4' : ''}`}
                    placeholder={placeholder}
                    required={required}
                />
            )}
            {helpText && <p className="text-xs text-gray-400 dark:text-gray-500 mt-1">{helpText}</p>}
            {errors[field] && <p className="text-xs text-red-600 mt-1">{errors[field]}</p>}
        </div>
    );
}

export default function EditStoreCategory({ category }) {
    const assignedCount = category?.tenants_count || 0;
    const [form, setForm] = useState({
        name: category?.name || '',
        slug: category?.slug || '',
        description: category?.description || '',
        is_active: category?.is_active ?? true,
        sort_order: category?.sort_order ?? '',
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

        router.put(`/superadmin/store-categories/${category.id}`, {
            name: form.name,
            slug: form.slug || '',
            description: form.description || '',
            is_active: form.is_active,
            sort_order: form.sort_order === '' ? undefined : form.sort_order,
        }, {
            onSuccess: () => setProcessing(false),
            onError: (errs) => {
                setErrors(errs);
                setProcessing(false);
            },
        });
    }

    return (
        <AdminLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Edit Store Category</h2>}>
            <Head title="Edit Store Category" />

            <div className="py-6">
                <div className="max-w-3xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white dark:bg-gray-900 overflow-hidden shadow-sm sm:rounded-lg">
                        <form onSubmit={handleSubmit} className="p-6 space-y-6">
                            <div className="border-b border-gray-200 dark:border-gray-800 pb-6">
                                <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Basic Information</h3>

                                {assignedCount > 0 && (
                                    <div className="mb-4 p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">
                                        <p className="text-sm text-amber-700 dark:text-amber-300">
                                            Assigned to {assignedCount} store(s). Reassign those stores to another category before deactivating or deleting this category.
                                        </p>
                                    </div>
                                )}

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <Input field="name" label="Name" required placeholder="e.g. Electronics" form={form} errors={errors} handleChange={handleChange} />
                                    <Input field="slug" label="Slug" placeholder="auto-generated from name" helpText="Lowercase letters, numbers, and hyphens. Must be unique." form={form} errors={errors} handleChange={handleChange} />
                                </div>

                                <div className="mt-4">
                                    <Input field="description" label="Description" type="textarea" placeholder="What kinds of stores belong in this category..." form={form} errors={errors} handleChange={handleChange} />
                                </div>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 mt-4">
                                    <Input field="sort_order" label="Sort Order" type="number" placeholder="0" form={form} errors={errors} handleChange={handleChange} />
                                    <div className="flex items-end pb-2">
                                        <label className="relative inline-flex items-center cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={form.is_active}
                                                onChange={(e) => handleChange('is_active', e.target.checked)}
                                                className="sr-only peer"
                                            />
                                            <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                            <span className="ml-3 text-sm text-gray-500 dark:text-gray-400">Active</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div className="flex items-center justify-end gap-3">
                                <Link
                                    href="/superadmin/store-categories"
                                    className="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 rounded-lg hover:bg-gray-200 transition-colors"
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
