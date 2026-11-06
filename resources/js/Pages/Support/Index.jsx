import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { MessageCircle, Send, Phone, Mail, Globe, Clock, MessageSquare } from 'lucide-react';

export default function SupportCenter({ settings }) {
    const s = settings || {};
    const schedule = s.support_schedule ? (typeof s.support_schedule === 'string' ? JSON.parse(s.support_schedule) : s.support_schedule) : null;
    const tz = schedule?.timezone || s.platform_timezone || 'UTC';
    const now = new Date();
    const formatter = new Intl.DateTimeFormat('en-US', { timeZone: tz, weekday: 'long', hour: '2-digit', minute: '2-digit', hour12: false });
    const parts = formatter.formatToParts(now);
    const weekday = parts.find(p => p.type === 'weekday')?.value?.toLowerCase();
    const hour = parts.find(p => p.type === 'hour')?.value;
    const minute = parts.find(p => p.type === 'minute')?.value;
    const currentTime = `${hour}:${minute}`;
    const daySchedule = schedule?.weekly?.[weekday];
    let isOpen = false;
    let statusText = 'Support hours have not been configured.';
    if (daySchedule && daySchedule.enabled) {
        for (const iv of (daySchedule.intervals || [])) {
            if (currentTime >= iv.start && currentTime < iv.end) { isOpen = true; break; }
        }
        statusText = isOpen ? 'Open Now' : 'Currently Closed';
    } else if (daySchedule) {
        statusText = 'Closed Today';
    }
    const [chatOpen, setChatOpen] = useState(false);
    const [messages, setMessages] = useState([{ from: 'support', text: 'Hello! How can we help you today?' }]);
    const [input, setInput] = useState('');

    const sendChat = (e) => {
        e.preventDefault();
        if (!input.trim()) return;
        setMessages(m => [...m, { from: 'user', text: input.trim() }]);
        setInput('');
    };

    const email = s.support_email;
    const phone = s.support_phone;
    const url = s.support_page_url;
    const telegram = s.telegram_url;
    const viber = s.viber_url;
    const whatsapp = s.whatsapp_url;
    const facebook = s.facebook_url;
    const messenger = s.messenger_url;
    const title = s.support_title || 'Support Center';
    const desc = s.support_description || 'We are here to help. Choose a channel below.';
    const btnText = s.support_button_text || 'Contact Support';

    const mailto = email ? `mailto:${email}?subject=${encodeURIComponent('Support Request')}&body=${encodeURIComponent('Email: \nMessage: ')}` : null;

    const channels = [
        telegram && { icon: Send, label: 'Telegram', desc: 'Chat on Telegram', href: telegram },
        viber && { icon: Phone, label: 'Viber', desc: 'Chat on Viber', href: viber },
        whatsapp && { icon: MessageCircle, label: 'WhatsApp', desc: 'Chat on WhatsApp', href: whatsapp },
        facebook && { icon: Globe, label: 'Facebook', desc: 'Visit Facebook Page', href: facebook },
        messenger && { icon: MessageSquare, label: 'Messenger', desc: 'Message us', href: messenger },
        email && { icon: Mail, label: 'Email', desc: email, href: mailto },
        phone && { icon: Phone, label: 'Phone', desc: phone, href: `tel:${phone}` },
        url && { icon: Globe, label: btnText, desc: 'Visit support page', href: url },
    ].filter(Boolean);

    return (
        <>
            <Head title={title} />
            <div className="min-h-screen bg-slate-50 dark:bg-gray-950 py-6 px-4 sm:px-6 lg:px-8">
                <div className="max-w-[1440px] mx-auto space-y-6">
                    <div className="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-5 sm:p-6">
                        <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                            <div className="flex items-center gap-3">
                                {s.site_logo_url && <img src={s.site_logo_url} alt="Logo" className="h-10 w-auto" />}
                                <div>
                                    <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">{title}</h1>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">{desc}</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3 flex-wrap">
                                <span className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${isOpen ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'}`}>
                                    {statusText}
                                </span>
                                <span className="text-xs text-gray-400 dark:text-gray-500">TZ: {tz}</span>
                                {daySchedule?.enabled && (
                                    <span className="text-xs text-gray-500 dark:text-gray-400">
                                        Today: {(daySchedule.intervals || []).map(iv => `${iv.start}–${iv.end}`).join(', ')}
                                    </span>
                                )}
                            </div>
                        </div>
                    </div>

                    <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                        {channels.map((ch, i) => (
                            <a key={i} href={ch.href} target={ch.href?.startsWith('http') ? '_blank' : undefined} rel="noopener noreferrer" className="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg p-3 hover:shadow-sm transition-shadow flex items-center gap-3">
                                <div className="w-9 h-9 rounded-lg bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center flex-shrink-0">
                                    <ch.icon className="w-4 h-4 text-blue-600" />
                                </div>
                                <div className="min-w-0">
                                    <p className="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{ch.label}</p>
                                    <p className="text-xs text-gray-400 dark:text-gray-500 truncate">{ch.desc}</p>
                                </div>
                            </a>
                        ))}
                        {channels.length === 0 && (
                            <p className="col-span-full text-center text-sm text-gray-400 dark:text-gray-500 py-6">No support channels configured.</p>
                        )}
                    </div>

                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        {schedule?.weekly && (
                            <div className="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg p-4">
                                <h2 className="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Support Hours</h2>
                                <div className="space-y-1.5">
                                    {Object.entries(schedule.weekly).map(([day, info]) => (
                                        <div key={day} className={`flex justify-between text-sm ${day === weekday ? 'font-semibold text-gray-900 dark:text-gray-100' : 'text-gray-600 dark:text-gray-400'}`}>
                                            <span className="capitalize">{day}</span>
                                            <span>{info.enabled ? (info.intervals || []).map(iv => `${iv.start} – ${iv.end}`).join(', ') : 'Closed'}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                        <div className="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg p-4">
                            <h2 className="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Send us an email</h2>
                            <form action={mailto || '#'} method="get" className="space-y-3">
                                <input type="email" placeholder="Your email" className="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-700 rounded-lg" />
                                <input type="text" placeholder="Subject" className="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-700 rounded-lg" />
                                <textarea rows={3} placeholder="Message" className="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-700 rounded-lg" />
                                <p className="text-xs text-gray-400 dark:text-gray-500">Your email application will open to send the message.</p>
                                <button type="submit" disabled={!mailto} className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50">
                                    Open Email
                                </button>
                            </form>
                        </div>
                    </div>

                    {s.live_chat_enabled && (
                        <div className="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg p-4">
                            <div className="flex items-center justify-between mb-2">
                                <h2 className="text-sm font-semibold text-gray-700 dark:text-gray-300">Live Chat</h2>
                                <button onClick={() => setChatOpen(!chatOpen)} className="text-xs text-blue-600 hover:text-blue-700">
                                    {chatOpen ? 'Close' : 'Open Chat'}
                                </button>
                            </div>
                            <p className="text-xs text-gray-400 dark:text-gray-500 mb-2">Chat preview — Live support is coming soon.</p>
                            {chatOpen && (
                                <div className="border border-gray-200 dark:border-gray-800 rounded-lg">
                                    <div className="h-40 overflow-y-auto p-3 space-y-2 bg-gray-50 dark:bg-gray-950">
                                        {messages.map((m, i) => (
                                            <div key={i} className={`text-sm ${m.from === 'user' ? 'text-right' : 'text-left'}`}>
                                                <span className={`inline-block px-3 py-1.5 rounded-lg ${m.from === 'user' ? 'bg-blue-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200'}`}>
                                                    {m.text}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                    <form onSubmit={sendChat} className="flex gap-2 p-2 border-t border-gray-200 dark:border-gray-800">
                                        <input value={input} onChange={e => setInput(e.target.value)} placeholder="Type a message..." className="flex-1 px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-700 rounded-lg" />
                                        <button type="submit" className="px-3 py-1.5 bg-blue-600 text-white text-sm rounded-lg">Send</button>
                                    </form>
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
