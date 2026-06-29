import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

export default function FinalCtaSection() {
    return (
        <section className="py-16 sm:py-20 lg:py-24 bg-gray-900">
            <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <h2 className="text-3xl sm:text-4xl font-extrabold text-white">
                    Ready to Start Selling?
                </h2>
                <p className="mt-4 text-lg text-gray-400 max-w-lg mx-auto">
                    Join merchants across Myanmar. Create your store today — no credit card required.
                </p>
                <div className="mt-8 flex flex-col sm:flex-row items-center justify-center gap-4">
                    <Link
                        href="/create-store"
                        className="inline-flex items-center gap-2 px-8 py-3.5 bg-white text-gray-900 font-bold text-base rounded-xl hover:bg-gray-100 transition-all shadow-lg"
                    >
                        Start Free Trial
                        <ArrowRight className="w-4 h-4" />
                    </Link>
                    <Link
                        href="/register"
                        className="inline-flex items-center gap-2 px-8 py-3.5 border-2 border-gray-700 text-gray-300 font-semibold text-base rounded-xl hover:border-gray-500 hover:text-white transition-all"
                    >
                        Create Account
                    </Link>
                </div>
                <p className="mt-6 text-sm text-gray-500">
                    No credit card required &middot; Free plan available &middot; Cancel anytime
                </p>
            </div>
        </section>
    );
}
