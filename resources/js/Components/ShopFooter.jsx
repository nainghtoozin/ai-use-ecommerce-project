import { useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { assetUrl } from '@/Utils/helpers';
import ContactDrawer from '@/Components/ContactDrawer';

export default function ShopFooter() {
    const { website_info } = usePage().props;
    const [drawerOpen, setDrawerOpen] = useState(false);
    const siteName = website_info?.site_name || 'My Store';
    const logoUrl = assetUrl(website_info?.logo);
    const themeColor = 'var(--theme-color, #3B82F6)';

    const ci = website_info?.contact_info || {};
    const ai = website_info?.address_info || {};

    const shopLinks = [
        { label: 'All Products', href: '/' },
        { label: 'New Arrivals', href: '/' },
        { label: 'Best Sellers', href: '/' },
        { label: 'Sale Items', href: '/' },
    ];

    const supportLinks = [
        { label: 'Contact Us', href: '/client/contact' },
        { label: 'FAQs', href: '/client/faq' },
        { label: 'Shipping Info', href: '/client/contact' },
        { label: 'Returns & Refunds', href: '/client/contact' },
    ];

    const companyLinks = [
        { label: 'About Us', href: '/client/about' },
        { label: 'Privacy Policy', href: '/client/privacy' },
        { label: 'Terms of Service', href: '/client/terms' },
        { label: 'Cookie Policy', href: '/client/privacy' },
    ];

    const socials = [
        { key: 'facebook', icon: 'bi-facebook', link: website_info?.facebook_url },
        { key: 'whatsapp', icon: 'bi-whatsapp', link: (ci.whatsapp_number || website_info?.whatsapp_number) ? `https://wa.me/${(ci.whatsapp_number || website_info?.whatsapp_number).replace(/\D/g,'')}` : null },
        { key: 'telegram', icon: 'bi-telegram', link: ci.telegram_username ? `https://t.me/${ci.telegram_username}` : null },
        { key: 'instagram', icon: 'bi-instagram', link: website_info?.instagram_url },
        { key: 'youtube', icon: 'bi-youtube', link: website_info?.youtube_url },
        { key: 'linkedin', icon: 'bi-linkedin', link: website_info?.linkedin_url },
    ].filter(s => s.link);

    const phone = ci.primary_phone || website_info?.phone;
    const supportEmail = ci.support_email || website_info?.support_email;
    const contactEmail = ci.contact_email || website_info?.contact_email;
    const hasMiniContact = phone || supportEmail || contactEmail;

    return (
        <>
            <ContactDrawer open={drawerOpen} onClose={() => setDrawerOpen(false)} />
            <footer className="bg-slate-900 text-white">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="py-8 lg:py-10 border-b border-slate-800">
                        <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-6 lg:gap-8">
                            <div className="col-span-2 md:col-span-1 lg:col-span-1.5">
                                <Link href="/" className="flex items-center gap-2.5 mb-3">
                                    {logoUrl ? (
                                        <img src={logoUrl} alt={siteName} className="h-8 w-auto" />
                                    ) : (
                                        <div className="h-8 w-8 rounded-lg flex items-center justify-center" style={{ backgroundColor: themeColor }}>
                                            <i className="bi bi-shop text-white text-base"></i>
                                        </div>
                                    )}
                                    <span className="text-lg font-bold">{siteName}</span>
                                </Link>
                                <p className="text-slate-400 text-xs leading-relaxed mb-4 line-clamp-2">
                                    {website_info?.about_description || website_info?.footer_description || 'Your trusted destination for quality products.'}
                                </p>
                                {socials.length > 0 && (
                                    <div className="flex items-center gap-1.5">
                                        {socials.map((social) => (
                                            <a
                                                key={social.key}
                                                href={social.link}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="w-8 h-8 rounded-lg flex items-center justify-center text-xs transition-all duration-200 hover:shadow-lg hover:opacity-90"
                                                style={{ backgroundColor: themeColor }}
                                            >
                                                <i className={`bi ${social.icon}`}></i>
                                            </a>
                                        ))}
                                    </div>
                                )}
                            </div>

                            <div>
                                <h4 className="text-xs font-semibold text-white uppercase tracking-wider mb-3">Shop</h4>
                                <ul className="space-y-2">
                                    {shopLinks.map((link) => (
                                        <li key={link.href + link.label}>
                                            <Link href={link.href} className="text-slate-400 hover:text-white text-xs transition-colors">
                                                {link.label}
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            </div>

                            <div>
                                <h4 className="text-xs font-semibold text-white uppercase tracking-wider mb-3">Support</h4>
                                <ul className="space-y-2">
                                    {supportLinks.map((link) => (
                                        <li key={link.href + link.label}>
                                            <Link href={link.href} className="text-slate-400 hover:text-white text-xs transition-colors">
                                                {link.label}
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            </div>

                            <div>
                                <h4 className="text-xs font-semibold text-white uppercase tracking-wider mb-3">Company</h4>
                                <ul className="space-y-2">
                                    {companyLinks.map((link) => (
                                        <li key={link.href + link.label}>
                                            <Link href={link.href} className="text-slate-400 hover:text-white text-xs transition-colors">
                                                {link.label}
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            </div>

                            <div className="col-span-2 md:col-span-1">
                                <h4 className="text-xs font-semibold text-white uppercase tracking-wider mb-3">Contact</h4>
                                {hasMiniContact ? (
                                    <div className="space-y-2 mb-3">
                                        {phone && (
                                            <a href={`tel:${phone}`} className="flex items-center gap-2 text-xs text-slate-400 hover:text-white transition-colors">
                                                <i className="bi bi-telephone" style={{ color: themeColor, fontSize: '0.7rem' }}></i>
                                                <span>{phone}</span>
                                            </a>
                                        )}
                                        {(supportEmail || contactEmail) && (
                                            <a href={`mailto:${supportEmail || contactEmail}`} className="flex items-center gap-2 text-xs text-slate-400 hover:text-white transition-colors truncate">
                                                <i className="bi bi-envelope" style={{ color: themeColor, fontSize: '0.7rem' }}></i>
                                                <span className="truncate">{supportEmail || contactEmail}</span>
                                            </a>
                                        )}
                                    </div>
                                ) : (
                                    <p className="text-xs text-slate-500 mb-3">No contact info</p>
                                )}
                                <button
                                    onClick={() => setDrawerOpen(true)}
                                    className="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg transition-all duration-200 hover:opacity-90"
                                    style={{ backgroundColor: themeColor, color: '#fff' }}
                                >
                                    <i className="bi bi-info-circle" style={{ fontSize: '0.7rem' }}></i>
                                    Contact Details
                                    <i className="bi bi-chevron-right" style={{ fontSize: '0.6rem' }}></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div className="py-4 flex flex-col sm:flex-row justify-between items-center gap-3">
                        <div className="flex items-center gap-2 text-slate-500 text-xs">
                            <i className="bi bi-copyright"></i>
                            <span>{new Date().getFullYear()} {siteName}. All rights reserved.</span>
                        </div>
                        <div className="flex items-center gap-4 text-xs">
                            <span className="text-slate-500">Powered by</span>
                            <span className="font-semibold text-white">{siteName}</span>
                        </div>
                    </div>
                </div>
            </footer>
        </>
    );
}