import { useState, useEffect } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { assetUrl } from '@/Utils/helpers';
import ContactDrawer from '@/Components/ContactDrawer';

function InfoModal({ open, onClose, title, children }) {
    useEffect(() => {
        if (!open) return undefined;
        const closeOnEscape = (event) => event.key === 'Escape' && onClose();
        document.addEventListener('keydown', closeOnEscape);
        return () => document.removeEventListener('keydown', closeOnEscape);
    }, [open, onClose]);
    if (!open) return null;
    return (
        <>
            <div className="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm" onClick={onClose} />
            <div role="dialog" aria-modal="true" aria-labelledby="store-info-modal-title" className="fixed inset-x-4 bottom-0 z-50 sm:inset-x-auto sm:top-1/2 sm:left-1/2 sm:-translate-x-1/2 sm:-translate-y-1/2 sm:w-[480px] bg-white dark:bg-gray-900 rounded-t-2xl sm:rounded-2xl shadow-2xl max-h-[70vh] sm:max-h-[60vh] flex flex-col animate-slide-up">
                <div className="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-800 flex-shrink-0">
                     <h3 id="store-info-modal-title" className="text-sm font-semibold text-gray-900 dark:text-gray-100">{title}</h3>
                     <button type="button" aria-label="Close dialog" onClick={onClose} className="w-7 h-7 rounded-lg flex items-center justify-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                        <i className="bi bi-x-lg text-xs"></i>
                    </button>
                </div>
                <div className="flex-1 overflow-y-auto px-5 py-4 text-sm text-gray-600 dark:text-gray-400 leading-relaxed whitespace-pre-line">
                     {children}
                 </div>
             </div>
        </>
    );
}

function BackToTop({ label = 'Back to top' }) {
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        const handleScroll = () => setVisible(window.scrollY > 400);
        window.addEventListener('scroll', handleScroll, { passive: true });
        return () => window.removeEventListener('scroll', handleScroll);
    }, []);

    if (!visible) return null;

    return (
        <button
            onClick={() => window.scrollTo({ top: 0, behavior: 'smooth' })}
            className="fixed bottom-6 right-6 z-40 w-10 h-10 rounded-full bg-slate-800 dark:bg-gray-100 text-white dark:text-gray-900 shadow-lg hover:shadow-xl hover:scale-105 transition-all duration-200 flex items-center justify-center"
            aria-label={label}
        >
            <i className="bi bi-chevron-up text-sm"></i>
        </button>
    );
}

export default function ShopFooter() {
    const { website_info, storefront, tenant } = usePage().props;
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [infoModal, setInfoModal] = useState(null);

    const labels = storefront?.content?.labels || {};
    const storeSlug = tenant?.slug;
    const storeUrl = (path) => `/store/${storeSlug}${path}`;

    const fs = website_info?.footer_settings || {};
    const logoSource = website_info?.footer_logo_url || storefront?.identity?.logo_url || website_info?.logo;
    const logoUrl = assetUrl(logoSource, false);
    const siteName = storefront?.identity?.site_title || storefront?.identity?.name || tenant?.name || website_info?.site_name || 'Store';
    const themeColor = 'var(--theme-color, #3B82F6)';

    const ci = website_info?.contact_info || {};
    const ai = website_info?.address_info || {};

    const description = fs.description || website_info?.footer_description || website_info?.about_description || '';
    const extraText = fs.extra_text || '';
    const stripHtml = (html) => html.replace(/<[^>]*>/g, '');
    const descStripped = stripHtml(description);
    const descTruncated = descStripped.length > 120;
    const descPreview = descTruncated ? descStripped.substring(0, 120) + '...' : descStripped;

    const footerCopyright = website_info?.footer_copyright || `\u00A9 ${new Date().getFullYear()} ${siteName}. ${labels.footer_copyright || 'All rights reserved.'}`;

    const phone = ci.primary_phone || website_info?.phone;
    const supportEmail = ci.support_email || website_info?.support_email;
    const contactEmail = ci.contact_email || website_info?.contact_email;
    const hasMiniContact = phone || supportEmail || contactEmail;

    const socials = [
        { key: 'facebook', icon: 'bi-facebook', link: website_info?.facebook_url },
        { key: 'whatsapp', icon: 'bi-whatsapp', link: (ci.whatsapp_number || website_info?.whatsapp_number) ? `https://wa.me/${(ci.whatsapp_number || website_info?.whatsapp_number).replace(/\D/g, '')}` : null },
        { key: 'telegram', icon: 'bi-telegram', link: ci.telegram_username ? `https://t.me/${ci.telegram_username}` : null },
        { key: 'instagram', icon: 'bi-instagram', link: website_info?.instagram_url },
        { key: 'youtube', icon: 'bi-youtube', link: website_info?.youtube_url },
        { key: 'linkedin', icon: 'bi-linkedin', link: website_info?.linkedin_url },
    ].filter(s => s.link);

    const footerNavItems = storefront?.navigation?.footer_items || [];
    const hasFooterNav = footerNavItems.length > 0;

    const quickLinks = hasFooterNav
        ? footerNavItems.map((item) => ({ label: item.label, href: storeUrl(item.path) }))
        : [
            { label: labels.home || 'Home', href: storeUrl('/') },
            { label: labels.products || 'Products', href: storeUrl('/products') },
            { label: labels.categories || 'Categories', href: storeUrl('/products') },
            { label: labels.new_arrivals || 'New Arrivals', href: storeUrl('/products?sort=newest') },
        ];

    const customerServiceLinks = [
        { label: labels.contact_us || 'Contact Us', href: storeUrl('/contact') },
        { label: labels.faq || 'FAQ', href: storeUrl('/faq') },
    ];

    const policyLinks = [
        { label: 'Privacy Policy', href: storeUrl('/privacy-policy'), field: 'privacy_policy' },
        { label: 'Terms & Conditions', href: storeUrl('/terms-and-conditions'), field: 'terms_conditions' },
        { label: 'Shipping Policy', href: storeUrl('/shipping-policy'), field: 'shipping_policy' },
        { label: 'Return Policy', href: storeUrl('/return-policy'), field: 'return_policy' },
        { label: 'Refund Policy', href: storeUrl('/refund-policy'), field: 'refund_policy' },
    ].filter((link) => website_info?.[link.field]);

    return (
        <>
            <ContactDrawer open={drawerOpen} onClose={() => setDrawerOpen(false)} />
            <InfoModal
                open={infoModal === 'description'}
                onClose={() => setInfoModal(null)}
                title={`About ${siteName}`}
            >
                <span dangerouslySetInnerHTML={{ __html: description }} />
            </InfoModal>
            <InfoModal
                open={infoModal === 'extra'}
                onClose={() => setInfoModal(null)}
                title={`About ${siteName}`}
            >
                <span dangerouslySetInnerHTML={{ __html: extraText }} />
            </InfoModal>
            <BackToTop label={labels.back_to_top || 'Back to top'} />

            <footer style={{ backgroundColor: 'var(--storefront-color-text, #0F172A)' }} className="text-white">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div style={{ borderColor: 'color-mix(in srgb, var(--storefront-color-surface, #FFFFFF) 12%, transparent)' }} className="py-8 lg:py-10 border-b">
                        <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-6 lg:gap-8">
                            {/* Brand */}
                            <div className="col-span-2 md:col-span-1 lg:col-span-1.5">
                                <Link href={storeUrl('/')} className="flex items-center gap-2.5 mb-3">
                                    {logoUrl ? (
                                        <img src={logoUrl} alt={siteName} className="h-8 w-auto" />
                                     ) : (
                                        <div className="h-8 w-8 rounded-lg flex items-center justify-center text-white text-sm font-bold" style={{ backgroundColor: themeColor }}>
                                            {siteName.trim().charAt(0).toUpperCase() || 'S'}
                                        </div>
                                    )}
                                    <span className="text-lg font-bold">{siteName}</span>
                                </Link>
                                {descPreview && <p className="text-slate-400 text-xs leading-relaxed">{descPreview}</p>}
                                {(descTruncated || extraText) && (
                                    <div className="flex items-center gap-2 mt-2">
                                        {descTruncated && (
                                            <button
                                                onClick={() => setInfoModal('description')}
                                                className="text-xs font-medium transition-colors"
                                                style={{ color: themeColor }}
                                            >
                                                {labels.read_more || 'Read More'} &rarr;
                                            </button>
                                        )}
                                        {extraText && (
                                            <button
                                                onClick={() => setInfoModal('extra')}
                                                className="text-xs font-medium transition-colors"
                                                style={{ color: themeColor }}
                                            >
                                                {labels.about_our_store || 'About Our Store'} &rarr;
                                            </button>
                                        )}
                                    </div>
                                )}
                                {socials.length > 0 && (
                                    <div className="flex items-center gap-1.5 mt-3">
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

                            {/* Quick Links */}
                            <div>
                                <h4 className="text-xs font-semibold text-white uppercase tracking-wider mb-3">{labels.quick_links || 'Quick Links'}</h4>
                                <ul className="space-y-2">
                                    {quickLinks.map((link) => (
                                        <li key={link.href + link.label}>
                                            <Link href={link.href} className="text-slate-400 hover:text-white text-xs transition-colors">
                                                {link.label}
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            </div>

                            {/* Customer Service */}
                            <div>
                                <h4 className="text-xs font-semibold text-white uppercase tracking-wider mb-3">{labels.customer_service || 'Customer Service'}</h4>
                                <ul className="space-y-2">
                                    {customerServiceLinks.map((link) => (
                                        <li key={link.href + link.label}>
                                            <Link href={link.href} className="text-slate-400 hover:text-white text-xs transition-colors">
                                                {link.label}
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            </div>

                            {/* Policies */}
                            {policyLinks.length > 0 && <div>
                                <h4 className="text-xs font-semibold text-white uppercase tracking-wider mb-3">{labels.policies || 'Policies'}</h4>
                                <ul className="space-y-2">
                                    {policyLinks.map((link) => (
                                        <li key={link.href + link.label}>
                                            <Link href={link.href} className="text-slate-400 hover:text-white text-xs transition-colors">
                                                {link.label}
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            </div>}

                            {/* Contact */}
                             {hasMiniContact && <div className="col-span-2 md:col-span-1">
                                <h4 className="text-xs font-semibold text-white uppercase tracking-wider mb-3">{labels.contact || 'Contact'}</h4>
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
                                <button
                                    onClick={() => setDrawerOpen(true)}
                                    className="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg transition-all duration-200 hover:opacity-90"
                                    style={{ backgroundColor: themeColor, color: '#fff' }}
                                >
                                    <i className="bi bi-info-circle" style={{ fontSize: '0.7rem' }}></i>
                                    {labels.contact_details || 'Contact Details'}
                                    <i className="bi bi-chevron-right" style={{ fontSize: '0.6rem' }}></i>
                                </button>
                             </div>}
                         </div>
                    </div>

                    <div className="py-4 flex flex-col sm:flex-row justify-between items-center gap-3">
                        <div className="flex min-w-0 items-center gap-2 text-slate-500 text-xs">
                            <i className="bi bi-copyright"></i>
                            <span className="min-w-0 break-words">{footerCopyright}</span>
                        </div>
                        <div className="flex items-center gap-4 text-xs">
                            <span className="text-slate-500">{labels.powered_by || 'Powered by'}</span>
                            <span className="font-semibold text-white">{siteName}</span>
                        </div>
                    </div>
                </div>
            </footer>
        </>
    );
}
