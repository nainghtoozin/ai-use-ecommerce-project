import { useState, useEffect } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { assetUrl } from '@/Utils/helpers';
import ContactDrawer from '@/Components/ContactDrawer';

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

function FooterLink({ href, children }) {
    return (
        <li>
            <Link href={href} className="text-slate-400 hover:text-white text-xs transition-colors">
                {children}
            </Link>
        </li>
    );
}

function ColumnTitle({ children }) {
    return (
        <h4 className="text-xs font-semibold text-white uppercase tracking-wider mb-3">{children}</h4>
    );
}

export default function ShopFooter() {
    const { website_info, storefront, tenant } = usePage().props;
    const [drawerOpen, setDrawerOpen] = useState(false);

    const labels = storefront?.content?.labels || {};
    const storeSlug = tenant?.slug;
    const storeUrl = (path) => `/store/${storeSlug}${path}`;

    const fs = website_info?.footer_settings || {};
    const logoSource = website_info?.footer_logo_url || storefront?.identity?.logo_url || website_info?.logo;
    const logoUrl = assetUrl(logoSource, false);
    const siteName = storefront?.identity?.site_title || storefront?.identity?.name || tenant?.name || website_info?.site_name || 'Store';
    const themeColor = 'var(--theme-color, #3B82F6)';

    const ci = website_info?.contact_info || {};
    const tagline = website_info?.footer_description || website_info?.site_tagline || '';

    const whatsappNumber = ci.whatsapp_number || website_info?.whatsapp_number;
    const whatsappLink = whatsappNumber ? `https://wa.me/${whatsappNumber.replace(/\D/g, '')}` : null;

    const phone = ci.primary_phone || website_info?.phone;
    const email = ci.contact_email || website_info?.contact_email;

    return (
        <>
            <ContactDrawer open={drawerOpen} onClose={() => setDrawerOpen(false)} />
            <BackToTop label={labels.back_to_top || 'Back to top'} />

            <footer style={{ backgroundColor: 'var(--storefront-color-text, #0F172A)' }} className="text-white">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div style={{ borderColor: 'color-mix(in srgb, var(--storefront-color-surface, #FFFFFF) 12%, transparent)' }} className="py-7 border-b">
                        <div className="grid grid-cols-2 lg:grid-cols-4 gap-6">
                            <div>
                                <Link href={storeUrl('/')} className="flex items-center gap-2 mb-2.5">
                                    {logoUrl ? (
                                        <img src={logoUrl} alt={siteName} className="h-7 w-auto" />
                                    ) : (
                                        <div className="h-7 w-7 rounded-lg flex items-center justify-center text-white text-xs font-bold" style={{ backgroundColor: themeColor }}>
                                            {siteName.trim().charAt(0).toUpperCase() || 'S'}
                                        </div>
                                    )}
                                    <span className="text-base font-bold truncate">{siteName}</span>
                                </Link>
                                {tagline && <p className="text-slate-400 text-xs leading-relaxed line-clamp-2 mb-3">{tagline}</p>}
                                {whatsappLink && (
                                    <a
                                        href={whatsappLink}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg bg-green-600 text-white hover:bg-green-700 transition-colors"
                                    >
                                        <i className="bi bi-whatsapp"></i>
                                        WhatsApp
                                    </a>
                                )}
                            </div>

                            <div>
                                <ColumnTitle>{labels.quick_links || 'Quick Links'}</ColumnTitle>
                                <ul className="space-y-2">
                                    <FooterLink href={storeUrl('/')}>{labels.home || 'Home'}</FooterLink>
                                    <FooterLink href={storeUrl('/products')}>{labels.products || 'Products'}</FooterLink>
                                    <FooterLink href={storeUrl('/contact')}>{labels.contact || 'Contact'}</FooterLink>
                                </ul>
                            </div>

                            <div>
                                <ColumnTitle>{labels.customer_help || 'Customer Help'}</ColumnTitle>
                                <ul className="space-y-2">
                                    <FooterLink href={storeUrl('/support')}>{labels.help_support || 'Help & Support'}</FooterLink>
                                    <FooterLink href={storeUrl('/faq')}>{labels.faq || 'FAQ'}</FooterLink>
                                    <FooterLink href={storeUrl('/customer/orders')}>{labels.my_orders || 'My Orders'}</FooterLink>
                                </ul>
                            </div>

                            <div>
                                <ColumnTitle>{labels.contact || 'Contact'}</ColumnTitle>
                                <div className="space-y-2 mb-3">
                                    {phone && (
                                        <a href={`tel:${phone.replace(/\s+/g, '')}`} className="flex items-center gap-2 text-xs text-slate-400 hover:text-white transition-colors">
                                            <i className="bi bi-telephone"></i>
                                            <span className="truncate">{phone}</span>
                                        </a>
                                    )}
                                    {email && (
                                        <a href={`mailto:${email}`} className="flex items-center gap-2 text-xs text-slate-400 hover:text-white transition-colors">
                                            <i className="bi bi-envelope"></i>
                                            <span className="truncate">{email}</span>
                                        </a>
                                    )}
                                    {!phone && !email && (
                                        <p className="text-xs text-slate-500">Contact info coming soon.</p>
                                    )}
                                </div>
                                <button
                                    onClick={() => setDrawerOpen(true)}
                                    className="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg transition-all duration-200 hover:opacity-90"
                                    style={{ backgroundColor: themeColor, color: '#fff' }}
                                >
                                    <i className="bi bi-info-circle"></i>
                                    {labels.contact_details || 'Contact Details'}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div className="py-4 flex flex-col sm:flex-row justify-between items-center gap-2">
                        <p className="text-xs text-slate-500">
                            &copy; {new Date().getFullYear()} {siteName}. All rights reserved.
                        </p>
                        <div className="flex items-center gap-1.5 text-xs">
                            <Link href={storeUrl('/privacy-policy')} className="text-slate-400 hover:text-white transition-colors">
                                Privacy Policy
                            </Link>
                            <span className="text-slate-600">&middot;</span>
                            <Link href={storeUrl('/terms-and-conditions')} className="text-slate-400 hover:text-white transition-colors">
                                Terms & Conditions
                            </Link>
                        </div>
                    </div>
                </div>
            </footer>
        </>
    );
}
