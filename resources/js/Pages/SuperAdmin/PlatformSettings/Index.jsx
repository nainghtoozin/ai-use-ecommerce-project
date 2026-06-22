import { useState } from 'react';
import { router, Head } from '@inertiajs/react';
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
                    value={form[field] ?? ''}
                    onChange={(e) => handleChange(field, e.target.value)}
                    className="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                    rows={3}
                />
            ) : (
                <input
                    id={id}
                    type={type}
                    value={form[field] ?? ''}
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

export default function PlatformSettingsIndex({ settings }) {
    const [form, setForm] = useState({
        site_name: settings.site_name || '',
        site_logo: settings.site_logo || '',
        favicon: settings.favicon || '',
        support_email: settings.support_email || '',
        maintenance_mode: settings.maintenance_mode || false,
        registration_enabled: settings.registration_enabled ?? true,
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

        router.put('/superadmin/platform-settings', form, {
            onSuccess: () => setProcessing(false),
            onError: (errs) => {
                setErrors(errs);
                setProcessing(false);
            },
        });
    }

    return (
        <AdminLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Platform Settings</h2>}>
            <Head title="Platform Settings" />

            <div className="py-6">
                <div className="max-w-3xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <form onSubmit={handleSubmit} className="p-6 space-y-6">
                            <div className="border-b border-gray-200 pb-6">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">General</h3>
                                <div className="space-y-4">
                                    <Input field="site_name" label="Site Name" required placeholder="My Application" form={form} errors={errors} handleChange={handleChange} />
                                    <Input field="support_email" label="Support Email" type="email" placeholder="support@example.com" form={form} errors={errors} handleChange={handleChange} />
                                </div>
                            </div>

                            <div className="border-b border-gray-200 pb-6">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">Branding</h3>
                                <div className="space-y-4">
                                    <Input field="site_logo" label="Logo URL" placeholder="/storage/logo.png" helpText="URL or path to the site logo." form={form} errors={errors} handleChange={handleChange} />
                                    <Input field="favicon" label="Favicon URL" placeholder="/storage/favicon.ico" helpText="URL or path to the favicon." form={form} errors={errors} handleChange={handleChange} />
                                </div>
                            </div>

                            <div className="border-b border-gray-200 pb-6">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">Features</h3>
                                <div className="space-y-4">
                                    <label className="flex items-center gap-3">
                                        <input
                                            type="checkbox"
                                            checked={form.maintenance_mode}
                                            onChange={(e) => handleChange('maintenance_mode', e.target.checked)}
                                            className="rounded border-gray-300 text-blue-600 focus:ring-blue-500 w-4 h-4"
                                        />
                                        <div>
                                            <span className="text-sm font-medium text-gray-700">Maintenance Mode</span>
                                            <p className="text-xs text-gray-400">When enabled, all storefronts display a maintenance notice.</p>
                                        </div>
                                    </label>
                                    <label className="flex items-center gap-3">
                                        <input
                                            type="checkbox"
                                            checked={form.registration_enabled}
                                            onChange={(e) => handleChange('registration_enabled', e.target.checked)}
                                            className="rounded border-gray-300 text-blue-600 focus:ring-blue-500 w-4 h-4"
                                        />
                                        <div>
                                            <span className="text-sm font-medium text-gray-700">Registration Enabled</span>
                                            <p className="text-xs text-gray-400">Allow new merchants to sign up.</p>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <div className="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
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
