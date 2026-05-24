import { useEffect } from 'react';
import { usePage } from '@inertiajs/react';

export default function ContactDrawer({ open, onClose }) {
    const { website_info } = usePage().props;

    useEffect(() => {
        if (!open) return;
        const handler = (e) => { if (e.key === 'Escape') onClose(); };
        document.addEventListener('keydown', handler);
        document.body.style.overflow = 'hidden';
        return () => {
            document.removeEventListener('keydown', handler);
            document.body.style.overflow = '';
        };
    }, [open, onClose]);

    const ci = website_info?.contact_info || {};
    const ai = website_info?.address_info || {};

    const phone = ci.primary_phone || website_info?.phone;
    const phone2 = ci.secondary_phone;
    const supportEmail = ci.support_email || website_info?.support_email;
    const salesEmail = ci.sales_email;
    const contactEmail = ci.contact_email || website_info?.contact_email;
    const whatsapp = ci.whatsapp_number || website_info?.whatsapp_number;
    const telegram = ci.telegram_username;
    const mapsLink = ai.google_maps_link || website_info?.google_maps_embed_url;
    const addrCountry = ai.country || website_info?.country;
    const addrParts = [ai.address_line_1, ai.address_line_2, ai.city, ai.state_region, ai.postal_code].filter(Boolean);

    const themeColor = 'var(--theme-color, #3B82F6)';

    const socials = [
        { key: 'facebook', icon: 'bi-facebook', link: website_info?.facebook_url, label: 'Facebook' },
        { key: 'instagram', icon: 'bi-instagram', link: website_info?.instagram_url, label: 'Instagram' },
        { key: 'youtube', icon: 'bi-youtube', link: website_info?.youtube_url, label: 'YouTube' },
        { key: 'linkedin', icon: 'bi-linkedin', link: website_info?.linkedin_url, label: 'LinkedIn' },
        { key: 'twitter', icon: 'bi-twitter-x', link: website_info?.twitter_url, label: 'Twitter' },
    ].filter(s => s.link);

    const quickActions = [
        ...(phone ? [{ icon: 'bi-telephone', label: 'Call', href: `tel:${phone}`, color: '#3B82F6' }] : []),
        ...(whatsapp ? [{ icon: 'bi-whatsapp', label: 'WhatsApp', href: `https://wa.me/${whatsapp.replace(/\D/g,'')}`, color: '#25D366' }] : []),
        ...(telegram ? [{ icon: 'bi-telegram', label: 'Telegram', href: `https://t.me/${telegram}`, color: '#0088cc' }] : []),
        ...((supportEmail || contactEmail) ? [{ icon: 'bi-envelope', label: 'Email', href: `mailto:${supportEmail || contactEmail}`, color: '#EF4444' }] : []),
        ...(mapsLink ? [{ icon: 'bi-geo-alt', label: 'Open Map', href: mapsLink, color: '#8B5CF6' }] : []),
    ];

    return (
        <>
            {open && (
                <div
                    className="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm transition-opacity duration-300"
                    onClick={onClose}
                    aria-hidden="true"
                />
            )}

            <div
                className={`fixed z-50 bg-white shadow-2xl transform transition-transform duration-300 ease-out
                    bottom-0 left-0 right-0 rounded-t-2xl max-h-[85vh]
                    sm:inset-y-0 sm:left-auto sm:right-0 sm:rounded-none sm:w-[420px] lg:w-[480px] sm:max-h-none
                    ${open ? 'translate-y-0 sm:translate-x-0' : 'translate-y-full sm:translate-y-0 sm:translate-x-full'}
                `}
            >
                <div className="flex flex-col h-full">
                    <div className="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                        <div className="flex items-center gap-2.5">
                            <div className="w-8 h-8 rounded-lg flex items-center justify-center" style={{ backgroundColor: themeColor }}>
                                <i className="bi bi-telephone text-white text-sm"></i>
                            </div>
                            <div>
                                <h2 className="text-base font-semibold text-gray-900">Contact Details</h2>
                                <p className="text-xs text-gray-400">Get in touch with us</p>
                            </div>
                        </div>
                        <button
                            onClick={onClose}
                            className="w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors"
                        >
                            <i className="bi bi-x-lg text-sm"></i>
                        </button>
                    </div>

                    <div className="flex-1 overflow-y-auto px-5 py-5 space-y-5">
                        {quickActions.length > 0 && (
                            <div className="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
                                {quickActions.map((action) => (
                                    <a
                                        key={action.label}
                                        href={action.href}
                                        target={action.href.startsWith('http') ? '_blank' : undefined}
                                        rel={action.href.startsWith('http') ? 'noopener noreferrer' : undefined}
                                        className="flex flex-col items-center gap-1.5 py-3 px-2 rounded-xl border border-gray-200 hover:shadow-sm hover:border-gray-300 transition-all group"
                                    >
                                        <div
                                            className="w-9 h-9 rounded-lg flex items-center justify-center transition-transform group-hover:scale-110"
                                            style={{ backgroundColor: `${action.color}15` }}
                                        >
                                            <i className={`bi ${action.icon}`} style={{ color: action.color, fontSize: '1rem' }}></i>
                                        </div>
                                        <span className="text-xs font-medium text-gray-700">{action.label}</span>
                                    </a>
                                ))}
                            </div>
                        )}

                        <div className="space-y-3">
                            <h3 className="text-xs font-semibold text-gray-400 uppercase tracking-wider">Contact Information</h3>
                            <div className="space-y-2">
                                {(phone || phone2) && (
                                    <div className="flex items-center gap-3 p-3 rounded-xl bg-gray-50 border border-gray-100">
                                        <div className="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0" style={{ backgroundColor: `${themeColor}15` }}>
                                            <i className="bi bi-telephone" style={{ color: themeColor, fontSize: '0.9rem' }}></i>
                                        </div>
                                        <div className="min-w-0">
                                            <p className="text-xs text-gray-400">Phone</p>
                                            <a href={`tel:${phone}`} className="text-sm font-medium text-gray-800 hover:text-blue-600 transition-colors">{phone}</a>
                                            {phone2 && <p className="text-xs text-gray-500 mt-0.5">{phone2}</p>}
                                        </div>
                                    </div>
                                )}
                                {(contactEmail || supportEmail || salesEmail) && (
                                    <div className="flex items-center gap-3 p-3 rounded-xl bg-gray-50 border border-gray-100">
                                        <div className="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0" style={{ backgroundColor: '#EF444415' }}>
                                            <i className="bi bi-envelope" style={{ color: '#EF4444', fontSize: '0.9rem' }}></i>
                                        </div>
                                        <div className="min-w-0">
                                            <p className="text-xs text-gray-400">Email</p>
                                            {contactEmail && <a href={`mailto:${contactEmail}`} className="text-sm font-medium text-gray-800 hover:text-red-500 transition-colors block truncate">{contactEmail}</a>}
                                            {supportEmail && <p className="text-xs text-gray-500 truncate">Support: {supportEmail}</p>}
                                            {salesEmail && <p className="text-xs text-gray-500 truncate">Sales: {salesEmail}</p>}
                                        </div>
                                    </div>
                                )}
                                {whatsapp && (
                                    <a href={`https://wa.me/${whatsapp.replace(/\D/g,'')}`} target="_blank" rel="noopener noreferrer" className="flex items-center gap-3 p-3 rounded-xl bg-gray-50 border border-gray-100 hover:border-green-200 transition-colors group">
                                        <div className="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0" style={{ backgroundColor: '#25D36615' }}>
                                            <i className="bi bi-whatsapp" style={{ color: '#25D366', fontSize: '0.9rem' }}></i>
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <p className="text-xs text-gray-400">WhatsApp</p>
                                            <p className="text-sm font-medium text-gray-800 group-hover:text-green-600 transition-colors">{whatsapp}</p>
                                        </div>
                                        <i className="bi bi-box-arrow-up-right text-gray-300 group-hover:text-green-500 text-xs transition-colors"></i>
                                    </a>
                                )}
                                {telegram && (
                                    <a href={`https://t.me/${telegram}`} target="_blank" rel="noopener noreferrer" className="flex items-center gap-3 p-3 rounded-xl bg-gray-50 border border-gray-100 hover:border-sky-200 transition-colors group">
                                        <div className="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0" style={{ backgroundColor: '#0088cc15' }}>
                                            <i className="bi bi-telegram" style={{ color: '#0088cc', fontSize: '0.9rem' }}></i>
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <p className="text-xs text-gray-400">Telegram</p>
                                            <p className="text-sm font-medium text-gray-800 group-hover:text-sky-600 transition-colors">@{telegram}</p>
                                        </div>
                                        <i className="bi bi-box-arrow-up-right text-gray-300 group-hover:text-sky-500 text-xs transition-colors"></i>
                                    </a>
                                )}
                                {addrParts.length > 0 && (
                                    <div className="flex items-start gap-3 p-3 rounded-xl bg-gray-50 border border-gray-100">
                                        <div className="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5" style={{ backgroundColor: '#8B5CF615' }}>
                                            <i className="bi bi-geo-alt" style={{ color: '#8B5CF6', fontSize: '0.9rem' }}></i>
                                        </div>
                                        <div>
                                            <p className="text-xs text-gray-400 mb-1">Address</p>
                                            <div className="text-sm text-gray-800 leading-relaxed">
                                                {addrParts.map((part, i) => (
                                                    <p key={i}>{part}</p>
                                                ))}
                                                {addrCountry && <p className="text-gray-500 mt-0.5">{addrCountry}</p>}
                                            </div>
                                            {mapsLink && (
                                                <a
                                                    href={mapsLink}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="inline-flex items-center gap-1 mt-2 text-xs font-medium px-3 py-1.5 rounded-lg border border-gray-200 hover:border-purple-200 hover:text-purple-600 transition-colors"
                                                >
                                                    <i className="bi bi-box-arrow-up-right" style={{ fontSize: '0.7rem' }}></i>
                                                    Open in Google Maps
                                                </a>
                                            )}
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>

                        {socials.length > 0 && (
                            <div className="space-y-3">
                                <h3 className="text-xs font-semibold text-gray-400 uppercase tracking-wider">Follow Us</h3>
                                <div className="flex items-center gap-2 flex-wrap">
                                    {socials.map((s) => (
                                        <a
                                            key={s.key}
                                            href={s.link}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="w-10 h-10 rounded-xl flex items-center justify-center text-gray-500 transition-all duration-200 border border-gray-200"
                                            onMouseEnter={(e) => { e.currentTarget.style.backgroundColor = themeColor; e.currentTarget.style.color = '#fff'; }}
                                            onMouseLeave={(e) => { e.currentTarget.style.backgroundColor = ''; e.currentTarget.style.color = ''; }}
                                            title={s.label}
                                        >
                                            <i className={`bi ${s.icon}`}></i>
                                        </a>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}