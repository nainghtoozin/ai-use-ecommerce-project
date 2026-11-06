import { Link } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';
import { MessageCircle, Send, Phone, Mail, Globe } from 'lucide-react';

export default function SupportSection() {
    const { supportSettings } = usePage().props;
    const s = supportSettings || {};
    const channels = [
        s.telegram_url && { label: 'Telegram', href: s.telegram_url, icon: Send },
        s.viber_url && { label: 'Viber', href: s.viber_url, icon: Phone },
        s.whatsapp_url && { label: 'WhatsApp', href: s.whatsapp_url, icon: MessageCircle },
        s.facebook_url && { label: 'Facebook', href: s.facebook_url, icon: Globe },
        s.messenger_url && { label: 'Messenger', href: s.messenger_url, icon: MessageCircle },
        s.support_email && { label: 'Email', href: `mailto:${s.support_email}`, icon: Mail },
        s.support_phone && { label: 'Phone', href: `tel:${s.support_phone}`, icon: Phone },
        s.support_page_url && { label: 'Support', href: s.support_page_url, icon: Globe },
    ].filter(Boolean);

    if (channels.length === 0) return null;

    return (
        <section id="support" className="py-12 sm:py-16 bg-white dark:bg-gray-900 scroll-mt-16">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="text-center mb-8">
                    <h2 className="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-gray-100">
                        {s.support_title || 'Contact Support'}
                    </h2>
                    <p className="mt-2 text-gray-500 dark:text-gray-400">
                        {s.support_description || 'We are here to help.'}
                    </p>
                </div>
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                    {channels.map((ch, i) => (
                        <a key={i} href={ch.href} target={ch.href?.startsWith('http') ? '_blank' : undefined} rel="noopener noreferrer" className="border border-gray-200 dark:border-gray-800 rounded-lg p-3 hover:shadow-sm transition-shadow flex items-center gap-3">
                            <div className="w-9 h-9 rounded-lg bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center flex-shrink-0">
                                <ch.icon className="w-4 h-4 text-blue-600" />
                            </div>
                            <span className="text-sm font-medium text-gray-900 dark:text-gray-100">{ch.label}</span>
                        </a>
                    ))}
                </div>

            </div>
        </section>
    );
}
