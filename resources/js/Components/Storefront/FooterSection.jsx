import { Link, usePage } from '@inertiajs/react';
import { assetUrl } from '@/Utils/helpers';

export default function FooterSection({ store, websiteInfo }) {
    const ws = websiteInfo || {};
    const siteName = store?.name || ws.site_name || 'My Store';
    const ci = ws.contact_info || {};
    const fs = ws.footer_settings || {};
    const description = fs.description || ws.footer_description || '';
    const phone = ci.primary_phone || ws.phone;
    const email = ci.support_email || ws.contact_email || ws.support_email;

    const socials = [
        { key: 'facebook', icon: 'bi-facebook', link: ws.facebook_url },
        { key: 'instagram', icon: 'bi-instagram', link: ws.instagram_url },
        { key: 'youtube', icon: 'bi-youtube', link: ws.youtube_url },
    ].filter(s => s.link);

    return (
        <footer className="bg-slate-900 text-white mt-auto">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-12">
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
                    <div className="sm:col-span-2 lg:col-span-1">
                        <h3 className="text-lg font-bold mb-3">{siteName}</h3>
                        {description && (
                            <p className="text-sm text-slate-400 leading-relaxed">{description}</p>
                        )}
                        {socials.length > 0 && (
                            <div className="flex items-center gap-2 mt-4">
                                {socials.map(s => (
                                    <a key={s.key} href={s.link} target="_blank" rel="noopener noreferrer"
                                        className="w-8 h-8 rounded-lg bg-slate-800 flex items-center justify-center text-slate-400 hover:bg-indigo-600 hover:text-white transition-colors">
                                        <i className={`bi ${s.icon} text-xs`}></i>
                                    </a>
                                ))}
                            </div>
                        )}
                    </div>
                    <div>
                        <h4 className="text-xs font-semibold uppercase tracking-wider text-slate-300 mb-3">Quick Links</h4>
                        <ul className="space-y-2">
                            <li><Link href="/" className="text-sm text-slate-400 hover:text-white transition-colors">Home</Link></li>
                            <li><Link href="/products" className="text-sm text-slate-400 hover:text-white transition-colors">Products</Link></li>
                            <li><Link href="/client/about" className="text-sm text-slate-400 hover:text-white transition-colors">About Us</Link></li>
                            <li><Link href="/client/contact" className="text-sm text-slate-400 hover:text-white transition-colors">Contact</Link></li>
                        </ul>
                    </div>
                    <div>
                        <h4 className="text-xs font-semibold uppercase tracking-wider text-slate-300 mb-3">Support</h4>
                        <ul className="space-y-2">
                            <li><Link href="/client/faq" className="text-sm text-slate-400 hover:text-white transition-colors">FAQs</Link></li>
                            <li><Link href="/client/contact" className="text-sm text-slate-400 hover:text-white transition-colors">Shipping Info</Link></li>
                            <li><Link href="/client/privacy" className="text-sm text-slate-400 hover:text-white transition-colors">Privacy Policy</Link></li>
                            <li><Link href="/client/terms" className="text-sm text-slate-400 hover:text-white transition-colors">Terms of Service</Link></li>
                        </ul>
                    </div>
                    <div>
                        <h4 className="text-xs font-semibold uppercase tracking-wider text-slate-300 mb-3">Contact</h4>
                        {phone && (
                            <a href={`tel:${phone}`} className="flex items-center gap-2 text-sm text-slate-400 hover:text-white transition-colors mb-2">
                                <i className="bi bi-telephone text-xs"></i>
                                <span>{phone}</span>
                            </a>
                        )}
                        {email && (
                            <a href={`mailto:${email}`} className="flex items-center gap-2 text-sm text-slate-400 hover:text-white transition-colors">
                                <i className="bi bi-envelope text-xs"></i>
                                <span className="truncate">{email}</span>
                            </a>
                        )}
                    </div>
                </div>
            </div>
            <div className="border-t border-slate-800">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex flex-col sm:flex-row justify-between items-center gap-2 text-xs text-slate-500">
                    <span>&copy; {new Date().getFullYear()} {siteName}. All rights reserved.</span>
                    <span>Powered by {siteName}</span>
                </div>
            </div>
        </footer>
    );
}
