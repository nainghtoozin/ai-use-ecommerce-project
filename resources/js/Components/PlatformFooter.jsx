import { Link, usePage } from '@inertiajs/react';
import { assetUrl } from '@/Utils/helpers';

export default function PlatformFooter() {
    const { platform_setting } = usePage().props;
    const logoUrl = assetUrl(platform_setting?.site_logo);
    const siteName = platform_setting?.site_name || 'My Store';
    const themeColor = 'var(--theme-color, #3B82F6)';

    const platformLinks = [
        { label: 'Create Store', href: '/create-store' },
        { label: 'Features', href: '/#features' },
        { label: 'How It Works', href: '/#how-it-works' },
    ];

    const companyLinks = [
        { label: 'About Us', href: '/client/about' },
        { label: 'Privacy Policy', href: '/client/privacy' },
        { label: 'Terms of Service', href: '/client/terms' },
        { label: 'Contact', href: '/client/contact' },
    ];

    return (
        <footer className="bg-slate-900 text-white">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="py-8 lg:py-10 border-b border-slate-800">
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-6 lg:gap-8">
                        <div className="col-span-2">
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
                            <p className="text-slate-400 text-xs leading-relaxed max-w-md">
                                Launch Your Online Store — A complete e-commerce platform for Myanmar merchants.
                                Create your branded storefront, manage products, accept orders, and grow your business — all in one place.
                            </p>
                        </div>

                        <div>
                            <h4 className="text-xs font-semibold text-white uppercase tracking-wider mb-3">Platform</h4>
                            <ul className="space-y-2">
                                {platformLinks.map((link) => (
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
                    </div>
                </div>

                <div className="py-4 flex flex-col sm:flex-row justify-between items-center gap-3">
                    <div className="flex items-center gap-2 text-slate-500 text-xs">
                        <i className="bi bi-copyright"></i>
                        <span>{new Date().getFullYear()} {siteName}. All rights reserved.</span>
                    </div>
                    {platform_setting?.support_email && (
                        <a href={`mailto:${platform_setting.support_email}`} className="text-xs text-slate-400 hover:text-white transition-colors">
                            <i className="bi bi-envelope mr-1"></i>
                            {platform_setting.support_email}
                        </a>
                    )}
                </div>
            </div>
        </footer>
    );
}
