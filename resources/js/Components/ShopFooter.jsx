import { Link, usePage } from '@inertiajs/react';
import { assetUrl } from '@/Utils/helpers';

export default function ShopFooter() {
    const { website_info } = usePage().props;
    const siteName = website_info?.site_name || 'My Store';
    const logoUrl = assetUrl(website_info?.logo);

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
        { key: 'facebook', icon: 'bi-facebook', link: website_info?.facebook_url, color: 'hover:bg-blue-600' },
        { key: 'whatsapp', icon: 'bi-whatsapp', link: website_info?.whatsapp_number ? `https://wa.me/${website_info.whatsapp_number.replace(/\D/g,'')}` : null, color: 'hover:bg-green-500' },
        { key: 'telegram', icon: 'bi-telegram', link: website_info?.telegram_username ? `https://t.me/${website_info.telegram_username}` : null, color: 'hover:bg-sky-500' },
        { key: 'instagram', icon: 'bi-instagram', link: website_info?.instagram_url, color: 'hover:bg-pink-500' },
        { key: 'youtube', icon: 'bi-youtube', link: website_info?.youtube_url, color: 'hover:bg-red-600' },
        { key: 'linkedin', icon: 'bi-linkedin', link: website_info?.linkedin_url, color: 'hover:bg-blue-700' },
    ].filter(s => s.link);

    return (
        <footer className="bg-slate-900 text-white">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="py-10 lg:py-14 border-b border-slate-800">
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-8 lg:gap-10">
                        <div className="lg:col-span-1.5">
                            <Link href="/" className="flex items-center gap-2.5 mb-4">
                                {logoUrl ? (
                                    <img src={logoUrl} alt={siteName} className="h-9 w-auto" />
                                ) : (
                                    <div className="h-9 w-9 bg-blue-600 rounded-lg flex items-center justify-center">
                                        <i className="bi bi-shop text-white text-lg"></i>
                                    </div>
                                )}
                                <span className="text-xl font-bold">{siteName}</span>
                            </Link>
                            <p className="text-slate-400 text-sm leading-relaxed mb-5">
                                {website_info?.about_description || website_info?.footer_description || 'Your trusted destination for quality products.'}
                            </p>
                            {socials.length > 0 && (
                                <div className="flex items-center gap-2">
                                    {socials.map((social) => (
                                        <a
                                            key={social.key}
                                            href={social.link}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className={`w-9 h-9 bg-slate-800 ${social.color} rounded-lg flex items-center justify-center text-sm transition-all duration-200 hover:shadow-lg`}
                                        >
                                            <i className={`bi ${social.icon}`}></i>
                                        </a>
                                    ))}
                                </div>
                            )}
                        </div>

                        <div>
                            <h4 className="text-sm font-semibold text-white uppercase tracking-wider mb-4">Shop</h4>
                            <ul className="space-y-2.5">
                                {shopLinks.map((link) => (
                                    <li key={link.href + link.label}>
                                        <Link href={link.href} className="text-slate-400 hover:text-white text-sm transition-colors">
                                            {link.label}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </div>

                        <div>
                            <h4 className="text-sm font-semibold text-white uppercase tracking-wider mb-4">Support</h4>
                            <ul className="space-y-2.5">
                                {supportLinks.map((link) => (
                                    <li key={link.href + link.label}>
                                        <Link href={link.href} className="text-slate-400 hover:text-white text-sm transition-colors">
                                            {link.label}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </div>

                        <div>
                            <h4 className="text-sm font-semibold text-white uppercase tracking-wider mb-4">Company</h4>
                            <ul className="space-y-2.5">
                                {companyLinks.map((link) => (
                                    <li key={link.href + link.label}>
                                        <Link href={link.href} className="text-slate-400 hover:text-white text-sm transition-colors">
                                            {link.label}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </div>

                        <div>
                            <h4 className="text-sm font-semibold text-white uppercase tracking-wider mb-4">Contact</h4>
                            <ul className="space-y-3">
                                {website_info?.phone && (
                                    <li>
                                        <a href={`tel:${website_info.phone}`} className="flex items-start gap-3 group">
                                            <div className="w-8 h-8 bg-slate-800 group-hover:bg-blue-600 rounded-lg flex items-center justify-center text-slate-400 group-hover:text-white transition-colors flex-shrink-0">
                                                <i className="bi bi-telephone text-sm"></i>
                                            </div>
                                            <div>
                                                <p className="text-xs text-slate-500">Phone</p>
                                                <p className="text-sm text-slate-300 group-hover:text-white transition-colors">{website_info.phone}</p>
                                            </div>
                                        </a>
                                    </li>
                                )}
                                {website_info?.support_email && (
                                    <li>
                                        <a href={`mailto:${website_info.support_email}`} className="flex items-start gap-3 group">
                                            <div className="w-8 h-8 bg-slate-800 group-hover:bg-blue-600 rounded-lg flex items-center justify-center text-slate-400 group-hover:text-white transition-colors flex-shrink-0">
                                                <i className="bi bi-envelope text-sm"></i>
                                            </div>
                                            <div>
                                                <p className="text-xs text-slate-500">Email</p>
                                                <p className="text-sm text-slate-300 group-hover:text-white transition-colors break-all">{website_info.support_email}</p>
                                            </div>
                                        </a>
                                    </li>
                                )}
                                {website_info?.address && (
                                    <li>
                                        <div className="flex items-start gap-3 group">
                                            <div className="w-8 h-8 bg-slate-800 group-hover:bg-blue-600 rounded-lg flex items-center justify-center text-slate-400 group-hover:text-white transition-colors flex-shrink-0">
                                                <i className="bi bi-geo-alt text-sm"></i>
                                            </div>
                                            <div>
                                                <p className="text-xs text-slate-500">Address</p>
                                                <p className="text-sm text-slate-300 group-hover:text-white transition-colors">{website_info.address}</p>
                                            </div>
                                        </div>
                                    </li>
                                )}
                            </ul>
                        </div>
                    </div>
                </div>

                <div className="py-5 flex flex-col sm:flex-row justify-between items-center gap-4">
                    <div className="flex items-center gap-2 text-slate-500 text-sm">
                        <i className="bi bi-copyright"></i>
                        <span>{new Date().getFullYear()} {siteName}. All rights reserved.</span>
                    </div>
                    <div className="flex items-center gap-6 text-sm">
                        <span className="text-slate-500">Powered by</span>
                        <span className="font-semibold text-white">{siteName}</span>
                    </div>
                </div>
            </div>
        </footer>
    );
}