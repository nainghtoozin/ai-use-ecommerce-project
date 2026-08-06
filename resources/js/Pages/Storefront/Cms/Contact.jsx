import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import CmsPage from '@/Components/Storefront/CmsPage';
import { Mail, Phone, MapPin, MessageCircle, Send, Loader2 } from 'lucide-react';

function ContactCard({ icon: Icon, label, value, href }) {
    if (!value) return null;
    const Wrapper = href ? 'a' : 'div';
    const wrapperProps = href ? { href, target: '_blank', rel: 'noopener noreferrer' } : {};

    return (
        <Wrapper
            {...wrapperProps}
            className="flex items-start gap-3 p-4 rounded-xl bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-800 hover:border-gray-300 dark:hover:border-gray-700 transition-colors"
        >
            <div className="flex-shrink-0 w-10 h-10 rounded-lg bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center">
                <Icon className="w-5 h-5 text-blue-600 dark:text-blue-400" />
            </div>
            <div className="min-w-0">
                <p className="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">{label}</p>
                <p className="text-sm font-medium text-gray-900 dark:text-gray-100 mt-0.5 truncate">{value}</p>
            </div>
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

    const hasContactInfo = contact.email || contact.phone || contact.whatsapp || contact.telegram || contact.address;

    return (
        <CmsPage
            title="Contact Us"
            breadcrumbs={[{ label: 'Contact' }]}
            maxWidth="max-w-5xl"
        >
            <div className="grid grid-cols-1 lg:grid-cols-5 gap-8 lg:gap-12">
                {/* Contact Info */}
                <div className="lg:col-span-2 space-y-4">
                    <div>
                        <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">Get in Touch</h2>
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            Have a question? We'd love to hear from you.
                        </p>
                    </div>

                    {hasContactInfo ? (
                        <div className="space-y-3">
                            <ContactCard icon={Mail} label="Email" value={contact.email || contact.support_email} href={contact.email ? `mailto:${contact.email}` : null} />
                            {contact.support_email && contact.support_email !== contact.email && (
                                <ContactCard icon={Mail} label="Support" value={contact.support_email} href={`mailto:${contact.support_email}`} />
                            )}
                            {contact.sales_email && (
                                <ContactCard icon={Mail} label="Sales" value={contact.sales_email} href={`mailto:${contact.sales_email}`} />
                            )}
                            <ContactCard icon={Phone} label="Phone" value={contact.phone} href={contact.phone ? `tel:${contact.phone}` : null} />
                            {contact.secondary_phone && (
                                <ContactCard icon={Phone} label="Secondary" value={contact.secondary_phone} href={`tel:${contact.secondary_phone}`} />
                            )}
                            <ContactCard icon={MessageCircle} label="WhatsApp" value={contact.whatsapp} href={contact.whatsapp ? `https://wa.me/${contact.whatsapp.replace(/\D/g, '')}` : null} />
                            {contact.telegram && (
                                <ContactCard icon={MessageCircle} label="Telegram" value={`@${contact.telegram}`} href={`https://t.me/${contact.telegram}`} />
                            )}
                            <ContactCard
                                icon={MapPin}
                                label="Address"
                                value={[contact.address, contact.address_line_2, contact.city, contact.state, contact.postal_code, contact.country].filter(Boolean).join(', ')}
                                href={contact.google_maps_url || null}
                            />
                        </div>
                    ) : (
                        <div className="p-4 rounded-xl bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-800">
                            <p className="text-sm text-gray-500 dark:text-gray-400">Contact information will be available soon.</p>
                        </div>
                    )}

                    {contact.google_maps_url && (
                        <div className="mt-4 rounded-xl overflow-hidden border border-gray-200 dark:border-gray-800">
                            <iframe
                                src={contact.google_maps_url}
                                width="100%"
                                height="200"
                                style={{ border: 0 }}
                                allowFullScreen=""
                                loading="lazy"
                                referrerPolicy="no-referrer-when-downgrade"
                                title="Store Location"
                            />
                        </div>
                    )}
                </div>

                {/* Contact Form */}
                <div className="lg:col-span-3">
                    <div className="p-6 rounded-xl bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-800">
                        <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Send us a Message</h2>

                        {submitted ? (
                            <div className="text-center py-8">
                                <div className="w-16 h-16 mx-auto mb-4 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                                    <i className="bi bi-check-circle text-2xl text-green-600 dark:text-green-400"></i>
                                </div>
                                <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100 mb-1">Message Sent!</h3>
                                <p className="text-sm text-gray-500 dark:text-gray-400 mb-4">
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
                            <form onSubmit={handleSubmit} className="space-y-4">
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name *</label>
                                        <input
                                            type="text"
                                            value={data.name}
                                            onChange={(e) => setData('name', e.target.value)}
                                            className="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                            required
                                        />
                                        {errors.name && <p className="mt-1 text-xs text-red-500">{errors.name}</p>}
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email *</label>
                                        <input
                                            type="email"
                                            value={data.email}
                                            onChange={(e) => setData('email', e.target.value)}
                                            className="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                            required
                                        />
                                        {errors.email && <p className="mt-1 text-xs text-red-500">{errors.email}</p>}
                                    </div>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Subject *</label>
                                    <input
                                        type="text"
                                        value={data.subject}
                                        onChange={(e) => setData('subject', e.target.value)}
                                        className="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                        required
                                    />
                                    {errors.subject && <p className="mt-1 text-xs text-red-500">{errors.subject}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Message *</label>
                                    <textarea
                                        value={data.message}
                                        onChange={(e) => setData('message', e.target.value)}
                                        rows={5}
                                        className="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm resize-none"
                                        required
                                    />
                                    {errors.message && <p className="mt-1 text-xs text-red-500">{errors.message}</p>}
                                </div>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
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
                    </div>
                </div>
            </div>
        </CmsPage>
    );
}
