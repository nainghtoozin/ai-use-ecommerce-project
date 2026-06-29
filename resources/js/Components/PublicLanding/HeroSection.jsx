import { Link, usePage } from '@inertiajs/react';
import { Sparkles, ArrowRight, Play } from 'lucide-react';

export default function HeroSection() {
    const { platform_setting } = usePage().props;
    const siteName = platform_setting?.site_name || 'My Store';

    return (
        <section className="relative overflow-hidden bg-gradient-to-b from-slate-50 to-white">
            <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-blue-50 via-transparent to-transparent pointer-events-none" />
            <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_bottom_left,_var(--tw-gradient-stops))] from-indigo-50 via-transparent to-transparent pointer-events-none" />

            <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-20 pb-16 sm:pt-28 sm:pb-20 lg:pt-36 lg:pb-28">
                <div className="text-center max-w-4xl mx-auto">
                    <div className="inline-flex items-center gap-2 px-4 py-1.5 bg-blue-50 border border-blue-100 rounded-full text-sm text-blue-700 font-medium mb-6">
                        <Sparkles className="w-4 h-4" />
                        <span>Launch your online store in minutes</span>
                    </div>

                    <h1 className="text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight text-gray-900 leading-tight">
                        Your E-Commerce Platform
                        <span className="block text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-indigo-600">
                            Built for Myanmar Merchants
                        </span>
                    </h1>

                    <p className="mt-6 text-lg sm:text-xl text-gray-500 max-w-2xl mx-auto leading-relaxed">
                        {siteName} gives you everything you need to launch and grow your online store.
                        No technical skills required. No credit card needed to start.
                    </p>

                    <div className="mt-8 sm:mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
                        <Link
                            href="/create-store"
                            className="inline-flex items-center gap-2 px-8 py-3.5 bg-gray-900 text-white font-semibold text-base rounded-xl hover:bg-gray-800 transition-all shadow-lg hover:shadow-xl focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2"
                        >
                            Start Free Trial
                            <ArrowRight className="w-4 h-4" />
                        </Link>
                        <Link
                            href="/#pricing"
                            className="inline-flex items-center gap-2 px-8 py-3.5 border-2 border-gray-200 text-gray-700 font-semibold text-base rounded-xl hover:border-gray-300 hover:bg-gray-50 transition-all focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-offset-2"
                        >
                            <Play className="w-4 h-4" />
                            View Pricing
                        </Link>
                    </div>

                    <div className="mt-12 flex flex-wrap items-center justify-center gap-8 sm:gap-12 text-sm text-gray-400">
                        <div className="flex items-center gap-2">
                            <div className="w-1.5 h-1.5 rounded-full bg-emerald-400" />
                            <span>Free trial available</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <div className="w-1.5 h-1.5 rounded-full bg-emerald-400" />
                            <span>No credit card</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <div className="w-1.5 h-1.5 rounded-full bg-emerald-400" />
                            <span>Cancel anytime</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}
