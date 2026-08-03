import { Zap, Shield, Cloud, Users, Layout, TrendingUp } from 'lucide-react';
import { useTranslation } from '@/Utils/useTranslation';

const benefitKeys = ['fast_setup', 'secure', 'cloud', 'multi_store', 'dashboard', 'scalable'];
const benefitIcons = [Zap, Shield, Cloud, Users, Layout, TrendingUp];

export default function BenefitsSection() {
    const { t } = useTranslation();

    return (
        <section className="py-16 sm:py-20 lg:py-24 bg-white dark:bg-gray-900">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="text-center max-w-2xl mx-auto mb-14">
                    <h2 className="text-3xl sm:text-4xl font-bold text-gray-900 dark:text-gray-100">
                        {t('landing.benefits.title')}
                    </h2>
                    <p className="mt-4 text-gray-500 dark:text-gray-400 text-lg">
                        {t('landing.benefits.subtitle')}
                    </p>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8">
                    {benefitKeys.map((key, index) => {
                        const Icon = benefitIcons[index];
                        return (
                            <div
                                key={key}
                                className="group relative bg-gray-50 dark:bg-gray-950 rounded-2xl p-6 sm:p-8 hover:bg-white dark:hover:bg-gray-900 hover:shadow-lg hover:shadow-blue-500/5 transition-all duration-200 border border-transparent hover:border-gray-200 dark:hover:border-gray-800"
                            >
                                <div className="w-10 h-10 rounded-xl bg-blue-100 dark:bg-blue-950 flex items-center justify-center mb-4 group-hover:bg-blue-600 transition-colors duration-200">
                                    <Icon className="w-5 h-5 text-blue-600 group-hover:text-white transition-colors duration-200" />
                                </div>
                                <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">
                                    {t(`landing.benefits.items.${key}.title`)}
                                </h3>
                                <p className="text-sm text-gray-500 dark:text-gray-400 leading-relaxed">
                                    {t(`landing.benefits.items.${key}.description`)}
                                </p>
                            </div>
                        );
                    })}
                </div>
            </div>
        </section>
    );
}
