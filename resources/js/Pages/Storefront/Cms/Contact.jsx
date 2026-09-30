import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';
import { Mail, Phone, MapPin, MessageCircle, Send, Loader2, BadgeDollarSign } from 'lucide-react';

function MethodRow({ icon: Icon, label, value, href }) {
    if (!value) return null;
    const Wrapper = href ? 'a' : 'div';
    const wrapperProps = href ? { href, target: '_blank', rel: 'noopener noreferrer' } : {};

    return (
        <Wrapper
            {...wrapperProps}
            className="flex items-center gap-2.5 px-3 py-2.5 rounded-lg bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-800 hover:border-gray-300 dark:hover:border-gray-700 transition-colors min-w-0"
        >
            <span className="flex-shrink-0 w-8 h-8 rounded-lg bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center">
                <Icon className="w-4 h-4 text-blue-600 dark:text-blue-400" />
            </span>
            <span className="min-w-0">
                <span className="block text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide leading-tight">{label}</span>
                <span className="block text-[13px] font-medium text-gray-900 dark:text-gray-100 truncate leading-tight mt-0.5">{value}</span>
            </span>
        </Wrapper>
    );
}

export default function Contact({ tenant, contact }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        subject: '',
        message: '',
    });
    const [submitted, setSubmitted] = useState(false);

    const handleSubmit = (e) => {
        e.preventDefault();
        post(`/store/${tenant.slug}/contact`, {
            onSuccess: () => {
                setSubmitted(true);
                reset();
            },
        });
    };

    const addressValue = [contact.address, contact.address_line_2, contact.city, contact.state, contact.postal_code, contact.country].filter(Boolean).join(', ');
    const hasContactInfo = contact.email || contact.phone || contact.whatsapp || contact.telegram || addressValue;

    return (
        <ShopLayout>
            <Head title={`Contact Us - ${tenant?.name || 'Store'}`} />

            <div className="max-w-6xl mx-auto px-4 sm:px-6 py-6 sm:py-8">
                <div>
                    <h1 className="text-xl sm:text-2xl font-bold text-gray-900 dark:text-gray-100">Contact Us</h1>
                    <p className="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Have a question? We'd love to hear from you.</p>
                </div>

                <div className="mt-5 grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-6 items-start">
                    <section>
                        <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wide mb-2.5">Get in Touch</h2>
                        {hasContactInfo ? (
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                <MethodRow icon={Mail} label="Email" value={contact.email || contact.support_email} href={contact.email ? `mailto:${contact.email}` : null} />
                                {contact.support_email && contact.support_email !== contact.email && (
                                    <MethodRow icon={Mail} label="Support" value={contact.support_email} href={`mailto:${contact.support_email}`} />
                                )}
                                {contact.sales_email && (
                                    <MethodRow icon={BadgeDollarSign} label="Sales" value={contact.sales_email} href={`mailto:${contact.sales_email}`} />
                                )}
                                <MethodRow icon={Phone} label="Phone" value={contact.phone} href={contact.phone ? `tel:${contact.phone}` : null} />
                                {contact.secondary_phone && (
                                    <MethodRow icon={Phone} label="Secondary" value={contact.secondary_phone} href={`tel:${contact.secondary_phone}`} />
                                )}
                                <MethodRow icon={MessageCircle} label="WhatsApp" value={contact.whatsapp} href={contact.whatsapp ? `https://wa.me/${contact.whatsapp.replace(/\D/g, '')}` : null} />
                                {contact.telegram && (
                                    <MethodRow icon={MessageCircle} label="Telegram" value={`@${contact.telegram}`} href={`https://t.me/${contact.telegram}`} />
                                )}
                                {addressValue && (
                                    <div className="sm:col-span-2">
                                        <MethodRow icon={MapPin} label="Address" value={addressValue} href={contact.google_maps_url || null} />
                                    </div>
                                )}
                            </div>
                        ) : (
                            <div className="p-4 rounded-xl bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-800">
                                <p className="text-sm text-gray-500 dark:text-gray-400">Contact information will be available soon.</p>
                            </div>
                        )}

                        {contact.google_maps_url && (
                            <div className="mt-2.5 rounded-xl overflow-hidden border border-gray-200 dark:border-gray-800">
                                <iframe
                                    src={contact.google_maps_url}
                                    width="100%"
                                    height="150"
                                    style={{ border: 0 }}
                                    allowFullScreen=""
                                    loading="lazy"
                                    referrerPolicy="no-referrer-when-downgrade"
                                    title="Store Location"
                                />
                            </div>
                        )}
                    </section>

                    <section className="p-4 sm:p-5 rounded-xl bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-800">
                        <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wide mb-3">Send us a Message</h2>

                        {submitted ? (
                            <div className="text-center py-6">
                                <div className="w-12 h-12 mx-auto mb-3 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                                    <i className="bi bi-check-circle text-xl text-green-600 dark:text-green-400"></i>
                                </div>
                                <h3 className="text-base font-medium text-gray-900 dark:text-gray-100">Message Sent!</h3>
                                <p className="text-sm text-gray-500 dark:text-gray-400 mt-1 mb-3">
                                    Thank you for reaching out. We'll get back to you soon.
                                </p>
                                <button
                                    onClick={() => setSubmitted(false)}
                                    className="text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline"
                                >
                                    Send another message
                                </button>
                            </div>
                        ) : (
                            <form onSubmit={handleSubmit} className="space-y-3">
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label className="block text-[13px] font-medium text-gray-700 dark:text-gray-300 mb-1">Name *</label>
                                        <input
                                            type="text"
                                            value={data.name}
                                            onChange={(e) => setData('name', e.target.value)}
                                            className="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2"
                                            required
                                        />
                                        {errors.name && <p className="mt-1 text-xs text-red-500">{errors.name}</p>}
                                    </div>
                                    <div>
                                        <label className="block text-[13px] font-medium text-gray-700 dark:text-gray-300 mb-1">Email *</label>
                                        <input
                                            type="email"
                                            value={data.email}
                                            onChange={(e) => setData('email', e.target.value)}
                                            className="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2"
                                            required
                                        />
                                        {errors.email && <p className="mt-1 text-xs text-red-500">{errors.email}</p>}
                                    </div>
                                </div>
                                <div>
                                    <label className="block text-[13px] font-medium text-gray-700 dark:text-gray-300 mb-1">Subject *</label>
                                    <input
                                        type="text"
                                        value={data.subject}
                                        onChange={(e) => setData('subject', e.target.value)}
                                        className="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2"
                                        required
                                    />
                                    {errors.subject && <p className="mt-1 text-xs text-red-500">{errors.subject}</p>}
                                </div>
                                <div>
                                    <label className="block text-[13px] font-medium text-gray-700 dark:text-gray-300 mb-1">Message *</label>
                                    <textarea
                                        value={data.message}
                                        onChange={(e) => setData('message', e.target.value)}
                                        rows={4}
                                        className="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm resize-none"
                                        required
                                    />
                                    {errors.message && <p className="mt-1 text-xs text-red-500">{errors.message}</p>}
                                </div>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                >
                                    {processing ? (
                                        <>
                                            <Loader2 className="w-4 h-4 animate-spin" />
                                            Sending...
                                        </>
                                    ) : (
                                        <>
                                            <Send className="w-4 h-4" />
                                            Send Message
                                        </>
                                    )}
                                </button>
                            </form>
                        )}
                    </section>
                </div>
            </div>
        </ShopLayout>
    );
}
