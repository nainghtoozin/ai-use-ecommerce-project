import { Package, ShoppingCart, Warehouse, Percent, BarChart3, CreditCard, Brain, MessageSquare, Globe } from 'lucide-react';

const features = [
    {
        icon: Package,
        title: 'Products',
        description: 'Single, variable, combo, and digital products with powerful inventory management.',
    },
    {
        icon: ShoppingCart,
        title: 'Orders',
        description: 'Full order lifecycle from placement to delivery with payment tracking.',
    },
    {
        icon: Warehouse,
        title: 'Inventory',
        description: 'Real-time stock tracking, low stock alerts, and multi-warehouse support.',
    },
    {
        icon: Percent,
        title: 'Marketing',
        description: 'Coupons, promotions, flash sales, and automated discount campaigns.',
    },
    {
        icon: BarChart3,
        title: 'Analytics',
        description: 'Detailed sales reports, payment logs, and product performance insights.',
    },
    {
        icon: CreditCard,
        title: 'Payments',
        description: 'Multiple payment gateways including KBZPay, WavePay, AYA Pay, and more.',
    },
    {
        icon: Brain,
        title: 'AI Ready',
        description: 'AI-powered product descriptions, SEO optimization, and translations.',
    },
    {
        icon: MessageSquare,
        title: 'Telegram Ready',
        description: 'Real-time order notifications and management through Telegram bot.',
    },
    {
        icon: Globe,
        title: 'Custom Domains',
        description: 'Use your own domain name for a professional branded storefront.',
    },
];

export default function FeaturesSection() {
    return (
        <section id="features" className="py-16 sm:py-20 lg:py-24 bg-gray-50">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="text-center max-w-2xl mx-auto mb-14">
                    <h2 className="text-3xl sm:text-4xl font-bold text-gray-900">
                        Everything You Need to Sell Online
                    </h2>
                    <p className="mt-4 text-gray-500 text-lg">
                        Powerful tools designed for Myanmar e-commerce merchants.
                    </p>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8">
                    {features.map((feature) => (
                        <div
                            key={feature.title}
                            className="bg-white rounded-2xl border border-gray-200 p-6 sm:p-8 hover:shadow-md hover:border-gray-300 transition-all duration-200"
                        >
                            <div className="w-10 h-10 rounded-xl bg-indigo-100 flex items-center justify-center mb-4">
                                <feature.icon className="w-5 h-5 text-indigo-600" />
                            </div>
                            <h3 className="text-lg font-semibold text-gray-900 mb-2">
                                {feature.title}
                            </h3>
                            <p className="text-sm text-gray-500 leading-relaxed">
                                {feature.description}
                            </p>
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}
