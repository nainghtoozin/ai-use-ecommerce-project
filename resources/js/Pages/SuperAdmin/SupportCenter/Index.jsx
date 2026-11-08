import { useState } from 'react';
import { router, Head, Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function SupportCenterIndex({ settings }) {
    const s = settings || {};
    const [supportCenterEnabled, setSupportCenterEnabled] = useState(s.support_center_enabled ?? true);
    const [supportEmail, setSupportEmail] = useState(s.support_email || '');
    const [supportPhone, setSupportPhone] = useState(s.support_phone || '');
    const [supportPageUrl, setSupportPageUrl] = useState(s.support_page_url || '');
    const [supportButtonText, setSupportButtonText] = useState(s.support_button_text || '');
    const [supportTitle, setSupportTitle] = useState(s.support_title || '');
    const [supportDescription, setSupportDescription] = useState(s.support_description || '');
    const [telegramUrl, setTelegramUrl] = useState(s.telegram_url || '');
    const [telegramUsername, setTelegramUsername] = useState(s.telegram_username || '');
    const [viberUrl, setViberUrl] = useState(s.viber_url || '');
    const [whatsappUrl, setWhatsappUrl] = useState(s.whatsapp_url || '');
    const [facebookUrl, setFacebookUrl] = useState(s.facebook_url || '');
    const [messengerUrl, setMessengerUrl] = useState(s.messenger_url || '');
    const [businessHours, setBusinessHours] = useState(s.business_hours || '');
    const [platformTimezone, setPlatformTimezone] = useState(s.platform_timezone || 'Asia/Yangon');
    const DAYS = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
    const defaultSchedule = () => DAYS.reduce((acc, d) => { acc[d] = { enabled: d !== 'sunday', intervals: [{ start: '09:00', end: '17:00' }] }; return acc; }, {});
    const [weeklySchedule, setWeeklySchedule] = useState(s.support_schedule ? (JSON.parse(s.support_schedule).weekly || defaultSchedule()) : defaultSchedule());
    const [liveChatEnabled, setLiveChatEnabled] = useState(s.live_chat_enabled ?? false);
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);

    function handleSubmit(e) {
        e.preventDefault();
        setProcessing(true);
        setErrors({});
        const formData = new FormData();
        formData.append('support_center_enabled', supportCenterEnabled ? '1' : '0');
        formData.append('support_email', supportEmail);
        formData.append('support_phone', supportPhone);
        formData.append('support_page_url', supportPageUrl);
        formData.append('support_button_text', supportButtonText);
        formData.append('support_title', supportTitle);
        formData.append('support_description', supportDescription);
        formData.append('telegram_url', telegramUrl);
        formData.append('telegram_username', telegramUsername);
        formData.append('viber_url', viberUrl);
        formData.append('whatsapp_url', whatsappUrl);
        formData.append('facebook_url', facebookUrl);
        formData.append('messenger_url', messengerUrl);
        formData.append('whatsapp_url', whatsappUrl);
        formData.append('facebook_url', facebookUrl);
        formData.append('messenger_url', messengerUrl);
        formData.append('business_hours', businessHours);
        formData.append('support_schedule', JSON.stringify({ timezone: platformTimezone, weekly: weeklySchedule }));
        formData.append('platform_timezone', platformTimezone);
        formData.append('live_chat_enabled', liveChatEnabled ? '1' : '0');

        router.post('/superadmin/support-center', formData, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => setProcessing(false),
            onError: (errs) => { setErrors(errs); setProcessing(false); },
        });
    }

    return (
        <AdminLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Support Center</h2>}>
            <Head title="Support Center" />
            <div className="py-6">
                <div className="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
                    <div className="bg-white dark:bg-gray-900 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <form onSubmit={handleSubmit} className="space-y-6">
                            <div className="space-y-4">
                                <label className="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-950 rounded-lg">
                                    <div>
                                        <span className="text-sm font-medium text-gray-700 dark:text-gray-300">Support Center Enabled</span>
                                    </div>
                                    <button type="button" role="switch" aria-checked={supportCenterEnabled} onClick={() => setSupportCenterEnabled(!supportCenterEnabled)} className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${supportCenterEnabled ? 'bg-blue-600' : 'bg-gray-300'}`}>
                                        <span className={`inline-block h-5 w-5 transform rounded-full bg-white dark:bg-gray-900 shadow-sm transition-transform ${supportCenterEnabled ? 'translate-x-6' : 'translate-x-0.5'}`} />
                                    </button>
                                </label>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Support Page Title</label>
                                    <input type="text" value={supportTitle} onChange={(e) => setSupportTitle(e.target.value)} className="w-full rounded-lg border-gray-300 dark:border-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Support Description</label>
                                    <textarea rows={3} value={supportDescription} onChange={(e) => setSupportDescription(e.target.value)} className="w-full rounded-lg border-gray-300 dark:border-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Support Email</label>
                                    <input type="email" value={supportEmail} onChange={(e) => setSupportEmail(e.target.value)} className="w-full rounded-lg border-gray-300 dark:border-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" />
                                    {errors.support_email && <p className="text-xs text-red-600 mt-1">{errors.support_email}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Support Phone</label>
                                    <input type="text" value={supportPhone} onChange={(e) => setSupportPhone(e.target.value)} className="w-full rounded-lg border-gray-300 dark:border-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Support Page URL</label>
                                    <input type="url" value={supportPageUrl} onChange={(e) => setSupportPageUrl(e.target.value)} className="w-full rounded-lg border-gray-300 dark:border-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" />
                                    {errors.support_page_url && <p className="text-xs text-red-600 mt-1">{errors.support_page_url}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Support Button Text</label>
                                    <input type="text" value={supportButtonText} onChange={(e) => setSupportButtonText(e.target.value)} className="w-full rounded-lg border-gray-300 dark:border-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Telegram URL</label>
                                    <div className="flex gap-2">
                                        <input type="text" value={telegramUrl} onChange={(e) => setTelegramUrl(e.target.value)} className="w-full rounded-lg border-gray-300 dark:border-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" placeholder="https://t.me/..." />
                                        <button type="button" onClick={() => navigator.clipboard.writeText('https://t.me/')} className="px-3 py-2 text-xs bg-gray-100 dark:bg-gray-800 rounded-lg">Copy Prefix</button>
                                    </div>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Telegram Username</label>
                                    <input type="text" value={telegramUsername} onChange={(e) => setTelegramUsername(e.target.value)} className="w-full rounded-lg border-gray-300 dark:border-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Viber URL</label>
                                    <div className="flex gap-2">
                                        <input type="text" value={viberUrl} onChange={(e) => setViberUrl(e.target.value)} className="w-full rounded-lg border-gray-300 dark:border-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" placeholder="viber://chat?number=..." />
                                        <button type="button" onClick={() => navigator.clipboard.writeText('viber://chat?number=')} className="px-3 py-2 text-xs bg-gray-100 dark:bg-gray-800 rounded-lg">Copy Prefix</button>
                                    </div>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">WhatsApp URL</label>
                                    <input type="text" value={whatsappUrl} onChange={(e) => setWhatsappUrl(e.target.value)} className="w-full rounded-lg border-gray-300 dark:border-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" placeholder="https://wa.me/..." />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Facebook Page URL</label>
                                    <input type="text" value={facebookUrl} onChange={(e) => setFacebookUrl(e.target.value)} className="w-full rounded-lg border-gray-300 dark:border-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" placeholder="https://facebook.com/..." />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Messenger URL</label>
                                    <input type="text" value={messengerUrl} onChange={(e) => setMessengerUrl(e.target.value)} className="w-full rounded-lg border-gray-300 dark:border-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" placeholder="https://m.me/..." />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Platform Timezone</label>
                                    <input type="text" value={platformTimezone} onChange={(e) => setPlatformTimezone(e.target.value)} className="w-full rounded-lg border-gray-300 dark:border-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" placeholder="Asia/Yangon" />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Platform Timezone</label>
                                    <input type="text" value={platformTimezone} onChange={(e) => setPlatformTimezone(e.target.value)} className="w-full rounded-lg border-gray-300 dark:border-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" placeholder="Asia/Yangon" />
                                </div>
                                <div className="space-y-3">
                                    <p className="text-sm font-medium text-gray-700 dark:text-gray-300">Weekly Schedule</p>
                                    {['monday','tuesday','wednesday','thursday','friday','saturday','sunday'].map(day => (
                                        <div key={day} className="border border-gray-200 dark:border-gray-700 rounded-lg p-3">
                                            <div className="flex items-center justify-between mb-2">
                                                <span className="text-sm font-medium capitalize">{day}</span>
                                                <button type="button" onClick={() => setWeeklySchedule(prev => ({ ...prev, [day]: { ...prev[day], enabled: !prev[day].enabled } }))} className={`px-2 py-1 text-xs rounded ${weeklySchedule[day]?.enabled ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'}`}>
                                                    {weeklySchedule[day]?.enabled ? 'Open' : 'Closed'}
                                                </button>
                                            </div>
                                            {weeklySchedule[day]?.enabled && (weeklySchedule[day].intervals || []).map((iv, idx) => (
                                                <div key={idx} className="flex gap-2 mb-2">
                                                    <input type="time" value={iv.start} onChange={(e) => {
                                                        const next = { ...weeklySchedule };
                                                        next[day].intervals[idx].start = e.target.value;
                                                        setWeeklySchedule(next);
                                                    }} className="px-2 py-1 text-sm border border-gray-300 dark:border-gray-700 rounded" />
                                                    <span className="text-sm self-center">to</span>
                                                    <input type="time" value={iv.end} onChange={(e) => {
                                                        const next = { ...weeklySchedule };
                                                        next[day].intervals[idx].end = e.target.value;
                                                        setWeeklySchedule(next);
                                                    }} className="px-2 py-1 text-sm border border-gray-300 dark:border-gray-700 rounded" />
                                                    <button type="button" onClick={() => {
                                                        const next = { ...weeklySchedule };
                                                        next[day].intervals = next[day].intervals.filter((_, i) => i !== idx);
                                                        setWeeklySchedule(next);
                                                    }} className="px-2 py-1 text-xs text-red-600">Remove</button>
                                                </div>
                                            ))}
                                            {weeklySchedule[day]?.enabled && (weeklySchedule[day].intervals || []).length < 3 && (
                                                <button type="button" onClick={() => {
                                                    const next = { ...weeklySchedule };
                                                    next[day].intervals = [...(next[day].intervals || []), { start: '09:00', end: '17:00' }];
                                                    setWeeklySchedule(next);
                                                }} className="px-2 py-1 text-xs text-blue-600">+ Add Interval</button>
                                            )}
                                        </div>
                                    ))}
                                </div>
                                <label className="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-950 rounded-lg">
                                    <div>
                                        <span className="text-sm font-medium text-gray-700 dark:text-gray-300">Live Chat UI Enabled</span>
                                    </div>
                                    <button type="button" role="switch" aria-checked={liveChatEnabled} onClick={() => setLiveChatEnabled(!liveChatEnabled)} className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${liveChatEnabled ? 'bg-blue-600' : 'bg-gray-300'}`}>
                                        <span className={`inline-block h-5 w-5 transform rounded-full bg-white dark:bg-gray-900 shadow-sm transition-transform ${liveChatEnabled ? 'translate-x-6' : 'translate-x-0.5'}`} />
                                    </button>
                                </label>
                            </div>

                            <div className="flex items-center justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-800">
                                <button type="submit" disabled={processing} className="px-6 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50">
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


