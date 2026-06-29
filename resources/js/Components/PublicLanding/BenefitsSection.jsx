import { Zap, Shield, Cloud, Users, Layout, TrendingUp } from 'lucide-react';

const benefits = [
    {
        icon: Zap,
        title: 'Fast Setup',
        description: 'Get your store online in under 5 minutes. No technical skills or coding required.',
    },
    {
        icon: Shield,
        title: 'Secure & Reliable',
        description: 'Enterprise-grade security with SSL encryption and automated daily backups.',
    },
    {
        icon: Cloud,
        title: 'Cloud Hosted',
        description: 'Fully managed cloud infrastructure. No servers to manage, no downtime worries.',
    },
    {
        icon: Users,
        title: 'Multi-Store Ready',
        description: 'Manage multiple stores from a single platform. Perfect for chains and franchises.',
    },
    {
        icon: Layout,
        title: 'Modern Dashboard',
        description: 'Intuitive admin panel with real-time analytics, order management, and inventory control.',
    },
    {
        icon: TrendingUp,
        title: 'Scalable',
        description: 'Start small and grow. Our platform scales with your business, from 10 to 10,000 orders.',
    },
];

export default function BenefitsSection() {
    return (
        <section className="py-16 sm:py-20 lg:py-24 bg-white">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="text-center max-w-2xl mx-auto mb-14">
                    <h2 className="text-3xl sm:text-4xl font-bold text-gray-900">
                        Why Choose Our Platform
                    </h2>
                    <p className="mt-4 text-gray-500 text-lg">
                        Everything you need to succeed in Myanmar e-commerce.
                    </p>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8">
                    {benefits.map((benefit) => (
                        <div
                            key={benefit.title}
                            className="group relative bg-gray-50 rounded-2xl p-6 sm:p-8 hover:bg-white hover:shadow-lg hover:shadow-blue-500/5 transition-all duration-200 border border-transparent hover:border-gray-200"
                        >
                            <div className="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center mb-4 group-hover:bg-blue-600 transition-colors duration-200">
                                <benefit.icon className="w-5 h-5 text-blue-600 group-hover:text-white transition-colors duration-200" />
                            </div>
                            <h3 className="text-lg font-semibold text-gray-900 mb-2">
                                {benefit.title}
                            </h3>
                            <p className="text-sm text-gray-500 leading-relaxed">
                                {benefit.description}
                            </p>
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}
