import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { useTranslation } from '@/Utils/useTranslation';

export default function CtaSection() {
    const { t } = useTranslation();

    return (
        <section className="py-16 sm:py-20 lg:py-24 bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900 dark:from-gray-950 dark:via-gray-900 dark:to-gray-950">
            <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <h2 className="text-3xl sm:text-4xl font-bold text-white mb-4">
                    {t('landing.cta.title')}
                </h2>
                <p className="text-lg text-gray-300 dark:text-gray-400 max-w-2xl mx-auto mb-8">
                    {t('landing.cta.subtitle')}
                </p>
                <div className="flex flex-col sm:flex-row items-center justify-center gap-4">
                    <Link
                        href="/register"
                        className="inline-flex items-center gap-2 px-8 py-3.5 bg-white text-gray-900 font-semibold text-base rounded-xl hover:bg-gray-100 transition-all shadow-lg hover:shadow-xl focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-gray-900"
                    >
                        {t('landing.cta.button')}
                        <ArrowRight className="w-4 h-4" />
                    </Link>
                    <span className="text-sm text-gray-400">
                        {t('landing.cta.no_card')}
                    </span>
                </div>
            </div>
        </section>
    );
}
