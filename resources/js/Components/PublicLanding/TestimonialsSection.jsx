import { useTranslation } from '@/Utils/useTranslation';
import { Star } from 'lucide-react';

export default function TestimonialsSection() {
    const { t } = useTranslation();

    const items = t('landing.testimonials.items');
    const testimonials = Array.isArray(items) ? items : [];

    if (testimonials.length === 0) return null;

    return (
        <section className="py-16 sm:py-20 lg:py-24 bg-gray-50 dark:bg-gray-950">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="text-center max-w-2xl mx-auto mb-14">
                    <h2 className="text-3xl sm:text-4xl font-bold text-gray-900 dark:text-gray-100">
                        {t('landing.testimonials.title')}
                    </h2>
                    <p className="mt-4 text-gray-500 dark:text-gray-400 text-lg">
                        {t('landing.testimonials.subtitle')}
                    </p>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-6 lg:gap-8">
                    {testimonials.map((testimonial, index) => (
                        <div
                            key={index}
                            className="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 p-6 sm:p-8 hover:shadow-md transition-all duration-200"
                        >
                            <div className="flex gap-1 mb-4">
                                {[...Array(5)].map((_, i) => (
                                    <Star key={i} className="w-4 h-4 fill-amber-400 text-amber-400" />
                                ))}
                            </div>
                            <p className="text-sm text-gray-600 dark:text-gray-400 leading-relaxed mb-6">
                                "{testimonial.content}"
                            </p>
                            <div className="flex items-center gap-3">
                                <div className="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white font-bold text-sm">
                                    {testimonial.name?.charAt(0)}
                                </div>
                                <div>
                                    <p className="text-sm font-semibold text-gray-900 dark:text-gray-100">{testimonial.name}</p>
                                    <p className="text-xs text-gray-500 dark:text-gray-400">{testimonial.role}</p>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}
