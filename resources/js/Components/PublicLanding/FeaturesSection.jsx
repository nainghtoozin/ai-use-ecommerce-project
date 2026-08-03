import { Package, ShoppingCart, Warehouse, Percent, BarChart3, CreditCard, Brain, MessageSquare, Globe } from 'lucide-react';
import { useTranslation } from '@/Utils/useTranslation';

const featureKeys = ['products', 'orders', 'inventory', 'marketing', 'analytics', 'payments', 'ai', 'telegram', 'domains'];
const featureIcons = [Package, ShoppingCart, Warehouse, Percent, BarChart3, CreditCard, Brain, MessageSquare, Globe];

export default function FeaturesSection() {
    const { t } = useTranslation();

    return (
        <section id="features" className="py-16 sm:py-20 lg:py-24 bg-gray-50 dark:bg-gray-950">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="text-center max-w-2xl mx-auto mb-14">
                    <h2 className="text-3xl sm:text-4xl font-bold text-gray-900 dark:text-gray-100">
                        {t('landing.features.title')}
                    </h2>
                    <p className="mt-4 text-gray-500 dark:text-gray-400 text-lg">
                        {t('landing.features.subtitle')}
                    </p>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8">
                    {featureKeys.map((key, index) => {
                        const Icon = featureIcons[index];
                        return (
                            <div
                                key={key}
                                className="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 p-6 sm:p-8 hover:shadow-md hover:border-gray-300 dark:hover:border-gray-700 transition-all duration-200"
                            >
                                <div className="w-10 h-10 rounded-xl bg-indigo-100 dark:bg-indigo-950 flex items-center justify-center mb-4">
                                    <Icon className="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                                </div>
                                <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">
                                    {t(`landing.features.items.${key}.title`)}
                                </h3>
                                <p className="text-sm text-gray-500 dark:text-gray-400 leading-relaxed">
                                    {t(`landing.features.items.${key}.description`)}
                                </p>
                            </div>
                        );
                    })}
                </div>
            </div>
        </section>
    );
}
