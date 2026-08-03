import { Head, usePage } from '@inertiajs/react';
import PlatformLayout from '@/Layouts/PlatformLayout';
import HeroSection from '@/Components/PublicLanding/HeroSection';
import BenefitsSection from '@/Components/PublicLanding/BenefitsSection';
import FeaturesSection from '@/Components/PublicLanding/FeaturesSection';
import PricingSection from '@/Components/PublicLanding/PricingSection';
import TestimonialsSection from '@/Components/PublicLanding/TestimonialsSection';
import StoreDirectorySection from '@/Components/PublicLanding/StoreDirectorySection';
import FaqSection from '@/Components/PublicLanding/FaqSection';
import CtaSection from '@/Components/PublicLanding/CtaSection';
import { useTranslation } from '@/Utils/useTranslation';

export default function Landing() {
    const { platform_setting, plans, featuredStores } = usePage().props;
    const { t } = useTranslation();
    const siteName = platform_setting?.site_name || 'My Store';
    const safePlans = Array.isArray(plans) ? plans : [];
    const safeStores = Array.isArray(featuredStores) ? featuredStores : [];

    return (
        <PlatformLayout>
            <Head>
                <title>{siteName}</title>
                <meta name="description" content={t('landing.hero.description', { siteName })} />
                <meta property="og:title" content={siteName} />
                <meta property="og:description" content={t('landing.cta.subtitle')} />
                <meta name="keywords" content="ecommerce, online store, Myanmar, create store, sell online" />
            </Head>

            <HeroSection />
            <BenefitsSection />
            <FeaturesSection />
            <PricingSection plans={safePlans} />
            <TestimonialsSection />
            <StoreDirectorySection stores={safeStores} />
            <FaqSection />
            <CtaSection />
        </PlatformLayout>
    );
}
