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
    const [sheetOpen, setSheetOpen] = useState(false);

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

            {/* ===== MOBILE / TABLET FLOATING HELP BUTTON + BOTTOM SHEET ===== */}
            <div className="md:hidden">
                {/* Floating help button */}
                <button
                    type="button"
                    onClick={() => setSheetOpen(true)}
                    className="fixed bottom-[calc(5rem+env(safe-area-inset-bottom))] right-4 z-50 flex items-center justify-center w-12 h-12 rounded-full bg-blue-600 text-white shadow-lg hover:bg-blue-700 active:scale-95 transition-all duration-200"
                    aria-label="Help and store information"
                >
                    <i className="bi bi-chat-dots text-lg"></i>
                </button>

                {sheetOpen && (
                    <div className="fixed inset-0 z-[60] flex items-end" onClick={() => setSheetOpen(false)}>
                        <div className="absolute inset-0 bg-black/40" />
                        <div
                            className="relative w-full bg-white dark:bg-gray-900 rounded-t-2xl shadow-xl max-h-[68vh] flex flex-col"
                            onClick={(e) => e.stopPropagation()}
                            style={{ paddingBottom: 'env(safe-area-inset-bottom)' }}
                        >
                            <div className="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-gray-800">
                                <h3 className="text-base font-semibold text-gray-900 dark:text-gray-100">Need Help?</h3>
                                <button type="button" onClick={() => setSheetOpen(false)} className="text-gray-400 hover:text-gray-600" aria-label="Close">
                                    <i className="bi bi-x-lg text-lg"></i>
                                </button>
                            </div>
                            <div className="overflow-y-auto px-3 py-3 space-y-1 flex-1">
                                <Link href={storeUrl('/support')} onClick={() => setSheetOpen(false)} className="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                    <i className="bi bi-question-circle text-blue-600 dark:text-blue-400 text-base w-5 text-center"></i>
                                    <span className="text-sm text-gray-700 dark:text-gray-300">Help & Support</span>
                                </Link>
                                <Link href={storeUrl('/faq')} onClick={() => setSheetOpen(false)} className="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                    <i className="bi bi-chat-square-text text-blue-600 dark:text-blue-400 text-base w-5 text-center"></i>
                                    <span className="text-sm text-gray-700 dark:text-gray-300">FAQ</span>
                                </Link>
                                <Link href={storeUrl('/contact')} onClick={() => setSheetOpen(false)} className="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                    <i className="bi bi-envelope text-blue-600 dark:text-blue-400 text-base w-5 text-center"></i>
                                    <span className="text-sm text-gray-700 dark:text-gray-300">Contact Us</span>
                                </Link>
                                <Link href={storeUrl('/about')} onClick={() => setSheetOpen(false)} className="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                    <i className="bi bi-info-circle text-blue-600 dark:text-blue-400 text-base w-5 text-center"></i>
                                    <span className="text-sm text-gray-700 dark:text-gray-300">About Store</span>
                                </Link>
                                <Link href={storeUrl('/shipping-policy')} onClick={() => setSheetOpen(false)} className="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                    <i className="bi bi-truck text-blue-600 dark:text-blue-400 text-base w-5 text-center"></i>
                                    <span className="text-sm text-gray-700 dark:text-gray-300">Delivery Information</span>
                                </Link>
                                <Link href={storeUrl('/return-policy')} onClick={() => setSheetOpen(false)} className="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                    <i className="bi bi-arrow-return-left text-blue-600 dark:text-blue-400 text-base w-5 text-center"></i>
                                    <span className="text-sm text-gray-700 dark:text-gray-300">Return Policy</span>
                                </Link>
                            </div>
                            <div className="px-5 py-3 border-t border-gray-200 dark:border-gray-800">
                                <p className="text-[11px] text-gray-400 dark:text-gray-500 text-center">
                                    &copy; {new Date().getFullYear()} {siteName}. All rights reserved.
                                </p>
                            </div>
                        </div>
                    </div>
                )}
            </div>

            {/* ===== MOBILE MINIMAL FOOTER (no duplicate branding) ===== */}
            <footer className="md:hidden bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-800 px-4 py-2 pb-20">
                <p className="text-[11px] text-gray-400 dark:text-gray-500 text-center">
                    &copy; {new Date().getFullYear()} {siteName}. All rights reserved.
                </p>
            </footer>

            {/* ===== DESKTOP FOOTER (unchanged) ===== */}
            <footer style={{ backgroundColor: 'var(--storefront-color-text, #0F172A)' }} className="hidden md:block text-white">
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


