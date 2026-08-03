import { Head, useForm, usePage, Link } from '@inertiajs/react';
import { useState } from 'react';
import PlatformLayout from '@/Layouts/PlatformLayout';
import { useTranslation } from '@/Utils/useTranslation';
import {
    Mail, Phone, MessageCircle, MapPin, Clock, Send,
    CheckCircle, ArrowRight, Headphones, Globe
} from 'lucide-react';

export default function Contact() {
    const { website_info, platform_setting } = usePage().props;
    const { t } = useTranslation();
    const [submitted, setSubmitted] = useState(false);

    const settings = website_info || {};
    const ci = settings.contact_info || {};
    const ai = settings.address_info || {};

    const tel = ci.primary_phone || settings.phone;
    const whatsapp = ci.whatsapp_number || settings.whatsapp_number;
    const email = ci.contact_email || settings.contact_email;
    const support = ci.support_email || settings.support_email;
    const sales = ci.sales_email;
    const telegram = ci.telegram_username;
    const facebook = settings.facebook_url;
    const addrParts = [ai.address_line_1, ai.address_line_2, ai.city, ai.state_region, ai.postal_code, ai.country || settings.country].filter(Boolean);
    const addrStr = addrParts.length > 0 ? addrParts.join(', ') : settings.address;
    const mapsLink = ai.google_maps_link || settings.google_maps_embed_url;

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        subject: '',
        message: '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/client/contact', {
            onSuccess: () => {
                setSubmitted(true);
                reset();
            },
        });
    };

    const contactCards = [
        tel && { icon: Phone, label: t('landing.contact_page.phone'), value: tel, color: 'blue', href: `tel:${tel}` },
        email && { icon: Mail, label: t('landing.contact_page.support_email'), value: email, color: 'red', href: `mailto:${email}` },
        sales && { icon: Mail, label: t('landing.contact_page.sales_email'), value: sales, color: 'orange', href: `mailto:${sales}` },
        whatsapp && { icon: MessageCircle, label: t('landing.contact_page.whatsapp'), value: whatsapp, color: 'green', href: `https://wa.me/${whatsapp.replace(/[^0-9]/g, '')}` },
        telegram && { icon: Send, label: t('landing.contact_page.telegram'), value: `@${telegram}`, color: 'sky', href: `https://t.me/${telegram}` },
        facebook && { icon: Globe, label: t('landing.contact_page.facebook'), value: 'Facebook Page', color: 'indigo', href: facebook },
    ].filter(Boolean);

    const colorMap = {
        blue: 'bg-blue-100 dark:bg-blue-950 text-blue-600 dark:text-blue-400',
        red: 'bg-red-100 dark:bg-red-950 text-red-600 dark:text-red-400',
        orange: 'bg-orange-100 dark:bg-orange-950 text-orange-600 dark:text-orange-400',
        green: 'bg-green-100 dark:bg-green-950 text-green-600 dark:text-green-400',
        sky: 'bg-sky-100 dark:bg-sky-950 text-sky-600 dark:text-sky-400',
        indigo: 'bg-indigo-100 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400',
        purple: 'bg-purple-100 dark:bg-purple-950 text-purple-600 dark:text-purple-400',
    };

    return (
        <PlatformLayout>
            <Head title={t('landing.contact_page.title')} />

            {/* Hero */}
            <section className="relative overflow-hidden bg-gradient-to-b from-slate-50 to-white dark:from-gray-950 dark:to-gray-900 py-16 sm:py-20">
                <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-blue-50 via-transparent to-transparent dark:from-blue-950/30 pointer-events-none" />
                <div className="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                    <div className="inline-flex items-center gap-2 px-4 py-1.5 bg-blue-50 dark:bg-blue-950/50 border border-blue-100 dark:border-blue-900 rounded-full text-sm text-blue-700 dark:text-blue-300 font-medium mb-6">
                        <Headphones className="w-4 h-4" />
                        <span>{t('landing.contact_page.hero_title')}</span>
                    </div>
                    <h1 className="text-4xl sm:text-5xl font-bold text-gray-900 dark:text-gray-100 mb-4">
                        {t('landing.contact_page.title')}
                    </h1>
                    <p className="text-lg text-gray-500 dark:text-gray-400 max-w-2xl mx-auto">
                        {t('landing.contact_page.hero_subtitle')}
                    </p>
                </div>
            </section>

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                {/* Contact Cards */}
                {contactCards.length > 0 && (
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-12">
                        {contactCards.map((card, index) => {
                            const Icon = card.icon;
                            const Wrapper = card.href ? 'a' : 'div';
                            const wrapperProps = card.href ? { href: card.href, target: card.href.startsWith('http') ? '_blank' : undefined, rel: card.href.startsWith('http') ? 'noopener noreferrer' : undefined } : {};
                            return (
                                <Wrapper
                                    key={index}
                                    {...wrapperProps}
                                    className="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 p-6 hover:shadow-md transition-all duration-200 block"
                                >
                                    <div className="flex items-center gap-3 mb-3">
                                        <div className={`p-2.5 rounded-xl ${colorMap[card.color] || colorMap.blue}`}>
                                            <Icon className="w-5 h-5" />
                                        </div>
                                        <h3 className="font-semibold text-gray-900 dark:text-gray-100">{card.label}</h3>
                                    </div>
                                    <p className="text-gray-600 dark:text-gray-400 text-sm">{card.value}</p>
                                </Wrapper>
                            );
                        })}
                    </div>
                )}

                <div className="grid lg:grid-cols-2 gap-8">
                    {/* Contact Form */}
                    <div className="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 p-6 sm:p-8">
                        <h2 className="text-xl font-bold text-gray-900 dark:text-gray-100 mb-6">
                            {t('landing.contact_page.contact_form')}
                        </h2>

                        {submitted ? (
                            <div className="text-center py-8">
                                <CheckCircle className="w-12 h-12 text-green-500 mx-auto mb-4" />
                                <p className="text-gray-700 dark:text-gray-300">
                                    {t('landing.contact_page.form_success')}
                                </p>
                            </div>
                        ) : (
                            <form onSubmit={handleSubmit} className="space-y-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                        {t('landing.contact_page.form_name')}
                                    </label>
                                    <input
                                        type="text"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        className="w-full px-4 py-2.5 text-sm border border-gray-300 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        required
                                    />
                                    {errors.name && <p className="text-red-500 text-xs mt-1">{errors.name}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                        {t('landing.contact_page.form_email')}
                                    </label>
                                    <input
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        className="w-full px-4 py-2.5 text-sm border border-gray-300 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        required
                                    />
                                    {errors.email && <p className="text-red-500 text-xs mt-1">{errors.email}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                        {t('landing.contact_page.form_subject')}
                                    </label>
                                    <input
                                        type="text"
                                        value={data.subject}
                                        onChange={(e) => setData('subject', e.target.value)}
                                        className="w-full px-4 py-2.5 text-sm border border-gray-300 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        required
                                    />
                                    {errors.subject && <p className="text-red-500 text-xs mt-1">{errors.subject}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                        {t('landing.contact_page.form_message')}
                                    </label>
                                    <textarea
                                        value={data.message}
                                        onChange={(e) => setData('message', e.target.value)}
                                        rows={5}
                                        className="w-full px-4 py-2.5 text-sm border border-gray-300 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                                        required
                                    />
                                    {errors.message && <p className="text-red-500 text-xs mt-1">{errors.message}</p>}
                                </div>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full inline-flex items-center justify-center gap-2 px-6 py-3 bg-blue-600 text-white font-semibold text-sm rounded-xl hover:bg-blue-700 transition-all focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    {processing ? (
                                        <>
                                            <div className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                                            Sending...
                                        </>
                                    ) : (
                                        <>
                                            <Send className="w-4 h-4" />
                                            {t('landing.contact_page.form_submit')}
                                        </>
                                    )}
                                </button>
                            </form>
                        )}
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-6">
                        {/* Business Hours */}
                        <div className="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 p-6">
                            <div className="flex items-center gap-3 mb-4">
                                <div className="p-2.5 rounded-xl bg-amber-100 dark:bg-amber-950 text-amber-600 dark:text-amber-400">
                                    <Clock className="w-5 h-5" />
                                </div>
                                <h3 className="font-semibold text-gray-900 dark:text-gray-100">{t('landing.contact_page.business_hours')}</h3>
                            </div>
                            <p className="text-sm text-gray-600 dark:text-gray-400">
                                {t('landing.contact_page.business_hours_value')}
                            </p>
                        </div>

                        {/* Response Time */}
                        <div className="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 p-6">
                            <div className="flex items-center gap-3 mb-4">
                                <div className="p-2.5 rounded-xl bg-emerald-100 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400">
                                    <CheckCircle className="w-5 h-5" />
                                </div>
                                <h3 className="font-semibold text-gray-900 dark:text-gray-100">{t('landing.contact_page.response_time')}</h3>
                            </div>
                            <p className="text-sm text-gray-600 dark:text-gray-400">
                                {t('landing.contact_page.response_time_value')}
                            </p>
                        </div>

                        {/* Address */}
                        {addrStr && (
                            <div className="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 p-6">
                                <div className="flex items-center gap-3 mb-4">
                                    <div className="p-2.5 rounded-xl bg-purple-100 dark:bg-purple-950 text-purple-600 dark:text-purple-400">
                                        <MapPin className="w-5 h-5" />
                                    </div>
                                    <h3 className="font-semibold text-gray-900 dark:text-gray-100">{t('landing.contact_page.address')}</h3>
                                </div>
                                <p className="text-sm text-gray-600 dark:text-gray-400">{addrStr}</p>
                            </div>
                        )}

                        {/* FAQ Shortcut */}
                        <div className="bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-blue-950/30 dark:to-indigo-950/30 rounded-2xl border border-blue-100 dark:border-blue-900 p-6">
                            <h3 className="font-semibold text-gray-900 dark:text-gray-100 mb-2">
                                {t('landing.contact_page.faq_shortcut')}
                            </h3>
                            <Link
                                href="/#faq"
                                className="inline-flex items-center gap-2 text-sm font-semibold text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 transition-colors"
                            >
                                {t('landing.contact_page.view_faq')}
                                <ArrowRight className="w-4 h-4" />
                            </Link>
                        </div>
                    </div>
                </div>

                {/* Map */}
                {mapsLink && (
                    <div className="mt-12 bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 p-4">
                        <div className="aspect-video rounded-xl overflow-hidden">
                            {mapsLink.includes('embed') ? (
                                <iframe
                                    src={mapsLink}
                                    width="100%"
                                    height="100%"
                                    style={{ border: 0 }}
                                    allowFullScreen=""
                                    loading="lazy"
                                    referrerPolicy="no-referrer-when-downgrade"
                                    title="Office Location"
                                />
                            ) : (
                                <a
                                    href={mapsLink}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="flex items-center justify-center w-full h-full bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                                >
                                    <div className="text-center">
                                        <MapPin className="w-12 h-12 text-gray-400 dark:text-gray-500 mx-auto mb-2" />
                                        <span className="text-gray-600 dark:text-gray-400 font-medium">View on Google Maps</span>
                                    </div>
                                </a>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </PlatformLayout>
    );
}
