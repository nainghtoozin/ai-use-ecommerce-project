import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function TelegramBotConnect({ settings = {} }) {
    const [testing, setTesting] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        telegram_bot_token: settings.telegram_bot_token || '',
        telegram_chat_id: settings.telegram_chat_id || '',
        telegram_parse_mode: settings.telegram_parse_mode || 'HTML',
        telegram_enabled: settings.telegram_enabled === 'true' || settings.telegram_enabled === true ? 'true' : 'false',
    });

    function handleSubmit(e) {
        e.preventDefault();
        post('/admin/settings/telegram');
    }

    async function handleTest(e) {
        e.preventDefault();
        setTesting(true);
        try {
            await post('/admin/settings/telegram/test', {
                preserveScroll: true,
                onFinish: () => setTesting(false),
            });
        } catch {
            setTesting(false);
        }
    }

    return (
        <AdminLayout>
            <Head title="Telegram Bot Connect" />
            <div className="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div className="mb-6">
                    <h1 className="text-2xl font-bold text-gray-900">Telegram Bot Connect</h1>
                    <p className="text-sm text-gray-500 mt-1">Configure your Telegram bot integration for order notifications.</p>
                </div>

                <div className="bg-white rounded-lg border border-gray-200 p-6">
                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div className="flex items-start gap-3 p-4 bg-gray-50 rounded-lg">
                            <input
                                id="telegram_enabled"
                                type="checkbox"
                                checked={data.telegram_enabled === 'true'}
                                onChange={(e) => setData('telegram_enabled', e.target.checked ? 'true' : 'false')}
                                className="mt-1 h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                            />
                            <div>
                                <label htmlFor="telegram_enabled" className="text-sm font-medium text-gray-900 cursor-pointer">
                                    Enable Telegram Bot
                                </label>
                                <p className="text-sm text-gray-500 mt-1">
                                    Toggle to enable or disable the Telegram bot integration.
                                </p>
                            </div>
                        </div>

                        <div>
                            <label htmlFor="telegram_bot_token" className="block text-sm font-medium text-gray-700 mb-1">
                                Bot Token
                            </label>
                            <input
                                id="telegram_bot_token"
                                type="text"
                                value={data.telegram_bot_token}
                                onChange={(e) => setData('telegram_bot_token', e.target.value)}
                                placeholder="123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11"
                                className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            />
                            {errors.telegram_bot_token && <p className="mt-1 text-sm text-red-600">{errors.telegram_bot_token}</p>}
                        </div>

                        <div>
                            <label htmlFor="telegram_chat_id" className="block text-sm font-medium text-gray-700 mb-1">
                                Chat ID
                            </label>
                            <input
                                id="telegram_chat_id"
                                type="text"
                                value={data.telegram_chat_id}
                                onChange={(e) => setData('telegram_chat_id', e.target.value)}
                                placeholder="-1001234567890"
                                className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            />
                            {errors.telegram_chat_id && <p className="mt-1 text-sm text-red-600">{errors.telegram_chat_id}</p>}
                        </div>

                        <div>
                            <label htmlFor="telegram_parse_mode" className="block text-sm font-medium text-gray-700 mb-1">
                                Parse Mode
                            </label>
                            <select
                                id="telegram_parse_mode"
                                value={data.telegram_parse_mode}
                                onChange={(e) => setData('telegram_parse_mode', e.target.value)}
                                className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            >
                                <option value="HTML">HTML</option>
                                <option value="Markdown">Markdown</option>
                            </select>
                            {errors.telegram_parse_mode && <p className="mt-1 text-sm text-red-600">{errors.telegram_parse_mode}</p>}
                        </div>

                        <div className="flex justify-end gap-3 pt-4 border-t border-gray-200">
                            <button
                                type="button"
                                onClick={handleTest}
                                disabled={testing || !data.telegram_bot_token || !data.telegram_chat_id}
                                className="px-4 py-2 text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors flex items-center gap-2"
                            >
                                {testing ? (
                                    <>
                                        <svg className="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                        Sending...
                                    </>
                                ) : (
                                    <>
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                                        </svg>
                                        Send Test Message
                                    </>
                                )}
                            </button>
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
