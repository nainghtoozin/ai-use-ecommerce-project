import { Head, Link, usePage } from '@inertiajs/react';
import ShopLayout from '@/Layouts/ShopLayout';
import HomepageHero from '@/Components/Storefront/HomepageHero';

export default function ClientProductIndex() {
    const { website_info } = usePage().props;
    const siteName = website_info?.site_name || 'My Store';

    const features = [
        {
            icon: 'bi-shop',
            title: 'Custom Storefront',
            description: 'Get your own branded online store with a unique URL. Customize colors, logo, and layout to match your brand.',
        },
        {
            icon: 'bi-credit-card',
            title: 'Payment Ready',
            description: 'Accept payments from customers with multiple payment methods. Track orders from checkout to delivery.',
        },
        {
            icon: 'bi-box-seam',
            title: 'Inventory Management',
            description: 'Manage products, stock levels, variants, and categories from a powerful admin dashboard.',
        },
        {
            icon: 'bi-percent',
            title: 'Promotions & Coupons',
            description: 'Create discounts, coupon codes, and promotional campaigns to boost your sales and attract customers.',
        },
        {
            icon: 'bi-graph-up',
            title: 'Sales Reports',
            description: 'Track your store performance with detailed sales reports, payment logs, and product analytics.',
        },
        {
            icon: 'bi-chat-dots',
            title: 'Customer Communication',
            description: 'Built-in chat and notification system to stay connected with your customers throughout the order process.',
        },
    ];

    const steps = [
        {
            step: '1',
            title: 'Create Your Store',
            description: 'Sign up with your store name and details. It takes less than 5 minutes to get started.',
        },
        {
            step: '2',
            title: 'Add Your Products',
            description: 'Upload product photos, set prices, manage inventory, and organize categories from your admin panel.',
        },
        {
            step: '3',
            title: 'Start Selling',
            description: 'Share your store URL with customers. Accept orders, manage deliveries, and grow your business.',
        },
    ];

    return (
        <ShopLayout>
            <Head title={siteName} />

            <HomepageHero websiteInfo={website_info} />

            <section className="py-16 sm:py-20 lg:py-24">
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
                                className="group bg-white rounded-2xl border border-gray-200 p-6 sm:p-8 hover:shadow-lg hover:border-indigo-200 transition-all duration-200"
                            >
                                <div className="w-12 h-12 rounded-xl bg-indigo-100 flex items-center justify-center mb-4 group-hover:bg-indigo-600 transition-colors duration-200">
                                    <i className={`bi ${feature.icon} text-xl text-indigo-600 group-hover:text-white transition-colors duration-200`}></i>
                                </div>
                                <h3 className="text-lg font-bold text-gray-900 mb-2">
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

            <section className="py-16 sm:py-20 bg-gray-50">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="text-center max-w-2xl mx-auto mb-14">
                        <h2 className="text-3xl sm:text-4xl font-bold text-gray-900">
                            How It Works
                        </h2>
                        <p className="mt-4 text-gray-500 text-lg">
                            Get your store online in three simple steps.
                        </p>
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-8 sm:gap-12">
                        {steps.map((item) => (
                            <div key={item.step} className="text-center">
                                <div className="w-16 h-16 rounded-full bg-indigo-600 text-white text-2xl font-bold flex items-center justify-center mx-auto mb-5 shadow-lg shadow-indigo-200">
                                    {item.step}
                                </div>
                                <h3 className="text-xl font-bold text-gray-900 mb-2">
                                    {item.title}
                                </h3>
                                <p className="text-sm text-gray-500 leading-relaxed max-w-xs mx-auto">
                                    {item.description}
                                </p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            <section className="py-16 sm:py-20 lg:py-24">
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                    <div className="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-3xl p-8 sm:p-12 lg:p-16 shadow-xl">
                        <h2 className="text-3xl sm:text-4xl font-extrabold text-white">
                            Ready to Start Selling?
                        </h2>
                        <p className="mt-4 text-lg text-indigo-100 max-w-lg mx-auto">
                            Join merchants across Myanmar. Create your store today — no credit card required.
                        </p>
                        <div className="mt-8 flex flex-col sm:flex-row items-center justify-center gap-4">
                            <Link
                                href="/create-store"
                                className="inline-flex items-center gap-2 px-8 py-3.5 bg-white text-indigo-700 font-bold text-base rounded-xl hover:bg-indigo-50 transition-all shadow-lg"
                            >
                                <i className="bi bi-plus-circle"></i>
                                Create Your Store
                            </Link>
                            <Link
                                href="/login"
                                className="inline-flex items-center gap-2 px-8 py-3.5 border-2 border-white/30 text-white font-semibold text-base rounded-xl hover:bg-white/10 transition-all"
                            >
                                <i className="bi bi-box-arrow-in-right"></i>
                                Merchant Login
                            </Link>
                        </div>
                    </div>
                </div>
            </section>
        </ShopLayout>
    );
}
