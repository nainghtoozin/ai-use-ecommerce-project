import { useEffect } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { assetUrl } from '@/Utils/helpers';

export default function Maintenance() {
    const { props } = usePage();
    const {
        message,
        siteName,
        logoUrl,
        contactEmail,
        phone,
        socialLinks,
    } = props;

    const logoSrc = assetUrl(logoUrl);

    useEffect(() => {
        const timer = setInterval(() => {
            window.location.reload();
        }, 60000);
        return () => clearInterval(timer);
    }, []);

    const social = socialLinks || {};

    return (
        <>
            <Head title={`${siteName || 'Store'} — Under Maintenance`} />
            <div className="min-h-screen bg-gradient-to-br from-gray-50 via-white to-gray-50 flex flex-col">
                <div className="flex-1 flex items-center justify-center px-4 sm:px-6 lg:px-8 py-12">
                    <div className="max-w-lg w-full text-center">
                        <div className="mb-10 flex justify-center">
                            {logoSrc ? (
                                <img src={logoSrc} alt={siteName} className="h-10 sm:h-12 w-auto" />
                            ) : (
                                <div className="h-14 w-14 rounded-2xl flex items-center justify-center shadow-sm" style={{ backgroundColor: 'var(--theme-color, #3B82F6)' }}>
                                    <i className="bi bi-shop text-white text-2xl"></i>
                                </div>
                            )}
                        </div>

                        <div className="mb-8 flex justify-center">
                            <div className="relative w-16 h-16">
                                <div className="absolute inset-0 rounded-full border-[3px] border-gray-100"></div>
                                <div className="absolute inset-0 rounded-full border-[3px] border-transparent rounded-full animate-spin" style={{ borderTopColor: 'var(--theme-color, #3B82F6)', animationDuration: '0.8s' }}></div>
                                <div className="absolute inset-0 rounded-full border-[3px] border-transparent rounded-full animate-spin" style={{ borderRightColor: 'var(--theme-color, #3B82F6)', animationDuration: '1.2s', animationDirection: 'reverse' }}></div>
                                <div className="absolute inset-[14px] rounded-full flex items-center justify-center" style={{ backgroundColor: 'var(--theme-color, #3B82F6)' }}>
                                    <i className="bi bi-gear text-white text-lg"></i>
                                </div>
                            </div>
                        </div>

                        <h1 className="text-2xl sm:text-3xl font-bold text-gray-900 mb-3 tracking-tight">
                            Under Maintenance
                        </h1>
                        <p className="text-gray-500 text-base sm:text-lg leading-relaxed mb-2">
                            {message || 'We are currently performing scheduled maintenance. Please check back soon.'}
                        </p>
                        <p className="text-gray-400 text-sm">
                            We expect to be back shortly. Thank you for your patience.
                        </p>

                        {(contactEmail || phone) && (
                            <div className="mt-10 pt-8 border-t border-gray-100">
                                <p className="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-4">
                                    Need help?
                                </p>
                                <div className="flex flex-wrap items-center justify-center gap-3">
                                    {contactEmail && (
                                        <a
                                            href={`mailto:${contactEmail}`}
                                            className="inline-flex items-center gap-2 px-4 py-2.5 bg-white rounded-xl text-sm font-medium text-gray-700 border border-gray-200 shadow-sm hover:shadow-md hover:border-gray-300 hover:text-gray-900 transition-all duration-200"
                                        >
                                            <i className="bi bi-envelope text-gray-400"></i>
                                            <span>{contactEmail}</span>
                                        </a>
                                    )}
                                    {phone && (
                                        <a
                                            href={`tel:${phone}`}
                                            className="inline-flex items-center gap-2 px-4 py-2.5 bg-white rounded-xl text-sm font-medium text-gray-700 border border-gray-200 shadow-sm hover:shadow-md hover:border-gray-300 hover:text-gray-900 transition-all duration-200"
                                        >
                                            <i className="bi bi-telephone text-gray-400"></i>
                                            <span>{phone}</span>
                                        </a>
                                    )}
                                </div>
                            </div>
                        )}

                        {(social.facebook_url || social.twitter_url || social.instagram_url || social.linkedin_url || social.youtube_url) && (
                            <div className="mt-6">
                                <p className="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-3">
                                    Follow us for updates
                                </p>
                                <div className="flex items-center justify-center gap-2">
                                    {social.facebook_url && (
                                        <a href={social.facebook_url} target="_blank" rel="noopener noreferrer" className="w-10 h-10 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center text-gray-500 hover:text-gray-700 transition-all duration-200">
                                            <i className="bi bi-facebook text-lg"></i>
                                        </a>
                                    )}
                                    {social.twitter_url && (
                                        <a href={social.twitter_url} target="_blank" rel="noopener noreferrer" className="w-10 h-10 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center text-gray-500 hover:text-gray-700 transition-all duration-200">
                                            <i className="bi bi-twitter-x text-lg"></i>
                                        </a>
                                    )}
                                    {social.instagram_url && (
                                        <a href={social.instagram_url} target="_blank" rel="noopener noreferrer" className="w-10 h-10 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center text-gray-500 hover:text-gray-700 transition-all duration-200">
                                            <i className="bi bi-instagram text-lg"></i>
                                        </a>
                                    )}
                                    {social.linkedin_url && (
                                        <a href={social.linkedin_url} target="_blank" rel="noopener noreferrer" className="w-10 h-10 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center text-gray-500 hover:text-gray-700 transition-all duration-200">
                                            <i className="bi bi-linkedin text-lg"></i>
                                        </a>
                                    )}
                                    {social.youtube_url && (
                                        <a href={social.youtube_url} target="_blank" rel="noopener noreferrer" className="w-10 h-10 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center text-gray-500 hover:text-gray-700 transition-all duration-200">
                                            <i className="bi bi-youtube text-lg"></i>
                                        </a>
                                    )}
                                </div>
                            </div>
                        )}

                        <div className="mt-8">
                            <Link
                                href="/"
                                className="inline-flex items-center gap-1.5 text-sm text-gray-400 hover:text-gray-600 transition-colors"
                            >
                                <i className="bi bi-arrow-clockwise"></i>
                                <span>Refresh</span>
                            </Link>
                        </div>
                    </div>
                </div>
                <div className="py-6 text-center">
                    <p className="text-xs text-gray-300">
                        Auto-refreshes every 60 seconds
                    </p>
                </div>
            </div>
        </>
    );
}
