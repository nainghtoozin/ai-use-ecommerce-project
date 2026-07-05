import { Head, usePage } from '@inertiajs/react';
import PlatformLayout from '@/Layouts/PlatformLayout';
import HeroSection from '@/Components/PublicLanding/HeroSection';
import BenefitsSection from '@/Components/PublicLanding/BenefitsSection';
import FeaturesSection from '@/Components/PublicLanding/FeaturesSection';
import PricingSection from '@/Components/PublicLanding/PricingSection';
import FaqSection from '@/Components/PublicLanding/FaqSection';

export default function Landing() {
    const { platform_setting, plans } = usePage().props;
    const siteName = platform_setting?.site_name || 'My Store';
    const safePlans = Array.isArray(plans) ? plans : [];

    return (
        <PlatformLayout>
            <Head>
                <title>{`${siteName || 'My Store'} — Launch Your Online Store`}</title>
                <meta name="description" content="Launch Your Online Store — A complete e-commerce platform for Myanmar merchants." />
                <meta property="og:title" content={`${siteName || 'My Store'} — Launch Your Online Store`} />
                <meta property="og:description" content="Create your branded online store. No credit card required. Start your free trial today." />
                <meta name="keywords" content="ecommerce, online store, Myanmar, create store, sell online" />
            </Head>

            <HeroSection />
            <BenefitsSection />
            <FeaturesSection />
            <PricingSection plans={safePlans} />
            <FaqSection />
        </PlatformLayout>
    );
}
