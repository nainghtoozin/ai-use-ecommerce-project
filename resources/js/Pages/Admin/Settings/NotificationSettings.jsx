import { Head, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';

export default function NotificationSettings({ settings = {} }) {
    const { data, setData, post, processing, errors } = useForm({
        notifications_enabled: settings.notifications_enabled === 'true' || settings.notifications_enabled === true ? 'true' : 'false',
    });

    function handleSubmit(e) {
        e.preventDefault();
        post(adminUrl('/admin/settings/notifications'));
    }

    return (
        <AdminLayout>
            <Head title="Notification Settings" />
            <div className="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div className="mb-6">
                    <h1 className="text-2xl font-bold text-gray-900">Notification Settings</h1>
                    <p className="text-sm text-gray-500 mt-1">Configure how order notifications are delivered.</p>
                </div>

                <div className="bg-white rounded-lg border border-gray-200 p-6">
                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div className="flex items-start gap-3 p-4 bg-gray-50 rounded-lg">
                            <input
                                id="notifications_enabled"
                                type="checkbox"
                                checked={data.notifications_enabled === 'true'}
                                onChange={(e) => setData('notifications_enabled', e.target.checked ? 'true' : 'false')}
                                className="mt-1 h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                            />
                            <div>
                                <label htmlFor="notifications_enabled" className="text-sm font-medium text-gray-900 cursor-pointer">
                                    Enable Website Notifications
                                </label>
                                <p className="text-sm text-gray-500 mt-1">
                                    When enabled, customer and admin bell notifications will be created after an order is placed.
                                </p>
                                {errors.notifications_enabled && <p className="mt-1 text-sm text-red-600">{errors.notifications_enabled}</p>}
                            </div>
                        </div>

                        <div className="flex justify-end gap-3 pt-4 border-t border-gray-200">
                            <button
                                type="submit"
                                disabled={processing}
                                className="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors flex items-center gap-2"
                            >
                                {processing ? (
                                    <>
                                        <svg className="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                        Saving...
                                    </>
                                ) : (
                                    <>
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                        </svg>
                                        Save Settings
                                    </>
                                )}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AdminLayout>
    );
}
