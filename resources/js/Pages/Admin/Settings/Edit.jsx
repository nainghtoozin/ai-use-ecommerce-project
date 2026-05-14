import { Head, Link, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function SettingsEdit({ settings = {} }) {
    const { data, setData, post, processing, errors } = useForm({
        telegram_link: settings.telegram_link || '',
        viber_link: settings.viber_link || '',
        facebook_link: settings.facebook_link || '',
        whatsapp_link: settings.whatsapp_link || '',
    });

    function handleSubmit(e) {
        e.preventDefault();
        post('/admin/settings');
    }

    return (
        <AdminLayout>
            <Head title="Settings" />
            <div className="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div className="mb-6">
                    <h1 className="text-2xl font-bold text-gray-900">Social Media Settings</h1>
                    <p className="text-sm text-gray-500 mt-1">Configure your social media and contact links.</p>
                </div>

                <div className="bg-white rounded-lg border border-gray-200 p-6">
                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div>
                            <label htmlFor="telegram_link" className="block text-sm font-medium text-gray-700 mb-1">
                                Telegram Link <span className="text-gray-400 font-normal">(@username or phone)</span>
                            </label>
                            <input id="telegram_link" type="text" value={data.telegram_link} onChange={(e) => setData('telegram_link', e.target.value)}
                                className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                            {errors.telegram_link && <p className="mt-1 text-sm text-red-600">{errors.telegram_link}</p>}
                        </div>

                        <div>
                            <label htmlFor="viber_link" className="block text-sm font-medium text-gray-700 mb-1">
                                Viber Link <span className="text-gray-400 font-normal">(phone number)</span>
                            </label>
                            <input id="viber_link" type="text" value={data.viber_link} onChange={(e) => setData('viber_link', e.target.value)}
                                className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                            {errors.viber_link && <p className="mt-1 text-sm text-red-600">{errors.viber_link}</p>}
                        </div>

                        <div>
                            <label htmlFor="facebook_link" className="block text-sm font-medium text-gray-700 mb-1">
                                Facebook Link <span className="text-gray-400 font-normal">(username or URL)</span>
                            </label>
                            <input id="facebook_link" type="text" value={data.facebook_link} onChange={(e) => setData('facebook_link', e.target.value)}
                                className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                            {errors.facebook_link && <p className="mt-1 text-sm text-red-600">{errors.facebook_link}</p>}
                        </div>

                        <div>
                            <label htmlFor="whatsapp_link" className="block text-sm font-medium text-gray-700 mb-1">
                                WhatsApp Link <span className="text-gray-400 font-normal">(phone number with country code)</span>
                            </label>
                            <input id="whatsapp_link" type="text" value={data.whatsapp_link} onChange={(e) => setData('whatsapp_link', e.target.value)}
                                className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                            {errors.whatsapp_link && <p className="mt-1 text-sm text-red-600">{errors.whatsapp_link}</p>}
                        </div>

                        <div className="flex justify-end gap-3">
                            <Link href="/admin/dashboard" className="px-4 py-2 text-gray-600 hover:text-gray-800">Cancel</Link>
                            <button type="submit" disabled={processing}
                                className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
                                {processing ? 'Saving...' : 'Save Settings'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AdminLayout>
    );
}
