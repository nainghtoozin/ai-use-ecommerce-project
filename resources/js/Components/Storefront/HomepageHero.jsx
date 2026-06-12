import { Link } from '@inertiajs/react';
import { assetUrl } from '@/Utils/helpers';

export default function HomepageHero({ websiteInfo }) {
    const siteName = websiteInfo?.site_name || 'My Store';
    const logoUrl = assetUrl(websiteInfo?.logo);

    return (
        <section className="relative overflow-hidden bg-gradient-to-br from-indigo-600 via-indigo-700 to-purple-800">
            <div className="absolute inset-0 bg-grid-white/5 [mask-image:radial-gradient(ellipse_at_center,white,transparent)]"></div>
            <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 sm:py-28 lg:py-36">
                <div className="max-w-3xl mx-auto text-center">
                    {logoUrl && (
                        <img src={logoUrl} alt={siteName} className="h-12 sm:h-16 w-auto mx-auto mb-6" />
                    )}
                    <h1 className="text-4xl sm:text-5xl lg:text-6xl font-extrabold text-white tracking-tight">
                        Launch Your Online Store
                    </h1>
                    <p className="mt-6 text-lg sm:text-xl text-indigo-100 max-w-2xl mx-auto leading-relaxed">
                        A complete e-commerce platform for Myanmar merchants. Create your branded storefront, manage products, accept orders, and grow your business — all in one place.
                    </p>
                    <div className="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
                        <Link
                            href="/create-store"
                            className="inline-flex items-center gap-2 px-8 py-3.5 bg-white text-indigo-700 font-bold text-base rounded-xl hover:bg-indigo-50 transition-all shadow-xl shadow-indigo-900/20 hover:shadow-indigo-900/30"
                        >
                            <i className="bi bi-plus-circle"></i>
                            Create Your Store
                        </Link>
                        <Link
                            href="/login"
                            className="inline-flex items-center gap-2 px-8 py-3.5 border-2 border-white/30 text-white font-semibold text-base rounded-xl hover:bg-white/10 transition-all"
                        >
                            <i className="bi bi-box-arrow-in-right"></i>
                            Merchant Login
                        </Link>
                    </div>
                </div>
            </div>
            <div className="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-white/20 to-transparent"></div>
        </section>
    );
}
