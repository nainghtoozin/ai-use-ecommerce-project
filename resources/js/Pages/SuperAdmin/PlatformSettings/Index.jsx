import { useState } from 'react';
import { router, Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import ImageUpload from '@/Components/ImageUpload';

export default function PlatformSettingsIndex({ settings }) {
    const [siteName, setSiteName] = useState(settings.site_name || '');
    const [supportEmail, setSupportEmail] = useState(settings.support_email || '');
    const [logo, setLogo] = useState(settings.site_logo || null);
    const [favicon, setFavicon] = useState(settings.favicon || null);
    const [maintenanceMode, setMaintenanceMode] = useState(settings.maintenance_mode || false);
    const [registrationEnabled, setRegistrationEnabled] = useState(settings.registration_enabled ?? true);
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);

    function handleSubmit(e) {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        const formData = new FormData();
        formData.append('site_name', siteName);
        formData.append('support_email', supportEmail);
        formData.append('maintenance_mode', maintenanceMode ? '1' : '0');
        formData.append('registration_enabled', registrationEnabled ? '1' : '0');

        if (logo instanceof File) {
            formData.append('logo', logo);
        }
        if (favicon instanceof File) {
            formData.append('favicon', favicon);
        }

        router.post('/superadmin/platform-settings', formData, {
            forceFormData: true,
            preserveScroll: true,
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
                <div className="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <form onSubmit={handleSubmit} className="space-y-8">
                            {/* Section 1: General Information */}
                            <div className="border-b border-gray-200 pb-6">
                                <h3 className="text-lg font-medium text-gray-900 mb-1">General Information</h3>
                                <p className="text-sm text-gray-500 mb-4">Manage platform-wide settings.</p>
                                <div className="space-y-4">
                                    <div>
                                        <label htmlFor="site_name" className="block text-sm font-medium text-gray-700 mb-1">
                                            Platform Name <span className="text-red-500">*</span>
                                        </label>
                                        <input
                                            id="site_name"
                                            type="text"
                                            value={siteName}
                                            onChange={(e) => setSiteName(e.target.value)}
                                            className="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                            placeholder="My Application"
                                        />
                                        {errors.site_name && <p className="text-xs text-red-600 mt-1">{errors.site_name}</p>}
                                    </div>
                                    <div>
                                        <label htmlFor="support_email" className="block text-sm font-medium text-gray-700 mb-1">
                                            Support Email
                                        </label>
                                        <input
                                            id="support_email"
                                            type="email"
                                            value={supportEmail}
                                            onChange={(e) => setSupportEmail(e.target.value)}
                                            className="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                            placeholder="support@example.com"
                                        />
                                        {errors.support_email && <p className="text-xs text-red-600 mt-1">{errors.support_email}</p>}
                                    </div>
                                </div>
                            </div>

                            {/* Section 2: Branding */}
                            <div className="border-b border-gray-200 pb-6">
                                <h3 className="text-lg font-medium text-gray-900 mb-1">Branding</h3>
                                <p className="text-sm text-gray-500 mb-4">Upload your platform logo and favicon.</p>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <ImageUpload
                                        name="logo"
                                        label="Platform Logo"
                                        value={logo}
                                        onChange={(file) => setLogo(file)}
                                        error={errors.logo}
                                    />
                                    <ImageUpload
                                        name="favicon"
                                        label="Favicon"
                                        value={favicon}
                                        onChange={(file) => setFavicon(file)}
                                        error={errors.favicon}
                                    />
                                </div>
                            </div>

                            {/* Section 3: System Settings */}
                            <div className="pb-6">
                                <h3 className="text-lg font-medium text-gray-900 mb-1">System Settings</h3>
                                <p className="text-sm text-gray-500 mb-4">Control platform-wide features.</p>
                                <div className="space-y-4">
                                    <label className="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                        <div>
                                            <span className="text-sm font-medium text-gray-700">Maintenance Mode</span>
                                            <p className="text-xs text-gray-400">When enabled, all storefronts display a maintenance notice.</p>
                                        </div>
                                        <button
                                            type="button"
                                            role="switch"
                                            aria-checked={maintenanceMode}
                                            onClick={() => setMaintenanceMode(!maintenanceMode)}
                                            className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 ${maintenanceMode ? 'bg-blue-600' : 'bg-gray-300'}`}
                                        >
                                            <span className={`inline-block h-5 w-5 transform rounded-full bg-white shadow-sm transition-transform ${maintenanceMode ? 'translate-x-6' : 'translate-x-0.5'}`} />
                                        </button>
                                    </label>
                                    <label className="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                        <div>
                                            <span className="text-sm font-medium text-gray-700">Registration Enabled</span>
                                            <p className="text-xs text-gray-400">Allow new merchants to sign up.</p>
                                        </div>
                                        <button
                                            type="button"
                                            role="switch"
                                            aria-checked={registrationEnabled}
                                            onClick={() => setRegistrationEnabled(!registrationEnabled)}
                                            className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 ${registrationEnabled ? 'bg-blue-600' : 'bg-gray-300'}`}
                                        >
                                            <span className={`inline-block h-5 w-5 transform rounded-full bg-white shadow-sm transition-transform ${registrationEnabled ? 'translate-x-6' : 'translate-x-0.5'}`} />
                                        </button>
                                    </label>
                                </div>
                            </div>

                            {/* Submit */}
                            <div className="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="px-6 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50"
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
